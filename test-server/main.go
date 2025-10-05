package main

import (
	"flag"
	"fmt"
	"log"
	"net"
	"strconv"

	"google.golang.org/grpc"
	_ "google.golang.org/grpc/encoding/gzip"
	"google.golang.org/grpc/reflection"

	"test-server/proto"
)

func main() {
	var port string
	flag.StringVar(&port, "port", "8080", "address to listen on")
	flag.Parse()

	if p, err := strconv.Atoi(port); err != nil || p == 0 {
		log.Fatalf("Invalid port: %s", port)
	}

	server := grpc.NewServer()

	proto.RegisterTestServiceServer(server, proto.NewTestService())
	reflection.Register(server)

	listener, err := net.Listen("tcp", fmt.Sprintf(":%s", port))
	if err != nil {
		log.Fatalf("Failed to listen: %v", err)
	}

	log.Printf("Listening on http://localhost:%s", port)

	if err := server.Serve(listener); err != nil {
		log.Fatalf("Failed to serve: %v", err)
	}
}
