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
  - Lagoon
  - Acquia
- Automated file versioning
- Dry-run mode
- Backup creation for existing files

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

### Non-Interactive Mode
Specify all required options for automated installation:
```bash
php scaffold-installer.php --scaffold=drevops --ci=circleci --hosting=lagoon
```

### Options
- `--scaffold=<type>`: Scaffold type (drevops, vortex, govcms)
- `--ci=<type>`: CI/CD type (circleci, github)
- `--hosting=<type>`: Hosting environment (lagoon, acquia)
- `--latest`: Use latest version
- `--version=<tag>`: Use specific version
- `--dry-run`: Show what would be changed without making changes
- `--force`: Overwrite existing files (creates backups)
- `--non-interactive`: Run without prompts
- `--source-dir=<path>`: Source directory for files
- `--target-dir=<path>`: Target directory for installation
- `--github-repo=<repo>`: Custom GitHub repository
- `--github-branch=<branch>`: Custom GitHub branch

### Safety Features

- **Dry Run Mode**: Use `--dry-run` to simulate changes without modifying files
- **Backup Creation**: Automatic backups of existing files before modification (format: filename.bak.YYYY-MM-DD-His)
- **Overwrite Protection**: By default, won't overwrite existing files. Use `--force` for overwriting with backups

### Examples

1. Interactive installation:
```bash
php scaffold-installer.php --latest
```

2. Non-interactive installation with DrevOps and CircleCI:
```bash
php scaffold-installer.php --latest --scaffold=drevops --ci=circleci --hosting=lagoon --non-interactive
```

3. Dry run to preview changes:
```bash
php scaffold-installer.php --latest --scaffold=drevops --ci=circleci --hosting=lagoon --dry-run
```

4. Force installation with backups:
```bash
php scaffold-installer.php --latest --scaffold=drevops --ci=circleci --hosting=lagoon --force
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