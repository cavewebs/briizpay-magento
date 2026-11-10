<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use BriizPay\PayByBank\Api\ClientInterface;
use BriizPay\PayByBank\Api\LineItemsInterface;
use BriizPay\PayByBank\Api\MoneyInterface;
use BriizPay\Sdk\Dto\CreatePaymentRequest;
use BriizPay\Sdk\Dto\LineItem;
use BriizPay\Sdk\Dto\Money as Amount;
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
 * The link includes start=1, so the BriizPay pay page starts the payment as it
 * loads and the customer goes straight to choosing their bank: they have already
 * seen the order and chosen this method at checkout.
 */
class PaymentStarter
{
    public const INFO_REQUEST_ID = 'briizpay_payment_request_id';
    public const INFO_PAY_URL = 'briizpay_pay_url';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly LineItemsInterface $lineItems,
        private readonly MoneyInterface $money,
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

        $lineItems = [];
        $lines = $this->lineItems->forOrder($order);
        if ($lines !== null) {
            foreach ($lines as $line) {
                $lineItems[] = new LineItem(
                    (string) $line['name'],
                    $line['quantity'],
                    (int) $line['unitPriceMinor'],
                    (int) $line['taxRateBps'],
                    (bool) $line['taxInclusive'],
                    isset($line['sku']) ? (string) $line['sku'] : null
                );
            }
        }

        $request = $this->client->createPaymentRequest(
            new CreatePaymentRequest(
                amount: new Amount(
                    $this->money->toMinor($order->getGrandTotal()),
                    (string) $order->getOrderCurrencyCode()
                ),
                externalReference: (string) $order->getIncrementId(),
                // The store knows who is buying. Without this the merchant sees a
                // payment with no name against an order that has one.
                customerName: $name !== '' ? $name : null,
                customerEmail: (string) $order->getCustomerEmail() ?: null,
                memo: sprintf('Order #%s at %s', $order->getIncrementId(), $order->getStore()->getFrontendName()),
                // Where the payer lands afterwards. Their arrival is not the
                // confirmation: the webhook and the API status check are.
                returnUrl: $this->url->getUrl('briizpay/checkout/back', [
                    '_secure' => true,
                    '_query' => [
                        'order' => $order->getIncrementId(),
                        'token' => $this->returnToken->for($order),
                    ],
                ]),
                lineItems: $lineItems
            ),
            $storeId
        );

        if ($request->id === '' || $request->payUrl === '') {
            throw new Api\ApiException(__('BriizPay could not take this payment right now.'));
        }

        $payUrl = $this->withStart($request->payUrl);
        $payment->setAdditionalInformation(self::INFO_REQUEST_ID, $request->id);
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
