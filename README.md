# Scaffold Toolkit

A toolkit for scaffolding CI/CD configurations for Drupal projects.

## Features

- Interactive installation process
- Support for multiple scaffold types:
  - DrevOps (available)
  - Vortex (coming soon)
  - GovCMS PaaS (coming soon)
- CI/CD integrations:
  - CircleCI (available)
  - GitHub Actions (coming soon)
- Hosting environments:
  - Lagoon (with cluster selection)
    - SalsaDigital cluster support
    - Automatic environment variable configuration
  - Acquia
- Automated file versioning
- Dry-run mode
- Backup creation for existing files
- Local configuration storage (.scaffold-toolkit.json)

## Installation

Download and run the installer:

```bash
curl -O https://raw.githubusercontent.com/salsadigitalauorg/scaffold-toolkit/main/scaffold-installer.php
php scaffold-installer.php
```

## Usage

### Interactive Mode
Run the installer without options to use interactive mode:
```bash
php scaffold-installer.php
```

The installer will prompt you for:
1. Scaffold type selection
2. CI/CD integration selection
3. Hosting environment selection
   - For Lagoon hosting, additional cluster selection:
     - SalsaDigital (configures environment variables automatically)
     - Other
4. SSH key fingerprint (for CircleCI)
5. Scripts directory and .twig_cs.php update confirmation

The installer will show a final review of all changes and ask for confirmation before proceeding.

### Local Configuration Storage
The installer saves your configuration choices in `.scaffold-toolkit.json` for future use. This includes:
- Selected scaffold type
- CI/CD integration type
- Hosting environment
- Lagoon cluster selection
- SSH fingerprint (for CircleCI)

These saved values will be suggested as defaults in future runs.

### Environment Variables
When selecting the SalsaDigital Lagoon cluster, the following environment variables are automatically added to your `.env` file if not already present:
```
LAGOON_WEBHOOK_ENDPOINT=https://webhookhandler.salsa.hosting/
DREVOPS_DEPLOY_LAGOON_INSTANCE=salsa
DREVOPS_DEPLOY_LAGOON_INSTANCE_GRAPHQL=https://api.salsa.hosting/graphql
DREVOPS_DEPLOY_LAGOON_INSTANCE_HOSTNAME=ssh.salsa.hosting
DREVOPS_DB_DOWNLOAD_LAGOON_SSH_HOST=ssh.salsa.hosting
DREVOPS_TASK_LAGOON_INSTANCE_HOSTNAME=ssh.salsa.hosting
DREVOPS_DEPLOY_LAGOON_INSTANCE_PORT=22
DREVOPS_DB_DOWNLOAD_LAGOON_SSH_PORT=22
DREVOPS_TASK_LAGOON_INSTANCE_PORT=22
DREVOPS_DB_DOWNLOAD_SOURCE=lagoon
DREVOPS_DEPLOY_TYPES=lagoon
DREVOPS_NOTIFY_EMAIL_RECIPIENTS=servicedesk.team@salsa.digital|Serice Desk
DREVOPS_DEPLOY_LAGOON_LAGOONCLI_VERSION=v0.21.3
```

Additionally, when Lagoon is selected as the hosting environment (either through interactive mode or `--hosting=lagoon`), the following variables are automatically set in your `.env` file:
- `DREVOPS_DB_DOWNLOAD_SOURCE=lagoon`
- `DREVOPS_DEPLOY_TYPES=lagoon`
- `DREVOPS_NOTIFY_EMAIL_RECIPIENTS=servicedesk.team@salsa.digital|Serice Desk`
- `DREVOPS_DEPLOY_LAGOON_LAGOONCLI_VERSION=v0.21.3`

### Non-Interactive Mode
Specify all required options for automated installation:
```bash
php scaffold-installer.php --scaffold=drevops --ci=circleci --hosting=lagoon --ssh-fingerprint="01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef"
```

### Options

Required options when using `--non-interactive`:
- `--scaffold=<type>`: Scaffold type (drevops, vortex, govcms)
- `--ci=<type>`: CI/CD type (circleci, github)
- `--hosting=<type>`: Hosting environment (lagoon, acquia)
- `--ssh-fingerprint=<fingerprint>`: SSH key fingerprint (required only for CircleCI)

Optional flags and their defaults:
- `--distribution=<type>`: Distribution type (default: drupal)
- `--latest`: Use latest version
- `--version=<tag>`: Use specific version
- `--dry-run`: Show what would be changed without making changes
- `--force`: Overwrite existing files (creates backups)
- `--non-interactive`: Run without prompts
- `--source-dir=<path>`: Source directory for files
- `--target-dir=<path>`: Target directory for installation (default: current directory)
- `--use-local-files`: Use local files instead of downloading from GitHub
- `--github-repo=<repo>`: Custom GitHub repository (default: salsadigitalauorg/scaffold-toolkit)
- `--github-branch=<branch>`: Custom GitHub branch (default: main)
- `--lagoon-cluster=<type>`: Lagoon cluster type (salsa, other) - only used when hosting=lagoon

### Lagoon Cluster Configuration

When using Lagoon hosting, you can specify the cluster type:

1. SalsaDigital Cluster (`--lagoon-cluster=salsa`):
   - Automatically configures environment variables for the SalsaDigital cluster
   - Sets up correct webhook endpoints and API URLs
   - Configures SSH hosts and ports
   - Creates/updates `drush/sites/lagoon.site.yml` with proper Lagoon configuration

2. Other Clusters (`--lagoon-cluster=other`):
   - Requires manual configuration of Lagoon-specific environment variables
   - You'll need to set up your own webhook endpoints and API URLs
   - You'll need to configure your own `drush/sites/lagoon.site.yml`

Example with SalsaDigital cluster:
```bash
php scaffold-installer.php --scaffold=drevops --ci=circleci --hosting=lagoon --lagoon-cluster=salsa
```

### CircleCI Environment Variables

#### Required Variables
Set these variables in your CircleCI project settings:

- `CIRCLE_PROJECT_REPONAME`: Your repository name (automatically set by CircleCI)
- `SCAFFOLD_TOOLKIT_CACHE_TAG`: Cache tag for build caching
- `DOCKER_PASS`: Docker Hub password for image pulls
- `RENOVATE_TOKEN`: Token for RenovateBot operations

Note: The SSH key fingerprint is now handled through the installer's interactive prompt or the `--ssh-fingerprint` option.

#### Optional Variables for Build Flexibility
These variables can be set to allow builds to pass while fixing code issues:

```bash
# Set to 1 to ignore failures for specific checks
DREVOPS_CI_HADOLINT_IGNORE_FAILURE=1    # Docker linting
DREVOPS_CI_PHPCS_IGNORE_FAILURE=1       # PHP CodeSniffer
DREVOPS_CI_PHPSTAN_IGNORE_FAILURE=1     # PHPStan
DREVOPS_CI_RECTOR_IGNORE_FAILURE=1      # Rector
DREVOPS_CI_PHPMD_IGNORE_FAILURE=1       # PHP Mess Detector
DREVOPS_CI_TWIGCS_IGNORE_FAILURE=1      # Twig CodeSniffer
DREVOPS_CI_NPM_LINT_IGNORE_FAILURE=1    # NPM linting
```

### Safety Features

- **Dry Run Mode**: Use `--dry-run` to simulate changes without modifying files
- **Backup Creation**: Automatic backups of existing files before modification (format: filename.bak.YYYY-MM-DD-His)
- **Overwrite Protection**: By default, won't overwrite existing files. Use `--force` for overwriting with backups
- **Final Review**: Shows all changes that will be made and requires confirmation before proceeding
- **Scripts Protection**: Creates backups of existing scripts directory and .twig_cs.php file before updating

### Important Notes

The installer can update your project's `scripts` directory and `.twig_cs.php` file. This is recommended as the tooling depends on certain versions of DrevOps scaffold, and missing this step may trigger broken pipelines. The installer will:

1. Create backups of existing files
2. Replace the `scripts` directory with the latest version
3. Update `.twig_cs.php` file
4. Show a summary of all changes made

### Examples

1. Interactive installation:
```bash
php scaffold-installer.php --latest
```

2. Non-interactive installation with DrevOps and CircleCI:
```bash
php scaffold-installer.php --latest --scaffold=drevops --ci=circleci --hosting=lagoon --non-interactive --ssh-fingerprint="01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef"
```

3. Dry run to preview changes:
```bash
php scaffold-installer.php --latest --scaffold=drevops --ci=circleci --hosting=lagoon --dry-run --ssh-fingerprint="01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef"
```

4. Force installation with backups:
```bash
php scaffold-installer.php --latest --scaffold=drevops --ci=circleci --hosting=lagoon --force --ssh-fingerprint="01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef"
```

## Project Structure
```
.
├── ci/
│   ├── circleci/               # CircleCI configuration
│   │   ├── acquia/            # Acquia-specific config
│   │   └── lagoon/            # Lagoon-specific config
│   └── gha/                   # GitHub Actions (coming soon)
│       ├── acquia/            # Acquia-specific config
│       └── lagoon/            # Lagoon-specific config
└── renovatebot/
    └── drupal/                # Drupal-specific Renovate config
        └── renovate.json
```

## Testing

Run tests using Ahoy commands:

```bash
# Start testing environment
ahoy up

# Run all tests
ahoy test

# Stop and clean environment
ahoy down
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License.