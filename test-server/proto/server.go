package proto

import (
	"context"
	"log"
	"time"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type testService struct {
	UnimplementedTestServiceServer
}

func (s *testService) GetTest(ctx context.Context, req *GetTestRequest) (*GetTestRequest, error) {
	log.Printf("GetTest: %+v", req)
	return req, nil
}

func (s *testService) EchoFast(ctx context.Context, req *EchoRequest) (*EchoResponse, error) {
	log.Printf("EchoFast: %s", req.Message)
	return &EchoResponse{Message: req.Message}, nil
}

func (s *testService) EchoSlow(ctx context.Context, req *EchoRequest) (*EchoResponse, error) {
	log.Printf("EchoSlow: %s", req.Message)
	time.Sleep(5 * time.Second)
	return &EchoResponse{Message: req.Message}, nil
}

func (s *testService) ReturnsInvalidArgument(ctx context.Context, req *Empty) (*Empty, error) {
	log.Printf("ReturnsInvalidArgument")
	return nil, status.Error(codes.InvalidArgument, "Invalid argument error")
}

func (s *testService) ReturnsNotFound(ctx context.Context, req *Empty) (*Empty, error) {
	log.Printf("ReturnsNotFound")
	return nil, status.Error(codes.NotFound, "Not found error")
}

func (s *testService) ReturnsPermissionDenied(ctx context.Context, req *Empty) (*Empty, error) {
	log.Printf("ReturnsPermissionDenied")
	return nil, status.Error(codes.PermissionDenied, "Permission denied error")
}

func (s *testService) ReturnsUnavailable(ctx context.Context, req *Empty) (*Empty, error) {
	log.Printf("ReturnsUnavailable")
	return nil, status.Error(codes.Unavailable, "Unavailable error")
}

func NewTestService() TestServiceServer {
	return &testService{}
}
