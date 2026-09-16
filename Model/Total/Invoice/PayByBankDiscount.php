<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Total\Invoice;

use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\Total\DocumentItems;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * The order's pay by bank discount, taken off its invoice.
 *
 * Without it an invoice for the whole order would come to more than the order,
 * and invoicing it would record more paid than the customer was charged. The
 * webhook invoices the whole order at once, so in practice the first invoice
 * takes the whole discount; a partial invoice raised by hand takes its share.
 *
 * Runs after tax (etc/sales.xml), because the share is measured on the items
 * with their tax.
 */
class PayByBankDiscount extends AbstractTotal
{
    public function collect(Invoice $invoice)
    {
        $invoice->setData(Discount::FIELD, 0.0);
        $invoice->setData(Discount::BASE_FIELD, 0.0);

        $order = $invoice->getOrder();
        $orderAmount = abs((float) $order->getData(Discount::FIELD));
        $baseOrderAmount = abs((float) $order->getData(Discount::BASE_FIELD));
        if ($orderAmount <= 0) {
            return $this;
        }

        $taken = 0.0;
        $baseTaken = 0.0;
        foreach ($order->getInvoiceCollection() as $previous) {
            if ($previous->getId() && $previous->getId() !== $invoice->getId()
                && (int) $previous->getState() !== Invoice::STATE_CANCELED
            ) {
                $taken += abs((float) $previous->getData(Discount::FIELD));
                $baseTaken += abs((float) $previous->getData(Discount::BASE_FIELD));
            }
        }

        [$part, $basePart] = DocumentItems::gross($invoice->getAllItems());
        [$whole, $baseWhole] = DocumentItems::gross($order->getAllItems());
        $isLast = $invoice->isLast();
        $amount = Discount::share($orderAmount, $taken, $isLast, $part, $whole);
        $baseAmount = Discount::share($baseOrderAmount, $baseTaken, $isLast, $basePart, $baseWhole);

        $invoice->setData(Discount::FIELD, -$amount);
        $invoice->setData(Discount::BASE_FIELD, -$baseAmount);
        $invoice->setGrandTotal((float) $invoice->getGrandTotal() - $amount);
        $invoice->setBaseGrandTotal((float) $invoice->getBaseGrandTotal() - $baseAmount);

        return $this;
    }
}
