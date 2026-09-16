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
 * any difference between the order's lines and its grand total is sent as a
 * line of its own (see build()), and the lines always agree with what the
 * customer pays.
 */
class LineItems
{
    /** BriizPay takes up to this many lines on one payment. */
    public const MAX_LINES = 100;

    /**
     * Lines for a Magento order, or null when there is nothing to list.
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

        // Adobe Commerce takes gift cards, store credit and reward points off
        // the grand total without an order line. Named here so the receipt says
        // what they were; on Magento Open Source these are never set.
        foreach ([
            'gift_cards_amount' => 'Gift card',
            'customer_balance_amount' => 'Store credit',
            'reward_currency_amount' => 'Reward points',
        ] as $field => $label) {
            $amount = Money::toMinor(abs((float) $order->getData($field)));
            if ($amount > 0) {
                $rows[] = ['name' => (string) __($label), 'quantity' => 1, 'gross' => -$amount, 'taxRateBps' => 0, 'sku' => ''];
            }
        }

        return self::build($rows, Money::toMinor($order->getGrandTotal()));
    }

    /**
     * Turn rows of name, quantity and pence into BriizPay lines that add up to
     * the total.
     *
     * BriizPay refuses lines that do not come to the amount charged, so any
     * difference between the rows and the grand total becomes a line of its
     * own: "Rounding" for the pennies per-row tax rounding leaves, "Other
     * discounts" or "Other charges" for anything larger that an extension took
     * off or added to the total without a line of its own. The amount charged
     * is always the grand total; these lines only make the receipt agree.
     *
     * Kept free of Magento so the arithmetic can be pinned by Test/run.php.
     *
     * @param list<array{name: string, quantity: float|int, gross: int, taxRateBps: int, sku?: string}> $rows
     * @return list<array<string, mixed>>|null Null only when there are no rows or nothing to charge.
     */
    public static function build(array $rows, int $totalMinor): ?array
    {
        if ($rows === [] || $totalMinor <= 0) {
            return null;
        }

        $lines = [];
        $grosses = [];
        foreach ($rows as $row) {
            $gross = (int) $row['gross'];
            $qty = (float) $row['quantity'];
            $name = self::cleanName((string) $row['name']);

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
            $grosses[] = $gross;
        }

        // Past the limit, the tail becomes one line, leaving room for a
        // balancing line after it.
        if (count($lines) > self::MAX_LINES - 1) {
            $keep = self::MAX_LINES - 2;
            $rest = count($lines) - $keep;
            $restSum = array_sum(array_slice($grosses, $keep));
            $lines = array_slice($lines, 0, $keep);
            $grosses = array_slice($grosses, 0, $keep);
            $lines[] = self::plainLine(sprintf(self::label('%d more items'), $rest), $restSum);
            $grosses[] = $restSum;
        }

        $difference = $totalMinor - array_sum($grosses);
        if ($difference !== 0) {
            // Each row's tax rounds to the penny on its own, so the rows can be
            // out by up to a penny each.
            if (abs($difference) <= count($rows)) {
                $label = self::label('Rounding');
            } elseif ($difference < 0) {
                $label = self::label('Other discounts');
            } else {
                $label = self::label('Other charges');
            }
            $lines[] = self::plainLine($label, $difference);
        }

        return $lines;
    }

    /** @return array<string, mixed> A line of one, with no VAT of its own. */
    private static function plainLine(string $name, int $gross): array
    {
        return [
            'name' => self::cleanName($name),
            'quantity' => 1,
            'unitPriceMinor' => $gross,
            'taxRateBps' => 0,
            'taxInclusive' => true,
        ];
    }

    /** Receipt wording, translated when Magento is loaded. */
    private static function label(string $text): string
    {
        return function_exists('__') ? (string) __($text) : $text;
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
