<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * The module's settings, read in one place.
 *
 * The API key and webhook secret are saved encrypted by the admin form. A value
 * set with `bin/magento config:set` is stored as typed, so a value that already
 * looks like a key or a secret is used as it is rather than decrypted into
 * nonsense.
 */
class Config
{
    public const METHOD_CODE = 'briizpay';

    private const PATH = 'payment/' . self::METHOD_CODE . '/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor,
        private readonly UrlInterface $url
    ) {
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'active', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getApiKey(?int $storeId = null): string
    {
        return $this->secret('api_key', 'bzp_sk_', $storeId);
    }

    public function getWebhookSecret(?int $storeId = null): string
    {
        return $this->secret('webhook_secret', 'whsec_', $storeId);
    }

    /** A key that could work: the right shape, before anything is sent. */
    public function hasValidApiKey(?int $storeId = null): bool
    {
        return (bool) preg_match('/^bzp_sk_(live|test)_[A-Za-z0-9]{40}$/', $this->getApiKey($storeId));
    }

    /** The environment follows the key, so a test key can never move real money. */
    public function isTestKey(?int $storeId = null): bool
    {
        return str_starts_with($this->getApiKey($storeId), 'bzp_sk_test_');
    }

    /** Where BriizPay sends payment notifications for this store. */
    public function getWebhookUrl(): string
    {
        return rtrim($this->url->getBaseUrl(['_type' => UrlInterface::URL_TYPE_WEB, '_secure' => true]), '/')
            . '/briizpay/webhook';
    }

    private function secret(string $field, string $plainPrefix, ?int $storeId): string
    {
        $raw = trim((string) $this->scopeConfig->getValue(self::PATH . $field, ScopeInterface::SCOPE_STORE, $storeId));
        if ($raw === '') {
            return '';
        }
        if (str_starts_with($raw, $plainPrefix)) {
            return $raw;
        }
        return trim((string) $this->encryptor->decrypt($raw));
    }
}
