<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Observer;

use BriizPay\PayByBank\Model\Api\Client;
use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\PaymentStarter;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * When a BriizPay order is cancelled, retire its pay link.
 *
 * Covers every way an order is cancelled: an admin, the return page, and
 * Magento's own cleanup of Pending Payment orders (Stores, Configuration, Sales,
 * Pending Payment Order Lifetime). Without it a customer who wanders back to an
 * old link could pay for an order the store has dropped, which is a refund and an
 * apology.
 *
 * Best effort by design: the order is cancelled in Magento either way, which is
 * what was asked for, and a failure is noted on the order so a person can act.
 */
class CancelPaymentRequest implements ObserverInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var Order|null $order */
        $order = $observer->getEvent()->getOrder();
        if (!$order || $order->getPayment()?->getMethod() !== Config::METHOD_CODE) {
            return;
        }

        $requestId = (string) $order->getPayment()->getAdditionalInformation(PaymentStarter::INFO_REQUEST_ID);
        if ($requestId === '') {
            return;
        }

        try {
            $this->client->cancelPaymentRequest($requestId, (int) $order->getStoreId());
            $order->addCommentToStatusHistory((string) __('BriizPay payment link cancelled.'));
        } catch (\Throwable $e) {
            $this->logger->warning('[BriizPay] Could not cancel payment link for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
            $order->addCommentToStatusHistory((string) __('BriizPay could not cancel the payment link (%1). It may still be payable.', $e->getMessage()));
        }
    }
}
