<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model;

use BriizPay\PayByBank\Model\WebhookVerifier;
use BriizPay\Sdk\Webhook\WebhookSignature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The Magento adapter around the SDK webhook verifier.
 *
 * Magento injects this class for WebhookVerifierInterface. It must accept and
 * refuse the same deliveries the SDK does, or a store would mark an order paid
 * on a signature the API never sent, or reject a genuine payment notification.
 *
 * The vector is the one SignatureTest uses: produced by the API's own
 * signWebhook for this body, secret and timestamp.
 *
 * @covers \BriizPay\PayByBank\Model\WebhookVerifier
 */
#[CoversClass(WebhookVerifier::class)]
class WebhookVerifierTest extends TestCase
{
    private const SECRET = 'whsec_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const TIMESTAMP = 1788780000;
    // phpcs:ignore Generic.Files.LineLength -- the signed body is one byte sequence; reformatting it changes what was signed
    private const BODY = '{"id":"evt_magento","type":"payment_request.paid","data":{"paymentRequestId":"req_1","externalReference":"000000042","amountMinor":1499}}';
    private const API_SIGNATURE = '592875df2a153953302ece484ac453d51a911812257abdd457ff45ea580210fa';

    private WebhookVerifier $adapter;
    private WebhookSignature $sdk;

    protected function setUp(): void
    {
        $this->adapter = new WebhookVerifier();
        $this->sdk = new WebhookSignature();
    }

    private function header(?int $timestamp = null, ?string $v1 = null): string
    {
        return 't=' . ($timestamp ?? self::TIMESTAMP) . ',v1=' . ($v1 ?? self::API_SIGNATURE);
    }

    public function testAcceptsTheVectorTheApiProduced(): void
    {
        $header = $this->header();

        self::assertTrue($this->sdk->verify(self::BODY, $header, self::SECRET, self::TIMESTAMP));
        self::assertTrue($this->adapter->verify(self::BODY, $header, self::SECRET, self::TIMESTAMP));
    }

    public function testRejectsAChangedAmount(): void
    {
        $tampered = str_replace('1499', '1', self::BODY);
        $header = $this->header();

        self::assertFalse($this->sdk->verify($tampered, $header, self::SECRET, self::TIMESTAMP));
        self::assertFalse($this->adapter->verify($tampered, $header, self::SECRET, self::TIMESTAMP));
    }

    public function testRejectsAStaleTimestamp(): void
    {
        $now = self::TIMESTAMP + WebhookSignature::TOLERANCE_SECONDS + 1;
        $header = $this->header();

        self::assertFalse($this->sdk->verify(self::BODY, $header, self::SECRET, $now));
        self::assertFalse($this->adapter->verify(self::BODY, $header, self::SECRET, $now));
    }

    public function testParseYieldsTheEventTheApiSigned(): void
    {
        $event = $this->adapter->parse(
            self::BODY,
            $this->header(),
            self::SECRET,
            self::TIMESTAMP
        );

        self::assertSame('evt_magento', $event->id);
        self::assertTrue($event->isPaymentRequestPaid());
        self::assertSame('req_1', $event->data->paymentRequestId);
        self::assertSame('000000042', $event->data->externalReference);
        self::assertSame(1499, $event->data->amountMinor);
    }
}
