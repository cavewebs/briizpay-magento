<?php
/**
 * PHPUnit bootstrap.
 *
 * Two environments have to work:
 *
 *   In a store, `bin/magento dev:tests:run unit` has Magento's autoloader
 *   already, so the real framework classes win and MagentoStubs declares
 *   nothing.
 *
 *   Standalone, in CI, magento/framework cannot be installed without
 *   repo.magento.com credentials, which a public repository has no way to hold.
 *   The stubs stand in so the suite still runs on every push.
 *
 * The module's own classes are loaded by PSR-4 off the repository root, which
 * is what composer.json declares, so nothing here needs a require list that
 * could fall behind the source.
 */
declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}

// Only matters standalone: inside Magento every one of these already exists.
require __DIR__ . '/MagentoStubs.php';

// PSR-4 for BriizPay\PayByBank\ off the repository root, for the standalone
// case where composer has not generated a map for the module itself.
spl_autoload_register(static function (string $class): void {
    $prefix = 'BriizPay\\PayByBank\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});
