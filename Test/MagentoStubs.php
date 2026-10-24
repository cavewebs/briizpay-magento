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
 *
 * Each guard autoloads deliberately. class_exists($name, false) does not, so
 * inside a store it answers false for any class Magento has not happened to
 * load yet, and the stub below then declares it and shadows the real one. That
 * is how a stub Phrase without render() reached a real LocalizedException.
 */
declare(strict_types=1);

namespace Magento\Framework\App\Config {
    if (!interface_exists(ScopeConfigInterface::class)) {
        interface ScopeConfigInterface
        {
            public function getValue($path, $scopeType = 'default', $scopeCode = null);

            public function isSetFlag($path, $scopeType = 'default', $scopeCode = null);
        }
    }
}

namespace Magento\Framework\Encryption {
    if (!interface_exists(EncryptorInterface::class)) {
        interface EncryptorInterface
        {
            public function decrypt($data);
        }
    }
}

namespace Magento\Framework {
    if (!interface_exists(UrlInterface::class)) {
        interface UrlInterface
        {
            public const URL_TYPE_WEB = 'web';

            public function getUrl($routePath = null, $routeParams = null);

            public function getBaseUrl($params = []);
        }
    }
}

namespace Magento\Store\Model {
    if (!interface_exists(ScopeInterface::class)) {
        interface ScopeInterface
        {
            public const SCOPE_STORE = 'store';
        }
    }

    if (!interface_exists(StoreManagerInterface::class)) {
        interface StoreManagerInterface
        {
            public function getStore($storeId = null);
        }
    }
}

namespace Magento\Checkout\Model {
    if (!interface_exists(ConfigProviderInterface::class)) {
        interface ConfigProviderInterface
        {
            public function getConfig();
        }
    }
}

namespace Magento\Framework\View\Asset {
    if (!class_exists(Repository::class)) {
        class Repository
        {
            public function getUrlWithParams($fileId, array $params)
            {
                return '';
            }
        }
    }
}

namespace Magento\Framework {
    // DataObject's magic getters are how Magento's events carry their payload,
    // so the stub needs them for an observer test to mean anything.
    if (!class_exists(DataObject::class)) {
        class DataObject
        {
            /** @param array<string, mixed> $data */
            public function __construct(protected array $data = [])
            {
            }

            public function getData($key = null)
            {
                if ($key === null) {
                    return $this->data;
                }
                return $this->data[$key] ?? null;
            }

            public function setData($key, $value = null): static
            {
                $this->data[$key] = $value;
                return $this;
            }

            /** @param array<int, mixed> $arguments */
            public function __call(string $method, array $arguments)
            {
                if (str_starts_with($method, 'get')) {
                    return $this->data[lcfirst(substr($method, 3))] ?? null;
                }
                if (str_starts_with($method, 'set')) {
                    $this->data[lcfirst(substr($method, 3))] = $arguments[0] ?? null;
                    return $this;
                }
                return null;
            }
        }
    }

    if (!class_exists(Event::class)) {
        class Event extends DataObject
        {
        }
    }
}

namespace Magento\Framework {
    if (!class_exists(Phrase::class)) {
        class Phrase
        {
            /** @param array<int, mixed> $arguments */
            public function __construct(private string $text, private array $arguments = [])
            {
            }

            public function __toString(): string
            {
                return $this->arguments === [] ? $this->text : vsprintf(
                    str_replace(['%1', '%2', '%3'], ['%s', '%s', '%s'], $this->text),
                    $this->arguments
                );
            }
        }
    }
}

namespace Magento\Framework\Exception {
    if (!class_exists(LocalizedException::class)) {
        class LocalizedException extends \Exception
        {
            public function __construct(
                \Magento\Framework\Phrase $phrase,
                ?\Exception $cause = null,
                int $code = 0
            ) {
                parent::__construct((string) $phrase, $code, $cause);
            }
        }
    }
}

namespace Magento\Framework\Event {
    if (!class_exists(Observer::class)) {
        class Observer extends \Magento\Framework\DataObject
        {
            public function getEvent(): \Magento\Framework\Event
            {
                return $this->data['event'];
            }
        }
    }

    if (!interface_exists(ObserverInterface::class)) {
        interface ObserverInterface
        {
            public function execute(Observer $observer);
        }
    }
}

namespace Magento\Framework\HTTP\Client {
    if (!class_exists(CurlFactory::class)) {
        class CurlFactory
        {
            public function create()
            {
                return null;
            }
        }
    }
}

namespace Magento\Framework\Serialize\Serializer {
    if (!class_exists(Json::class)) {
        class Json
        {
            public function serialize($data)
            {
                return json_encode($data);
            }

            public function unserialize($string)
            {
                return json_decode((string) $string, true);
            }
        }
    }
}

namespace Magento\Sales\Api {
    if (!interface_exists(OrderRepositoryInterface::class)) {
        interface OrderRepositoryInterface
        {
            public function get($id);
        }
    }
}

namespace Psr\Log {
    if (!interface_exists(LoggerInterface::class)) {
        interface LoggerInterface
        {
            public function emergency($message, array $context = []);

            public function alert($message, array $context = []);

            public function critical($message, array $context = []);

            public function error($message, array $context = []);

            public function warning($message, array $context = []);

            public function notice($message, array $context = []);

            public function info($message, array $context = []);

            public function debug($message, array $context = []);

            public function log($level, $message, array $context = []);
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
