<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/** A BriizPay API failure, with a message safe to show a shopper. */
class ApiException extends LocalizedException
{
    public function __construct(Phrase $phrase, private readonly int $httpStatus = 0)
    {
        parent::__construct($phrase);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
