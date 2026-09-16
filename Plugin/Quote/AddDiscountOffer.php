<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Plugin\Quote;

use BriizPay\PayByBank\Model\DiscountCalculator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\CartTotalRepositoryInterface;
use Magento\Quote\Api\Data\TotalsExtensionFactory;
use Magento\Quote\Api\Data\TotalsInterface;
use Psr\Log\LoggerInterface;

/**
 * Tell the checkout what paying by bank would save on this basket.
 *
 * The saving only appears in the totals once BriizPay is chosen, but the
 * reason to choose it has to be visible before that. The checkout's JavaScript
 * reads this to say "and save £1.20" next to the method name. It is worked out
 * here, by the same calculator the totals use, rather than repeated in
 * JavaScript, so the promise and the order cannot differ by a penny of tax
 * rounding.
 *
 * Every totals response the checkout reads comes through this repository: the
 * page's first totals, the shipping step's answer and each refresh.
 */
class AddDiscountOffer
{
    public function __construct(
        private readonly CartRepositoryInterface $quotes,
        private readonly DiscountCalculator $calculator,
        private readonly TotalsExtensionFactory $extensionFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /** @param int|string $cartId */
    public function afterGet(CartTotalRepositoryInterface $subject, TotalsInterface $totals, $cartId): TotalsInterface
    {
        try {
            /** @var \Magento\Quote\Model\Quote $quote */
            $quote = $this->quotes->getActive($cartId);
            $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
            [$offer] = $this->calculator->offerFor($address->getAllItems(), (int) $quote->getStoreId());
        } catch (\Throwable $e) {
            // A label is not worth failing the basket's totals over.
            $this->logger->warning('[BriizPay] Could not work out the pay by bank saving: ' . $e->getMessage());
            return $totals;
        }

        if ($offer > 0) {
            $extension = $totals->getExtensionAttributes() ?? $this->extensionFactory->create();
            $extension->setBriizpayDiscountOffer($offer);
            $totals->setExtensionAttributes($extension);
        }

        return $totals;
    }
}
