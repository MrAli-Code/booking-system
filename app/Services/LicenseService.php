<?php
namespace BBS\App\Services;

use BBS\Core\App;
use BBS\Core\Database;
use BBS\App\Models\License;

class LicenseService
{
    private string $cipher = 'aes-256-cbc';
    private App $app;
    private ?Database $db = null;

    public function __construct()
    {
        $this->app = App::getInstance();
    }

    public function generateLicenseKey(array $payload): string
    {
        $data = json_encode($payload);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        $encrypted = openssl_encrypt($data, $this->cipher, $this->getEncryptionKey(), 0, $iv);
        $combined = base64_encode($iv) . ':' . $encrypted;
        $hash = hash('sha256', $combined . $this->getSigningKey());

        // Format: BBS-{payload_encrypted}.{signature}
        $encoded = base64_encode($combined);
        $chunks = str_split($encoded, 4);
        $key = implode('-', $chunks);
        $key = 'BBS-' . $key . '.' . substr($hash, 0, 8);
        return $key;
    }

    public function validateLicenseKey(string $licenseKey): ?array
    {
        if (!str_starts_with($licenseKey, 'BBS-')) {
            return null;
        }

        $keyPart = substr($licenseKey, 4);
        $dotPos = strrpos($keyPart, '.');
        if ($dotPos === false) return null;

        $encoded = substr($keyPart, 0, $dotPos);
        $signature = substr($keyPart, $dotPos + 1);

        $combined = base64_decode($encoded);
        if ($combined === false) return null;

        $expectedHash = substr(hash('sha256', $combined . $this->getSigningKey()), 0, 8);
        if (!hash_equals($expectedHash, $signature)) {
            return null;
        }

        $parts = explode(':', $combined, 2);
        if (count($parts) !== 2) return null;

        [$iv, $encrypted] = $parts;
        $iv = base64_decode($iv);
        if ($iv === false) return null;

        $decrypted = openssl_decrypt($encrypted, $this->cipher, $this->getEncryptionKey(), 0, $iv);
        if ($decrypted === false) return null;

        $payload = json_decode($decrypted, true);
        if (!$payload) return null;

        return $payload;
    }

    public function validateOnInstall(string $licenseKey): array
    {
        $payload = $this->validateLicenseKey($licenseKey);
        if (!$payload) {
            return ['valid' => false, 'error' => 'Invalid license key format or signature'];
        }

        if (isset($payload['expires_at'])) {
            $expires = strtotime($payload['expires_at']);
            if ($expires !== false && $expires < time()) {
                return ['valid' => false, 'error' => 'License has expired', 'payload' => $payload];
            }
        }

        if (isset($payload['domain'])) {
            $currentDomain = $_SERVER['HTTP_HOST'] ?? '';
            $allowedDomains = explode(',', $payload['domain']);
            $matched = false;
            foreach ($allowedDomains as $domain) {
                $domain = trim($domain);
                if ($domain === '*' || $domain === $currentDomain || strpos($currentDomain, $domain) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return ['valid' => false, 'error' => 'License not valid for this domain'];
            }
        }

        if (isset($payload['fingerprint'])) {
            $currentFP = $this->getServerFingerprint();
            if ($payload['fingerprint'] !== $currentFP) {
                return ['valid' => false, 'error' => 'License not valid for this server'];
            }
        }

        $features = $payload['features'] ?? '*';
        $this->app->setConfig('license.features', $features);
        $this->app->setConfig('license.valid', true);
        $this->app->setConfig('license.payload', $payload);

        return [
            'valid' => true,
            'payload' => $payload,
            'features' => $features,
            'plan' => $payload['plan'] ?? 'basic',
            'expires_at' => $payload['expires_at'] ?? null,
        ];
    }

    public function getServerFingerprint(): string
    {
        $components = [
            php_uname('n'),
            $_SERVER['SERVER_SOFTWARE'] ?? '',
            $_SERVER['DOCUMENT_ROOT'] ?? '',
            DB_HOST ?? '',
            DB_NAME ?? '',
        ];
        return hash('sha256', implode('|', array_filter($components)));
    }

    public function getEncryptionKey(): string
    {
        $key = $this->app->config('app.key', '');
        if (empty($key)) {
            $key = 'bbs-default-encryption-key-change-in-production!!';
        }
        return hash('sha256', $key, true);
    }

    public function getSigningKey(): string
    {
        return substr(hash('sha256', $this->getEncryptionKey() . 'bbs-signing-salt'), 0, 32);
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $features = $this->app->config('license.features', []);
        if ($features === '*') return true;
        if (empty($features)) {
            $modules = $this->app->config('modules', []);
            return $modules[$feature]['enabled'] ?? false;
        }
        return in_array($feature, $features);
    }

    public function getLicenseInfo(): array
    {
        $payload = $this->app->config('license.payload', []);
        return [
            'valid' => $this->app->config('license.valid', false),
            'plan' => $payload['plan'] ?? 'unknown',
            'customer' => $payload['customer_id'] ?? null,
            'customer_name' => $payload['customer_name'] ?? '',
            'expires_at' => $payload['expires_at'] ?? null,
            'features' => $payload['features'] ?? [],
            'issued_at' => $payload['issued_at'] ?? null,
        ];
    }

    public function revokeLicense(int $tenantId): bool
    {
        $license = License::findBy('tenant_id', $tenantId);
        if ($license) {
            $license->status = 'revoked';
            $license->save();
            return true;
        }
        return false;
    }

    public function activateTrial(int $tenantId, int $days = 14): array
    {
        $payload = [
            'customer_id' => $tenantId,
            'plan' => 'basic',
            'features' => '*',
            'issued_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$days} days")),
            'trial' => true,
        ];

        $key = $this->generateLicenseKey($payload);

        $license = License::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'license_key' => $key,
                'license_hash' => hash('sha256', $key),
                'plan' => 'basic',
                'status' => 'active',
                'features' => json_encode('*'),
                'issued_at' => date('Y-m-d H:i:s'),
                'expires_at' => $payload['expires_at'],
                'activated_at' => date('Y-m-d H:i:s'),
            ]
        );

        return ['license_key' => $key, 'expires_at' => $payload['expires_at']];
    }

    public function checkLicensePeriodic(): array
    {
        $licenseInfo = $this->getLicenseInfo();
        if (!$licenseInfo['valid']) {
            return ['valid' => false, 'error' => 'No valid license found'];
        }

        if ($licenseInfo['expires_at']) {
            $expires = strtotime($licenseInfo['expires_at']);
            if ($expires !== false && $expires < time()) {
                $this->app->setConfig('license.valid', false);
                return ['valid' => false, 'error' => 'License has expired'];
            }
        }

        $modules = $this->app->config('modules', []);
        $features = $licenseInfo['features'] ?? [];
        foreach ($modules as $module => $config) {
            if (isset($config['enabled']) && $config['enabled']) {
                if ($features !== '*' && !in_array($module, $features)) {
                    continue;
                }
            }
        }

        return ['valid' => true];
    }
}
