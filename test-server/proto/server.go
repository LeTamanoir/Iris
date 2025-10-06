package proto

import (
	"context"
	_log "log"
	"os"
	"sync"
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
	failureCounter map[string]int32
	mu             sync.Mutex
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

func (s *testService) GetFailurePattern(ctx context.Context, req *FailurePatternRequest) (*Empty, error) {
	Log("GetFailurePattern: fail_times=%d, error_code=%d, key=%s", req.FailTimes, req.ErrorCode, req.Key)

	s.mu.Lock()
	currentCount := s.failureCounter[req.Key]
	s.failureCounter[req.Key]++
	s.mu.Unlock()

	Log("GetFailurePattern: currentCount=%d", currentCount)

	if currentCount < req.FailTimes || req.FailTimes == 0 {
		code := codes.Code(req.ErrorCode)
		Log("GetFailurePattern: returning error code %d", code)
		return nil, status.Error(code, "simulated failure")
	}

	Log("GetFailurePattern: returning success")
	return &Empty{}, nil
}

func NewTestService() TestServiceServer {
	return &testService{
		failureCounter: make(map[string]int32),
	}
}
