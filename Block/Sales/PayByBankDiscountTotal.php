<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Block\Sales;

use BriizPay\PayByBank\Model\Discount;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\AbstractBlock;

/**
 * The pay by bank discount as a line in an order's, invoice's or credit memo's
 * totals: in the admin, in the customer's account, on printouts and in the
 * order emails.
 *
 * Added as a child of each of those totals blocks by layout. The parent calls
 * initTotals() on its children before it renders and knows which document it
 * is showing, so one block serves all of them.
 */
class PayByBankDiscountTotal extends AbstractBlock
{
    public function initTotals(): self
    {
        /** @var \Magento\Sales\Block\Order\Totals|false $parent */
        $parent = $this->getParentBlock();
        if (!$parent || !$parent->getSource()) {
            return $this;
        }

        $source = $parent->getSource();
        $amount = (float) $source->getData(Discount::FIELD);
        if ($amount == 0.0) {
            return $this;
        }

        // Next to the coupon discount when there is one, so the reductions
        // read together, otherwise straight after the subtotal.
        $parent->addTotal(new DataObject([
            'code' => Discount::TOTAL_CODE,
            'label' => __(Discount::LABEL),
            'value' => $amount,
            'base_value' => (float) $source->getData(Discount::BASE_FIELD),
        ]), $parent->getTotal('discount') ? 'discount' : 'subtotal');

        return $this;
    }
}
