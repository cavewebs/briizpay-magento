<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use Magento\Sales\Model\Order;

/**
 * The order's lines, in the shape BriizPay puts on the receipt.
 *
 * Without them the customer's receipt has one line, "Order #000000004 at Main
 * Website Store", instead of what they bought.
 *
 * BriizPay refuses lines that do not add up to the amount being charged, so
 * this sends none rather than a set that is a penny out: the receipt falls back
 * to the memo and the checkout still goes through. Store credit, gift cards or a
 * total adjusted by another extension end up there, which is the safe outcome.
 */
class LineItems
{
    /** BriizPay takes up to this many lines on one payment. */
    public const MAX_LINES = 100;

    /**
     * Lines for a Magento order, or null when they cannot be sent.
     *
     * Visible items only, so a configurable product is one line, not a parent
     * and a child. Each line is what the customer paid for it: the row after
     * its discount, with its tax. Shipping is a line of its own on the same
     * terms, so the lines add up to the grand total the way Magento's does.
     *
     * @return list<array<string, mixed>>|null
     */
    public static function forOrder(Order $order): ?array
    {
        $rows = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $rows[] = [
                'name' => (string) $item->getName(),
                'quantity' => (float) $item->getQtyOrdered(),
                'gross' => Money::toMinor(
                    (float) $item->getRowTotal()
                    + (float) $item->getTaxAmount()
                    + (float) $item->getDiscountTaxCompensationAmount()
                    - abs((float) $item->getDiscountAmount())
                ),
                'taxRateBps' => (int) round((float) $item->getTaxPercent() * 100),
                'sku' => (string) $item->getSku(),
            ];
        }

        $shipping = Money::toMinor(
            (float) $order->getShippingAmount()
            + (float) $order->getShippingTaxAmount()
            + (float) $order->getShippingDiscountTaxCompensationAmount()
            - abs((float) $order->getShippingDiscountAmount())
        );
        if ($shipping !== 0 || (string) $order->getShippingDescription() !== '') {
            $rows[] = [
                'name' => (string) ($order->getShippingDescription() ?: __('Shipping')),
                'quantity' => 1,
                'gross' => $shipping,
                'taxRateBps' => self::rateBps(
                    Money::toMinor((float) $order->getShippingAmount() + (float) $order->getShippingTaxAmount()),
                    Money::toMinor((float) $order->getShippingTaxAmount())
                ),
                'sku' => '',
            ];
        }

        return self::build($rows, Money::toMinor($order->getGrandTotal()));
    }

    /**
     * Turn rows of name, quantity and pence into BriizPay lines.
     *
     * Kept free of Magento so the arithmetic can be pinned by Test/run.php.
     *
     * @param list<array{name: string, quantity: float|int, gross: int, taxRateBps: int, sku?: string}> $rows
     * @return list<array<string, mixed>>|null
     */
    public static function build(array $rows, int $totalMinor): ?array
    {
        $lines = [];
        $sum = 0;
        foreach ($rows as $row) {
            $gross = (int) $row['gross'];
            $qty = (float) $row['quantity'];
            $name = self::cleanName((string) $row['name']);
            $sum += $gross;

            if ($qty >= 1 && floor($qty) === $qty && $gross % (int) $qty === 0) {
                $unit = intdiv($gross, (int) $qty);
            } else {
                // A discount that leaves a row not dividing evenly by its
                // quantity: say how many in the name and charge the row once,
                // so the line is exactly what the customer paid for it.
                $name = self::cleanName(sprintf('%s × %s', $name, self::formatQty($qty)));
                $qty = 1.0;
                $unit = $gross;
            }

            $line = [
                'name' => $name,
                'quantity' => floor($qty) === $qty ? (int) $qty : $qty,
                'unitPriceMinor' => $unit,
                'taxRateBps' => max(0, min(10000, (int) $row['taxRateBps'])),
                'taxInclusive' => true,
            ];
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $line['sku'] = mb_substr($sku, 0, 64);
            }
            $lines[] = $line;
        }

        if ($lines === [] || count($lines) > self::MAX_LINES || $sum !== $totalMinor) {
            return null;
        }
        return $lines;
    }

    /** A rate worked back from a gross and its tax, to the whole percent. */
    public static function rateBps(int $gross, int $tax): int
    {
        $net = $gross - $tax;
        if ($tax <= 0 || $net <= 0) {
            return 0;
        }
        return (int) round($tax / $net * 100) * 100;
    }

    private static function cleanName(string $name): string
    {
        $name = trim(html_entity_decode(strip_tags($name), ENT_QUOTES, 'UTF-8'));
        return mb_substr($name !== '' ? $name : 'Item', 0, 140);
    }

    private static function formatQty(float $qty): string
    {
        return floor($qty) === $qty ? (string) (int) $qty : rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
    }
}
