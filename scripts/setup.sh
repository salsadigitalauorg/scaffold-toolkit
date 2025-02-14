#!/usr/bin/env bash

# Source common functions and variables
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./common.sh
source "${SCRIPT_DIR}/common.sh"

# Create test directories
create_test_dirs() {
  for i in {1..8}; do
    dc_exec mkdir -p "/workspace/test${i}"
  done
}

# Start environment
start_env() {
  print_header "Starting test environment..."
  
  # Clean up existing resources
  docker compose -f "${DOCKER_COMPOSE_FILE}" down -v
  docker system prune -f --volumes
  
  # Build and start fresh
  docker compose -f "${DOCKER_COMPOSE_FILE}" build --no-cache --pull
  docker compose -f "${DOCKER_COMPOSE_FILE}" up -d
  
  # Create test directories
  create_test_dirs
}

# Stop environment
stop_env() {
  print_header "Stopping test environment..."
  docker compose -f "${DOCKER_COMPOSE_FILE}" down -v
  docker system prune -af --volumes
}

# Handle command line arguments
case "${1:-}" in
  "start")
    start_env
    ;;
  "stop")
    stop_env
    ;;
  *)
    echo "Usage: $0 {start|stop}"
    exit 1
    ;;
esac 