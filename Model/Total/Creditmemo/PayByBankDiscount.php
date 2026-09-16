<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Total\Creditmemo;

use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\Total\DocumentItems;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * The order's pay by bank discount, kept off its refund.
 *
 * The customer paid the discounted total, so a credit memo for everything
 * refunds that, not the undiscounted price; Magento would otherwise refuse it
 * as more than was paid. A credit memo for part of the order keeps back its
 * share of the discount, and the last one whatever is left.
 *
 * Runs after tax and before the grand total's adjustments (etc/sales.xml).
 */
class PayByBankDiscount extends AbstractTotal
{
    public function collect(Creditmemo $creditmemo)
    {
        $creditmemo->setData(Discount::FIELD, 0.0);
        $creditmemo->setData(Discount::BASE_FIELD, 0.0);

        $order = $creditmemo->getOrder();
        $orderAmount = abs((float) $order->getData(Discount::FIELD));
        $baseOrderAmount = abs((float) $order->getData(Discount::BASE_FIELD));
        if ($orderAmount <= 0) {
            return $this;
        }

        $taken = 0.0;
        $baseTaken = 0.0;
        foreach ($order->getCreditmemosCollection() ?: [] as $previous) {
            if ($previous->getId() && $previous->getId() !== $creditmemo->getId()
                && (int) $previous->getState() !== Creditmemo::STATE_CANCELED
            ) {
                $taken += abs((float) $previous->getData(Discount::FIELD));
                $baseTaken += abs((float) $previous->getData(Discount::BASE_FIELD));
            }
        }

        [$part, $basePart] = DocumentItems::gross($creditmemo->getAllItems());
        [$whole, $baseWhole] = DocumentItems::gross($order->getAllItems());
        $isLast = $creditmemo->isLast();
        $amount = Discount::share($orderAmount, $taken, $isLast, $part, $whole);
        $baseAmount = Discount::share($baseOrderAmount, $baseTaken, $isLast, $basePart, $baseWhole);

        $creditmemo->setData(Discount::FIELD, -$amount);
        $creditmemo->setData(Discount::BASE_FIELD, -$baseAmount);
        $creditmemo->setGrandTotal((float) $creditmemo->getGrandTotal() - $amount);
        $creditmemo->setBaseGrandTotal((float) $creditmemo->getBaseGrandTotal() - $baseAmount);

        return $this;
    }
}
