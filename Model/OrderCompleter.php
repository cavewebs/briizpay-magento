<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Mark an order paid, once.
 *
 * Shared by the webhook and the return page, so whichever arrives first wins and
 * the other finds the order already invoiced and does nothing. Paid means: an
 * invoice captured offline (the money went bank to bank, nothing is left to
 * capture), the order in Processing, and the BriizPay transaction id recorded.
 */
class OrderCompleter
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly OrderRepositoryInterface $orders,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $data the payment request or the webhook's data
     * @return bool true when this call marked the order paid
     */
    public function complete(Order $order, array $data, string $source): bool
    {
        if ($order->hasInvoices() || in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
            return false;
        }

        // The amount is checked, not assumed. An event that only said "order 41
        // is paid" would ask the store to trust a number it never saw.
        if (isset($data['amountMinor'])) {
            $expected = Money::toMinor($order->getGrandTotal());
            $paid = (int) $data['amountMinor'];
            if ($paid < $expected) {
                $order->hold();
                $order->addCommentToStatusHistory((string) __(
                    'BriizPay reported %1 paid against an order total of %2. Held for review.',
                    number_format($paid / 100, 2),
                    number_format($expected / 100, 2)
                ));
                $this->orders->save($order);
                $this->logger->warning(sprintf('[BriizPay] Underpayment on order %s: %d vs %d', $order->getIncrementId(), $paid, $expected));
                return false;
            }
        }

        if ($order->canUnhold()) {
            $order->unhold();
        }

        $transactionId = isset($data['transactionId']) ? (string) $data['transactionId'] : '';
        $payment = $order->getPayment();
        if ($transactionId !== '') {
            $payment->setTransactionId($transactionId);
            $payment->setLastTransId($transactionId);
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        if ($transactionId !== '') {
            $invoice->setTransactionId($transactionId);
        }
        $invoice->register();
        $invoice->pay();

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
        $order->addCommentToStatusHistory((string) __('Payment confirmed by BriizPay (%1).', $source))
            ->setIsCustomerNotified(false);

        $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        return true;
    }
}
