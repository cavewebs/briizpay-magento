<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Total\Quote;

use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\DiscountCalculator;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;

/**
 * The pay by bank discount as a line of its own in the basket's totals.
 *
 * It lives and dies with the payment method: every time the totals are
 * collected it is worked out again from the quote's method, so choosing
 * another method and collecting again takes it off. Magento collects the
 * totals whenever a method is set, so the checkout sees the change on its next
 * totals request.
 *
 * Runs after tax (etc/sales.xml) because the basis includes each item's tax,
 * and before the grand total, which is the sum of every collector's amount.
 * The discount itself is not taxable: it is taken off what the customer pays,
 * not spread across the basket's VAT rates, which would leave the merchant
 * pennies of rounding to explain.
 */
class PayByBankDiscount extends AbstractTotal
{
    public function __construct(
        private readonly DiscountCalculator $calculator
    ) {
        $this->setCode(Discount::TOTAL_CODE);
    }

    public function collect(Quote $quote, ShippingAssignmentInterface $shippingAssignment, Total $total)
    {
        parent::collect($quote, $shippingAssignment, $total);

        // Written as zero first, so a discount from an earlier collection with
        // BriizPay chosen never survives on the address once it is not.
        $total->setData(Discount::FIELD, 0.0);
        $total->setData(Discount::BASE_FIELD, 0.0);
        $total->setTotalAmount($this->getCode(), 0.0);
        $total->setBaseTotalAmount($this->getCode(), 0.0);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            // The other address of the quote (billing, for a basket that ships).
            return $this;
        }

        [$amount, $baseAmount] = $this->calculator->amountFor($quote, $items);

        $total->setData(Discount::FIELD, -$amount);
        $total->setData(Discount::BASE_FIELD, -$baseAmount);
        $total->setTotalAmount($this->getCode(), -$amount);
        $total->setBaseTotalAmount($this->getCode(), -$baseAmount);
        $quote->setData(Discount::FIELD, -$amount);
        $quote->setData(Discount::BASE_FIELD, -$baseAmount);

        return $this;
    }

    /**
     * The checkout's total segment. $total here is built from the address's
     * saved data, which is why the amount is a column on quote_address and not
     * only a number in memory during collection.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(Quote $quote, Total $total)
    {
        $amount = (float) $total->getData(Discount::FIELD);
        if ($amount == 0.0) {
            return null;
        }
        return [
            'code' => $this->getCode(),
            'title' => __(Discount::LABEL),
            'value' => $amount,
        ];
    }

    public function getLabel()
    {
        return __(Discount::LABEL);
    }
}
