<?php

declare(strict_types=1);

/**
 * Scaffold Toolkit Installer.
 *
 * This script handles the installation and configuration of CI/CD setups
 * for Drupal projects, supporting both CircleCI and GitHub Actions,
 * with Lagoon and Acquia hosting environments.
 */

namespace SalsaDigital\ScaffoldToolkit;

class ScaffoldInstaller {
    private string $version = '1.0.0';
    private bool $dryRun = false;
    private bool $force = false;
    private bool $nonInteractive = false;
    private ?string $ciType = null;
    private ?string $hostingType = null;
    private string $sourceDir;
    private string $targetDir;
    private array $fileVersions = [];
    private array $backupFiles = [];
    private array $errors = [];
    private bool $useLocalFiles = false;
    private string $githubRepo = 'salsadigitalauorg/scaffold-toolkit';
    private string $githubBranch = 'main';

    public function __construct(array $options = []) {
        $this->dryRun = isset($options['dry-run']);
        $this->force = isset($options['force']);
        $this->nonInteractive = isset($options['non-interactive']);
        $this->ciType = $options['ci'] ?? null;
        $this->hostingType = $options['hosting'] ?? null;
        $this->sourceDir = $options['source-dir'] ?? '.';
        $this->targetDir = $options['target-dir'] ?? '.';
        $this->useLocalFiles = isset($options['use-local-files']);
        
        if (isset($options['version'])) {
            $this->version = $options['version'];
        }
        
        if (isset($options['github-repo'])) {
            $this->githubRepo = $options['github-repo'];
        }
        
        if (isset($options['github-branch'])) {
            $this->githubBranch = $options['github-branch'];
        }
    }

    /**
     * Run the installation process.
     */
    public function run(): void {
        $this->printHeader();
        $this->validateEnvironment();
        $this->selectCiType();
        $this->selectHostingType();
        
        // Validate existing files before making any changes
        $this->validateExistingFiles();
        
        if (!$this->dryRun) {
            $this->createBackups();
        }
        
        $this->ensureDirectoriesExist();
        $this->installFiles();
        $this->printSummary();
    }

    /**
     * Print the installer header.
     */
    private function printHeader(): void {
        echo "Scaffold Toolkit Installer v{$this->version}\n";
        echo "=====================================\n\n";
    }

    /**
     * Validate the PHP environment.
     */
    private function validateEnvironment(): void {
        if (PHP_VERSION_ID < 80100) {
            throw new \RuntimeException('PHP 8.1 or higher is required.');
        }

        if (!extension_loaded('curl')) {
            throw new \RuntimeException('cURL extension is required.');
        }
    }

    /**
     * Select the CI/CD type.
     */
    private function selectCiType(): void {
        if ($this->ciType === null) {
            if ($this->nonInteractive) {
                throw new \RuntimeException('CI type must be specified in non-interactive mode');
            }
            echo "Select CI/CD integration:\n";
            echo "1. CircleCI\n";
            echo "2. GitHub Actions\n";
            $choice = trim(fgets(STDIN));
            $this->ciType = $choice === '1' ? 'circleci' : 'github';
        }
    }

    /**
     * Select the hosting environment.
     */
    private function selectHostingType(): void {
        if ($this->hostingType === null) {
            if ($this->nonInteractive) {
                throw new \RuntimeException('Hosting type must be specified in non-interactive mode');
            }
            echo "Select hosting environment:\n";
            echo "1. Lagoon\n";
            echo "2. Acquia\n";
            $choice = trim(fgets(STDIN));
            $this->hostingType = $choice === '1' ? 'lagoon' : 'acquia';
        }
    }

    /**
     * Validate existing files and ask for confirmation.
     */
    private function validateExistingFiles(): void {
        $files = $this->getFileList();
        $existingFiles = [];
        
        foreach ($files as $file) {
            if (file_exists($file['target'])) {
                $existingFiles[] = $file['target'];
            }
        }
        
        if (!empty($existingFiles)) {
            echo "\nThe following files already exist:\n";
            foreach ($existingFiles as $file) {
                echo "- {$file}\n";
            }
            
            if (!$this->force && !$this->nonInteractive) {
                echo "\nWould you like to overwrite these files? (This will create backups) [y/n] ";
                $answer = trim(fgets(STDIN));
                if (strtolower($answer) === 'y') {
                    $this->force = true;
                } else {
                    echo "Installation cancelled.\n";
                    exit(0);
                }
            } else if (!$this->force) {
                echo "\nUse --force to overwrite existing files.\n";
                exit(1);
            }
            
            if (!$this->dryRun) {
                if (!$this->nonInteractive) {
                    echo "\nWARNING: These files will be backed up and overwritten.\n";
                    echo "Continue? [y/n] ";
                    $answer = trim(fgets(STDIN));
                    if (strtolower($answer) !== 'y') {
                        echo "Installation cancelled.\n";
                        exit(0);
                    }
                }
            }
        }
    }

    /**
     * Create backups of existing files.
     */
    private function createBackups(): void {
        $files = $this->getFileList();
        foreach ($files as $file) {
            if (file_exists($file['target'])) {
                $backupFile = $file['target'] . '.bak.' . date('Y-m-d-His');
                if (!copy($file['target'], $backupFile)) {
                    throw new \RuntimeException("Failed to create backup of {$file['target']}");
                }
                $this->backupFiles[] = $backupFile;
                echo "Created backup: {$backupFile}\n";
            }
        }
    }

    /**
     * Ensure required directories exist.
     */
    private function ensureDirectoriesExist(): void {
        $directories = [
            '.circleci',
            '.github/workflows',
            'renovatebot',
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if ($this->dryRun) {
                    echo "[DRY RUN] Would create directory: {$dir}\n";
                } else {
                    if (!mkdir($dir, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$dir}");
                    }
                    echo "Created directory: {$dir}\n";
                }
            }
        }
    }

    /**
     * Install configuration files.
     */
    private function installFiles(): void {
        $files = $this->getFileList();
        foreach ($files as $file) {
            $this->processFile($file);
        }
    }

    /**
     * Get the list of files to process based on selections.
     */
    private function getFileList(): array {
        $files = [];
        
        // CI/CD configuration files
        if ($this->ciType === 'circleci') {
            $files[] = [
                'source' => "ci/circleci/{$this->hostingType}/config.yml",
                'target' => '.circleci/config.yml',
            ];
        } else {
            if ($this->hostingType === 'lagoon') {
                echo "\nNOTE: GitHub Actions integration for Lagoon is not yet available.\n";
                echo "Only RenovateBot configuration will be installed.\n\n";
            } else {
                $files[] = [
                    'source' => "ci/gha/{$this->hostingType}/build-test-deploy.yml",
                    'target' => '.github/workflows/build-test-deploy.yml',
                ];
            }
        }

        // RenovateBot configuration
        $files[] = [
            'source' => 'renovatebot/drupal/renovate.json',
            'target' => 'renovate.json',
        ];

        return $files;
    }

    /**
     * Process a single file.
     */
    private function processFile(array $file): void {
        $targetFile = $this->targetDir . '/' . $file['target'];
        
        echo "Processing {$targetFile}...\n";

        try {
            // Create target directory if it doesn't exist
            $targetDir = dirname($targetFile);
            if (!is_dir($targetDir)) {
                if ($this->dryRun) {
                    echo "[DRY RUN] Would create directory: {$targetDir}\n";
                } else {
                    if (!mkdir($targetDir, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$targetDir}");
                    }
                    echo "Created directory: {$targetDir}\n";
                }
            }

            if ($this->dryRun) {
                echo "[DRY RUN] Would copy file to {$targetFile}\n";
                return;
            }

            // Get file content either from local source or GitHub
            $content = $this->getFileContent($file['source']);
            if ($content === false) {
                throw new \RuntimeException("Failed to get content for {$file['source']}");
            }

            if (file_exists($targetFile) && !$this->force) {
                $currentVersion = $this->getFileVersion($targetFile);
                $newVersion = $this->getVersionFromContent($content);
                
                if ($currentVersion && !$this->shouldOverwrite($currentVersion, $newVersion)) {
                    echo "Skipping {$targetFile} (current version: {$currentVersion})\n";
                    return;
                }
            }

            if (!file_put_contents($targetFile, $content)) {
                throw new \RuntimeException("Failed to write to {$targetFile}");
            }
            echo "Installed {$targetFile}\n";
        } catch (\Exception $e) {
            $this->addError("Error processing {$targetFile}: " . $e->getMessage());
            if ($this->nonInteractive) {
                throw $e;
            }
        }
    }

    /**
     * Get file content either from local source or GitHub.
     */
    private function getFileContent(string $sourcePath): string|false {
        if ($this->useLocalFiles) {
            $localPath = $this->sourceDir . '/' . $sourcePath;
            if (!file_exists($localPath)) {
                return false;
            }
            return file_get_contents($localPath);
        }

        // Construct GitHub raw content URL
        $githubUrl = sprintf(
            'https://raw.githubusercontent.com/%s/refs/heads/%s/%s',
            $this->githubRepo,
            $this->githubBranch,
            $sourcePath
        );

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $githubUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        // Add user agent to avoid GitHub API rate limiting
        curl_setopt($ch, CURLOPT_USERAGENT, 'Scaffold Toolkit Installer');

        // Execute the request
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($content === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Failed to download file from GitHub: $error (HTTP $httpCode)");
        }
        
        curl_close($ch);

        return $content;
    }

    /**
     * Extract version from file content.
     */
    private function getVersionFromContent(string $content): ?string {
        if (preg_match('/# Version: ([0-9.]+)/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Get version from existing file.
     */
    private function getFileVersion(string $file): ?string {
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        return $this->getVersionFromContent($content);
    }

    /**
     * Determine if a file should be overwritten.
     */
    private function shouldOverwrite(string $currentVersion, ?string $newVersion): bool {
        if ($newVersion === null) {
            return false;
        }

        echo "Current version: {$currentVersion}\n";
        echo "Repository version: {$newVersion}\n";

        if ($this->nonInteractive) {
            return $this->force;
        }

        echo "Would you like to override this file? [y/n] ";
        $answer = trim(fgets(STDIN));
        return strtolower($answer) === 'y';
    }

    /**
     * Add an error message.
     */
    private function addError(string $message): void {
        $this->errors[] = $message;
    }

    /**
     * Print installation summary.
     */
    private function printSummary(): void {
        echo "\nInstallation Summary\n";
        echo "===================\n";
        echo "CI/CD Type: " . ucfirst($this->ciType) . "\n";
        echo "Hosting: " . ucfirst($this->hostingType) . "\n";
        echo "Mode: " . ($this->dryRun ? 'Dry Run' : 'Live') . "\n";
        
        if (!empty($this->backupFiles)) {
            echo "\nBackup files created:\n";
            foreach ($this->backupFiles as $file) {
                echo "- {$file}\n";
            }
        }

        if (!empty($this->errors)) {
            echo "\nErrors:\n";
            foreach ($this->errors as $error) {
                echo "- {$error}\n";
            }
        }
        
        if ($this->dryRun) {
            echo "\nThis was a dry run. No files were modified.\n";
            echo "Run without --dry-run to apply changes.\n";
        }

        if (empty($this->errors) && !$this->dryRun && !$this->nonInteractive) {
            $this->askCleanup();
        }

        if (!empty($this->errors)) {
            exit(1);
        }
    }

    /**
     * Ask user if they want to remove the installer file.
     */
    private function askCleanup(): void {
        echo "\nWould you like to remove the installer file (scaffold-installer.php)? [y/n] ";
        $answer = trim(fgets(STDIN));
        if (strtolower($answer) === 'y') {
            $installerFile = __FILE__;
            if (unlink($installerFile)) {
                echo "Installer file removed successfully.\n";
            } else {
                echo "Failed to remove installer file. You can delete it manually.\n";
            }
        }
    }
}

// Parse command line arguments
$options = getopt('', [
    'latest',
    'version:',
    'dry-run',
    'force',
    'ci:',
    'hosting:',
    'source-dir:',
    'target-dir:',
    'non-interactive',
    'use-local-files',
    'github-repo:',
    'github-branch:'
]);

try {
    $installer = new ScaffoldInstaller($options);
    $installer->run();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 