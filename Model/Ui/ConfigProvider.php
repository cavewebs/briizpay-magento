<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Ui;

use BriizPay\PayByBank\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * What the checkout's JavaScript needs: the line under the method name, where
 * to go once the order is placed, and whether choosing the method changes the
 * price.
 */
class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
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
                    // Only when this is on does the checkout ask for new totals
                    // as the customer moves between methods.
                    'discountOffered' => $this->config->isDiscountOffered((int) $this->storeManager->getStore()->getId()),
                ],
            ],
        ];
    }
}
