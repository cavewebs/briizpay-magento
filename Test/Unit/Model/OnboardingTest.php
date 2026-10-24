<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\Onboarding;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What a store is told before BriizPay is connected.
 *
 * This is the first thing a merchant reads about the product, and it is also
 * copy that has to obey the standing claims rules: every plan has a
 * per-payment fee, bank payment disputes exist, and settlement is usually
 * within seconds rather than instant. A test is the only thing that stops that
 * drifting back.
 *
 * @covers \BriizPay\PayByBank\Model\Onboarding
 */
#[CoversClass(Onboarding::class)]
class OnboardingTest extends TestCase
{
    private const VALID_KEY = 'bzp_sk_live_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** @param array<string, mixed> $values */
    private function onboarding(array $values): Onboarding
    {
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

        return new Onboarding(new Config($scopeConfig, $encryptor, $url));
    }

    private function allCopy(Onboarding $onboarding): string
    {
        return implode(' ', array_merge(
            [
                $onboarding->headline(),
                $onboarding->summary(),
                $onboarding->customerExperience(),
                $onboarding->footnote(),
                $onboarding->webhookHeading(),
                $onboarding->webhookBody(),
                $onboarding->messageText(),
            ],
            $onboarding->benefits(),
            $onboarding->steps()
        ));
    }

    /**
     * @dataProvider connectionProvider
     */
    #[DataProvider('connectionProvider')]
    public function testKnowsWhetherTheStoreIsConnected(array $values, bool $expected, string $case): void
    {
        self::assertSame($expected, $this->onboarding($values)->isConnected(), $case);
    }

    public static function connectionProvider(): array
    {
        return [
            'nothing saved' => [[], false, 'a new install is not connected'],
            'a key in the wrong shape' => [
                ['payment/briizpay/api_key' => 'bzp_sk_live_short'], false, 'not a usable key',
            ],
            'a key in the right shape' => [['payment/briizpay/api_key' => self::VALID_KEY], true, 'connected'],
        ];
    }

    /**
     * The webhook is what marks an order paid. A store with a key and no
     * signing secret takes a customer's money and leaves the order sitting
     * unpaid, and nothing at the checkout looks wrong while it happens, so it
     * is said out loud rather than left to a field comment.
     *
     * @dataProvider webhookProvider
     */
    #[DataProvider('webhookProvider')]
    public function testWarnsOnlyWhenAConnectedStoreHasNoSigningSecret(
        array $values,
        bool $expected,
        string $case
    ): void {
        self::assertSame($expected, $this->onboarding($values)->needsWebhookSecret(), $case);
    }

    public static function webhookProvider(): array
    {
        return [
            'connected, no secret' => [
                ['payment/briizpay/api_key' => self::VALID_KEY],
                true,
                'the one configuration that silently loses orders',
            ],
            'connected with a secret' => [
                ['payment/briizpay/api_key' => self::VALID_KEY, 'payment/briizpay/webhook_secret' => 'whsec_x'],
                false,
                'nothing to say',
            ],
            'not connected at all' => [
                [],
                false,
                'an unconnected store gets the introduction, not a warning about a step it has not reached',
            ],
        ];
    }

    /**
     * A merchant choosing a payment method is deciding about their takings.
     * "Your customer pays from their banking app" is the mechanism, and on its
     * own it reads as a reason to do nothing.
     */
    public function testLeadsWithWhatTheStoreGains(): void
    {
        self::assertStringStartsWith('Keep more of each sale', $this->onboarding([])->benefits()[0]);
    }

    public function testTheFirstStepIsMakingAnAccount(): void
    {
        self::assertStringContainsString('Create a BriizPay account', $this->onboarding([])->steps()[0]);
    }

    /** An unconfigured module is harmless, and saying so stops a support ticket. */
    public function testSaysTheMethodStaysHiddenUntilAKeyIsSaved(): void
    {
        self::assertStringContainsString('hidden at checkout', $this->onboarding([])->footnote());
    }

    public function testTheWebhookWarningSaysTheOrderStaysUnpaid(): void
    {
        self::assertStringContainsString('the order will stay unpaid', $this->onboarding([])->webhookBody());
    }

    /** The app, not the marketing site: a merchant sent to a brochure has to find the door again. */
    public function testPointsAtTheApplication(): void
    {
        self::assertSame('https://app.briizpay.com/register', Onboarding::SIGNUP_URL);
        self::assertSame('https://app.briizpay.com/dashboard/integrations', Onboarding::CONNECT_URL);
    }

    /**
     * Claims rules, which are a compliance constraint rather than a style
     * preference. Every plan has a per-payment fee, bank payment disputes
     * exist, and a payment must never be described as irreversible.
     *
     * @dataProvider forbiddenClaimProvider
     */
    #[DataProvider('forbiddenClaimProvider')]
    public function testMakesNoClaimWeCannotStandBehind(string $pattern, string $why): void
    {
        self::assertDoesNotMatchRegularExpression($pattern, $this->allCopy($this->onboarding([])), $why);
    }

    public static function forbiddenClaimProvider(): array
    {
        return [
            'no transaction fees' => ['/no transaction fees/i', 'every plan has a per-payment fee'],
            'no chargebacks' => ['/no chargebacks/i', 'bank payment disputes exist'],
            'irreversible' => ['/irreversible/i', 'APP reimbursement rules'],
            'instantly' => ['/\binstantly\b/i', 'settlement is usually within seconds, not instant'],
            'FCA regulated' => ['/fca[- ]regulated/i', 'BriizPay is not FCA regulated'],
        ];
    }

    /** A standing rule from Tim, and the one that survives everywhere. */
    public function testUsesNoEmDash(): void
    {
        self::assertStringNotContainsString("\u{2014}", $this->allCopy($this->onboarding([])));
    }
}
