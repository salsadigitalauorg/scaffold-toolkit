#!/usr/bin/env bash

# Exit on any error
set -e

# Colors
COLOR_RED='\033[0;31m'
COLOR_GREEN='\033[0;32m'
COLOR_BOLD='\033[1m'
COLOR_RESET='\033[0m'

# Test SSH fingerprint
TEST_SSH_FINGERPRINT="01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef"

# Docker compose file
DOCKER_COMPOSE_FILE=${DOCKER_COMPOSE_FILE:-"docker-compose.test.yml"}

# Print success message
print_success() {
  echo -e "${COLOR_GREEN}✓ $1${COLOR_RESET}"
}

# Print error message
print_error() {
  echo -e "${COLOR_RED}✗ $1${COLOR_RESET}"
}

# Print header
print_header() {
  echo -e "\n${COLOR_BOLD}$1${COLOR_RESET}"
}

# Execute docker compose command
dc_exec() {
  docker compose -f "${DOCKER_COMPOSE_FILE}" exec -T test "$@"
}

# Run installer with common parameters
run_installer() {
  local target_dir=$1
  shift
  dc_exec php scaffold-installer.php \
    --latest \
    --non-interactive \
    --source-dir=/source \
    --target-dir="${target_dir}" \
    "$@"
} 