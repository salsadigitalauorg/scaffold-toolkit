# Scaffold Toolkit

A toolkit for scaffolding CI/CD configurations for Drupal projects, supporting both CircleCI and GitHub Actions with Lagoon and Acquia hosting environments.

## Features

- Interactive installation process
- Support for CircleCI and GitHub Actions
- Support for Lagoon and Acquia hosting environments
- Automated file versioning and metadata tracking
- Backup creation for existing files
- Non-interactive mode for automated installations
- GitHub integration for file sourcing
- Comprehensive test suite covering all configuration combinations

## Installation

Download and run the installer:

```bash
curl -O https://raw.githubusercontent.com/salsadigitalauorg/scaffold-toolkit/main/scaffold-installer.php
php scaffold-installer.php --latest
```

## Usage

### Command Line Options

- `--latest`: Use latest version
- `--version=<tag>`: Use specific version
- `--force`: Overwrite existing files (creates backups)
- `--ci=<circleci|github>`: Pre-select CI/CD type
- `--hosting=<lagoon|acquia>`: Pre-select hosting environment
- `--source-dir=<path>`: Specify source directory for files
- `--target-dir=<path>`: Specify target directory for installation
- `--non-interactive`: Run in non-interactive mode (requires --ci and --hosting)
- `--github-repo`: Specify custom GitHub repository (default: salsadigitalauorg/scaffold-toolkit)
- `--github-branch`: Specify custom GitHub branch (default: main)
- `--use-local-files`: Use local files instead of GitHub (for testing)

### Examples

Interactive installation from GitHub:
```bash
php scaffold-installer.php --latest
```

Non-interactive installation with CircleCI and Lagoon:
```bash
php scaffold-installer.php --latest --non-interactive --ci=circleci --hosting=lagoon
```

Force installation with backups:
```bash
php scaffold-installer.php --latest --force
```

Custom GitHub source:
```bash
php scaffold-installer.php --latest --github-repo="myorg/scaffold-toolkit" --github-branch="develop"
```

## Testing

The toolkit includes a comprehensive test suite that verifies all configuration combinations.

### Prerequisites

- Docker
- Docker Compose V2
- Ahoy CLI tool

### Environment Setup

Start the testing environment:
```bash
ahoy up
```

Stop and clean up:
```bash
ahoy down
```

### Running Tests

Run the full test suite:
```bash
ahoy test
```

The test suite:
- Tests all combinations of CI/CD and hosting configurations
- Performs both normal and force installations
- Uses local files instead of GitHub for testing
- Cleans the environment between tests
- Shows directory contents after each test
- Provides colored output for pass/fail status

### Test Output

Example test output:
```
Running test: Install - circleci with lagoon
✓ Test passed: Install - circleci with lagoon

Test directory contents for Install - circleci with lagoon:
.circleci/
└── config.yml
renovate.json

Running test: Install - circleci with acquia
✓ Test passed: Install - circleci with acquia
...

✓ All tests passed
```

## Project Structure

```
.
├── ci/
│   ├── circleci/               # CircleCI configuration
│   │   ├── acquia/            # Acquia-specific config
│   │   └── lagoon/            # Lagoon-specific config
│   └── gha/                   # GitHub Actions
│       ├── acquia/            # Acquia-specific config
│       └── lagoon/            # Lagoon-specific config
├── renovatebot/
│   └── drupal/                # Drupal-specific Renovate config
│       └── renovate.json
├── scaffold-installer.php     # Main installer script
├── Dockerfile.test           # Testing environment setup
├── docker-compose.test.yml   # Docker Compose configuration
└── .ahoy.yml                 # Ahoy commands for testing
```

## File Versioning

All scaffold files include version metadata:
```
# Version: 1.0.0
# Customized: false
```

The installer tracks these versions and prompts for updates when newer versions are available.

## Contributing

1. Fork the repository
2. Create your feature branch
3. Add tests for any new functionality
4. Ensure all tests pass with `ahoy test`
5. Submit a pull request

## License

MIT License - see LICENSE file for details 