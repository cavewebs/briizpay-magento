<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The settings, and the one decision that matters most in them.
 *
 * The API key is the only thing that decides which BriizPay a store talks to.
 * Get the mapping wrong and a store sends real payments at a test environment,
 * or test traffic at production, and both succeed against the wrong host and
 * look fine from the admin.
 *
 * @covers \BriizPay\PayByBank\Model\Config
 */
#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    /**
     * Stubs, not mocks: nothing here asserts how often Magento's own config is
     * asked, only what this class does with the answer. PHPUnit 12 says so out
     * loud, and createStub() exists back to PHPUnit 9, so it is the form that
     * works across every Magento version this module supports.
     *
     * @param array<string, mixed> $values
     */
    private function config(array $values, ?array &$flagReadFor = null): Config
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->willReturnCallback(static fn (string $path) => $values[$path] ?? null);
        $scopeConfig->method('isSetFlag')
            ->willReturnCallback(
                static function (string $path, $scopeType = null, $scopeCode = null) use ($values, &$flagReadFor) {
                    $flagReadFor[$path] = $scopeCode;
                    return (bool) ($values[$path] ?? false);
                }
            );

        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnArgument(0);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn ($path) => 'https://shop.test/' . $path);
        $url->method('getBaseUrl')->willReturn('https://shop.test/');

        return new Config($scopeConfig, $encryptor, $url);
    }

    private static function key(string $environment): string
    {
        return 'bzp_sk_' . $environment . '_' . str_repeat('a', 40);
    }

    /**
     * @dataProvider environmentProvider
     */
    #[DataProvider('environmentProvider')]
    public function testTheKeyDecidesTheEnvironment(string $apiKey, string $expected, string $case): void
    {
        $config = $this->config(['payment/briizpay/api_key' => $apiKey]);

        self::assertSame($expected, $config->environment(), $case);
    }

    public static function environmentProvider(): array
    {
        return [
            'a live key' => [self::key('live'), 'live', 'live keys talk to production'],
            'a sandbox key' => [self::key('sandbox'), 'sandbox', 'sandbox keys talk to sandbox'],
            'a test key' => [self::key('test'), 'test', 'test keys talk to dev'],
            // Fails towards live, which refuses an unknown key, rather than
            // towards a test environment that would accept it and take nothing.
            'something unrecognised' => ['nonsense', 'live', 'never quietly treated as a test environment'],
        ];
    }

    /**
     * @dataProvider validityProvider
     */
    #[DataProvider('validityProvider')]
    public function testOnlyTheThreeKnownEnvironmentsAreValidKeys(string $apiKey, bool $expected, string $case): void
    {
        self::assertSame(
            $expected,
            $this->config(['payment/briizpay/api_key' => $apiKey])->hasValidApiKey(),
            $case
        );
    }

    public static function validityProvider(): array
    {
        return [
            'live' => [self::key('live'), true, 'live is a key'],
            'sandbox' => [self::key('sandbox'), true, 'sandbox is a key'],
            'an invented segment' => [self::key('staging'), false, 'there is no staging environment'],
            'too short' => ['bzp_sk_live_short', false, 'the secret part has a fixed length'],
            'nothing saved' => ['', false, 'an empty setting is not a key'],
        ];
    }

    /**
     * @dataProvider keyKindProvider
     */
    #[DataProvider('keyKindProvider')]
    public function testATestKeyIsAnythingThatIsNotLive(string $apiKey, bool $expected): void
    {
        self::assertSame($expected, $this->config(['payment/briizpay/api_key' => $apiKey])->isTestKey());
    }

    public static function keyKindProvider(): array
    {
        return [
            'live' => [self::key('live'), false],
            'sandbox' => [self::key('sandbox'), true],
            'test' => [self::key('test'), true],
        ];
    }

    /**
     * @dataProvider logoFlagProvider
     */
    #[DataProvider('logoFlagProvider')]
    public function testReadsTheLogoFlag(array $values, bool $expected, string $case): void
    {
        self::assertSame($expected, $this->config($values)->showLogo(3), $case);
    }

    public static function logoFlagProvider(): array
    {
        return [
            'on' => [['payment/briizpay/show_logo' => '1'], true, 'the flag reads on'],
            'off' => [['payment/briizpay/show_logo' => '0'], false, 'the flag reads off'],
            // Unset reads off here, so the shipped config.xml default is what
            // actually turns it on for a new store. PackagingTest holds that.
            'unset' => [[], false, 'nothing saved reads off'],
        ];
    }

    /** A per-store setting read for the wrong store is the same bug as not reading it. */
    public function testTheFlagIsReadForTheStoreAskedAbout(): void
    {
        $readFor = [];
        $this->config(['payment/briizpay/show_logo' => '1'], $readFor)->showLogo(7);

        self::assertSame(7, $readFor['payment/briizpay/show_logo']);
    }
}
