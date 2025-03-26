<?php

declare(strict_types=1);

namespace SalsaDigital\ScaffoldToolkit\Scaffold;

trait DrevOpsIntegration {
    private function getDrevOpsFiles(): array {
        if ($this->scaffoldType !== 'drevops') {
            return [];
        }

        $files = [
            [
                'source' => 'scaffold/drevops/phpmd.xml',
                'target' => 'phpmd.xml',
            ],
            [
                'source' => 'scaffold/drevops/phpunit.xml',
                'target' => 'phpunit.xml',
            ],
            [
                'source' => 'scaffold/drevops/rector.php',
                'target' => 'rector.php',
            ],
            [
                'source' => 'scaffold/drevops/.ahoy.yml',
                'target' => '.ahoy.yml',
            ],
        ];

        if ($this->shouldUpdateScripts) {
            $files[] = [
                'source' => 'scaffold/drevops/.twig_cs.php',
                'target' => '.twig_cs.php',
            ];
        }

        return $files;
    }

    private function processDrevOpsFile(array $file): void {
        $targetFile = $this->targetDir . '/' . $file['target'];

        echo "Processing {$targetFile}...\n";

        try {
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

            $content = $this->getFileContent($file['source']);
            if ($content === false) {
                throw new \RuntimeException("Failed to get content for {$file['source']}");
            }

            // If this is .twig_cs.php, replace webroot placeholder
            if ($file['target'] === '.twig_cs.php') {
                $webroot = $this->getWebroot();
                $content = str_replace('${WEBROOT}', $webroot, $content);
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
            $this->changedFiles[] = $file['target'];
        } catch (\Exception $e) {
            $this->addError("Error processing {$targetFile}: " . $e->getMessage());
            if ($this->nonInteractive) {
                throw $e;
            }
        }
    }

    private function getWebroot(): string {
        $envFile = $this->targetDir . '/.env';
        $webroot = 'web'; // Default value

        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if ($content !== false) {
                // First try WEBROOT
                if (preg_match('/^WEBROOT=(.+)$/m', $content, $matches)) {
                    return trim($matches[1]);
                }
                // Then try DREVOPS_WEBROOT
                if (preg_match('/^DREVOPS_WEBROOT=(.+)$/m', $content, $matches)) {
                    return trim($matches[1]);
                }
            }
        }

        return $webroot;
    }
}
