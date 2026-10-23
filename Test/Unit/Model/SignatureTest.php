<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The webhook signature, which is the only authentication on a public URL.
 *
 * `/briizpay/webhook` is reachable by anyone. If this check is wrong in the
 * permissive direction, a stranger can mark any order paid. The vector below
 * was produced by the API's own signWebhook (briizpay-api
 * src/lib/webhook-signature.ts) for this exact body, secret and timestamp, and
 * pasted here: if either side ever changes how it signs, this fails here
 * instead of every store silently rejecting every payment notification.
 *
 * Both the attribute and the annotation are present throughout, on purpose.
 * This module supports Magento 2.4.6 to 2.4.9, which ships PHPUnit 9 through
 * 12: PHPUnit 9 reads the annotation and ignores the attribute, PHPUnit 10 and
 * later read the attribute and no longer apply the annotation. With only one
 * of them the suite silently runs no data sets on half the supported range.
 *
 * @covers \BriizPay\PayByBank\Model\Signature
 */
#[CoversClass(Signature::class)]
class SignatureTest extends TestCase
{
    private const SECRET = 'whsec_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const TIMESTAMP = 1788780000;
    // phpcs:ignore Generic.Files.LineLength -- the signed body is one byte sequence; reformatting it changes what was signed
    private const BODY = '{"id":"evt_magento","type":"payment_request.paid","data":{"paymentRequestId":"req_1","externalReference":"000000042","amountMinor":1499}}';
    private const API_SIGNATURE = '592875df2a153953302ece484ac453d51a911812257abdd457ff45ea580210fa';

    private Signature $signature;

    protected function setUp(): void
    {
        $this->signature = new Signature();
    }

    private function header(?int $timestamp = null, ?string $v1 = null): string
    {
        return 't=' . ($timestamp ?? self::TIMESTAMP) . ',v1=' . ($v1 ?? self::API_SIGNATURE);
    }

    public function testAcceptsTheVectorTheApiProduced(): void
    {
        self::assertTrue(
            $this->signature->verify(self::BODY, $this->header(), self::SECRET, self::TIMESTAMP)
        );
    }

    public function testRejectsAChangedAmount(): void
    {
        $tampered = str_replace('1499', '1', self::BODY);

        self::assertFalse(
            $this->signature->verify($tampered, $this->header(), self::SECRET, self::TIMESTAMP)
        );
    }

    public function testRejectsTheWrongSecret(): void
    {
        $other = 'whsec_' . str_repeat('b', 64);

        self::assertFalse(
            $this->signature->verify(self::BODY, $this->header(), $other, self::TIMESTAMP)
        );
    }

    /**
     * The timestamp is inside the signed material, so a captured delivery can
     * only be replayed if we fail to check its age.
     *
     * @dataProvider ageProvider
     */
    #[DataProvider('ageProvider')]
    public function testChecksTheAgeOfTheDelivery(int $now, bool $expected, string $case): void
    {
        self::assertSame(
            $expected,
            $this->signature->verify(self::BODY, $this->header(), self::SECRET, $now),
            $case
        );
    }

    public static function ageProvider(): array
    {
        return [
            'inside five minutes' => [self::TIMESTAMP + 299, true, 'a fresh delivery is accepted'],
            'past five minutes' => [self::TIMESTAMP + 301, false, 'a replay is refused'],
            'from the future' => [self::TIMESTAMP - 301, false, 'a clock ahead of ours is refused too'],
        ];
    }

    /**
     * Every one of these must be a plain no. A malformed header that threw
     * would be a 500 where a refusal belongs, and hash_equals throws on a
     * length mismatch unless the length is checked first.
     *
     * @dataProvider malformedProvider
     */
    #[DataProvider('malformedProvider')]
    public function testRefusesAnythingItCannotRead(?string $header, string $case): void
    {
        self::assertFalse(
            $this->signature->verify(self::BODY, $header, self::SECRET, self::TIMESTAMP),
            $case
        );
    }

    public static function malformedProvider(): array
    {
        return [
            'no header at all' => [null, 'a request with no signature is refused'],
            'not a signature' => ['nonsense', 'a header in the wrong shape is refused'],
            'truncated signature' => [
                't=' . self::TIMESTAMP . ',v1=abc', 'a short signature is refused, not thrown on',
            ],
            'timestamp is not a number' => ['t=abc,v1=' . self::API_SIGNATURE, 'an unparseable timestamp is refused'],
        ];
    }

    /**
     * A store with no secret saved can verify nothing, so everything must fail
     * closed. The controller refuses before reaching this, and this is the
     * second lock on the same door.
     */
    public function testRefusesEverythingWhenNoSecretIsConfigured(): void
    {
        self::assertFalse(
            $this->signature->verify(self::BODY, $this->header(), '', self::TIMESTAMP)
        );
    }
}
