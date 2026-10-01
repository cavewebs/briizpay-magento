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
 * bank logos beside it, where to go once the order is placed, and whether choosing
 * the method changes the price.
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * The bundled bank logos, in the module's own static files and in the order
     * they are shown. Each is a 40px square, twice the 20px it is displayed at.
     * They are trademarks of their owners, shown only to indicate that the
     * customer pays from their own bank.
     */
    public const LOGO_ASSETS = [
        'BriizPay_PayByBank::images/banks/barclays.png',
        'BriizPay_PayByBank::images/banks/hsbc.png',
        'BriizPay_PayByBank::images/banks/natwest.png',
        'BriizPay_PayByBank::images/banks/monzo.png',
    ];

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
                    'logoUrls' => $this->getLogoUrls(),
                    'redirectUrl' => $this->url->getUrl('briizpay/checkout/redirect', ['_secure' => true]),
                    // Only when this is on does the checkout ask for new totals
                    // as the customer moves between methods.
                    'discountOffered' => $this->config->isDiscountOffered($storeId),
                ],
            ],
        ];
    }

    /**
     * The logos' addresses through Magento's static files, so they follow the
     * theme's deployed path, version and CDN.
     *
     * A logo that cannot be resolved is left out on its own, and none at all
     * leaves an empty list: the checkout then shows the name on its own, which
     * is better than failing the page over a picture.
     *
     * @return string[]
     */
    private function getLogoUrls(): array
    {
        $urls = [];
        foreach (self::LOGO_ASSETS as $asset) {
            try {
                $urls[] = $this->assets->getUrlWithParams($asset, ['_secure' => true]);
            } catch (\Throwable) {
                continue;
            }
        }

        return $urls;
    }
}
