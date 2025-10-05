package proto

import (
	"context"
	_log "log"
	"os"
	"time"

	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

func Log(format string, a ...any) {
	if os.Getenv("TEST_VERBOSE") == "1" || os.Getenv("TEST_VERBOSE") == "true" {
		_log.Printf("[test-server] "+format, a...)
	}
}

type testService struct {
	UnimplementedTestServiceServer
}

func (s *testService) GetTest(ctx context.Context, req *GetTestRequest) (*GetTestRequest, error) {
	Log("GetTest: %+v", req)
	return req, nil
}

func (s *testService) EchoFast(ctx context.Context, req *EchoRequest) (*EchoResponse, error) {
	Log("EchoFast: %s", req.Message)
	return &EchoResponse{Message: req.Message}, nil
}

func (s *testService) EchoSlow(ctx context.Context, req *EchoRequest) (*EchoResponse, error) {
	Log("EchoSlow: %s", req.Message)
	time.Sleep(5 * time.Second)
	return &EchoResponse{Message: req.Message}, nil
}

func (s *testService) ReturnsInvalidArgument(ctx context.Context, req *Empty) (*Empty, error) {
	Log("ReturnsInvalidArgument")
	return nil, status.Error(codes.InvalidArgument, "Invalid argument error")
}

func (s *testService) ReturnsNotFound(ctx context.Context, req *Empty) (*Empty, error) {
	Log("ReturnsNotFound")
	return nil, status.Error(codes.NotFound, "Not found error")
}

func (s *testService) ReturnsPermissionDenied(ctx context.Context, req *Empty) (*Empty, error) {
	Log("ReturnsPermissionDenied")
	return nil, status.Error(codes.PermissionDenied, "Permission denied error")
}

func (s *testService) ReturnsUnavailable(ctx context.Context, req *Empty) (*Empty, error) {
	Log("ReturnsUnavailable")
	return nil, status.Error(codes.Unavailable, "Unavailable error")
}

func NewTestService() TestServiceServer {
	return &testService{}
}
