<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Observer;

use BriizPay\PayByBank\Api\ClientInterface;
use BriizPay\PayByBank\Api\MoneyInterface;
use BriizPay\PayByBank\Model\Api\ApiException;
use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\PaymentStarter;
use BriizPay\Sdk\Dto\CreateRefund;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment\Transaction;
use Psr\Log\LoggerInterface;

/**
 * Telling BriizPay when a store issues a credit memo.
 *
 * The module does not process refunds, and `can_refund` stays 0. Turning it on
 * would put an online Refund button in the order screen that, on the Open
 * Banking rail, moves no money: the merchant still sends it from their bank.
 * An admin pressing it and reading "refunded" would tell a customer their
 * money was on its way when nothing had happened.
 *
 * So Magento keeps its offline credit memo, which the store already knows, and
 * this reports it. BriizPay records the refund against the payment, the
 * merchant sees it beside the original, and the two sets of books agree.
 * Whether the money moves depends on the rail, and the order comment repeats
 * BriizPay's own answer rather than inventing one.
 *
 * See briizpay-api docs/decisions/refunds.md.
 */
class ReportCreditMemo implements ObserverInterface
{
    /**
     * Marks a credit memo as reported, so the event firing twice cannot
     * refund a customer twice. BriizPay deduplicates on externalReference as
     * well, and the two guards cover each other.
     */
    public const REPORTED_FLAG = 'briizpay_refund_reported';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly MoneyInterface $money,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Creditmemo|null $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();
        if (!$creditmemo) {
            return;
        }

        $order = $creditmemo->getOrder();
        // Only our own payments. A store takes cards too, and a credit memo
        // against a card order is nothing to do with BriizPay.
        if (!$order || $order->getPayment()?->getMethod() !== Config::METHOD_CODE) {
            return;
        }

        if ($creditmemo->getData(self::REPORTED_FLAG)) {
            return;
        }

        $requestId = (string) $order->getPayment()->getAdditionalInformation(PaymentStarter::INFO_REQUEST_ID);
        if ($requestId === '') {
            return;
        }

        $amountMinor = $this->money->toMinor((float) $creditmemo->getGrandTotal());
        if ($amountMinor <= 0) {
            return;
        }

        // Written before the call, so a timeout cannot leave the door open for
        // a second report of the same memo.
        $creditmemo->setData(self::REPORTED_FLAG, true);

        try {
            $response = $this->client->createRefund(
                new CreateRefund(
                    paymentRequestId: $requestId,
                    amountMinor: $amountMinor,
                    // The store's own id for this refund, which is what makes
                    // a retry return the first refund instead of a second.
                    externalReference: 'magento_creditmemo_'
                        . ($creditmemo->getIncrementId() ?: $creditmemo->getId()),
                    reason: (string) ($creditmemo->getCustomerNote() ?: '')
                ),
                (int) $order->getStoreId()
            );
        } catch (ApiException $e) {
            // The credit memo exists in Magento either way, which is what the
            // admin asked for. The comment is how they learn the two sides
            // disagree and can put it right.
            // phpcs:ignore Generic.Files.LineLength -- a translatable string is one literal; splitting it hides it from i18n:collect-phrases
            $message = __('BriizPay could not record this refund (%1). Record it in your BriizPay dashboard so your records match.', $e->getMessage());
            $order->addCommentToStatusHistory((string) $message);
            $this->logger->error(
                '[BriizPay] Could not report credit memo for order '
                . $order->getIncrementId() . ': ' . $e->getMessage()
            );
            return;
        }

        // BriizPay's own words. On the Open Banking rail the money has not
        // moved yet, and saying otherwise is how a customer gets told the
        // wrong thing.
        $message = $response->instruction() ?: (string) __('Recorded in BriizPay.');

        $order->addCommentToStatusHistory('BriizPay: ' . $message);

        $this->linkRefundToCapture($order->getPayment(), $creditmemo, $response->getTransactionId());
    }

    /**
     * Magento's refund row sits under the capture whose txn_id is the BriizPay
     * payment id, so Sales > Transactions shows them as parent and child.
     */
    private function linkRefundToCapture(mixed $payment, object $creditmemo, ?string $fromRefund): void
    {
        if (!is_object($payment)) {
            return;
        }

        $parentId = (string) $payment->getLastTransId();
        if ($parentId === '') {
            $parentId = (string) $payment->getAdditionalInformation(PaymentStarter::INFO_TRANSACTION_ID);
        }
        if ($parentId === '' && $fromRefund) {
            $parentId = $fromRefund;
        }
        if ($parentId === '') {
            return;
        }

        $refundTxnId = $parentId . '-refund';
        $creditmemo->setTransactionId($refundTxnId);
        $payment->setParentTransactionId($parentId);
        $payment->setTransactionId($refundTxnId);
        $payment->addTransaction(Transaction::TYPE_REFUND, $creditmemo);
    }
}
