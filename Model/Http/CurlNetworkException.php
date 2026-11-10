<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** Transport failure Magento Curl throws, as PSR-18 NetworkException. */
class CurlNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(
        string $message,
        private readonly RequestInterface $request,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
