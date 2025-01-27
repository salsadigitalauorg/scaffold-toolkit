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
    private string $version = '1.0.12';
    private bool $dryRun = false;
    private bool $force = false;
    private bool $nonInteractive = false;
    private ?string $ciType = null;
    private ?string $hostingType = null;
    private string $sourceDir;
    private string $targetDir;
    private array $fileVersions = [];
    private array $errors = [];
    private bool $useLocalFiles = false;
    private string $githubRepo = 'salsadigitalauorg/scaffold-toolkit';
    private string $githubBranch = 'main';
    private ?string $scaffoldType = null;
    private ?string $distributionType = null;
    private ?string $sshFingerprint = null;
    private bool $shouldUpdateScripts = false;
    private array $changedFiles = [];
    private array $savedValues = [];
    private string $configFile;
    private string $installerDir;

    public function __construct(array $options = []) {
        $this->dryRun = isset($options['dry-run']);
        $this->force = isset($options['force']);
        $this->nonInteractive = isset($options['non-interactive']);
        $this->ciType = $options['ci'] ?? null;
        $this->hostingType = $options['hosting'] ?? null;
        $this->targetDir = $options['target-dir'] ?? '.';
        $this->useLocalFiles = isset($options['use-local-files']);
        $this->scaffoldType = $options['scaffold'] ?? null;
        $this->distributionType = $options['distribution'] ?? 'drupal';
        $this->sshFingerprint = $options['ssh-fingerprint'] ?? null;
        $this->configFile = $this->targetDir . '/.scaffold-toolkit.json';
        $this->installerDir = $this->targetDir . '/.scaffold-installer';
        
        if (isset($options['version'])) {
            $this->version = $options['version'];
        }
        
        if (isset($options['github-repo'])) {
            $this->githubRepo = $options['github-repo'];
        }
        
        if (isset($options['github-branch'])) {
            $this->githubBranch = $options['github-branch'];
        }

        $this->loadSavedValues();
    }

    /**
     * Load previously saved values from config file.
     */
    private function loadSavedValues(): void {
        if (file_exists($this->configFile)) {
            $content = file_get_contents($this->configFile);
            if ($content !== false) {
                $values = json_decode($content, true);
                if (is_array($values)) {
                    $this->savedValues = $values;
                    
                    // Load saved values if not provided in options
                    if (!$this->scaffoldType && isset($this->savedValues['scaffold_type'])) {
                        $this->scaffoldType = $this->savedValues['scaffold_type'];
                    }
                    if (!$this->ciType && isset($this->savedValues['ci_type'])) {
                        $this->ciType = $this->savedValues['ci_type'];
                    }
                    if (!$this->hostingType && isset($this->savedValues['hosting_type'])) {
                        $this->hostingType = $this->savedValues['hosting_type'];
                    }
                    if (!$this->sshFingerprint && isset($this->savedValues['ssh_fingerprint'])) {
                        $this->sshFingerprint = $this->savedValues['ssh_fingerprint'];
                    }
                }
            }
        }
    }

    /**
     * Save current values to config file.
     */
    private function saveValues(): void {
        if ($this->dryRun) {
            return;
        }

        $values = [
            'scaffold_type' => $this->scaffoldType,
            'ci_type' => $this->ciType,
            'hosting_type' => $this->hostingType,
            'ssh_fingerprint' => $this->sshFingerprint,
        ];

        if (file_put_contents($this->configFile, json_encode($values, JSON_PRETTY_PRINT)) === false) {
            echo "Warning: Failed to save configuration values.\n";
        }
    }

    /**
     * Run the installation process.
     */
    public function run(): void {
        $this->printHeader();
        $this->validateEnvironment();

        $this->downloadRepository();

        $this->sourceDir = $this->installerDir;
        $this->useLocalFiles = true;

        $this->selectScaffoldType();
        $this->selectCiType();
        $this->selectHostingType();
        
        if ($this->ciType === 'circleci' && !$this->sshFingerprint) {
            $this->sshFingerprint = $this->promptSshFingerprint();
        }

        if (!$this->nonInteractive) {
            $this->promptScriptsUpdate();
        }
        
        if (!$this->nonInteractive && !$this->confirmChanges()) {
            $this->cleanup();
            echo "Installation cancelled.\n";
            exit(1);
        }
        
        $this->validateExistingFiles();
        
        $this->ensureDirectoriesExist();
        $this->installFiles();

        if ($this->shouldUpdateScripts) {
            $this->updateScriptsAndTwigCs();
        }

        $this->saveValues();
        $this->printSummary();
        $this->cleanup();
    }

    /**
     * Download the repository into a local directory.
     */
    private function downloadRepository(): void {
        echo "Downloading scaffold files...\n";

        // Remove existing installer directory if it exists
        if (is_dir($this->installerDir)) {
            echo "Removing existing installer directory...\n";
            if (!$this->removeDirectory($this->installerDir)) {
                throw new \RuntimeException("Failed to remove existing installer directory: {$this->installerDir}");
            }
        }

        // Create installer directory
        if (!mkdir($this->installerDir, 0755, true)) {
            throw new \RuntimeException("Failed to create installer directory: {$this->installerDir}");
        }

        // Download repository archive
        $url = sprintf(
            'https://api.github.com/repos/%s/tarball/%s',
            $this->githubRepo,
            $this->githubBranch
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Scaffold Toolkit Installer');
        
        $tarball = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$tarball) {
            throw new \RuntimeException("Failed to download repository archive (HTTP {$httpCode})");
        }

        // Save tarball to a temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'scaffold_');
        if (!file_put_contents($tempFile, $tarball)) {
            throw new \RuntimeException("Failed to save repository archive");
        }

        // Extract using tar command (available on macOS and Linux)
        $command = sprintf('tar -xzf %s -C %s', escapeshellarg($tempFile), escapeshellarg($this->installerDir));
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            unlink($tempFile);
            throw new \RuntimeException("Failed to extract repository archive");
        }

        // Clean up the temporary file
        unlink($tempFile);

        // The archive creates a subdirectory with a generated name, move all files up
        $extractedDir = glob($this->installerDir . '/*', GLOB_ONLYDIR)[0];
        if (!$extractedDir) {
            throw new \RuntimeException("Failed to find extracted directory");
        }

        // Move files up one level
        $command = sprintf('mv %s/* %s/', escapeshellarg($extractedDir), escapeshellarg($this->installerDir));
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new \RuntimeException("Failed to move files from extracted directory");
        }

        // Remove the now-empty extracted directory
        rmdir($extractedDir);

        echo "Downloaded scaffold files successfully.\n\n";
    }

    /**
     * Clean up temporary files.
     */
    private function cleanup(): void {
        if (is_dir($this->installerDir)) {
            $this->removeDirectory($this->installerDir);
        }
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
     * Select the scaffold type.
     */
    private function selectScaffoldType(): void {
        if ($this->scaffoldType === null || !$this->nonInteractive) {
            if ($this->nonInteractive) {
                throw new \RuntimeException('Scaffold type must be specified in non-interactive mode');
            }
            echo "Select scaffold type:\n";
            echo "1. DrevOps\n";
            echo "2. Vortex (coming soon)\n";
            echo "3. GovCMS PaaS (coming soon)\n";
            
            if (isset($this->savedValues['scaffold_type'])) {
                echo "\nPreviously used: {$this->savedValues['scaffold_type']}\n";
                echo "Press Enter to use the previous value, or select a new option: ";
            }
            
            $choice = trim(fgets(STDIN));
            
            if ($choice === '' && isset($this->savedValues['scaffold_type'])) {
                $this->scaffoldType = $this->savedValues['scaffold_type'];
                return;
            }
            
            switch ($choice) {
                case '1':
                    $this->scaffoldType = 'drevops';
                    break;
                case '2':
                    echo "\nNOTE: Vortex scaffolding is not yet available.\n";
                    exit(1);
                case '3':
                    echo "\nNOTE: GovCMS PaaS scaffolding is not yet available.\n";
                    exit(1);
                default:
                    $this->scaffoldType = 'drevops';
            }
        } elseif ($this->scaffoldType !== 'drevops') {
            echo "\nNOTE: Only DrevOps scaffolding is currently available.\n";
            echo "Specified scaffold type '{$this->scaffoldType}' is not yet supported.\n";
            exit(1);
        }
    }

    /**
     * Select the CI/CD type.
     */
    private function selectCiType(): void {
        if ($this->ciType === null || !$this->nonInteractive) {
            if ($this->nonInteractive) {
                throw new \RuntimeException('CI type must be specified in non-interactive mode');
            }
            echo "Select CI/CD integration:\n";
            echo "1. CircleCI\n";
            echo "2. GitHub Actions (Coming soon)\n";
            
            if (isset($this->savedValues['ci_type'])) {
                echo "\nPreviously used: {$this->savedValues['ci_type']}\n";
                echo "Press Enter to use the previous value, or select a new option: ";
            }
            
            $choice = trim(fgets(STDIN));
            
            if ($choice === '' && isset($this->savedValues['ci_type'])) {
                $this->ciType = $this->savedValues['ci_type'];
                return;
            }
            
            if ($choice === '2') {
                echo "\nNOTE: GitHub Actions integration is not yet available.\n";
                echo "Only CircleCI is currently supported.\n\n";
                echo "Would you like to proceed with CircleCI instead? [Y/n] ";
                $answer = trim(fgets(STDIN)) ?: 'y';
                if (strtolower($answer) !== 'n') {
                    $this->ciType = 'circleci';
                } else {
                    echo "Installation cancelled.\n";
                    exit(1);
                }
            } else {
                $this->ciType = 'circleci';
            }
        } elseif ($this->ciType === 'github') {
            echo "\nNOTE: GitHub Actions integration is not yet available.\n";
            echo "Only CircleCI is currently supported.\n";
            exit(1);
        }
    }

    /**
     * Select the hosting environment.
     */
    private function selectHostingType(): void {
        if ($this->hostingType === null || !$this->nonInteractive) {
            if ($this->nonInteractive) {
                throw new \RuntimeException('Hosting type must be specified in non-interactive mode');
            }
            echo "Select hosting environment:\n";
            echo "1. Lagoon\n";
            echo "2. Acquia\n";
            
            if (isset($this->savedValues['hosting_type'])) {
                echo "\nPreviously used: {$this->savedValues['hosting_type']}\n";
                echo "Press Enter to use the previous value, or select a new option: ";
            }
            
            $choice = trim(fgets(STDIN));
            
            if ($choice === '' && isset($this->savedValues['hosting_type'])) {
                $this->hostingType = $this->savedValues['hosting_type'];
                return;
            }
            
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
            
            if (!$this->force) {
                if ($this->nonInteractive) {
                    echo "\nError: Files exist and --force option was not used.\n";
                    exit(1);
                }
                
                echo "\nWould you like to overwrite these files? [Y/n] ";
                $answer = trim(fgets(STDIN)) ?: 'y';
                if (strtolower($answer) === 'n') {
                    echo "Installation cancelled.\n";
                    exit(1);
                }
                $this->force = true;
            }
            
            if (!$this->dryRun && !$this->nonInteractive) {
                echo "\nWARNING: These files will be overwritten.\n";
                echo "Continue? [Y/n] ";
                $answer = trim(fgets(STDIN)) ?: 'y';
                if (strtolower($answer) === 'n') {
                    echo "Installation cancelled.\n";
                    exit(1);
                }
            }
        }
    }

    /**
     * Ensure required directories exist.
     */
    private function ensureDirectoriesExist(): void {
        $directories = [];
        
        // Only create CI/CD directories if needed
        if ($this->ciType === 'circleci') {
            $directories[] = '.circleci';
        } else if ($this->ciType === 'github' && $this->hostingType !== 'lagoon') {
            $directories[] = '.github/workflows';
        }
        
        // Always create renovatebot directory
        $directories[] = 'renovatebot';

        foreach ($directories as $dir) {
            $targetDir = $this->targetDir . '/' . $dir;
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
        }

        // RenovateBot configuration is always installed
        $files[] = [
            'source' => "renovatebot/{$this->distributionType}/renovate.json",
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

            // Replace repository placeholder with CI-specific variable
            $content = $this->replaceRepositoryPlaceholder($content);

            // Replace SSH fingerprint placeholder if this is a CircleCI config file
            if ($this->ciType === 'circleci' && $file['target'] === '.circleci/config.yml') {
                $content = $this->replaceSshFingerprintPlaceholder($content);
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
     * Replace repository placeholder with CI-specific variable.
     */
    private function replaceRepositoryPlaceholder(string $content): string {
        $placeholder = '[SCAFFOLD_TOOLKIT_REPOSITORY]';
        
        if ($this->ciType === 'circleci') {
            // CircleCI uses ${CIRCLE_PROJECT_REPONAME}
            $replacement = '${CIRCLE_PROJECT_REPONAME}';
        } else {
            // GitHub Actions uses ${{ github.repository }}
            $replacement = '${{ github.repository }}';
        }
        
        return str_replace($placeholder, $replacement, $content);
    }

    /**
     * Replace SSH fingerprint placeholder with actual fingerprint.
     */
    private function replaceSshFingerprintPlaceholder(string $content): string {
        if (!$this->sshFingerprint) {
            throw new \RuntimeException('SSH fingerprint is required for CircleCI configuration.');
        }
        
        return str_replace('${SCAFFOLD_TOOLKIT_SSH_FINGERPRINT}', $this->sshFingerprint, $content);
    }

    /**
     * Get file content either from local source or GitHub.
     */
    private function getFileContent(string $sourcePath): string|false {
        if ($this->useLocalFiles) {
            // For local files, we need to strip the .scaffold-installer prefix since files are already in that directory
            $localPath = $this->installerDir . '/' . preg_replace('/^\.scaffold-installer\//', '', $sourcePath);
            if (!file_exists($localPath)) {
                throw new \RuntimeException("Local file not found: {$localPath}");
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

        echo "Would you like to override this file? [Y/n] ";
        $answer = trim(fgets(STDIN)) ?: 'y';
        return strtolower($answer) !== 'n';
    }

    /**
     * Add an error message.
     */
    private function addError(string $message): void {
        $this->errors[] = $message;
    }

    /**
     * Prompt user about updating scripts directory and twig_cs.php.
     */
    private function promptScriptsUpdate(): void {
        echo "\nIMPORTANT: The installer can update your scripts directory and .twig_cs.php file.\n";
        echo "This is recommended as the tooling depends on certain versions of DrevOps scaffold.\n";
        echo "Missing this step may trigger broken pipelines.\n\n";
        echo "Would you like to proceed with updating scripts directory and .twig_cs.php? [Y/n] ";
        
        $answer = trim(fgets(STDIN)) ?: 'y';
        $this->shouldUpdateScripts = strtolower($answer) !== 'n';
    }

    /**
     * Show final review of changes and get confirmation.
     */
    private function confirmChanges(): bool {
        echo "\nFinal Review of Changes\n";
        echo "=====================\n";
        echo "The following changes will be made:\n\n";

        // List CI/CD configuration files
        if ($this->ciType === 'circleci') {
            echo "1. Install CircleCI configuration for {$this->hostingType}\n";
        }

        // List RenovateBot configuration
        echo "2. Install RenovateBot configuration\n";

        // List scripts and twig_cs.php changes if selected
        if ($this->shouldUpdateScripts) {
            echo "3. Replace existing scripts directory with new version\n";
            echo "4. Update .twig_cs.php file\n";
        }

        echo "\nWARNING: This operation will create backups of existing files before replacing them.\n";
        echo "Would you like to proceed with these changes? [Y/n] ";
        
        $answer = trim(fgets(STDIN)) ?: 'y';
        return strtolower($answer) !== 'n';
    }

    /**
     * Update scripts directory and twig_cs.php file.
     */
    private function updateScriptsAndTwigCs(): void {
        // Handle scripts directory
        $targetScriptsDir = $this->targetDir . '/scripts';
        if (is_dir($targetScriptsDir)) {
            $backupScriptsDir = $targetScriptsDir . '.bak.' . date('Y-m-d-His');
            if (!$this->dryRun) {
                if (!rename($targetScriptsDir, $backupScriptsDir)) {
                    throw new \RuntimeException("Failed to backup scripts directory");
                }
                echo "Created backup of scripts directory: {$backupScriptsDir}\n";
            }
        }

        // Download or copy scripts directory
        if (!$this->dryRun) {
            try {
                if ($this->useLocalFiles) {
                    $sourceScriptsDir = $this->sourceDir . '/scripts';
                    if (!$this->copyDirectory($sourceScriptsDir, $targetScriptsDir)) {
                        throw new \RuntimeException("Failed to copy scripts directory");
                    }
                } else {
                    // Create target scripts directory
                    if (!is_dir($targetScriptsDir) && !mkdir($targetScriptsDir, 0755, true)) {
                        throw new \RuntimeException("Failed to create scripts directory");
                    }
                    
                    // Download directly to the target directory
                    $this->downloadDirectoryRecursive('scripts', $targetScriptsDir);
                }

                // Set execute permissions on all .sh files
                $this->setScriptPermissions($targetScriptsDir);

                $this->changedFiles[] = 'scripts/';
                echo "Updated scripts directory\n";
            } catch (\Exception $e) {
                // If something goes wrong and we have a backup, restore it
                if (isset($backupScriptsDir) && is_dir($backupScriptsDir)) {
                    if (is_dir($targetScriptsDir)) {
                        $this->removeDirectory($targetScriptsDir);
                    }
                    rename($backupScriptsDir, $targetScriptsDir);
                    throw new \RuntimeException("Failed to update scripts directory: " . $e->getMessage() . "\nOriginal directory was restored from backup.");
                }
                throw $e;
            }
        }

        // Handle .twig_cs.php
        $targetTwigCs = $this->targetDir . '/.twig_cs.php';
        if (file_exists($targetTwigCs)) {
            $backupTwigCs = $targetTwigCs . '.bak.' . date('Y-m-d-His');
            if (!$this->dryRun) {
                if (!copy($targetTwigCs, $backupTwigCs)) {
                    throw new \RuntimeException("Failed to backup .twig_cs.php");
                }
                echo "Created backup: {$backupTwigCs}\n";
            }
        }

        // Copy new .twig_cs.php
        if (!$this->dryRun) {
            $twigCsContent = <<<'EOD'
<?php

declare(strict_types=1);

use FriendsOfTwig\Twigcs;

return Twigcs\Config\Config::create()
  ->setName('custom-config')
  ->setSeverity('error')
  ->setReporter('console')
  ->setRuleSet(Twigcs\Ruleset\Official::class)
  ->addFinder(Twigcs\Finder\TemplateFinder::create()->in(__DIR__ . '/web/modules/custom'))
  ->addFinder(Twigcs\Finder\TemplateFinder::create()->in(__DIR__ . '/web/themes/custom'));
EOD;
            
            if (file_put_contents($targetTwigCs, $twigCsContent) === false) {
                throw new \RuntimeException("Failed to write .twig_cs.php");
            }
            $this->changedFiles[] = '.twig_cs.php';
            echo "Updated .twig_cs.php\n";
        }
    }

    /**
     * Remove a directory and its contents recursively.
     */
    private function removeDirectory(string $dir): bool {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }

    /**
     * Download a directory from GitHub.
     */
    private function downloadDirectory(string $dir): string {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('scaffold_', true);
        mkdir($tempDir);

        $this->downloadDirectoryRecursive($dir, $tempDir);

        return $tempDir;
    }

    /**
     * Download a directory and its contents recursively from GitHub.
     */
    private function downloadDirectoryRecursive(string $dir, string $targetDir): void {
        // Use GitHub API to get directory contents
        $url = sprintf(
            'https://api.github.com/repos/%s/contents/%s?ref=%s',
            $this->githubRepo,
            $dir,
            $this->githubBranch
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Scaffold Toolkit Installer');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \RuntimeException("Failed to get directory listing from GitHub for {$dir} (HTTP {$httpCode})");
        }

        $files = json_decode($response, true);
        if (!is_array($files)) {
            throw new \RuntimeException("Invalid response from GitHub for {$dir}");
        }

        // Ensure the target directory exists
        if (!file_exists($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                throw new \RuntimeException("Failed to create directory: {$targetDir}");
            }
        }

        foreach ($files as $file) {
            // Preserve the full path structure
            $relativePath = substr($file['path'], strlen($dir));
            $relativePath = ltrim($relativePath, '/');
            $targetPath = $targetDir . '/' . $relativePath;

            if ($file['type'] === 'dir') {
                if (!file_exists($targetPath)) {
                    if (!mkdir($targetPath, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$targetPath}");
                    }
                }
                $this->downloadDirectoryRecursive($file['path'], dirname($targetPath));
            } else {
                // For files, download directly from the download_url
                $downloadUrl = $file['download_url'];
                if (!$downloadUrl) {
                    throw new \RuntimeException("No download URL available for file: {$file['path']}");
                }

                // Ensure the parent directory exists
                $parentDir = dirname($targetPath);
                if (!file_exists($parentDir)) {
                    if (!mkdir($parentDir, 0755, true)) {
                        throw new \RuntimeException("Failed to create directory: {$parentDir}");
                    }
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $downloadUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Scaffold Toolkit Installer');
                
                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode !== 200 || $content === false) {
                    throw new \RuntimeException("Failed to download file: {$file['path']} (HTTP {$httpCode})");
                }

                if (file_put_contents($targetPath, $content) === false) {
                    throw new \RuntimeException("Failed to write file: {$targetPath}");
                }

                // Set proper permissions for the file
                chmod($targetPath, 0644);
            }
        }
    }

    /**
     * Copy a directory recursively.
     */
    private function copyDirectory(string $source, string $dest): bool {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($dest)) {
            if (!mkdir($dest, 0755, true)) {
                return false;
            }
        }

        $dir = dir($source);
        while (($entry = $dir->read()) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $srcPath = $source . '/' . $entry;
            $destPath = $dest . '/' . $entry;

            if (is_dir($srcPath)) {
                if (!$this->copyDirectory($srcPath, $destPath)) {
                    return false;
                }
            } else {
                if (!copy($srcPath, $destPath)) {
                    return false;
                }
            }
        }

        $dir->close();
        return true;
    }

    /**
     * Set execute permissions on all .sh files in a directory recursively.
     */
    private function setScriptPermissions(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'sh') {
                chmod($file->getPathname(), 0755);
                echo "Set execute permissions on: " . $file->getPathname() . "\n";
            }
        }
    }

    /**
     * Print installation summary.
     */
    private function printSummary(): void {
        echo "\nInstallation Summary\n";
        echo "===================\n";
        echo "Scaffold Type: " . ucfirst($this->scaffoldType) . "\n";
        echo "CI/CD Type: " . ucfirst($this->ciType) . "\n";
        echo "Hosting: " . ucfirst($this->hostingType) . "\n";
        echo "Mode: " . ($this->dryRun ? 'Dry Run' : 'Live') . "\n";
        
        if (!empty($this->changedFiles)) {
            echo "\nChanged files:\n";
            foreach ($this->changedFiles as $file) {
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
        echo "\nWould you like to remove the installer file (scaffold-installer.php)? [Y/n] ";
        $answer = trim(fgets(STDIN)) ?: 'y';
        if (strtolower($answer) !== 'n') {
            $installerFile = __FILE__;
            if (unlink($installerFile)) {
                echo "Installer file removed successfully.\n";
            } else {
                echo "Failed to remove installer file. You can delete it manually.\n";
            }
        }
    }

    private function promptSshFingerprint(): string {
        if ($this->nonInteractive) {
            throw new \Exception('SSH fingerprint is required for CircleCI in non-interactive mode. Use --ssh-fingerprint option.');
        }

        echo "CircleCI requires an SSH key fingerprint for deployment.\n";
        echo "Please follow these steps to set up your SSH key:\n";
        echo "1. Go to CircleCI project settings\n";
        echo "2. Navigate to SSH Keys section\n";
        echo "3. Add a new SSH key or use an existing one\n";
        echo "4. Copy the fingerprint (MD5 format)\n\n";
        
        if (isset($this->savedValues['ssh_fingerprint'])) {
            echo "Previously used fingerprint: {$this->savedValues['ssh_fingerprint']}\n";
            echo "Press Enter to use the previous value, or enter a new fingerprint: ";
        } else {
            echo "Enter the SSH key fingerprint (e.g., 01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef): ";
        }
        
        $fingerprint = trim(readline(''));
        
        if ($fingerprint === '' && isset($this->savedValues['ssh_fingerprint'])) {
            return $this->savedValues['ssh_fingerprint'];
        }
        
        if (!preg_match('/^([0-9a-fA-F]{2}:){15}[0-9a-fA-F]{2}$/', $fingerprint)) {
            throw new \Exception('Invalid SSH key fingerprint format. It should be in MD5 format (16 pairs of hexadecimal digits separated by colons).');
        }

        return $fingerprint;
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
    'github-branch:',
    'scaffold:',
    'distribution:',
    'ssh-fingerprint:'
]);

try {
    $installer = new ScaffoldInstaller($options);
    $installer->run();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 