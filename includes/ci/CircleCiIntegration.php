<?php

declare(strict_types=1);

namespace SalsaDigital\ScaffoldToolkit\CI;

trait CircleCiIntegration {
    private ?string $sshFingerprint = null;

    private function promptSshFingerprint(): string {
        if ($this->nonInteractive) {
            throw new \Exception('SSH fingerprint is required for CircleCI in non-interactive mode. Use --ssh-fingerprint option.');
        }

        echo "\nCircleCI requires an SSH key fingerprint for deployment.\n";
        echo "Please follow these steps to set up your SSH key:\n";
        echo "1. Go to CircleCI project settings\n";
        echo "2. Navigate to SSH Keys section\n";
        echo "3. Add a new SSH key or use an existing one\n";
        echo "4. Copy the fingerprint (MD5 format)\n\n";
        
        if (isset($this->savedValues['ssh_fingerprint'])) {
            echo "Previously used fingerprint: " . $this->colorize($this->savedValues['ssh_fingerprint']) . "\n";
            echo "Press Enter to use the previous value, or enter a new fingerprint: ";
        } else {
            echo "Enter the SSH key fingerprint (e.g., 01:23:45:67:89:ab:cd:ef:01:23:45:67:89:ab:cd:ef): ";
        }
        
        $fingerprint = trim(fgets(STDIN));
        
        if ($fingerprint === '' && isset($this->savedValues['ssh_fingerprint'])) {
            return $this->savedValues['ssh_fingerprint'];
        }
        
        if (!preg_match('/^([0-9a-fA-F]{2}:){15}[0-9a-fA-F]{2}$/', $fingerprint)) {
            throw new \Exception('Invalid SSH key fingerprint format. It should be in MD5 format (16 pairs of hexadecimal digits separated by colons).');
        }

        return $fingerprint;
    }

    private function replaceRepositoryPlaceholder(string $content): string {
        $placeholder = '[SCAFFOLD_TOOLKIT_REPOSITORY]';
        return str_replace($placeholder, '${CIRCLE_PROJECT_REPONAME}', $content);
    }

    private function replaceSshFingerprintPlaceholder(string $content): string {
        if (!$this->sshFingerprint) {
            throw new \RuntimeException('SSH fingerprint is required for CircleCI configuration.');
        }
        
        return str_replace('${SCAFFOLD_TOOLKIT_SSH_FINGERPRINT}', $this->sshFingerprint, $content);
    }
} 