package main

import (
	//"bytes"
	"encoding/json"
	"flag"
	"io/ioutil"
	"log"

	"os"
	"strings"

	"github.com/VictoriaMetrics/metricsql"
)

func readInput(input string) map[string]interface{} {
	jsonFile, err := os.Open(input)
	// if we os.Open returns an error then handle it
	if err != nil {
		log.Fatal(err)
	}
	defer jsonFile.Close()

	fileContent, _ := ioutil.ReadAll(jsonFile)

	var result map[string]interface{}
	json.Unmarshal(fileContent, &result)

	return result
}

func writeOutput(dashBoard map[string]interface{}, output string) {
	jsonString, _ := json.MarshalIndent(dashBoard, "", "    ")
	ioutil.WriteFile(output, jsonString, os.ModePerm)
}

func processTarget(targetObj map[string]interface{}) {
	val, ok := targetObj["expr"]
	if !ok {
		return
	}
	expr := val.(string)
	log.Printf("orig expr=%s\n", expr)

	magicRateInterval := "9876789"
	expr = strings.Replace(expr, "$__rate_interval", magicRateInterval, -1)

	queryExpr, err := metricsql.Parse(expr)
	if err != nil {
		log.Printf("Cannot parse query %s", expr)
		log.Println(err)
		return
	}
	siteFilter := metricsql.LabelFilter{Label: "site", Value: "$site", IsNegative: false, IsRegexp: false}
	f := func(e metricsql.Expr) {
		switch e.(type) {
		case *metricsql.MetricExpr:
			me := e.(*metricsql.MetricExpr)
			log.Printf("processPanels: me pre=%v\n", me)
			me.LabelFilters = append(me.LabelFilters, siteFilter)
			log.Printf("processPanels: me post=%v\n", me)
		}
	}
	metricsql.VisitAll(queryExpr, f)
	expr = string(queryExpr.AppendString(nil))
	expr = strings.Replace(expr, magicRateInterval, "$__rate_interval", -1)
	log.Printf("updated expr=%s\n", expr)
	targetObj["expr"] = expr
}

func processPanels(panels []interface{}) {

	for _, panel := range panels {
		panelObj := panel.(map[string]interface{})
		targets, ok := panelObj["targets"]
		if ok {
			for _, target := range targets.([]interface{}) {
				targetObj := target.(map[string]interface{})
				processTarget(targetObj)
			}
		}

		subpanels, ok := panelObj["panels"].([]interface{})
		if ok {
			processPanels(subpanels)
		}
	}

}

func processTemplatingList(dashBoard map[string]interface{}) {
	templating := dashBoard["templating"].(map[string]interface{})
	templatingList := templating["list"].([]interface{})
	var dataSource string
	for _, template := range templatingList {
		templateObj := template.(map[string]interface{})

		if templateObj["datasource"] != nil {
			switch templateObj["datasource"].(type) {
			case string:
				dataSource = templateObj["datasource"].(string)
			default:
				dataSourceStruct := templateObj["datasource"].(map[string]interface{})
				dataSource = dataSourceStruct["uid"].(string)
			}
		}
	}
	siteTemplate := map[string]interface{}{
		"allValue":   "wildcard",
		"datasource": dataSource,
		"definition": "",
		"includeAll": false,
		"multi":      false,
		"label":      "Site",
		"name":       "site",
		"type":       "query",
		"query":      "label_values(site)",
		"refresh":    2,
	}
	siteTemplateSlice := []interface{}{siteTemplate}
	templatingList = append(siteTemplateSlice, templatingList...)
	templating["list"] = templatingList
}

func main() {
	var (
		input  string
		output string
	)

	flagset := flag.NewFlagSet(os.Args[0], flag.ExitOnError)
	flagset.StringVar(&input, "input", "", "Input file")
	flagset.StringVar(&output, "output", "", "Output file")

	flagset.Parse(os.Args[1:])

	dashBoard := readInput(input)

	processTemplatingList(dashBoard)

	if _, ok := dashBoard["panels"]; ok {
		processPanels(dashBoard["panels"].([]interface{}))
	}
	if rows, ok := dashBoard["rows"]; ok {
		for _, row := range rows.([]interface{}) {
			rowObj := row.(map[string]interface{})
			processPanels(rowObj["panels"].([]interface{}))
		}
	}

	if _, ok := dashBoard["uid"]; ok {
		dashBoard["uid"] = dashBoard["uid"].(string) + "-1"
	}

	writeOutput(dashBoard, output)
}
