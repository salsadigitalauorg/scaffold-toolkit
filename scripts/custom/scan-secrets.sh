#!/usr/bin/env bash
##
# Security scan for secrets using TruffleHog.
#
# This script uses TruffleHog to scan for secrets in the codebase
# while respecting exclusion patterns.
#
# Version: 1.0.0
# Customized: false
#

set -e
[ "${DREVOPS_DEBUG-}" = "1" ] && set -x

# Current directory.
SCRIPT_DIR="$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")"
PROJECT_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"

# Default values.
ARTIFACTS_DIR=${ARTIFACTS_DIR:-"/tmp/artifacts/security-scan"}
EXCLUSIONS_FILE="${PROJECT_DIR}/.circleci/trufflehog-exclusions.txt"
TMP_EXCLUSIONS_FILE="${ARTIFACTS_DIR}/trufflehog-exclusions.txt"
DB_FILES_LIST="${ARTIFACTS_DIR}/db-files.txt"
EXIT_CODE_FILE="${ARTIFACTS_DIR}/scan_exit_code"

# Define symbols for output (plain text without color codes)
TICK="✓"
CROSS="❌"
WARNING="⚠️"

# Create artifacts directory if it doesn't exist.
mkdir -p "${ARTIFACTS_DIR}"
mkdir -p "$(dirname "${EXCLUSIONS_FILE}")"

# Print header.
echo "==================================================================="
echo "Starting security scan for secrets at $(date '+%Y-%m-%d %H:%M:%S')"
echo "==================================================================="
echo

# Check if exclusions file exists and create a temporary copy
if [ -f "${EXCLUSIONS_FILE}" ]; then
  echo "${TICK} Found exclusions file: ${EXCLUSIONS_FILE}"
  
  # Create a temporary copy of the exclusions file
  cp "${EXCLUSIONS_FILE}" "${TMP_EXCLUSIONS_FILE}"
  
  # Check if sanitize.sql is already in the exclusions file
  if ! grep -q "scripts\/sanitize\.sql" "${TMP_EXCLUSIONS_FILE}"; then
    echo "Adding sanitize.sql to exclusions file..."
    # Add sanitize.sql to the temporary exclusions file
    echo "scripts\/sanitize\.sql" >> "${TMP_EXCLUSIONS_FILE}"
  fi
else
  echo "Exclusions file not found, creating a temporary one..."
  cat << EOF > "${TMP_EXCLUSIONS_FILE}"
# Sanitize SQL file (allowed)
scripts\/sanitize\.sql
EOF
  echo "${TICK} Created temporary exclusions file: ${TMP_EXCLUSIONS_FILE}"
fi

# Initialize exit code
FINAL_EXIT_CODE=0
SCAN_EXIT_CODE=0
ENV_SECRETS_FOUND=0

# Scan the entire repository
echo "==================================================================="
echo "Scanning repository for secrets..."
echo "==================================================================="
REPO_RESULTS="${ARTIFACTS_DIR}/repo-results.json"

# Run TruffleHog on the entire repository
echo "Running TruffleHog on the entire repository..."
docker run --rm \
  -v "${PROJECT_DIR}:/code" \
  -v "${TMP_EXCLUSIONS_FILE}:/trufflehog-exclusions.txt" \
  trufflesecurity/trufflehog:latest \
  filesystem "/code" \
  --results=verified,unknown \
  --fail \
  -x "/trufflehog-exclusions.txt" \
  --json > "${REPO_RESULTS}" || SCAN_EXIT_CODE=$?

# Check if any secrets were found
if [ -s "${REPO_RESULTS}" ]; then
  echo "${WARNING} WARNING: Potential secrets found in the repository:"
  cat "${REPO_RESULTS}"
  FINAL_EXIT_CODE=1
else
  echo "${TICK} No secrets found in the repository."
fi
echo

# Check for database files in Git
echo "==================================================================="
echo "Checking for database files in Git..."
echo "==================================================================="
# Find SQL files tracked by Git, excluding sanitize.sql
git ls-files "*.sql" "*.sql.*" | grep -v "scripts/sanitize.sql" > "${DB_FILES_LIST}" || true

if [ -s "${DB_FILES_LIST}" ]; then
  echo "${CROSS} ERROR: Database files found in Git:"
  cat "${DB_FILES_LIST}"
  echo
  echo "Database files should not be committed to Git as they may contain sensitive information."
  echo "Please remove these files from Git and add them to .gitignore."
  FINAL_EXIT_CODE=3
else
  echo "${TICK} No database files found in Git."
fi
echo

echo "==================================================================="
echo "Scan completed at $(date '+%Y-%m-%d %H:%M:%S')"
echo "==================================================================="
echo

# Determine final exit status
if [ "${FINAL_EXIT_CODE}" -eq 0 ]; then
  echo "${TICK} SUCCESS: No secrets or sensitive information found in the codebase."
  echo "    Review complete - your code appears to be clean."
elif [ "${FINAL_EXIT_CODE}" -eq 3 ]; then
  echo "${CROSS} FAILURE: Database files found in Git."
  echo "    These files may contain sensitive information and should not be committed."
  echo "    See the list of database files above."
elif [ "${FINAL_EXIT_CODE}" -eq 2 ]; then
  echo "${CROSS} FAILURE: Secrets found in environment files."
  echo "    Environment files contain sensitive information that should not be committed."
  echo "    Please review the scan results above and remove or mask the secrets."
  echo "    CircleCI build will fail to prevent exposure of sensitive information."
else
  echo "${WARNING} WARNING: Potential secrets found in the codebase."
  echo "    Please review the scan results above and in the artifacts directory."
  echo "    CI/CD pipeline will be stopped to prevent exposure of sensitive information."
fi

echo
echo "Full scan results have been saved to the artifacts directory: ${ARTIFACTS_DIR}"
echo "==================================================================="

# Save exit code to file for CircleCI to check
echo "${FINAL_EXIT_CODE}" > "${EXIT_CODE_FILE}"

# Exit with the final exit code to stop the pipeline if violations were found
exit ${FINAL_EXIT_CODE} 