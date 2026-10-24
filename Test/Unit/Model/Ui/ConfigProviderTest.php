<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model\Ui;

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\Ui\ConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * What the checkout is handed for the BriizPay method.
 *
 * The bank logos are the part worth testing hardest. A shopper who does not
 * recognise "pay by bank" recognises their own bank's mark, so the row is the
 * difference between choosing the method and scrolling past it. It also has to
 * fail softly: a store whose static content has not been deployed must get a
 * checkout without logos, never a checkout that errors.
 *
 * @covers \BriizPay\PayByBank\Model\Ui\ConfigProvider
 */
#[CoversClass(ConfigProvider::class)]
class ConfigProviderTest extends TestCase
{
    private const LOGO_BASE = 'https://shop.test/static/version1/frontend/Magento/luma/en_GB/BriizPay_PayByBank/';
    private const BANKS = ['barclays', 'hsbc', 'natwest', 'monzo'];

    /** @var array<int, array{0: string, 1: array}> */
    private array $assetsAsked = [];

    private static function bankAsset(string $bank): string
    {
        return 'BriizPay_PayByBank::images/banks/' . $bank . '.png';
    }

    private static function bankUrl(string $bank): string
    {
        return self::LOGO_BASE . 'images/banks/' . $bank . '.png';
    }

    /**
     * @param array<string, mixed> $values
     * @param string|null $logoBase null stands for a store whose static content cannot resolve at all.
     * @param string|null $unresolvable a single asset that cannot be resolved.
     * @return array<string, mixed>
     */
    private function checkoutConfig(array $values, ?string $logoBase, ?string $unresolvable = null): array
    {
        $this->assetsAsked = [];

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(static fn (string $path) => $values[$path] ?? null);
        $scopeConfig->method('isSetFlag')
            ->willReturnCallback(static fn (string $path) => (bool) ($values[$path] ?? false));

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnArgument(0);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($path) => 'https://shop.test/' . $path);
        $url->method('getBaseUrl')->willReturn('https://shop.test/');

        $store = new class {
            public function getId(): int
            {
                return 3;
            }
        };
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        $assets = $this->createStub(AssetRepository::class);
        $assets->method('getUrlWithParams')->willReturnCallback(
            function (string $fileId, array $params) use ($logoBase, $unresolvable): string {
                $this->assetsAsked[] = [$fileId, $params];
                if ($logoBase === null || $fileId === $unresolvable) {
                    throw new RuntimeException('no such file');
                }
                return $logoBase . substr($fileId, strpos($fileId, '::') + 2);
            }
        );

        $provider = new ConfigProvider(
            $scopeConfig,
            $url,
            new Config($scopeConfig, $encryptor, $url),
            $stores,
            $assets
        );

        return $provider->getConfig()['payment']['briizpay'];
    }

    public function testTellsTheCheckoutToShowTheLogosAndWhereTheyAre(): void
    {
        $config = $this->checkoutConfig(['payment/briizpay/show_logo' => '1'], self::LOGO_BASE);

        self::assertTrue($config['showLogo']);
        self::assertSame(
            array_map([self::class, 'bankUrl'], self::BANKS),
            $config['logoUrls'],
            'the four banks, in the order the template expects'
        );
    }

    /** Over http a browser blocks them, so the secure flag is not optional. */
    public function testAsksForTheAssetsSecurely(): void
    {
        $this->checkoutConfig(['payment/briizpay/show_logo' => '1'], self::LOGO_BASE);

        self::assertSame(
            array_map(
                static fn (string $bank): array => [self::bankAsset($bank), ['_secure' => true]],
                self::BANKS
            ),
            $this->assetsAsked
        );
    }

    public function testTurnedOffTheCheckoutIsToldNotToShowThem(): void
    {
        $config = $this->checkoutConfig(['payment/briizpay/show_logo' => '0'], self::LOGO_BASE);

        self::assertFalse($config['showLogo']);
    }

    /**
     * A store that has not run setup:static-content:deploy resolves nothing.
     * An empty row is a checkout that still takes payments.
     */
    public function testAStoreThatCannotResolveAssetsGetsAnEmptyListRatherThanAnError(): void
    {
        $config = $this->checkoutConfig(['payment/briizpay/show_logo' => '1'], null);

        self::assertSame([], $config['logoUrls']);
    }

    public function testOneMissingLogoIsLeftOutAlone(): void
    {
        $config = $this->checkoutConfig(
            ['payment/briizpay/show_logo' => '1'],
            self::LOGO_BASE,
            self::bankAsset('hsbc')
        );

        self::assertSame(
            [self::bankUrl('barclays'), self::bankUrl('natwest'), self::bankUrl('monzo')],
            $config['logoUrls']
        );
    }

    /** The logo setting must not disturb anything else the checkout is given. */
    public function testTheRestOfTheCheckoutConfigIsUntouched(): void
    {
        $config = $this->checkoutConfig(
            ['payment/briizpay/show_logo' => '1', 'payment/briizpay/description' => 'Pay from your bank'],
            self::LOGO_BASE
        );

        self::assertSame('Pay from your bank', $config['description']);
        self::assertSame('https://shop.test/briizpay/checkout/redirect', $config['redirectUrl']);
    }

    public function testTheDiscountOfferFlagIsIndependentOfTheLogos(): void
    {
        $without = $this->checkoutConfig(['payment/briizpay/show_logo' => '0'], self::LOGO_BASE);
        $with = $this->checkoutConfig([
            'payment/briizpay/show_logo' => '1',
            'payment/briizpay/active' => '1',
            'payment/briizpay/discount_enabled' => '1',
            'payment/briizpay/discount_amount' => '2',
        ], self::LOGO_BASE);

        self::assertFalse($without['discountOffered']);
        self::assertTrue($with['discountOffered']);
    }
}
