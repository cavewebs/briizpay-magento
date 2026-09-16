<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use Magento\Sales\Model\Order;

/**
 * A token on the return link, so the return page only acts for the order it was
 * made for.
 *
 * Built from the order's protect code, a random value Magento keeps per order and
 * never shows, so an increment id typed into the URL by someone else does not
 * restore that order's basket or cancel it. The return page still decides nothing
 * about payment on its own: it asks BriizPay.
 */
class ReturnToken
{
    public function for(Order $order): string
    {
        return hash_hmac('sha256', (string) $order->getIncrementId(), (string) $order->getProtectCode());
    }

    public function matches(Order $order, string $token): bool
    {
        return $token !== '' && hash_equals($this->for($order), $token);
    }
}
