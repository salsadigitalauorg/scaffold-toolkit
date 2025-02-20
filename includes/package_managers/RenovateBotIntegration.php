<?php

declare(strict_types=1);

namespace SalsaDigital\ScaffoldToolkit\PackageManagers;

trait RenovateBotIntegration {
    private function getRenovateBotFiles(): array {
        return [
            [
                'source' => "renovatebot/{$this->distributionType}/renovate.json",
                'target' => 'renovate.json',
            ]
        ];
    }

    private function processRenovateBotFile(array $file): void {
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
} 