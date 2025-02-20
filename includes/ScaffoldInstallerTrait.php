<?php

declare(strict_types=1);

namespace SalsaDigital\ScaffoldToolkit;

trait ScaffoldInstallerTrait {
    private const GREEN = "\033[32m";
    private const BLUE = "\033[34m";
    private const DARK_ORANGE = "\033[38;5;208m";
    private const RESET = "\033[0m";

    private function colorize(string $text, string $color = self::GREEN): string {
        return $color . $text . self::RESET;
    }

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

    private function getFileContent(string $sourcePath): string|false {
        if ($this->useLocalFiles) {
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
                $downloadUrl = $file['download_url'];
                if (!$downloadUrl) {
                    throw new \RuntimeException("No download URL available for file: {$file['path']}");
                }

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

                chmod($targetPath, 0644);
            }
        }
    }
} 