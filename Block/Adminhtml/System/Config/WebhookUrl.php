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
 */
class WebhookUrl extends Field
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        return '<code>' . $this->escapeHtml($this->config->getWebhookUrl()) . '</code>';
    }
}
