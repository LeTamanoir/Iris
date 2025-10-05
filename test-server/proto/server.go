package proto

import (
	"context"
	_log "log"
	"os"
	"time"
)

func Log(format string, a ...any) {
	if os.Getenv("TEST_VERBOSE") == "1" || os.Getenv("TEST_VERBOSE") == "true" {
		_log.Printf("[test-server] "+format, a...)
	}
}

type testService struct {
	UnimplementedTestServiceServer
}

func (s *testService) GetDataTypes(ctx context.Context, req *DataTypes) (*DataTypes, error) {
	Log("GetDataTypes: %+v", req)
	return req, nil
}

func (s *testService) GetEmpty(ctx context.Context, req *Empty) (*Empty, error) {
	Log("GetEmpty: %+v", req)
	return req, nil
}

func (s *testService) GetDelayRequest(ctx context.Context, req *DelayRequest) (*Empty, error) {
	Log("GetDelayRequest: %+v", req)
	time.Sleep(time.Duration(req.Ms) * time.Millisecond)
	return &Empty{}, nil
}

func NewTestService() TestServiceServer {
	return &testService{}
}
