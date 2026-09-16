<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Plugin\Quote;

use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\DiscountCalculator;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Just before a basket becomes an order, make sure its pay by bank discount is
 * the one its payment method earns.
 *
 * The discount is worked out when totals are collected, and totals are only
 * collected when something asks. A basket totalled with BriizPay chosen keeps
 * that discount on its address until the next collection, so any way of
 * placing an order that changes the method without collecting again would
 * carry the discount onto an order paid some other way. In the WooCommerce
 * plugin exactly that happened: choose pay by bank, see the discount, place
 * the order naming a card. Magento's own checkout collects again when the
 * method is set, but that is a property of today's core code paths, not a
 * promise, and third-party checkouts call submit() too.
 *
 * So the check is made here, on every route to an order: when the discount on
 * the basket differs from what its method earns now, the totals are collected
 * again, which takes the discount off (or puts it on) and corrects the grand
 * total with it. Observer/CopyDiscountToOrder then refuses any order that
 * still disagrees.
 */
class RecollectStaleDiscount
{
    public function __construct(
        private readonly DiscountCalculator $calculator
    ) {
    }

    /**
     * @param array<string, mixed> $orderData
     * @return array{0: Quote, 1: array<string, mixed>}
     */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote, $orderData = []): array
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $carried = round(abs((float) $address->getData(Discount::FIELD)), 2);
        [$earned] = $this->calculator->amountFor($quote, $address->getAllItems());

        if (abs($carried - round($earned, 2)) >= 0.005) {
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
        }

        return [$quote, $orderData];
    }
}
