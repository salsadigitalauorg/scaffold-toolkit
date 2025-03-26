#!/usr/bin/env bash

# Source common functions and variables
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=./common.sh
source "${SCRIPT_DIR}/common.sh"

# Track overall test status
test_status=0

# Test dry run mode
test_dry_run() {
  print_header "Testing dry run mode..."
  if run_installer "/workspace/test1" --dry-run --scaffold=drevops --ci=circleci --hosting=lagoon --ssh-fingerprint="${TEST_SSH_FINGERPRINT}" --ssh-renovate-fingerprint="${TEST_SSH_FINGERPRINT}"; then
    print_success "Test passed: Dry run mode"
  else
    print_error "Test failed: Dry run mode"
    test_status=1
  fi
}

# Test CircleCI with Lagoon
test_circleci_lagoon() {
  print_header "Testing CircleCI with Lagoon (normal installation)..."
  if run_installer "/workspace/test2" --scaffold=drevops --ci=circleci --hosting=lagoon --ssh-fingerprint="${TEST_SSH_FINGERPRINT}" --ssh-renovate-fingerprint="${TEST_SSH_FINGERPRINT}"; then
    # Check for the existence of .ahoy.yml file
    if dc_exec test -f "/workspace/test2/.ahoy.yml"; then
      print_success "Test passed: CircleCI with Lagoon (.ahoy.yml installed)"
    else
      print_error "Test failed: CircleCI with Lagoon (.ahoy.yml not found)"
      test_status=1
      return
    fi
    print_success "Test passed: CircleCI with Lagoon"
  else
    print_error "Test failed: CircleCI with Lagoon"
    test_status=1
  fi
}

# Test CircleCI with Acquia
test_circleci_acquia() {
  print_header "Testing CircleCI with Acquia (normal installation)..."
  if run_installer "/workspace/test3" --scaffold=drevops --ci=circleci --hosting=acquia --ssh-fingerprint="${TEST_SSH_FINGERPRINT}"; then
    print_success "Test passed: CircleCI with Acquia"
  else
    print_error "Test failed: CircleCI with Acquia"
    test_status=1
  fi
}

# Test CircleCI without SSH fingerprint
test_circleci_no_ssh() {
  print_header "Testing CircleCI without SSH fingerprint (should fail)..."
  if ! run_installer "/workspace/test4" --scaffold=drevops --ci=circleci --hosting=lagoon; then
    print_success "Test passed: CircleCI correctly requires SSH fingerprint"
  else
    print_error "Test failed: CircleCI should have required SSH fingerprint"
    test_status=1
  fi
}

# Test CircleCI with Lagoon without RenovateBot SSH fingerprint
test_circleci_lagoon_no_renovate_ssh() {
  print_header "Testing CircleCI with Lagoon without RenovateBot SSH fingerprint (should fail)..."
  if ! run_installer "/workspace/test5" --scaffold=drevops --ci=circleci --hosting=lagoon --ssh-fingerprint="${TEST_SSH_FINGERPRINT}"; then
    print_success "Test passed: CircleCI with Lagoon correctly requires RenovateBot SSH fingerprint"
  else
    print_error "Test failed: CircleCI with Lagoon should have required RenovateBot SSH fingerprint"
    test_status=1
  fi
}

# Test GitHub Actions with Lagoon
test_github_lagoon() {
  print_header "Testing GitHub Actions with Lagoon (should show not available message and exit)..."
  if ! run_installer "/workspace/test6" --scaffold=drevops --ci=github --hosting=lagoon; then
    print_success "Test passed: GitHub Actions with Lagoon correctly shows not available message"
  else
    print_error "Test failed: GitHub Actions with Lagoon should have failed"
    test_status=1
  fi
}

# Test force installation
test_force_install() {
  print_header "Testing force installation..."

  # First install normally
  run_installer "/workspace/test7" --scaffold=drevops --ci=circleci --hosting=acquia --ssh-fingerprint="${TEST_SSH_FINGERPRINT}"
  first_install=$?

  # Then try to install again with force
  run_installer "/workspace/test7" --scaffold=drevops --ci=circleci --hosting=acquia --force --ssh-fingerprint="${TEST_SSH_FINGERPRINT}"
  second_install=$?

  if [ $first_install -eq 0 ] && [ $second_install -eq 0 ]; then
    print_success "Test passed: Force installation"
  else
    print_error "Test failed: Force installation"
    test_status=1
  fi
}

# Test Vortex scaffold
test_vortex() {
  print_header "Testing Vortex scaffold type (should show not available message and exit)..."
  if ! run_installer "/workspace/test8" --scaffold=vortex --ci=circleci --hosting=acquia --ssh-fingerprint="${TEST_SSH_FINGERPRINT}"; then
    print_success "Test passed: Vortex scaffold correctly shows not available message"
  else
    print_error "Test failed: Vortex scaffold should have failed"
    test_status=1
  fi
}

# Test GovCMS PaaS scaffold
test_govcms() {
  print_header "Testing GovCMS PaaS scaffold type (should show not available message and exit)..."
  if ! run_installer "/workspace/test9" --scaffold=govcms --ci=circleci --hosting=acquia --ssh-fingerprint="${TEST_SSH_FINGERPRINT}"; then
    print_success "Test passed: GovCMS PaaS scaffold correctly shows not available message"
  else
    print_error "Test failed: GovCMS PaaS scaffold should have failed"
    test_status=1
  fi
}

# Show test results
show_results() {
  print_header "Test Results:"
  dc_exec ls -la /workspace/test* || true

  if [ $test_status -eq 0 ]; then
    print_success "All tests passed"
    exit 0
  else
    print_error "Some tests failed"
    exit 1
  fi
}

# Run all tests
test_dry_run
test_circleci_lagoon
test_circleci_acquia
test_circleci_no_ssh
test_circleci_lagoon_no_renovate_ssh
test_github_lagoon
test_force_install
test_vortex
test_govcms
show_results
