package main

import (
	"crypto/tls"
	"encoding/json"
	"flag"
	"io/ioutil"
	"log"
	"net/http"
	"net/http/httputil"
	"net/url"
	"os"
	"regexp"
	"strings"
	"sync"
	"time"

	"github.com/VictoriaMetrics/metricsql"
	"github.com/golang-jwt/jwt"
)

type LookupResult int

const (
	NotAllowed LookupResult = iota
	Allowed
	Unknown
)

type SiteAccess struct {
	Allowed bool
	Created time.Time
}

type DDPResponseEntry struct {
	Site    string `json:"site"`
	Allowed bool   `json:"allowed"`
}

type DDPResponse []DDPResponseEntry

var upstreamURL *url.URL
var ddpaccess string

var accessMap sync.Map

func getSitesFromQuery(query string) []string {
	log.Printf("getSiteFromQuery: query=%s\n", query)

	siteSet := make(map[string]struct{})

	re, _ := regexp.Compile("site=\"([^\"]+)\"")
	matches := re.FindAllStringSubmatch(query, -1)
	if matches != nil {
		for _, match := range matches {
			siteSet[match[1]] = struct{}{}
		}
	}

	siteList := make([]string, 0, len(siteSet))
	for site := range siteSet {
		siteList = append(siteList, site)
	}

	log.Printf("getSitesFromQuery siteList=%v\n", siteList)

	return siteList
}

func lookupAccess(user string, site string) LookupResult {
	log.Printf("lookupAccess user=%s site=%s\n", user, site)

	req, _ := http.NewRequest("GET", ddpaccess, nil)
	req.Header.Add("Accept", "application/json")

	q := req.URL.Query()
	q.Add("site", site)
	q.Add("user", user)
	req.URL.RawQuery = q.Encode()

	client := &http.Client{}
	resp, err := client.Do(req)
	if err != nil {
		log.Println("lookupAccess Errored when sending request to the server")
		return Unknown
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode > 299 {
		log.Printf("lookupAccess Unexpected response status code: %d\n", resp.StatusCode)
		return Unknown
	}

	resp_body, err := ioutil.ReadAll(resp.Body)
	if err != nil {
		log.Println("lookupAccess Errored when reading response from the server")
		return Unknown
	}

	var ddpResponse DDPResponse
	err = json.Unmarshal(resp_body, &ddpResponse)
	if err != nil {
		log.Println("lookupAccess Errored when decoding response from the server")
		return Unknown
	}

	log.Printf("lookupAccess ddpResponse=%v\n", ddpResponse)

	if ddpResponse[0].Allowed {
		return Allowed
	} else {
		return NotAllowed
	}
}

func getSiteAccess(user string, site string) SiteAccess {
	key := user + site
	var sa SiteAccess
	haveValidSa := false

	// Check if we have a valid cached SiteAccess
	value, exists := accessMap.Load(key)
	if exists {
		cachedSa := value.(SiteAccess)
		saAge := time.Since(cachedSa.Created)
		if saAge.Minutes() < 5 {
			sa = cachedSa
			haveValidSa = true
		}
	}

	if !haveValidSa {
		lookupResult := lookupAccess(user, site)
		sa = SiteAccess{Allowed: lookupResult == Allowed, Created: time.Now()}
		// Only cache result if we got an definite answer from DDP
		if lookupResult != Unknown {
			accessMap.Store(key, sa)
		}
	}

	log.Printf("getSiteAccess user=%s site=%s sa=%v \n", user, site, sa)
	return sa
}

func isAccessAllowed(user string, siteList []string) bool {
	result := true
	for _, site := range siteList {
		sa := getSiteAccess(user, site)
		result = result && sa.Allowed
	}
	log.Printf("isAccessAllowed user=%s result=%t\n", user, result)

	return result
}

func getUser(req *http.Request) string {
	user := ""
	idTokenString := strings.TrimSpace(req.Header.Get("X-Id-Token"))
	if idTokenString != "" {
		log.Print("getUser: Using id_token")
		token, _, err := new(jwt.Parser).ParseUnverified(idTokenString, jwt.MapClaims{})
		if err != nil {
			log.Println(err)
			return ""
		}

		//log.Printf("getUser: token=%v\n", token)

		//for k, v := range token.Header {
		//	log.Printf("getUser: token.Header %s=%v\n", k, v)
		//}

		claims, ok := token.Claims.(jwt.MapClaims)
		if ok {
			//for k, v := range claims {
			//	log.Printf("getUser: claims %s=%v\n", k, v)
			//
			if name, ok := claims["name"]; ok {
				user = name.(string)
			}
		} else {
			log.Printf("Invalid JWT Token")
			return ""
		}
	}
	if user == "" {
		user = req.Header.Get("X-Grafana-User")
	}

	log.Printf("getUser: user=%v", user)
	return user
}

func handleSharedRequestAndRedirect(res http.ResponseWriter, req *http.Request) {
	log.Printf("handleSharedRequestAndRedirect: %v %v\n", req.Method, req.URL)
	if debug {
		requestDump, _ := httputil.DumpRequest(req, true)
		log.Printf("handleSharedRequestAndRedirect: %s\n", requestDump)
	}

	user := getUser(req)
	if user == "" {
		http.Error(res, "", http.StatusForbidden)
		return
	}

	if req.URL.Path == "/api/v1/query" || req.URL.Path == "/api/v1/query_range" {
		err := req.ParseForm()
		if err != nil {
			http.Error(res, "Error parsing form", 500)
			return
		}

		siteList := getSitesFromQuery(req.FormValue("query"))
		if len(siteList) == 0 {
			http.Error(res, "Bad request. The site query parameter must be provided.", http.StatusBadRequest)
			return
		}

		allowed := isAccessAllowed(user, siteList)
		if !allowed {
			http.Error(res, "You do not have access to the specified site", http.StatusForbidden)
			return
		}

		// assign a new body with previous byte slice
		body := req.PostForm.Encode()
		req.Body = ioutil.NopCloser(strings.NewReader(body))
		req.ContentLength = int64(len(body))
	}

	// create the reverse proxy
	proxy := httputil.NewSingleHostReverseProxy(upstreamURL)

	// Update the headers to allow for SSL redirection
	req.URL.Host = upstreamURL.Host
	req.URL.Scheme = upstreamURL.Scheme
	req.Header.Set("X-Forwarded-Host", req.Header.Get("Host"))
	req.Host = upstreamURL.Host

	// Note that ServeHttp is non blocking and uses a go routine under the hood
	proxy.ServeHTTP(res, req)
}

func handleSiteRequestAndRedirect(res http.ResponseWriter, req *http.Request) {
	requestDump, err := httputil.DumpRequest(req, true)
	if err != nil {
		log.Fatal(err)
	}
	log.Printf("handleSiteRequestAndRedirect: %s\n", requestDump)

	user := getUser(req)
	if user == "" {
		http.Error(res, "", http.StatusForbidden)
		return
	}

	re, _ := regexp.Compile("^/site/([^/]+)(.*)")
	match := re.FindStringSubmatch(req.URL.Path)
	if match == nil {
		http.Error(res, "Bad request. The path must be /site/<sitename>/...", http.StatusBadRequest)
		return
	}

	siteList := []string{match[1]}

	allowed := isAccessAllowed(user, siteList)
	if !allowed {
		http.Error(res, "You do not have access to the specified site", http.StatusForbidden)
		return
	}

	req.URL.Path = match[2]

	if req.URL.Path == "/api/v1/query" || req.URL.Path == "/api/v1/query_range" {
		urlQueryValues, _ := url.ParseQuery(req.URL.RawQuery)

		queryStr := urlQueryValues.Get("query")
		queryExpr, err := metricsql.Parse(queryStr)
		if err != nil {
			http.Error(res, "Cannot parse query", http.StatusBadRequest)
			return
		}
		log.Printf("handleSpecificRequestAndRedirect: queryExpr=%v\n", queryExpr)

		siteFilter := metricsql.LabelFilter{Label: "site", Value: siteList[0], IsNegative: false, IsRegexp: false}

		f := func(e metricsql.Expr) {
			switch e.(type) {
			case *metricsql.MetricExpr:
				me := e.(*metricsql.MetricExpr)
				log.Printf("handleSpecificRequestAndRedirect: me pre=%v\n", me)
				me.LabelFilters = append(me.LabelFilters, siteFilter)
				log.Printf("handleSpecificRequestAndRedirect: me post=%v\n", me)
			}
		}
		metricsql.VisitAll(queryExpr, f)

		urlQueryValues.Set("query", string(queryExpr.AppendString(nil)))
		req.URL.RawQuery = urlQueryValues.Encode()
	}

	// create the reverse proxy
	proxy := httputil.NewSingleHostReverseProxy(upstreamURL)

	// Update the headers to allow for SSL redirection
	req.URL.Host = upstreamURL.Host
	req.URL.Scheme = upstreamURL.Scheme
	req.Header.Set("X-Forwarded-Host", req.Header.Get("Host"))
	req.Host = upstreamURL.Host

	// Note that ServeHttp is non blocking and uses a go routine under the hood
	proxy.ServeHTTP(res, req)
}

var debug bool

func main() {
	var (
		upstream      string
		listenAddress string
	)

	flagset := flag.NewFlagSet(os.Args[0], flag.ExitOnError)
	flagset.StringVar(&upstream, "upstream", "", "The upstream URL to proxy to.")
	flagset.StringVar(&ddpaccess, "ddpaccess", "http://localhost/php/common/checkaccess.php", "The URL ")
	flagset.StringVar(&listenAddress, "listen", ":8482", "Adress to listen for incoming requests")
	flagset.BoolVar(&debug, "debug", false, "Enable debug")

	flagset.Parse(os.Args[1:])

	var err error
	upstreamURL, err = url.Parse(upstream)
	if err != nil {
		log.Fatalf("Failed to build parse upstream URL: %v", err)
	}

	_, err = url.Parse(ddpaccess)
	if err != nil {
		log.Fatalf("Failed to build parse ddpaccess URL: %v", err)
	}

	// Allow up make https requests to DDP without having to setup the cert
	http.DefaultTransport.(*http.Transport).TLSClientConfig = &tls.Config{InsecureSkipVerify: true}

	http.HandleFunc("/api/", handleSharedRequestAndRedirect)
	http.HandleFunc("/site/", handleSiteRequestAndRedirect)
	http.HandleFunc("/healthz", func(w http.ResponseWriter, _ *http.Request) {
		w.Write([]byte("OK"))
	})

	log.Fatal(http.ListenAndServe(listenAddress, nil))
}
