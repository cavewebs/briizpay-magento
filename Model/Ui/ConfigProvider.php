<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Ui;

use BriizPay\PayByBank\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * What the checkout's JavaScript needs: the line under the method name, and
 * where to go once the order is placed.
 */
class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url
    ) {
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return [
            'payment' => [
                Config::METHOD_CODE => [
                    'description' => (string) $this->scopeConfig->getValue(
                        'payment/' . Config::METHOD_CODE . '/description',
                        ScopeInterface::SCOPE_STORE
                    ),
                    'redirectUrl' => $this->url->getUrl('briizpay/checkout/redirect', ['_secure' => true]),
                ],
            ],
        ];
    }
}
