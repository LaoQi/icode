package main

import (
	"fmt"
	"log"
	"net/http"
	"os"
	"strconv"

	"github.com/akamensky/argparse"
	"github.com/skip2/go-qrcode"
)

type Config struct {
	Port *int
}

func handle(w http.ResponseWriter, r *http.Request) {
	log.Printf("%s %s %s %s", r.RemoteAddr, r.Method, r.URL, r.Proto)
	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "text/html")

	var err error
	level := qrcode.Highest
	size := 256

	h := r.FormValue("h")
	l := r.FormValue("l")
	s := r.FormValue("s")

	if s != "" {
		size, err = strconv.Atoi(s)
		if err != nil {
			log.Print(err)
			w.WriteHeader(http.StatusBadRequest)
			fmt.Fprintf(w, "bad request")
			return
		}
	}

	switch l {
	case "low":
		level = qrcode.Low
	case "Medium":
		level = qrcode.Medium
	case "high":
		level = qrcode.High
	case "highest":
		level = qrcode.Highest
	default:
		level = qrcode.Highest
	}

	png, err := qrcode.Encode(h, level, size)

	if err == nil {
		w.Header().Set("Content-Type", "image/png")
		log.Printf("encode [%s]", h)
		w.Write(png)
	} else {
		log.Print(err)
		w.WriteHeader(http.StatusBadRequest)
		fmt.Fprintf(w, "bad request")
	}
}

func main() {
	parser := argparse.NewParser("QrcodeGen", "Qrcode service")

	config := Config{}
	config.Port = parser.Int("p", "port", &argparse.Options{Default: 8301, Help: "set port"})
	err := parser.Parse(os.Args)
	if err != nil {
		fmt.Print(parser.Usage(err))
		os.Exit(2)
	}

	http.HandleFunc("/", handle)

	log.Fatal(http.ListenAndServe(fmt.Sprintf(":%d", *config.Port), nil))
	os.Exit(0)
}
