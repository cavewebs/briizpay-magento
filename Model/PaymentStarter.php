<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use BriizPay\PayByBank\Model\Api\Client;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;

/**
 * Create the BriizPay payment for an order and say where to send the customer.
 *
 * The amount comes from the order in pence, never from the browser. The pay
 * link is kept on the order payment so a customer who reloads, or comes back
 * from the bank and tries again, is sent to the same payment rather than
 * creating a second one for the same order.
 *
 * The link carries start=1, so the BriizPay pay page starts the payment as it
 * loads and the customer goes straight to choosing their bank: they have already
 * seen the order and chosen this method at checkout.
 */
class PaymentStarter
{
    public const INFO_REQUEST_ID = 'briizpay_payment_request_id';
    public const INFO_PAY_URL = 'briizpay_pay_url';

    public function __construct(
        private readonly Client $client,
        private readonly UrlInterface $url,
        private readonly OrderRepositoryInterface $orders,
        private readonly ReturnToken $returnToken
    ) {
    }

    public function payUrlFor(Order $order): string
    {
        $payment = $order->getPayment();
        $existing = (string) $payment->getAdditionalInformation(self::INFO_PAY_URL);
        if ($existing !== '') {
            return $existing;
        }

        $storeId = (int) $order->getStoreId();
        $billing = $order->getBillingAddress();
        $name = $billing ? trim($billing->getFirstname() . ' ' . $billing->getLastname()) : '';

        $body = [
            'amountMinor' => Money::toMinor($order->getGrandTotal()),
            'currency' => (string) $order->getOrderCurrencyCode(),
            'externalReference' => (string) $order->getIncrementId(),
            // The store knows who is buying. Without this the merchant sees a
            // payment with no name against an order that has one.
            'customerName' => $name !== '' ? $name : null,
            'customerEmail' => (string) $order->getCustomerEmail(),
            'memo' => sprintf('Order #%s at %s', $order->getIncrementId(), $order->getStore()->getFrontendName()),
            // Where the payer lands afterwards. Their arrival is not the
            // confirmation: the webhook and the API status check are.
            'returnUrl' => $this->url->getUrl('briizpay/checkout/back', [
                '_secure' => true,
                '_query' => [
                    'order' => $order->getIncrementId(),
                    'token' => $this->returnToken->for($order),
                ],
            ]),
        ];
        // What was bought, for the customer's receipt. Left out when the lines
        // do not add up to the grand total exactly, so the receipt falls back
        // to the memo rather than the checkout failing.
        $lines = LineItems::forOrder($order);
        if ($lines !== null) {
            $body['lineItems'] = $lines;
        }

        $request = $this->client->createPaymentRequest($body, $storeId);

        if (empty($request['id']) || empty($request['payUrl'])) {
            throw new Api\ApiException(__('BriizPay could not take this payment right now.'));
        }

        $payUrl = $this->withStart((string) $request['payUrl']);
        $payment->setAdditionalInformation(self::INFO_REQUEST_ID, (string) $request['id']);
        $payment->setAdditionalInformation(self::INFO_PAY_URL, $payUrl);
        $order->addCommentToStatusHistory(
            (string) __('Waiting for the customer to approve the payment in their banking app.')
        );
        $this->orders->save($order);

        return $payUrl;
    }

    private function withStart(string $payUrl): string
    {
        return $payUrl . (str_contains($payUrl, '?') ? '&' : '?') . 'start=1';
    }
}
