<?php
/**
 * The parts that need no Magento: the signature contract and pence conversion.
 *
 * Run: php Test/run.php
 *
 * The signature below was produced by the same computation as the API's
 * signWebhook() (briizpay-api src/lib/webhook-signature.ts) for this body,
 * secret and timestamp, and pasted here. If either side ever changes how it
 * signs, this fails, instead of every store silently rejecting every payment
 * notification.
 */
declare(strict_types=1);

require __DIR__ . '/../Model/Signature.php';
require __DIR__ . '/../Model/Money.php';
require __DIR__ . '/../Model/LineItems.php';

use BriizPay\PayByBank\Model\LineItems;
use BriizPay\PayByBank\Model\Money;
use BriizPay\PayByBank\Model\Signature;

$secret = 'whsec_' . str_repeat('a', 64);
$timestamp = 1788780000;
$body = '{"id":"evt_magento","type":"payment_request.paid","data":{"paymentRequestId":"req_1","externalReference":"000000042","amountMinor":1499}}';
$apiSignature = '592875df2a153953302ece484ac453d51a911812257abdd457ff45ea580210fa';
$header = "t={$timestamp},v1={$apiSignature}";

$s = new Signature();

$mugs = ['name' => 'Blue mug', 'quantity' => 2.0, 'gross' => 2500, 'taxRateBps' => 2000, 'sku' => 'MUG-1'];
$ship = ['name' => 'Flat Rate - Fixed', 'quantity' => 1, 'gross' => 500, 'taxRateBps' => 0, 'sku' => ''];
$basket = LineItems::build([$mugs, $ship], 3000);
$split = LineItems::build([['name' => 'Tea', 'quantity' => 3.0, 'gross' => 1000, 'taxRateBps' => 0]], 1000);

$checks = [
    'accepts the API-signed vector' => [$s->verify($body, $header, $secret, $timestamp), true],
    'rejects a changed amount' => [$s->verify(str_replace('1499', '1', $body), $header, $secret, $timestamp), false],
    'rejects the wrong secret' => [$s->verify($body, $header, 'whsec_' . str_repeat('b', 64), $timestamp), false],
    'rejects a replay past five minutes' => [$s->verify($body, $header, $secret, $timestamp + 301), false],
    'accepts inside five minutes' => [$s->verify($body, $header, $secret, $timestamp + 299), true],
    'rejects a timestamp in the future' => [$s->verify($body, $header, $secret, $timestamp - 301), false],
    'rejects no header' => [$s->verify($body, null, $secret, $timestamp), false],
    'rejects a malformed header' => [$s->verify($body, 'nonsense', $secret, $timestamp), false],
    'rejects a truncated signature' => [$s->verify($body, "t={$timestamp},v1=abc", $secret, $timestamp), false],
    'rejects a non-numeric timestamp' => [$s->verify($body, "t=abc,v1={$apiSignature}", $secret, $timestamp), false],
    'rejects an empty secret' => [$s->verify($body, $header, '', $timestamp), false],
    '19.99 is 1999 pence' => [Money::toMinor(19.99), 1999],
    '"14.85" string is 1485 pence' => [Money::toMinor('14.85'), 1485],
    '0.1 + 0.2 rounds to 30 pence' => [Money::toMinor(0.1 + 0.2), 30],
    '1234.565 rounds half up to 123457' => [Money::toMinor('1234.565'), 123457],
    'a line keeps its quantity and unit price' => [$basket[0]['quantity'] . '@' . $basket[0]['unitPriceMinor'], '2@1250'],
    'the sku goes with the line' => [$basket[0]['sku'], 'MUG-1'],
    'no sku key when there is none' => [isset($basket[1]['sku']), false],
    'lines are tax inclusive at the item rate' => [$basket[0]['taxInclusive'] . '/' . $basket[0]['taxRateBps'], '1/2000'],
    'shipping is a line of its own' => [$basket[1]['name'] . '@' . $basket[1]['unitPriceMinor'], 'Flat Rate - Fixed@500'],
    'a row that will not divide is charged once' => [$split[0]['name'] . '|' . $split[0]['quantity'] . '@' . $split[0]['unitPriceMinor'], 'Tea × 3|1@1000'],
    'a penny out sends no lines' => [LineItems::build([$mugs, $ship], 3001), null],
    'no rows sends no lines' => [LineItems::build([], 0), null],
    'over 100 lines sends none' => [LineItems::build(array_fill(0, 101, ['name' => 'Pin', 'quantity' => 1, 'gross' => 1, 'taxRateBps' => 0]), 101), null],
    'names lose markup and entities' => [LineItems::build([['name' => '<b>Fish &amp; chips</b>', 'quantity' => 1, 'gross' => 850, 'taxRateBps' => 0]], 850)[0]['name'], 'Fish & chips'],
    'shipping VAT is worked back from its tax' => [LineItems::rateBps(600, 100), 2000],
];

$failed = 0;
foreach ($checks as $name => [$actual, $expected]) {
    $ok = $actual === $expected;
    if (!$ok) {
        $failed++;
    }
    printf("%s  %s\n", $ok ? 'PASS' : 'FAIL', $name);
}
printf("\n%d of %d passed\n", count($checks) - $failed, count($checks));
exit($failed > 0 ? 1 : 0);
