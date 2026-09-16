<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;

/**
 * Pounds only.
 *
 * BriizPay settles to a UK bank account over Faster Payments, so an order in any
 * other currency cannot be paid this way. The method is hidden for it rather than
 * shown and refused.
 */
class CurrencyValidator extends AbstractValidator
{
    /** @param array<string, mixed> $validationSubject */
    public function validate(array $validationSubject): ResultInterface
    {
        return $this->createResult(($validationSubject['currency'] ?? '') === 'GBP');
    }
}
