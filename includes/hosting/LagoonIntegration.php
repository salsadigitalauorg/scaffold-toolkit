<?php

declare(strict_types=1);

namespace SalsaDigital\ScaffoldToolkit\Hosting;

trait LagoonIntegration {
    private const OLD_SSH_HOST = 'ssh.lagoon.amazeeio.cloud';
    private const NEW_SSH_HOST = 'ssh.salsa.hosting';
    private ?string $lagoonCluster = null;
    private array $lagoonEnvVars = [
        'LAGOON_WEBHOOK_ENDPOINT' => 'https://webhookhandler.salsa.hosting/',
        'DREVOPS_DEPLOY_LAGOON_INSTANCE' => 'salsa',
        'DREVOPS_DEPLOY_LAGOON_INSTANCE_GRAPHQL' => 'https://api.salsa.hosting/graphql',
        'DREVOPS_DEPLOY_LAGOON_INSTANCE_HOSTNAME' => 'ssh.salsa.hosting',
        'DREVOPS_DB_DOWNLOAD_LAGOON_SSH_HOST' => 'ssh.salsa.hosting',
        'DREVOPS_TASK_LAGOON_INSTANCE_HOSTNAME' => 'ssh.salsa.hosting',
        'DREVOPS_DEPLOY_LAGOON_INSTANCE_PORT' => '22',
        'DREVOPS_DB_DOWNLOAD_LAGOON_SSH_PORT' => '22',
        'DREVOPS_TASK_LAGOON_INSTANCE_PORT' => '22',
        'DREVOPS_DB_DOWNLOAD_SOURCE' => 'lagoon',
        'DREVOPS_DEPLOY_TYPES' => 'lagoon',
        'DREVOPS_NOTIFY_EMAIL_RECIPIENTS' => 'servicedesk.team@salsa.digital|Serice Desk',
        'DREVOPS_DEPLOY_LAGOON_LAGOONCLI_VERSION' => 'v0.21.3',
        'DREVOPS_WEBROOT' => 'web',
        'DREVOPS_DB_DOWNLOAD_SSH_KEY_FILE' => '/home/.ssh/lagoon_cli.key'
    ];

    private function selectLagoonCluster(): void {
        if ($this->nonInteractive) {
            if (isset($this->savedValues['lagoon_cluster'])) {
                $this->lagoonCluster = $this->savedValues['lagoon_cluster'];
            }
            return;
        }

        $options = [
            'SalsaDigital',
            'Other'
        ];

        $default = isset($this->savedValues['lagoon_cluster']) ? ucfirst($this->savedValues['lagoon_cluster']) : 'SalsaDigital';
        
        echo "\nSelect Lagoon cluster:\n";
        $choice = $this->arrowKeySelect($options, $default);
        
        $this->lagoonCluster = strtolower($choice === 'SalsaDigital' ? 'salsadigital' : 'other');

        if ($this->lagoonCluster === 'salsadigital') {
            $this->updateEnvFile();
        }
    }

    private function replaceSSHHost(string $filePath): void {
        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        $newContent = str_replace(self::OLD_SSH_HOST, self::NEW_SSH_HOST, $content);
        if ($newContent !== $content) {
            if (file_put_contents($filePath, $newContent) === false) {
                throw new \RuntimeException("Failed to write to file: {$filePath}");
            }
            echo "Updated SSH host in: {$filePath}\n";
        }
    }

    private function replaceSSHHostInScripts(string $directory): void {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $this->replaceSSHHost($file->getPathname());
            }
        }
    }

    private function updateEnvFile(): void {
        $envFile = $this->targetDir . '/.env';
        $content = file_exists($envFile) ? file_get_contents($envFile) : '';
        $content = $content ?: '';
        $updatedVars = [];

        // Remove any existing DREVOPS_DEPLOY_TYPE (singular) setting
        $content = preg_replace('/^DREVOPS_DEPLOY_TYPE=.*$/m', '', $content);
        
        // Handle DREVOPS_DEPLOY_TYPES (plural)
        if (!preg_match('/^DREVOPS_DEPLOY_TYPES=/m', $content)) {
            $updatedVars['DREVOPS_DEPLOY_TYPES'] = 'lagoon';
        } else {
            $content = preg_replace_callback(
                '/^(DREVOPS_DEPLOY_TYPES=.*)$/m',
                function($matches) {
                    if (!str_contains($matches[1], 'lagoon')) {
                        return 'DREVOPS_DEPLOY_TYPES=lagoon';
                    }
                    return $matches[1];
                },
                $content
            );
        }

        // Get current DREVOPS_WEBROOT value if it exists
        $webroot = 'web'; // Default value
        if (preg_match('/^DREVOPS_WEBROOT=(.+)$/m', $content, $matches)) {
            $webroot = trim($matches[1]);
        }

        // Check which variables need to be added
        foreach ($this->lagoonEnvVars as $key => $value) {
            if ($key === 'DREVOPS_WEBROOT' && isset($matches)) {
                continue;
            }
            if ($key === 'DREVOPS_DEPLOY_TYPES') {
                continue;
            }
            if (!preg_match("/^{$key}=/m", $content)) {
                $updatedVars[$key] = $value;
            }
        }

        if (!empty($updatedVars)) {
            if (!str_contains($content, '# Lagoon variables')) {
                $content .= "\n# Lagoon variables\n";
            }

            foreach ($updatedVars as $key => $value) {
                $content .= "{$key}={$value}\n";
            }

            if (file_put_contents($envFile, $content) === false) {
                throw new \RuntimeException("Failed to update .env file");
            }

            $this->changedFiles[] = '.env';
            echo "Updated .env file with Lagoon variables\n";
        }

        if (!$this->dryRun) {
            $this->replaceSSHHost($envFile);
        }

        $scriptsDir = $this->targetDir . '/scripts';
        if (!$this->dryRun && is_dir($scriptsDir)) {
            $this->replaceSSHHostInScripts($scriptsDir);
        }

        // Create/update drush/sites/lagoon.site.yml
        $drushDir = $this->targetDir . '/drush/sites';
        $lagoonSiteYml = $drushDir . '/lagoon.site.yml';
        $lagoonSiteContent = <<<EOD
'*':
  host: ssh.salsa.hosting
  paths:
    files: /app/{$webroot}/sites/default/files
  user: \${env-name}
  ssh:
    options: '-o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -o LogLevel=FATAL -p 22'
    tty: false
EOD;

        if (!is_dir($drushDir)) {
            if (!$this->dryRun) {
                if (!mkdir($drushDir, 0755, true)) {
                    throw new \RuntimeException("Failed to create directory: {$drushDir}");
                }
                echo "Created directory: {$drushDir}\n";
            }
        }

        if (!$this->dryRun) {
            if (file_put_contents($lagoonSiteYml, $lagoonSiteContent) === false) {
                throw new \RuntimeException("Failed to write to {$lagoonSiteYml}");
            }
            $this->changedFiles[] = 'drush/sites/lagoon.site.yml';
            echo "Created/Updated drush/sites/lagoon.site.yml\n";
        }
    }
} 