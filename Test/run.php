<?php
/**
 * The parts that need no Magento: the signature contract, pence conversion,
 * the receipt lines and the pay by bank discount's arithmetic.
 *
 * Run: php Test/run.php
 *
 * The signature below was produced by the same computation as the API's
 * signWebhook() (briizpay-api src/lib/webhook-signature.ts) for this body,
 * secret and timestamp, and pasted here. If either side ever changes how it
 * signs, this fails, instead of every store silently rejecting every payment
 * notification.
 *
 * The logo cases run the module's real Config and checkout ConfigProvider
 * against the small stand-ins for Magento's interfaces in MagentoStubs.php, and
 * read the shipped config.xml, system.xml, images, template and stylesheet, so
 * the setting, its default, the files and the decorative markup cannot drift
 * apart unnoticed.
 *
 * The discount cases are the WooCommerce plugin's tests/test-discount.php, case
 * for case, so both plugins give a merchant's customers the same saving.
 */
declare(strict_types=1);

require __DIR__ . '/../Model/Signature.php';
require __DIR__ . '/../Model/Money.php';
require __DIR__ . '/../Model/LineItems.php';
require __DIR__ . '/../Model/Discount.php';
require __DIR__ . '/MagentoStubs.php';
require __DIR__ . '/../Model/Config.php';
require __DIR__ . '/../Model/Ui/ConfigProvider.php';

use BriizPay\PayByBank\Model\Config;
use BriizPay\PayByBank\Model\Discount;
use BriizPay\PayByBank\Model\LineItems;
use BriizPay\PayByBank\Model\Money;
use BriizPay\PayByBank\Model\Signature;
use BriizPay\PayByBank\Model\Ui\ConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Store\Model\StoreManagerInterface;

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
$penny = LineItems::build([$mugs, $ship], 3001);
$credit = LineItems::build([$mugs, $ship, ['name' => 'Store credit', 'quantity' => 1, 'gross' => -1000, 'taxRateBps' => 0]], 2000);
$gift = LineItems::build([$mugs, $ship], 1000);
$extra = LineItems::build([$mugs, $ship], 3250);
$many = LineItems::build(array_fill(0, 150, ['name' => 'Pin', 'quantity' => 1, 'gross' => 2, 'taxRateBps' => 0]), 299);
$bankRows = LineItems::reductionRows([Discount::LABEL => '-0.3000', 'Gift card' => null, 'Store credit' => '0.0000']);
$banked = LineItems::build(array_merge([$mugs, $ship], $bankRows), 2970);
$linesTotal = static fn (array $lines): int => array_sum(array_map(static fn ($l) => (int) round($l['quantity'] * $l['unitPriceMinor']), $lines));
$last = static fn (array $lines): string => end($lines)['name'] . '@' . end($lines)['unitPriceMinor'];

// A config store keyed by path, recording the store id each flag was read for.
$scope = static function (array $values) use (&$readFor): ScopeConfigInterface {
    $readFor = [];
    return new class ($values, $readFor) implements ScopeConfigInterface {
        public function __construct(private array $values, private array &$readFor)
        {
        }

        public function getValue($path, $scopeType = 'default', $scopeCode = null)
        {
            return $this->values[$path] ?? null;
        }

        public function isSetFlag($path, $scopeType = 'default', $scopeCode = null)
        {
            $this->readFor[$path] = $scopeCode;
            return (bool) ($this->values[$path] ?? false);
        }
    };
};
$moduleConfig = static function (ScopeConfigInterface $scopeConfig): Config {
    $encryptor = new class implements EncryptorInterface {
        public function decrypt($data)
        {
            return $data;
        }
    };
    $url = new class implements UrlInterface {
        public function getUrl($routePath = null, $routeParams = null)
        {
            return 'https://shop.test/' . $routePath;
        }

        public function getBaseUrl($params = [])
        {
            return 'https://shop.test/';
        }
    };
    return new Config($scopeConfig, $encryptor, $url);
};
$provider = static function (array $values, ?string $logoBase, ?string $unresolvable = null) use ($scope, $moduleConfig, &$assetAsked): array {
    $assetAsked = [];
    $scopeConfig = $scope($values);
    $url = new class implements UrlInterface {
        public function getUrl($routePath = null, $routeParams = null)
        {
            return 'https://shop.test/' . $routePath;
        }

        public function getBaseUrl($params = [])
        {
            return 'https://shop.test/';
        }
    };
    $stores = new class implements StoreManagerInterface {
        public function getStore($storeId = null)
        {
            return new class {
                public function getId()
                {
                    return 3;
                }
            };
        }
    };
    // A null base stands for an asset repository that cannot resolve any file,
    // and $unresolvable for one that cannot resolve a single file.
    $assets = new class ($logoBase, $unresolvable, $assetAsked) extends Repository {
        public function __construct(private ?string $logoBase, private ?string $unresolvable, private &$asked)
        {
        }

        public function getUrlWithParams($fileId, array $params)
        {
            $this->asked[] = [$fileId, $params];
            if ($this->logoBase === null || $fileId === $this->unresolvable) {
                throw new \RuntimeException('no such file');
            }
            return $this->logoBase . substr($fileId, strpos($fileId, '::') + 2);
        }
    };
    return (new ConfigProvider($scopeConfig, $url, $moduleConfig($scopeConfig), $stores, $assets))
        ->getConfig()['payment']['briizpay'];
};
$logoBase = 'https://shop.test/static/version1/frontend/Magento/luma/en_GB/BriizPay_PayByBank/';
$banks = ['barclays', 'hsbc', 'natwest', 'monzo'];
$bankUrls = array_map(static fn (string $bank): string => $logoBase . 'images/banks/' . $bank . '.png', $banks);
$bankAssets = array_map(static fn (string $bank): string => 'BriizPay_PayByBank::images/banks/' . $bank . '.png', $banks);
$logoOn = $provider(['payment/briizpay/show_logo' => '1', 'payment/briizpay/description' => 'Pay from your bank'], $logoBase);
$logoAsked = $assetAsked;
$logoOff = $provider(['payment/briizpay/show_logo' => '0'], $logoBase);
$logoLost = $provider(['payment/briizpay/show_logo' => '1'], null);
$logoOneLost = $provider(['payment/briizpay/show_logo' => '1'], $logoBase, $bankAssets[1]);
$withDiscount = $provider([
    'payment/briizpay/show_logo' => '1',
    'payment/briizpay/active' => '1',
    'payment/briizpay/discount_enabled' => '1',
    'payment/briizpay/discount_amount' => '2',
], $logoBase);
$flagOn = $moduleConfig($scope(['payment/briizpay/show_logo' => '1']))->showLogo(3);
$flagOff = $moduleConfig($scope(['payment/briizpay/show_logo' => '0']))->showLogo(3);
$flagUnset = $moduleConfig($scope([]))->showLogo();
$moduleConfig($scope(['payment/briizpay/show_logo' => '1']))->showLogo(7);
$shipped = simplexml_load_file(__DIR__ . '/../etc/config.xml')->default->payment->briizpay;
$adminField = simplexml_load_file(__DIR__ . '/../etc/adminhtml/system.xml')
    ->xpath('//group[@id="briizpay"]/field[@id="show_logo"]')[0] ?? null;
$images = array_map(static function (string $bank): array {
    $size = getimagesize(__DIR__ . '/../view/frontend/web/images/banks/' . $bank . '.png') ?: [];
    return [$size[0] ?? 0, $size[1] ?? 0, $size['mime'] ?? ''];
}, $banks);
$template = (string) file_get_contents(__DIR__ . '/../view/frontend/web/template/payment/briizpay.html');
$css = (string) file_get_contents(__DIR__ . '/../view/frontend/web/css/briizpay.css');

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
    'lines that add up get no extra line' => [count($basket), 2],
    'a penny out gets a rounding line' => [$last($penny), 'Rounding@1'],
    'a rounding line keeps the lines on the total' => [$linesTotal($penny), 3001],
    'named store credit needs no balancing line' => [$last($credit), 'Store credit@-1000'],
    'a large shortfall is other discounts' => [$last($gift), 'Other discounts@-2000'],
    'a large excess is other charges' => [$last($extra), 'Other charges@250'],
    'no rows sends no lines' => [LineItems::build([], 500), null],
    'nothing to charge sends no lines' => [LineItems::build([$mugs], 0), null],
    'over 100 lines is cut to 100' => [count($many), 100],
    'the tail becomes one line' => [$many[98]['name'] . '@' . $many[98]['unitPriceMinor'], '52 more items@104'],
    'a long order still adds up' => [$linesTotal($many), 299],
    'names lose markup and entities' => [LineItems::build([['name' => '<b>Fish &amp; chips</b>', 'quantity' => 1, 'gross' => 850, 'taxRateBps' => 0]], 850)[0]['name'], 'Fish & chips'],
    'shipping VAT is worked back from its tax' => [LineItems::rateBps(600, 100), 2000],
    'the pay by bank discount is a line of its own' => [$last($banked), 'Pay by bank discount@-30'],
    'a discount line leaves nothing to balance' => [count($banked), 3],
    'a discount line keeps the lines on the total' => [$linesTotal($banked), 2970],
    'unset and zero reductions send no line' => [count($bankRows), 1],
    'a reduction stored positive is still taken off' => [LineItems::reductionRows(['Gift card' => 5])[0]['gross'], -500],

    // Discount::compute(), the cases of the WooCommerce plugin's tests/test-discount.php.
    'discount: one percent of a round basket' => [Discount::compute(100.00, 'percent', 1), 1.00],
    'discount: one percent rounds to the penny' => [Discount::compute(45.99, 'percent', 1), 0.46],
    'discount: half a percent on a small basket' => [Discount::compute(12.34, 'percent', 0.5), 0.06],
    'discount: a fixed amount is taken as pounds' => [Discount::compute(100.00, 'fixed', 1), 1.00],
    'discount: a fixed amount never exceeds the basket' => [Discount::compute(0.50, 'fixed', 5), 0.50],
    'discount: one hundred percent is the whole basket' => [Discount::compute(80.00, 'percent', 100), 80.00],
    'discount: nothing off when the amount is zero' => [Discount::compute(100.00, 'percent', 0), 0.0],
    'discount: nothing off a negative amount' => [Discount::compute(100.00, 'percent', -3), 0.0],
    'discount: nothing off an empty basket' => [Discount::compute(0, 'percent', 1), 0.0],
    'discount: strings from the config table work' => [Discount::compute('60.00', 'percent', '2.5'), 1.50],
    'discount: an unknown type is treated as percent' => [Discount::compute(100.00, 'bogus', 1), 1.00],
    'discount: currency precision is honoured' => [Discount::compute(45.99, 'percent', 1, 0), 0.0],

    // The logo setting and what the checkout is given.
    'logo: the flag reads on' => [$flagOn, true],
    'logo: the flag reads off' => [$flagOff, false],
    'logo: an unset flag reads off, so the shipped default is what turns it on' => [$flagUnset, false],
    'logo: the flag is read for the store asked about' => [$readFor['payment/briizpay/show_logo'], 7],
    'logo: on by default in config.xml' => [(string) $shipped->show_logo, '1'],
    'logo: the admin field is a Yes/No at store scope' => [
        $adminField === null ? null : [
            (string) $adminField->label,
            (string) $adminField->source_model,
            (string) $adminField['showInStore'],
            (string) $adminField->comment,
        ],
        [
            'Show bank logos at checkout',
            'Magento\Config\Model\Config\Source\Yesno',
            '1',
            'Turn off if your theme already decorates payment methods.',
        ],
    ],
    'logo: the checkout is told to show them' => [$logoOn['showLogo'], true],
    'logo: the checkout is given the four bank addresses, in order' => [$logoOn['logoUrls'], $bankUrls],
    'logo: the assets are the module images, asked for securely' => [
        $logoAsked,
        array_map(static fn (string $asset): array => [$asset, ['_secure' => true]], $bankAssets),
    ],
    'logo: turned off, the checkout is told not to show them' => [$logoOff['showLogo'], false],
    'logo: assets that cannot be resolved give an empty list, not an error' => [$logoLost['logoUrls'], []],
    'logo: one asset that cannot be resolved is left out alone' => [
        $logoOneLost['logoUrls'],
        [$bankUrls[0], $bankUrls[2], $bankUrls[3]],
    ],
    'logo: the description is still given' => [$logoOn['description'], 'Pay from your bank'],
    'logo: the redirect address is still given' => [$logoOn['redirectUrl'], 'https://shop.test/briizpay/checkout/redirect'],
    'logo: the pay by bank offer flag is untouched' => [[$logoOff['discountOffered'], $withDiscount['discountOffered']], [false, true]],
    'logo: the shipped images are four 40px squares, twice their 20px display size' => [$images, array_fill(0, 4, [40, 40, 'image/png'])],
    'logo: the old wordmark is not shipped' => [file_exists(__DIR__ . '/../view/frontend/web/images/briizpay-logo.png'), false],
    'logo: every image is decorative, with an empty alt' => [substr_count($template, 'alt=""'), 1],
    'logo: no image in the template has alt text' => [preg_match('/alt="[^"]/', $template), 0],
    'logo: the row is hidden from assistive technology' => [str_contains($template, 'class="briizpay-logos" aria-hidden="true"'), true],
    'logo: the icons are 20px with 4px rounded corners and a 4px gap' => [
        [str_contains($css, 'height: 20px;'), str_contains($css, 'width: 20px;'), str_contains($css, 'border-radius: 4px;'), str_contains($css, 'gap: 4px;')],
        [true, true, true, true],
    ],

    // Discount::share(), for invoices and credit memos.
    'share: a whole-order document takes it all' => [Discount::share(1.20, 0.0, true, 120.00, 120.00), 1.20],
    'share: half the items take half' => [Discount::share(1.20, 0.0, false, 60.00, 120.00), 0.60],
    'share: the last document takes what is left' => [Discount::share(1.00, 0.33, true, 40.00, 120.00), 0.67],
    'share: never more than is left' => [Discount::share(1.00, 0.90, false, 60.00, 120.00), 0.10],
    'share: nothing once it is all taken' => [Discount::share(1.00, 1.00, true, 60.00, 120.00), 0.0],
    'share: a document with no items takes none' => [Discount::share(1.00, 0.0, false, 0.0, 120.00), 0.0],
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
