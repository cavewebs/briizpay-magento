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

    /**
     * Show the bank logos beside the method's name at checkout.
     *
     * Store scoped, like the title they sit beside, so a multi-store merchant
     * can drop them on a storefront whose theme already decorates methods. The
     * config path stays show_logo so a store that set it for the single logo
     * keeps its choice.
     */
    public function showLogo(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'show_logo', ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getApiKey(?int $storeId = null): string
    {
        return $this->secret('api_key', 'bzp_sk_', $storeId);
    }

    public function getWebhookSecret(?int $storeId = null): string
    {
        return $this->secret('webhook_secret', 'whsec_', $storeId);
    }

    /** A key that could work: the right format, before anything is sent. */
    public function hasValidApiKey(?int $storeId = null): bool
    {
        return (bool) preg_match('/^bzp_sk_(live|sandbox|test)_[A-Za-z0-9]{40}$/', $this->getApiKey($storeId));
    }

    /**
     * Which BriizPay this key belongs to: live, sandbox, or test.
     *
     * The environment follows the key, so a store pastes one value and cannot
     * hold a second setting that disagrees with it. sandbox is where BriizPay
     * invites merchants and integration partners to test. test is BriizPay's
     * own development environment, and a key for it is not something a store
     * would normally be given.
     */
    public function environment(?int $storeId = null): string
    {
        $key = $this->getApiKey($storeId);
        if (str_starts_with($key, 'bzp_sk_sandbox_')) {
            return 'sandbox';
        }
        if (str_starts_with($key, 'bzp_sk_test_')) {
            return 'test';
        }
        return 'live';
    }

    /** Anything that is not a live key, so no real money moves. */
    public function isTestKey(?int $storeId = null): bool
    {
        return $this->environment($storeId) !== 'live';
    }

    /**
     * The pay by bank discount is on, for a method that is on.
     *
     * A discount for a method the customer cannot choose would never be
     * applied anyway, but checking here keeps a disabled method from costing
     * the collector anything on every basket.
     */
    public function isDiscountOffered(?int $storeId = null): bool
    {
        return $this->isActive($storeId)
            && $this->scopeConfig->isSetFlag(self::PATH . 'discount_enabled', ScopeInterface::SCOPE_STORE, $storeId)
            && $this->getDiscountAmount($storeId) > 0;
    }

    /** 'percent' or 'fixed'. Anything else is read as a percentage, as Discount::compute() does. */
    public function getDiscountType(?int $storeId = null): string
    {
        $type = (string) $this->scopeConfig->getValue(self::PATH . 'discount_type', ScopeInterface::SCOPE_STORE, $storeId);
        return $type === Discount::TYPE_FIXED ? Discount::TYPE_FIXED : Discount::TYPE_PERCENT;
    }

    /** Percent, or pounds for a fixed discount. Never negative. */
    public function getDiscountAmount(?int $storeId = null): float
    {
        return max(0.0, (float) $this->scopeConfig->getValue(self::PATH . 'discount_amount', ScopeInterface::SCOPE_STORE, $storeId));
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
