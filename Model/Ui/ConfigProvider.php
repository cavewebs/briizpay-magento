<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Ui;

use BriizPay\PayByBank\Model\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * What the checkout's JavaScript needs: the line under the method name, the
 * logo beside it, where to go once the order is placed, and whether choosing
 * the method changes the price.
 */
class ConfigProvider implements ConfigProviderInterface
{
    /** Where the bundled logo lives, in the module's own static files. */
    public const LOGO_ASSET = 'BriizPay_PayByBank::images/briizpay-logo.png';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly UrlInterface $url,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly AssetRepository $assets
    ) {
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();

        return [
            'payment' => [
                Config::METHOD_CODE => [
                    'description' => (string) $this->scopeConfig->getValue(
                        'payment/' . Config::METHOD_CODE . '/description',
                        ScopeInterface::SCOPE_STORE
                    ),
                    'showLogo' => $this->config->showLogo($storeId),
                    'logoUrl' => $this->getLogoUrl(),
                    'redirectUrl' => $this->url->getUrl('briizpay/checkout/redirect', ['_secure' => true]),
                    // Only when this is on does the checkout ask for new totals
                    // as the customer moves between methods.
                    'discountOffered' => $this->config->isDiscountOffered($storeId),
                ],
            ],
        ];
    }

    /**
     * The logo's address through Magento's static files, so it follows the
     * theme's deployed path, version and CDN.
     *
     * Empty when it cannot be worked out: the checkout then shows the name on
     * its own, which is better than failing the page over a picture.
     */
    private function getLogoUrl(): string
    {
        try {
            return $this->assets->getUrlWithParams(self::LOGO_ASSET, ['_secure' => true]);
        } catch (\Throwable) {
            return '';
        }
    }
}
