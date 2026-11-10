<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model\Api;

use BriizPay\PayByBank\Model\Api\ApiException;
use BriizPay\PayByBank\Model\Api\Client;
use BriizPay\PayByBank\Model\Api\SdkClientFactory;
use BriizPay\PayByBank\Model\Config;
use BriizPay\Sdk\ClientInterface as SdkClientInterface;
use BriizPay\Sdk\Dto\CreatePaymentRequest;
use BriizPay\Sdk\Dto\Money as Amount;
use BriizPay\Sdk\Dto\PaymentRequest;
use BriizPay\Sdk\Dto\PaymentStatus;
use BriizPay\Sdk\Exception\AuthenticationException;
use BriizPay\Sdk\Exception\BriizPayException;
use BriizPay\Sdk\Exception\ConflictException;
use BriizPay\Sdk\Exception\NetworkException;
use BriizPay\Sdk\Exception\NotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Magento's wrapper around the PHP SDK: store-scoped keys, and the shopper-
 * facing messages for the typed exceptions the SDK throws.
 *
 * @covers \BriizPay\PayByBank\Model\Api\Client
 */
#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    private function paymentRequest(): PaymentRequest
    {
        return new PaymentRequest(
            id: 'pr_1',
            status: PaymentStatus::Pending,
            amount: new Amount(1995, 'GBP'),
            payUrl: 'https://pay.briizpay.com/pr_1'
        );
    }

    private function createRequest(): CreatePaymentRequest
    {
        return new CreatePaymentRequest(new Amount(1995, 'GBP'));
    }

    /**
     * @param callable(SdkClientInterface): void $arrange
     */
    private function client(callable $arrange, bool $hasKey = true): Client
    {
        $sdk = $this->createMock(SdkClientInterface::class);
        $arrange($sdk);

        $factory = $this->createStub(SdkClientFactory::class);
        $factory->method('create')->willReturn($sdk);

        $config = $this->createStub(Config::class);
        $config->method('hasValidApiKey')->willReturn($hasKey);
        $config->method('getApiKey')->willReturn('bzp_sk_live_' . str_repeat('a', 40));

        return new Client($config, $factory, $this->createStub(LoggerInterface::class));
    }

    public function testPassesATypedRequestThroughToTheSdk(): void
    {
        $expected = $this->paymentRequest();
        $client = $this->client(function (SdkClientInterface $sdk) use ($expected): void {
            $sdk->method('createPaymentRequest')->willReturn($expected);
        });

        self::assertSame($expected, $client->createPaymentRequest($this->createRequest(), 3));
    }

    public function testRefusesToCallTheSdkWhenNoKeyIsSaved(): void
    {
        $factory = $this->createMock(SdkClientFactory::class);
        $factory->expects(self::never())->method('create');

        $config = $this->createStub(Config::class);
        $config->method('hasValidApiKey')->willReturn(false);

        $client = new Client($config, $factory, $this->createStub(LoggerInterface::class));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('BriizPay is not set up yet');
        $client->createPaymentRequest($this->createRequest());
    }

    public function testANetworkFailureIsAMessageAShopperCanActOn(): void
    {
        $client = $this->client(function (SdkClientInterface $sdk): void {
            $sdk->method('createPaymentRequest')
                ->willThrowException(new NetworkException('connection timed out'));
        });

        try {
            $client->createPaymentRequest($this->createRequest());
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(0, $e->getHttpStatus());
            self::assertSame('Could not reach BriizPay. Please try again.', $e->getMessage());
        }
    }

    public function testARejectedKeyIsNamedAsSuch(): void
    {
        $client = $this->client(function (SdkClientInterface $sdk): void {
            $sdk->method('createPaymentRequest')
                ->willThrowException(new AuthenticationException('unauthorized', 401));
        });

        try {
            $client->createPaymentRequest($this->createRequest());
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->getHttpStatus());
            self::assertStringContainsString('rejected the API key', $e->getMessage());
        }
    }

    /**
     * Cancelling a paid payment is HTTP 409. The observer still swallows it;
     * the status has to survive the mapping so a caller that cares can tell
     * conflict from a missing key.
     */
    public function testAConflictKeepsHttp409(): void
    {
        $client = $this->client(function (SdkClientInterface $sdk): void {
            $sdk->method('cancelPaymentRequest')
                ->willThrowException(new ConflictException('already paid', 409, 'ALREADY_PAID'));
        });

        try {
            $client->cancelPaymentRequest('pr_1');
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(409, $e->getHttpStatus());
            self::assertSame('already paid', $e->getMessage());
        }
    }

    /**
     * @dataProvider otherFailureProvider
     */
    #[DataProvider('otherFailureProvider')]
    public function testOtherSdkFailuresKeepTheirStatusAndMessage(
        BriizPayException $thrown,
        int $status,
        string $message
    ): void {
        $client = $this->client(function (SdkClientInterface $sdk) use ($thrown): void {
            $sdk->method('getPaymentRequest')->willThrowException($thrown);
        });

        try {
            $client->getPaymentRequest('pr_1');
            self::fail('expected ApiException');
        } catch (ApiException $e) {
            self::assertSame($status, $e->getHttpStatus());
            self::assertSame($message, $e->getMessage());
        }
    }

    public static function otherFailureProvider(): array
    {
        return [
            'forbidden' => [new AuthenticationException('forbidden', 403), 403, 'forbidden'],
            'not found' => [new NotFoundException('missing', 404), 404, 'missing'],
        ];
    }
}
