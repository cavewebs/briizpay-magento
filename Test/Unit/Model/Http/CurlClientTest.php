<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit\Model\Http;

use BriizPay\PayByBank\Model\Http\CurlClient;
use BriizPay\PayByBank\Model\Http\CurlNetworkException;
use BriizPay\PayByBank\Model\Http\Request;
use BriizPay\PayByBank\Model\Http\Stream;
use BriizPay\PayByBank\Model\Http\Uri;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\HTTP\Client\CurlFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Magento Curl as PSR-18, with the 20s wait the module has always used.
 *
 * @covers \BriizPay\PayByBank\Model\Http\CurlClient
 */
#[CoversClass(CurlClient::class)]
class CurlClientTest extends TestCase
{
    private function request(string $method = 'GET', string $body = ''): Request
    {
        return (new Request($method, new Uri('https://api.briizpay.com/v1/x'), new Stream($body)))
            ->withHeader('Authorization', 'Bearer secret')
            ->withHeader('Accept', 'application/json');
    }

    public function testWaitsTwentySecondsByDefault(): void
    {
        $curl = new Curl();
        $factory = $this->createStub(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        $response = (new CurlClient($factory))->sendRequest($this->request());

        self::assertSame(20, $curl->timeout);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('GET', $curl->method);
    }

    public function testPostsTheJsonBodyTheSdkBuilt(): void
    {
        $curl = new Curl();
        $factory = $this->createStub(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        $json = '{"amountMinor":1995}';
        (new CurlClient($factory, 20))->sendRequest($this->request('POST', $json));

        self::assertSame('POST', $curl->method);
        self::assertSame($json, $curl->posted);
        self::assertSame('Bearer secret', $curl->headers['authorization']);
    }

    public function testACurlFailureIsAPsr18NetworkException(): void
    {
        $curl = new Curl();
        $curl->throws = new \RuntimeException('timed out');
        $factory = $this->createStub(CurlFactory::class);
        $factory->method('create')->willReturn($curl);

        $this->expectException(CurlNetworkException::class);
        (new CurlClient($factory))->sendRequest($this->request());
    }
}
