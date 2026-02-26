<?php

namespace App\Support;

use InvalidArgumentException;

class DatabaseProfileManager
{
    private const PROFILE_PREFIX = [
        'sandbox' => 'DB_SANDBOX_',
        'production' => 'DB_PRODUCTION_',
    ];

    public function getProfileConfig(string $profile): array
    {
        $prefix = $this->getPrefix($profile);

        return [
            'host' => (string) env($prefix . 'HOST', ''),
            'port' => (string) env($prefix . 'PORT', ''),
            'database' => (string) env($prefix . 'DATABASE', ''),
            'username' => (string) env($prefix . 'USERNAME', ''),
            'password' => (string) env($prefix . 'PASSWORD', ''),
        ];
    }

    public function validateProfile(string $profile): array
    {
        $config = $this->getProfileConfig($profile);
        $missing = [];

        foreach (['host', 'port', 'database', 'username'] as $requiredKey) {
            if (trim((string) $config[$requiredKey]) === '') {
                $missing[] = $requiredKey;
            }
        }

        return $missing;
    }

    public function applyProfileToEnv(string $profile, string $envPath): void
    {
        if (!is_file($envPath)) {
            throw new InvalidArgumentException("Environment file not found at {$envPath}");
        }

        $config = $this->getProfileConfig($profile);
        $content = (string) file_get_contents($envPath);

        $updates = [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $config['host'],
            'DB_PORT' => $config['port'],
            'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => $config['password'],
        ];

        foreach ($updates as $key => $value) {
            $content = $this->upsertEnvValue($content, $key, $value);
        }

        file_put_contents($envPath, $content);
    }

    private function getPrefix(string $profile): string
    {
        $profile = strtolower(trim($profile));

        if (!array_key_exists($profile, self::PROFILE_PREFIX)) {
            throw new InvalidArgumentException("Unknown profile [{$profile}]. Use sandbox or production.");
        }

        return self::PROFILE_PREFIX[$profile];
    }

    private function upsertEnvValue(string $content, string $key, string $value): string
    {
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        $line = $key . '=' . $this->formatEnvValue($value);

        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, $line, $content, 1);
        }

        $separator = str_ends_with($content, PHP_EOL) ? '' : PHP_EOL;

        return $content . $separator . $line . PHP_EOL;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_\-\.]+$/', $value)) {
            return $value;
        }

        $escaped = str_replace(['\\', '"'], ['\\\\', '\"'], $value);

        return '"' . $escaped . '"';
    }
}
