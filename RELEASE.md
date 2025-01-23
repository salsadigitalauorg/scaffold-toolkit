# Release v1.0.0

## Major Features

### Installation Improvements
- Added support for source and target directory specification
- Implemented automatic directory creation
- Added non-interactive mode for automated installations
- Added version metadata tracking and validation
- Implemented automatic backup creation for existing files
- Added GitHub integration for file sourcing
- Added support for custom GitHub repository and branch

### CI/CD Integration
- Support for CircleCI and GitHub Actions
- Support for Lagoon and Acquia hosting environments
- Automated configuration based on selected options
- Renovate configuration for Drupal projects

### Testing Framework
- Comprehensive test suite with Docker-based environment
- Test matrix covering all CI/CD and hosting combinations
- Automated cleanup between test runs
- Colored output for test results
- Directory content verification after each test
- Local file support for testing environment

## Command Line Options
- `--latest`: Use latest version
- `--version=<tag>`: Use specific version
- `--force`: Overwrite existing files (with automatic backup)
- `--ci=<circleci|github>`: Select CI/CD type
- `--hosting=<lagoon|acquia>`: Select hosting environment
- `--source-dir=<path>`: Specify source directory
- `--target-dir=<path>`: Specify target directory
- `--non-interactive`: Run without prompts
- `--use-local-files`: Use local files instead of GitHub (for testing)
- `--github-repo`: Specify custom GitHub repository
- `--github-branch`: Specify custom GitHub branch

## Testing Improvements
- Source files are now copied to `/source` during container build
- Test outputs are created in `/workspace`
- Each test runs in a clean environment
- Automatic cleanup after each test
- Detailed test output with directory contents
- Colored pass/fail indicators
- Local file support for testing

## Error Handling
- Improved error messages for missing files
- Added validation for source and target directories
- Implemented proper exit codes for test failures
- Added colored output for errors
- Added automatic backup creation before modifications
- Added GitHub download error handling

## Breaking Changes
- Removed dry-run mode in favor of more robust testing
- Changed directory structure for test environments
- Modified test output format for better readability
- Changed file sourcing to use GitHub by default

## Installation
```bash
curl -O https://raw.githubusercontent.com/salsadigitalauorg/scaffold-toolkit/main/scaffold-installer.php
php scaffold-installer.php --latest
```

## Testing
```bash
# Start testing environment
ahoy up

# Run all tests
ahoy test

# Stop and clean environment
ahoy down
```

## Usage Examples

### Standard Installation
```bash
# Interactive installation from GitHub
php scaffold-installer.php --latest

# Non-interactive installation with specific options
php scaffold-installer.php --latest --non-interactive --ci=circleci --hosting=lagoon
```

### Custom GitHub Source
```bash
# Use a different repository or branch
php scaffold-installer.php --latest --github-repo="myorg/scaffold-toolkit" --github-branch="develop"
```

### Testing with Local Files
```bash
# Use local files instead of GitHub
php scaffold-installer.php --latest --use-local-files --source-dir=/path/to/files
```

## Contributors
- Initial implementation and testing framework
- Documentation updates
- CI/CD integration improvements
- GitHub integration implementation

## License
MIT License - see LICENSE file for details 