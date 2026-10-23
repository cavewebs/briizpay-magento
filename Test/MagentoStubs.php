<?php
/**
 * Just enough of Magento's interfaces to run the unit tests with no Magento
 * installed, and nothing at all when it is.
 *
 * Every declaration is guarded. Inside a real store the framework is already
 * loaded and this file does nothing, so `bin/magento dev:tests:run unit` runs
 * the same tests against the real interfaces. Standalone, in CI, where
 * `magento/framework` cannot be installed without repo.magento.com
 * credentials, the stubs stand in so the tests still run on every push.
 *
 * That is the whole reason the guards matter: without them this file is a
 * redeclaration fatal in a store, and the tests could only ever run in one of
 * the two places they need to.
 *
 * Signatures are the ones the module calls. If Magento changes one, the real
 * interface disagrees with the module and the store tells us, which is the
 * right place to find out.
 */
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    if (!interface_exists(ScopeConfigInterface::class, false)) {
        interface ScopeConfigInterface
        {
            public function getValue($path, $scopeType = 'default', $scopeCode = null);

            public function isSetFlag($path, $scopeType = 'default', $scopeCode = null);
        }
    }
}

namespace Magento\Framework\Encryption {
    if (!interface_exists(EncryptorInterface::class, false)) {
        interface EncryptorInterface
        {
            public function decrypt($data);
        }
    }
}

namespace Magento\Framework {
    if (!interface_exists(UrlInterface::class, false)) {
        interface UrlInterface
        {
            public const URL_TYPE_WEB = 'web';

            public function getUrl($routePath = null, $routeParams = null);

            public function getBaseUrl($params = []);
        }
    }
}

namespace Magento\Store\Model {
    if (!interface_exists(ScopeInterface::class, false)) {
        interface ScopeInterface
        {
            public const SCOPE_STORE = 'store';
        }
    }

    if (!interface_exists(StoreManagerInterface::class, false)) {
        interface StoreManagerInterface
        {
            public function getStore($storeId = null);
        }
    }
}

namespace Magento\Checkout\Model {
    if (!interface_exists(ConfigProviderInterface::class, false)) {
        interface ConfigProviderInterface
        {
            public function getConfig();
        }
    }
}

namespace Magento\Framework\View\Asset {
    if (!class_exists(Repository::class, false)) {
        class Repository
        {
            public function getUrlWithParams($fileId, array $params)
            {
                return '';
            }
        }
    }
}

namespace {
    if (!function_exists('__')) {
        /**
         * Magento's translation function, for the standalone case.
         *
         * The module calls __() with a literal string everywhere, because
         * that is the only form Magento's `i18n:collect-phrases` can extract.
         * Without this stub the tests could not load a class that does so.
         *
         * @param string $text
         * @param mixed ...$arguments
         */
        function __($text, ...$arguments): string
        {
            return $arguments === [] ? (string) $text : vsprintf(
                str_replace(['%1', '%2', '%3'], ['%s', '%s', '%s'], (string) $text),
                $arguments
            );
        }
    }
}
