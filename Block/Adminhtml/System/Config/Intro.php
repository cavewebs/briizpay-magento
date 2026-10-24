<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Block\Adminhtml\System\Config;

use BriizPay\PayByBank\Model\Onboarding;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * The introduction at the top of the BriizPay config section.
 *
 * Whoever is reading this section with no key saved is deciding whether to use
 * BriizPay at all, not filling in a form, so the section leads with what the
 * store gains and how to get an account. Once a key is saved this would be in
 * the way, so it disappears and only the missing-webhook case can speak.
 *
 * render() is overridden rather than _getElementHtml() because this is not a
 * field: it is a full width row, and the label column beside a paragraph of
 * explanation reads like something went wrong.
 *
 * The copy is in Model/Onboarding.php, which the admin system message also
 * reads. Two copies of a merchant's first impression of the product would
 * drift.
 *
 * @api
 */
class Intro extends Field
{
    /** The config table is five columns wide (label, value, inherit, scope, note). */
    private const COLUMNS = 5;

    /** @param array<string, mixed> $data Magento's block arguments, passed through untouched. */
    public function __construct(
        Context $context,
        private readonly Onboarding $onboarding,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getOnboarding(): Onboarding
    {
        return $this->onboarding;
    }

    public function getSignupUrl(): string
    {
        return Onboarding::SIGNUP_URL;
    }

    public function getConnectUrl(): string
    {
        return Onboarding::CONNECT_URL;
    }

    public function render(AbstractElement $element): string
    {
        if ($this->onboarding->needsWebhookSecret()) {
            return $this->row($this->renderTemplate('BriizPay_PayByBank::system/config/webhook-warning.phtml'));
        }
        if ($this->onboarding->isConnected()) {
            return '';
        }
        return $this->row($this->renderTemplate('BriizPay_PayByBank::system/config/intro.phtml'));
    }

    /**
     * The panel, in a row of its own.
     *
     * Still built here rather than in a template: the surrounding table is
     * Magento's, and a template that opened a <tr> would be a template about
     * someone else's markup.
     */
    private function row(string $html): string
    {
        return '<tr id="row_' . $this->escapeHtmlAttr((string) $this->getHtmlId()) . '">'
            . '<td colspan="' . self::COLUMNS . '" class="briizpay-intro__cell">' . $html . '</td>'
            . '</tr>';
    }

    private function renderTemplate(string $template): string
    {
        $this->setTemplate($template);
        return $this->_toHtml();
    }
}
