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
 */
class Intro extends Field
{
    /** The config table is five columns wide (label, value, inherit, scope, note). */
    private const COLUMNS = 5;

    public function __construct(
        Context $context,
        private readonly Onboarding $onboarding,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        if ($this->onboarding->needsWebhookSecret()) {
            return $this->row($this->webhookWarning());
        }
        if ($this->onboarding->isConnected()) {
            return '';
        }
        return $this->row($this->intro());
    }

    private function row(string $html): string
    {
        return '<tr id="row_' . $this->escapeHtmlAttr((string) $this->getHtmlId()) . '">'
            . '<td colspan="' . self::COLUMNS . '" style="padding:0 0 18px">' . $html . '</td>'
            . '</tr>';
    }

    private function intro(): string
    {
        $out = '<div style="background:#fff;border:1px solid #adadad;border-left:4px solid #007bdb;padding:16px 20px;max-width:900px">';

        $out .= '<h3 style="margin:0 0 8px;font-size:18px">' . $this->escapeHtml($this->onboarding->headline()) . '</h3>';
        $out .= '<p style="margin:0 0 12px">' . $this->escapeHtml($this->onboarding->summary()) . '</p>';

        $out .= '<p style="margin:0 0 4px"><strong>' . $this->escapeHtml((string) __('What your store gains')) . '</strong></p>';
        $out .= '<ul style="margin:0 0 12px 20px;list-style:disc">';
        foreach ($this->onboarding->benefits() as $benefit) {
            $out .= '<li style="list-style:disc">' . $this->escapeHtml($benefit) . '</li>';
        }
        $out .= '</ul>';

        $out .= '<p style="margin:0 0 4px"><strong>' . $this->escapeHtml((string) __('What your customer does')) . '</strong></p>';
        $out .= '<p style="margin:0 0 12px">' . $this->escapeHtml($this->onboarding->customerExperience()) . '</p>';

        $out .= '<p style="margin:0 0 4px"><strong>' . $this->escapeHtml((string) __('To start taking payments')) . '</strong></p>';
        $out .= '<ol style="margin:0 0 14px 20px">';
        foreach ($this->onboarding->steps() as $step) {
            $out .= '<li style="list-style:decimal">' . $this->escapeHtml($step) . '</li>';
        }
        $out .= '</ol>';

        $out .= '<p style="margin:0 0 12px">';
        $out .= '<a href="' . $this->escapeUrl(Onboarding::SIGNUP_URL) . '" target="_blank" rel="noopener" class="action-default scalable">'
            . $this->escapeHtml((string) __('Create a BriizPay account')) . '</a> ';
        $out .= '<a href="' . $this->escapeUrl(Onboarding::CONNECT_URL) . '" target="_blank" rel="noopener">'
            . $this->escapeHtml((string) __('I have an account, connect this store')) . '</a>';
        $out .= '</p>';

        $out .= '<p style="margin:0;color:#666">' . $this->escapeHtml($this->onboarding->footnote()) . '</p>';
        $out .= '</div>';

        return $out;
    }

    private function webhookWarning(): string
    {
        return '<div style="background:#fff;border:1px solid #adadad;border-left:4px solid #eb5202;padding:14px 20px;max-width:900px">'
            . '<p style="margin:0 0 6px"><strong>' . $this->escapeHtml($this->onboarding->webhookHeading()) . '</strong></p>'
            . '<p style="margin:0">' . $this->escapeHtml($this->onboarding->webhookBody()) . '</p>'
            . '</div>';
    }
}
