<?php
/**
 * Just enough of Magento's interfaces for Test/run.php to run Config and the
 * checkout ConfigProvider for real, with no Magento installed. Signatures are
 * the ones the module calls; nothing here is loaded inside Magento.
 */
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    interface ScopeConfigInterface
    {
        public function getValue($path, $scopeType = 'default', $scopeCode = null);

        public function isSetFlag($path, $scopeType = 'default', $scopeCode = null);
    }
}

namespace Magento\Framework\Encryption {
    interface EncryptorInterface
    {
        public function decrypt($data);
    }
}

namespace Magento\Framework {
    interface UrlInterface
    {
        public const URL_TYPE_WEB = 'web';

        public function getUrl($routePath = null, $routeParams = null);

        public function getBaseUrl($params = []);
    }
}

namespace Magento\Store\Model {
    interface ScopeInterface
    {
        public const SCOPE_STORE = 'store';
    }

    interface StoreManagerInterface
    {
        public function getStore($storeId = null);
    }
}

namespace Magento\Checkout\Model {
    interface ConfigProviderInterface
    {
        public function getConfig();
    }
}

namespace Magento\Framework\View\Asset {
    class Repository
    {
        public function getUrlWithParams($fileId, array $params)
        {
            return '';
        }
    }
}
