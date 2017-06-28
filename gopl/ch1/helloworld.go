package main

import (
	"fmt"
	"os"
)

func main() {
	for n, s := range os.Args[1:] {
		fmt.Println(n, s)
	}
	fmt.Println("hello  world!")
}
