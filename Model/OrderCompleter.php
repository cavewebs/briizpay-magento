<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use BriizPay\PayByBank\Api\MoneyInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;

/**
 * Mark an order paid, once.
 *
 * Shared by the webhook and the return page, so whichever arrives first wins and
 * the other finds the order already invoiced and does nothing. Paid means: an
 * invoice captured offline (the money went bank to bank, nothing is left to
 * capture), the order in Processing, and the BriizPay transaction id recorded
 * on the payment and in Sales, Transactions when BriizPay sent one.
 */
class OrderCompleter
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly OrderRepositoryInterface $orders,
        private readonly LoggerInterface $logger,
        private readonly MoneyInterface $money
    ) {
    }

    /**
     * @param array<string, mixed> $data the payment request or the webhook's data
     * @return bool true when this call marked the order paid
     */
    public function complete(Order $order, array $data, string $source): bool
    {
        $transactionId = $this->transactionIdFrom($data);

        $settled = [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED];
        if ($order->hasInvoices() || in_array($order->getState(), $settled, true)) {
            if ($this->rememberTransactionId($order, $transactionId)) {
                $this->orders->save($order);
            }
            return false;
        }

        // The amount is checked, not assumed. An event that only said "order 41
        // is paid" would ask the store to trust a number it never saw.
        if (isset($data['amountMinor'])) {
            $expected = $this->money->toMinor($order->getGrandTotal());
            $paid = (int) $data['amountMinor'];
            if ($paid < $expected) {
                $order->hold();
                $order->addCommentToStatusHistory((string) __(
                    'BriizPay reported %1 paid against an order total of %2. Held for review.',
                    number_format($paid / 100, 2),
                    number_format($expected / 100, 2)
                ));
                $this->orders->save($order);
                $this->logger->warning(sprintf(
                    '[BriizPay] Underpayment on order %s: %d vs %d',
                    $order->getIncrementId(),
                    $paid,
                    $expected
                ));
                return false;
            }
        }

        if ($order->canUnhold()) {
            $order->unhold();
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $invoice->pay();

        $order->setState(Order::STATE_PROCESSING);
        $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
        $order->addCommentToStatusHistory((string) __('Payment confirmed by BriizPay (%1).', $source))
            // 0, not false: the interface takes an int, and this records
            // "the customer was not emailed about this comment".
            ->setIsCustomerNotified(0);

        $this->rememberTransactionId($order, $transactionId, $invoice);

        $this->transactionFactory->create()
            ->addObject($invoice)
            ->addObject($order)
            ->save();

        return true;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function transactionIdFrom(array $data): string
    {
        if (!isset($data['transactionId']) || $data['transactionId'] === null) {
            return '';
        }
        return trim((string) $data['transactionId']);
    }

    /**
     * last_trans_id, a Sales > Transactions capture, and an order comment.
     * A second notification for the same id is a no-op.
     *
     * @return bool true when this call wrote the id
     */
    private function rememberTransactionId(Order $order, string $transactionId, mixed $invoice = null): bool
    {
        if ($transactionId === '') {
            return false;
        }

        $payment = $order->getPayment();
        $stored = (string) $payment->getAdditionalInformation(PaymentStarter::INFO_TRANSACTION_ID);

        $payment->setTransactionId($transactionId);
        $payment->setLastTransId($transactionId);
        if (is_object($invoice)) {
            $invoice->setTransactionId($transactionId);
        }

        if ($stored === $transactionId) {
            return false;
        }

        $payment->setAdditionalInformation(PaymentStarter::INFO_TRANSACTION_ID, $transactionId);
        $payment->addTransaction(Transaction::TYPE_CAPTURE, $invoice ?: false);

        $order->addCommentToStatusHistory((string) __('BriizPay transaction ID: %1', $transactionId))
            ->setIsCustomerNotified(0);

        return true;
    }
}
