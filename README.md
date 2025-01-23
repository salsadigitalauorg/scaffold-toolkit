# Scaffold Toolkit

A toolkit for scaffolding CI/CD configurations for Drupal projects, supporting both CircleCI and GitHub Actions with Lagoon and Acquia hosting environments.

## Features

- Interactive installation process
- Support for CircleCI and GitHub Actions
- Support for Lagoon and Acquia hosting environments
- Automated file versioning and metadata tracking
- Backup creation for existing files
- Non-interactive mode for automated testing
- Comprehensive test suite covering all configuration combinations

## Installation

Download and run the installer:

```bash
curl -O https://raw.githubusercontent.com/salsadigitalauorg/scaffold-toolkit/main/scaffold-installer.php
php scaffold-installer.php --latest
```

## Usage

### Command Line Options

- `--latest`: Use the latest version of scaffold files
- `--version=<tag>`: Use a specific version
- `--force`: Overwrite existing files (creates backups)
- `--ci=<circleci|github>`: Pre-select CI/CD integration
- `--hosting=<lagoon|acquia>`: Pre-select hosting environment
- `--source-dir=<path>`: Specify source directory for scaffold files
- `--target-dir=<path>`: Specify target directory for installation
- `--non-interactive`: Run in non-interactive mode (requires --ci and --hosting)

### Examples

Interactive installation:
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
├── ci
│   ├── circleci                  # CircleCI configuration
│   │   ├── acquia                # Configuration for Acquia and CircleCI
│   │   └── lagoon                # Configuration for Lagoon and CircleCI
│   └── gha                       # GitHub Actions configuration
│       ├── acquia                # Configuration for Acquia and GitHub Actions
│       └── lagoon                # Configuration for Lagoon and GitHub Actions
└── renovatebot                   # Renovate configuration
    └── drupal
        └── renovate.json         # Renovate configuration for Drupal
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