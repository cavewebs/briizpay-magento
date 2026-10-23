<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Test\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The files that ship, and the promises they make.
 *
 * These are not about a class's behaviour, they are about the package: a
 * default in config.xml, an admin field pointing at the right source model, an
 * image at the size the stylesheet expects. Every one of them can drift
 * silently, because nothing fails until a merchant installs it and looks.
 *
 * The API hosts are asserted against the shipped source rather than by calling
 * the client, which would need Magento's curl factory. Three distinct hosts is
 * the thing that has to be true: a wrong one sends real payments at a test
 * environment, and it succeeds and looks fine.
 */
class ShippedFilesTest extends TestCase
{
    private const BANKS = ['barclays', 'hsbc', 'natwest', 'monzo'];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $relative): string
    {
        $path = self::root() . '/' . $relative;
        self::assertFileExists($path, $relative . ' ships with the module');
        return (string) file_get_contents($path);
    }

    /** On by default, so a new store shows them without being told to. */
    public function testTheLogosAreOnByDefaultInTheShippedConfig(): void
    {
        $config = simplexml_load_string(self::read('etc/config.xml'));

        self::assertSame('1', (string) $config->default->payment->briizpay->show_logo);
    }

    public function testTheAdminFieldIsAYesNoAtStoreScope(): void
    {
        $system = simplexml_load_string(self::read('etc/adminhtml/system.xml'));
        $field = $system->xpath('//group[@id="briizpay"]/field[@id="show_logo"]')[0] ?? null;

        self::assertNotNull($field, 'the setting has an admin field');
        self::assertSame('Show bank logos at checkout', (string) $field->label);
        self::assertSame('Magento\Config\Model\Config\Source\Yesno', (string) $field->source_model);
        self::assertSame('1', (string) $field['showInStore'], 'a per-store setting, like the rest of the method');
    }

    /**
     * Twice the 20px display size, so they stay sharp on a phone.
     *
     * @dataProvider bankProvider
     */
    #[DataProvider('bankProvider')]
    public function testEachBankLogoIsAFortyPixelSquarePng(string $bank): void
    {
        $size = getimagesize(self::root() . '/view/frontend/web/images/banks/' . $bank . '.png');

        self::assertIsArray($size, $bank . ' is a readable image');
        self::assertSame([40, 40], [$size[0], $size[1]], $bank . ' is 40 by 40');
        self::assertSame('image/png', $size['mime']);
    }

    public static function bankProvider(): array
    {
        return array_map(static fn (string $bank): array => [$bank], self::BANKS);
    }

    public function testTheOldWordmarkIsNoLongerShipped(): void
    {
        self::assertFileDoesNotExist(
            self::root() . '/view/frontend/web/images/briizpay-logo.png',
            'replaced by the bank logos; shipping both confuses which is current'
        );
    }

    /**
     * The logos repeat the method name to a screen reader and say nothing a
     * blind shopper can act on, so the row is decorative and hidden.
     */
    public function testTheLogoRowIsHiddenFromAssistiveTechnology(): void
    {
        $template = self::read('view/frontend/web/template/payment/briizpay.html');

        self::assertStringContainsString('class="briizpay-logos" aria-hidden="true"', $template);
        self::assertSame(1, preg_match_all('/alt=""/', $template), 'every image has an empty alt');
        self::assertSame(0, preg_match('/alt="[^"]/', $template), 'no image carries alt text');
    }

    /**
     * @dataProvider styleProvider
     */
    #[DataProvider('styleProvider')]
    public function testTheLogosAreStyledAsTheDesignIntends(string $declaration): void
    {
        self::assertStringContainsString($declaration, self::read('view/frontend/web/css/briizpay.css'));
    }

    public static function styleProvider(): array
    {
        return [
            '20px tall' => ['height: 20px;'],
            '20px wide' => ['width: 20px;'],
            'rounded corners' => ['border-radius: 4px;'],
            'spaced apart' => ['gap: 4px;'],
        ];
    }

    /**
     * @dataProvider hostProvider
     */
    #[DataProvider('hostProvider')]
    public function testTheClientShipsADistinctHostPerEnvironment(string $declaration): void
    {
        self::assertStringContainsString($declaration, self::read('Model/Api/Client.php'));
    }

    public static function hostProvider(): array
    {
        return [
            'live' => ["LIVE_BASE = 'https://api.briizpay.com'"],
            'sandbox' => ["SANDBOX_BASE = 'https://sandbox.api.briizpay.com'"],
            'test' => ["TEST_BASE = 'https://dev.api.briizpay.com'"],
            'sandbox is mapped' => ["'sandbox' => self::SANDBOX_BASE"],
            'test is mapped' => ["'test' => self::TEST_BASE"],
            'anything else is live' => ['default => self::LIVE_BASE'],
        ];
    }

    /** The first-run guidance only reaches a merchant if both places are wired up. */
    public function testTheFirstRunGuidanceIsWiredIntoTheAdmin(): void
    {
        self::assertStringContainsString(
            'System\\Message\\NotConfigured',
            self::read('etc/adminhtml/di.xml'),
            'the admin system message is registered'
        );
        self::assertStringContainsString(
            'Config\\Intro',
            self::read('etc/adminhtml/system.xml'),
            'the configuration section renders the introduction'
        );
    }

    /**
     * Every Magento module the code imports must be required, or a minimal
     * install can resolve without it and the module fatals on a class that is
     * not there.
     */
    public function testEveryMagentoModuleTheCodeUsesIsDeclared(): void
    {
        $composer = json_decode(self::read('composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $required = array_keys($composer['require']);

        $used = [];
        foreach (self::phpFiles() as $file) {
            preg_match_all('/^use Magento\\\\([A-Za-z]+)\\\\/m', (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $namespace) {
                $used[$namespace] = true;
            }
        }

        $missing = [];
        foreach (array_keys($used) as $namespace) {
            // Framework is magento/framework, every other namespace is a module.
            $package = $namespace === 'Framework'
                ? 'magento/framework'
                : 'magento/module-' . strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $namespace));
            if (!in_array($package, $required, true)) {
                $missing[] = $package;
            }
        }

        self::assertSame([], $missing, 'composer.json is missing a module the code imports');
    }

    public function testTheModuleSequenceCoversWhatItExtends(): void
    {
        $module = simplexml_load_string(self::read('etc/module.xml'));
        $sequenced = array_map(
            static fn ($node): string => (string) $node['name'],
            $module->xpath('//sequence/module') ?: []
        );

        foreach (['Magento_Sales', 'Magento_Payment', 'Magento_Checkout', 'Magento_Quote'] as $expected) {
            self::assertContains($expected, $sequenced);
        }
    }

    /** Marked translatable and with nowhere to be translated to is the same as not translatable. */
    public function testEveryTranslatableStringHasATranslationEntry(): void
    {
        $csv = self::read('i18n/en_US.csv');
        $known = [];
        foreach (explode("\n", $csv) as $line) {
            $row = str_getcsv($line);
            if (isset($row[0]) && $row[0] !== '') {
                $known[$row[0]] = true;
            }
        }

        $missing = [];
        foreach (self::phpFiles() as $file) {
            preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $string) {
                $string = str_replace("\\'", "'", $string);
                if (!isset($known[$string])) {
                    $missing[] = $string;
                }
            }
        }

        self::assertSame([], array_values(array_unique($missing)), 'i18n/en_US.csv is missing a string');
    }

    /** @return array<int, string> */
    private static function phpFiles(): array
    {
        $files = [];
        $directories = ['Block', 'Controller', 'Gateway', 'Model', 'Observer', 'Plugin'];
        foreach ($directories as $directory) {
            $path = self::root() . '/' . $directory;
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        return $files;
    }
}
