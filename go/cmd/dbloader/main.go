package main

import (
	"archive/tar"
	"compress/gzip"
	"context"
	"errors"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"
)

func downloadVersion(url string, tarball string) error {
	resp, err := http.Get(url)
	if err != nil {
		return err
	}
	defer resp.Body.Close()

	// Create the file
	out, err := os.Create(tarball)
	if err != nil {
		return err
	}
	defer out.Close()

	// Write the body to file
	_, err = io.Copy(out, resp.Body)

	if err != nil {
		return err
	}

	return nil
}

func extractDownload(tarball string, dst string) error {
	r, err := os.Open(tarball)
	if err != nil {
		log.Fatalf(err.Error())
	}

	gzr, err := gzip.NewReader(r)
	if err != nil {
		return err
	}
	defer gzr.Close()

	tr := tar.NewReader(gzr)

	for {
		header, err := tr.Next()

		switch {

		// if no more files are found return
		case err == io.EOF:
			return nil

		// return any other error
		case err != nil:
			return err

		// if the header is nil, just skip it (not sure how this happens)
		case header == nil:
			continue
		}

		// the target location where the dir/file should be created
		target := filepath.Join(dst, header.Name)

		// the following switch could also be done using fi.Mode(), not sure if there
		// a benefit of using one vs. the other.
		// fi := header.FileInfo()

		// check the file type
		switch header.Typeflag {

		// if its a dir and it doesn't exist create it
		case tar.TypeDir:
			if _, err := os.Stat(target); err != nil {
				if err := os.MkdirAll(target, 0755); err != nil {
					return err
				}
			}

		// if it's a file create it
		case tar.TypeReg:
			f, err := os.OpenFile(target, os.O_CREATE|os.O_RDWR, os.FileMode(header.Mode))
			if err != nil {
				return err
			}

			// copy over contents
			if _, err := io.Copy(f, tr); err != nil {
				return err
			}

			// manually close here after each file operation; defering would cause each file close
			// to wait until all operations have completed.
			f.Close()
		}
	}
}

func processRequest(version string) error {
	dashboardDir := os.Getenv("DASHBOARD_DIR")
	if dashboardDir == "" {
		dashboardDir = "/dashboards"
	}

	url := fmt.Sprintf("https://arm1s11-eiffel004.eiffel.gic.ericsson.se:8443/nexus/content/repositories/releases/com/ericsson/cifwk/diagmon/DDP-GrafanaDB/%s/DDP-GrafanaDB-%s.tar.gz", version, version)

	log.Printf("Downloading %s\n", url)
	tarball := filepath.Join(dashboardDir, "download.tar.gz")
	if _, err := os.Stat(tarball); !os.IsNotExist(err) {
		log.Printf("Removing existing %s", tarball)
		os.Remove(tarball)
	}

	err := downloadVersion(url, tarball)
	if err != nil {
		return err
	}

	log.Println("Extract tarball")
	extractDir := filepath.Join(dashboardDir, "/extract")
	if _, err := os.Stat(extractDir); !os.IsNotExist(err) {
		log.Printf("Removing existing %s", extractDir)
		os.RemoveAll(extractDir)
	}
	err = extractDownload(tarball, extractDir)
	if err != nil {
		return err
	}

	os.Remove(tarball)

	topDir := filepath.Join(dashboardDir, "top")
	oldDir := filepath.Join(dashboardDir, "old")
	if _, err := os.Stat(topDir); !os.IsNotExist(err) {
		log.Println("Moving top to old")
		os.Rename(topDir, oldDir)
	}

	extractedDbDir := filepath.Join(
		extractDir,
		fmt.Sprintf("/DDP-GrafanaDB-%s", version),
	)
	log.Printf("Rename %s to %s\n", extractedDbDir, topDir)
	err = os.Rename(extractedDbDir, topDir)
	if err != nil {
		return err
	}

	log.Printf("Remove %s\n", extractDir)
	os.RemoveAll(extractDir)

	if _, err := os.Stat(oldDir); !os.IsNotExist(err) {
		log.Printf("Remove %s\n", oldDir)
		os.RemoveAll(oldDir)
	}

	versionPath := filepath.Join(dashboardDir, "/version.txt")
	os.WriteFile(versionPath, []byte(version), 0644)

	log.Println("Done")

	return nil
}

func handleUpdate(reply http.ResponseWriter, request *http.Request) {
	if request.URL.Query().Has("version") {
		version := request.URL.Query().Get("version")
		err := processRequest(version)
		if err == nil {
			reply.WriteHeader(http.StatusAccepted)
		} else {
			log.Printf("update request failed, %s", err)
			reply.WriteHeader(http.StatusBadRequest)
		}
	}
}

func handleCurrent(reply http.ResponseWriter, request *http.Request) {
	dashboardDir := os.Getenv("DASHBOARD_DIR")
	if dashboardDir == "" {
		dashboardDir = "/dashboards"
	}
	versionPath := filepath.Join(dashboardDir, "/version.txt")

	io.WriteString(reply, "{ \"version\": \"")

	dat, err := os.ReadFile(versionPath)
	if err != nil {
		log.Printf("File to read version file %s, %s", versionPath, err)
		io.WriteString(reply, "NA")
	} else {
		reply.Write(dat)
	}
	io.WriteString(reply, "\" }")
}

func main() {
	mux := http.NewServeMux()
	mux.HandleFunc("/update", handleUpdate)
	mux.HandleFunc("/current", handleCurrent)
	mux.HandleFunc("/healthz", func(w http.ResponseWriter, _ *http.Request) {
		w.Write([]byte("OK"))
	})

	httpServer := http.Server{
		Addr:              ":8080",
		Handler:           mux,
		ReadHeaderTimeout: 5 * time.Second,
	}

	// Run HTTP server
	go func() {
		err := httpServer.ListenAndServe()
		if !errors.Is(err, http.ErrServerClosed) {
			log.Fatalf("HTTP Server error %v", err)
		}
	}()

	// Setup trap of SIGTERM and wait for it to occur
	signalChan := make(chan os.Signal, 1)
	signal.Notify(
		signalChan,
		syscall.SIGTERM,
	)
	<-signalChan

	log.Print("os.Interrupt, shutdown http")
	httpServer.Shutdown(context.Background())
	log.Print("Stopping\n")
}
