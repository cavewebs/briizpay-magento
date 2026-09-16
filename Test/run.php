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

use BriizPay\PayByBank\Model\Money;
use BriizPay\PayByBank\Model\Signature;

$secret = 'whsec_' . str_repeat('a', 64);
$timestamp = 1788780000;
$body = '{"id":"evt_magento","type":"payment_request.paid","data":{"paymentRequestId":"req_1","externalReference":"000000042","amountMinor":1499}}';
$apiSignature = '592875df2a153953302ece484ac453d51a911812257abdd457ff45ea580210fa';
$header = "t={$timestamp},v1={$apiSignature}";

$s = new Signature();

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
