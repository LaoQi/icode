package main

import (
	"fmt"
	"os"
	"strings"
)

func main() {
	fmt.Println(strings.Join(os.Args[0:], " "))
	for i := 1; i < len(os.Args); i++ {
		fmt.Println("Index", i)
		fmt.Println("Value", os.Args[i])
	}
}