# Scaffold Toolkit

A toolkit for scaffolding CI/CD configurations for Drupal projects, supporting both CircleCI and GitHub Actions, with Lagoon and Acquia hosting environments.

## Prerequisites

- [Docker](https://docs.docker.com/get-docker/)
- [Docker Compose V2](https://docs.docker.com/compose/install/)
- [Ahoy](https://github.com/ahoy-cli/ahoy#installation)

## Features

- Interactive installation process
- Support for CircleCI and GitHub Actions
- Support for Lagoon and Acquia hosting environments
- Automated file versioning and metadata tracking
- Shared RenovateBot configuration
- Dry-run mode for safe testing
- Automatic backup of existing files
- Safe overwrite protection
- Automated testing environment

## Installation

1. Download the installer:
```bash
curl -O https://raw.githubusercontent.com/salsadigitalauorg/scaffold-toolkit/main/scaffold-installer.php
```

2. Run the installer:
```bash
php scaffold-installer.php --latest
```

## Usage

### Basic Installation
```bash
# Dry run to see what would change
php scaffold-installer.php --latest --dry-run

# Install with confirmation for each change
php scaffold-installer.php --latest

# Force installation (overwrites existing files with backup)
php scaffold-installer.php --latest --force
```

### Options
- `--latest`: Fetch and apply the latest scaffold release
- `--version=<tag>`: Fetch and apply a specific release
- `--dry-run`: Simulate changes and output a report
- `--force`: Allow overwriting of existing files (creates backups)
- `--ci=<circleci|github>`: Pre-select CI/CD integration
- `--hosting=<lagoon|acquia>`: Pre-select hosting environment

### Testing Environment

The project includes a Dockerized testing environment for validating the installer script functionality.

#### Using Ahoy Commands

1. Start the testing environment:
```bash
ahoy up
```

2. Run all installer tests:
```bash
ahoy test
```

3. Stop and clean up the environment:
```bash
ahoy down
```

#### Manual Testing Commands

If you prefer not to use Ahoy, you can run the commands directly:

1. Build and start the testing environment:
```bash
docker compose -f docker-compose.test.yml build
docker compose -f docker-compose.test.yml up -d
```

2. Run installer tests:
```bash
# Test with dry run
docker compose -f docker-compose.test.yml exec test php scaffold-installer.php --latest --dry-run

# Test with CircleCI and Lagoon
docker compose -f docker-compose.test.yml exec test php scaffold-installer.php --latest --ci=circleci --hosting=lagoon

# Test with GitHub Actions and Acquia
docker compose -f docker-compose.test.yml exec test php scaffold-installer.php --latest --ci=github --hosting=acquia

# Test with force flag
docker compose -f docker-compose.test.yml exec test php scaffold-installer.php --latest --force
```

#### Testing Environment Features
- PHP 8.3 CLI environment with required extensions
- Pre-created test directories
- Volume mounting for live code updates
- Automated test scenarios for all installation options
- Clean environment for each test run

### Safety Features

1. **Dry Run Mode**
   - Use `--dry-run` to see what changes would be made
   - No files are modified in this mode

2. **Backup Creation**
   - Automatic backup of existing files before modification
   - Backups are timestamped: `filename.bak.YYYY-MM-DD-HHMMSS`

3. **Overwrite Protection**
   - By default, existing files are not overwritten
   - Use `--force` to enable overwriting with backup
   - Interactive confirmation before overwriting

### Environment Variables
The following environment variables need to be set in your CI/CD platform:

#### Common Variables
- `SCAFFOLD_TOOLKIT_SSH_FINGERPRINT`: SSH key fingerprint for deployments
- `SCAFFOLD_TOOLKIT_CACHE_TAG`: Cache tag for CI/CD caching
- `GITHUB_TOKEN`: GitHub token for private repository access (optional)

#### Acquia-specific
- `ACQUIA_DB_NAME`: Acquia database name
- `ACQUIA_APP_NAME`: Acquia application name

#### Lagoon-specific
- `LAGOON_PROJECT`: Lagoon project name
- `DREVOPS_DEPLOY_TYPES`: Deployment types configuration

## Project Structure

```
.
├── ci/
│   ├── circleci/               # CircleCI configuration
│   │   ├── acquia/            # Configuration for Acquia and CircleCI
│   │   └── lagoon/            # Configuration for Lagoon and CircleCI
│   └── gha/                   # GitHub Actions configuration
│       ├── acquia/            # Configuration for Acquia and GitHub Actions
│       └── lagoon/            # Configuration for Lagoon and GitHub Actions
├── renovatebot/               # Renovate configuration
│   ├── drupal/                # Drupal-specific Renovate config
│   │   └── renovate.json
│   └── govcms_paas/           # GovCMS PAAS Renovate config
│       └── renovate.json
├── Dockerfile.test            # Testing environment Dockerfile
└── docker-compose.test.yml    # Testing environment configuration
```

## File Versioning

All scaffold files include metadata for version tracking:
```yaml
# Version: 1.0.0
# Customized: false
```

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details. 