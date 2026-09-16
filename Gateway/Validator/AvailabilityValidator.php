<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Gateway\Validator;

use BriizPay\PayByBank\Model\Config;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

/**
 * Hidden at checkout until there is an API key that could work.
 *
 * Offering pay by bank and failing at the last step is worse than not offering
 * it, so a store that has enabled the method but not pasted its key yet shows
 * nothing rather than an error at Place order.
 */
class AvailabilityValidator extends AbstractValidator
{
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly Config $config
    ) {
        parent::__construct($resultFactory);
    }

    /** @param array<string, mixed> $validationSubject */
    public function validate(array $validationSubject): ResultInterface
    {
        $storeId = isset($validationSubject['storeId']) ? (int) $validationSubject['storeId'] : null;
        return $this->createResult($this->config->hasValidApiKey($storeId));
    }
}
