package main

import (
	"bufio"
	"compress/gzip"
	"encoding/json"
	"flag"
	"fmt"
	"io/ioutil"
	"log"
	"os"
	"path/filepath"
	"regexp"

	//"math"
	"bytes"
	"net/http"
	"strconv"
	"sync"
	"time"

	"github.com/gogo/protobuf/proto"
	"github.com/golang/snappy"
	"github.com/prometheus/prometheus/prompb"
)

type timeseries struct {
	Labels     map[string]string
	Timestamps []int64
	Values     []float64
}

const PromWriteBatchSize = 5000
const vmMaxInsertRequestSize = 32 * 1024 * 1024

var Commit = ""

func sendRequest(client *http.Client, endPoint string, series []*prompb.TimeSeries, count int) {
	promTsSeries := make([]prompb.TimeSeries, count)
	for index := 0; index < count; index++ {
		promTsSeries[index] = *series[index]
	}
	writeRequest := prompb.WriteRequest{Timeseries: promTsSeries}
	data, err := proto.Marshal(&writeRequest)
	if err != nil {
		log.Fatal(err)
	}
	dataSize := len(data)
	if dataSize > vmMaxInsertRequestSize {
		log.Printf("series=%v", series)
		log.Fatalf("dataSize=%d to large", dataSize)
	}

	encoded := snappy.Encode(nil, data)

	body := bytes.NewReader(encoded)

	req, err := http.NewRequest("POST", endPoint, body)
	if err != nil {
		log.Fatal(err)
	}

	req.Header.Set("Content-Type", "application/x-protobuf")
	req.Header.Set("Content-Encoding", "snappy")
	req.Header.Set("User-Agent", "ddp_remoteload")
	req.Header.Set("X-Prometheus-Remote-Write-Version", "0.1.0")

	resp, err := client.Do(req)
	if err != nil {
		log.Println("POST request failed")
		log.Fatal(err)
	}
	defer resp.Body.Close()

	respContent, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		log.Println("Failed to read response")
		log.Fatal(err)
	}

	if resp.StatusCode < 200 || resp.StatusCode > 299 {
		log.Printf("Invalid status code %d\n", resp.StatusCode)
		log.Fatalf("Response body: %s", respContent)
	}

}

func remoteWrite(endPoint string, tschannel <-chan *prompb.TimeSeries, wg *sync.WaitGroup) {
	defer wg.Done()

	client := &http.Client{}

	series := make([]*prompb.TimeSeries, PromWriteBatchSize)
	count := 0
	for promTS := range tschannel {
		series[count] = promTS
		count++
		if count == PromWriteBatchSize {
			sendRequest(client, endPoint, series, count)
			count = 0
		}
	}

	if count > 0 {
		sendRequest(client, endPoint, series, count)
	}

	log.Println("remoteWrite complete")
}

func processFiles(files_to_process []string, site string, tschannel chan<- *prompb.TimeSeries, wg *sync.WaitGroup) {
	defer wg.Done()
	defer close(tschannel)

	timestampChecked := false

	// count of any metrics due to to many labels
	metricLabelsDropped := make(map[string]int)
	maxLabels := 28

	for _, file_path := range files_to_process {
		log.Printf("Processing %s\n", file_path)
		file_handle, err := os.Open(file_path)
		if err != nil {
			log.Fatal(err)
		}
		defer file_handle.Close()
		gzip_reader, err := gzip.NewReader(file_handle)
		if err != nil {
			log.Fatal(err)
		}
		defer gzip_reader.Close()

		scanner := bufio.NewScanner(gzip_reader)
		scanner.Split(bufio.ScanLines)

		// Skip header
		scanner.Scan()
		scanner.Text()

		for scanner.Scan() {
			var ts timeseries
			err := json.Unmarshal([]byte(scanner.Text()), &ts)
			if err != nil {
				log.Println("Failed to decode line in " + file_path)
				log.Fatal(err)
			} else {
				if (timestampChecked == false) && (len(ts.Timestamps) > 0) {
					timestamp := time.Unix(ts.Timestamps[0]/1000, 0)
					twoDays, _ := time.ParseDuration("48h")

					if timestamp.After(time.Now().Add(twoDays)) {
						log.Fatalf("timestamp > 2 days in the future, aborting")
					} else {
						timestampChecked = true
					}
				}
				if len(ts.Labels) < maxLabels {
					tschannel <- toPromTimeSeries(ts, site)
				} else {
					dropCount, exists := metricLabelsDropped[ts.Labels["__name__"]]
					if !exists {
						dropCount = 0
					}
					dropCount++
					metricLabelsDropped[ts.Labels["__name__"]] = dropCount
				}
			}
		}
	}

	log.Println("processFiles complete")
	if len(metricLabelsDropped) > 0 {
		log.Print("Metrics dropped due to too many labels")
		for k, v := range metricLabelsDropped {
			fmt.Printf("%20s -> %5d\n", k, v)
		}
	}
}

func toPromTimeSeries(ts timeseries, site string) *prompb.TimeSeries {
	prom_labels := make([]prompb.Label, len(ts.Labels)+1)
	prom_labels[0] = prompb.Label{Name: "site", Value: site}
	var label_index int = 1
	for name, value := range ts.Labels {
		prom_labels[label_index] = prompb.Label{Name: name, Value: value}
		label_index++
	}

	prom_samples := make([]prompb.Sample, len(ts.Timestamps))
	for sample_index := 0; sample_index < len(ts.Timestamps); sample_index++ {
		prom_samples[sample_index] = prompb.Sample{
			Timestamp: ts.Timestamps[sample_index],
			Value:     ts.Values[sample_index],
		}
	}

	return &prompb.TimeSeries{Labels: prom_labels, Samples: prom_samples}
}

func main() {
	pVerbose := flag.Bool("v", false, "Print version")

	var dir_path string
	flag.StringVar(&dir_path, "dir", "", "Path to directory containing remote_writer files")

	var file_offset int
	flag.IntVar(&file_offset, "index", 1, "Starting index to process")

	var site string
	flag.StringVar(&site, "site", "", "Site name")

	var endPoint string
	flag.StringVar(&endPoint, "endpoint", "", "HTTP end point for external store")

	flag.Parse()

	if *pVerbose {
		log.Printf("Version = %s\n", Commit)
		return
	}

	files, err := ioutil.ReadDir(dir_path)
	if err != nil {
		fmt.Println("Failed to read dir " + dir_path)
		log.Fatal(err)
	}
	var filesToProcess []string
	re, _ := regexp.Compile("^dump.([0-9]+).gz$")
	for _, f := range files {
		fileName := f.Name()
		match := re.FindStringSubmatch(fileName)
		if match != nil {
			fileIndex, _ := strconv.Atoi(match[1])
			if fileIndex > file_offset {
				filesToProcess = append(filesToProcess, filepath.Join(dir_path, fileName))
			}
		}
	}

	tschannel := make(chan *prompb.TimeSeries, PromWriteBatchSize*2)
	var wg sync.WaitGroup
	wg.Add(2)

	go processFiles(filesToProcess, site, tschannel, &wg)
	go remoteWrite(endPoint, tschannel, &wg)

	wg.Wait()
}
