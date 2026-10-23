<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Block\Adminhtml\System\Config;

use BriizPay\PayByBank\Model\Config;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * The store's webhook URL, shown rather than typed.
 *
 * Connecting in the BriizPay dashboard fills this in from the store address, so
 * it is here to check against, not to edit.
 *
 * @api
 */
class WebhookUrl extends Field
{
    /** @param array<string, mixed> $data Magento's block arguments, passed through untouched. */
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWebhookUrl(): string
    {
        return $this->config->getWebhookUrl();
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->setTemplate('BriizPay_PayByBank::system/config/webhook-url.phtml');
        return $this->_toHtml();
    }
}
