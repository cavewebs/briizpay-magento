<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Adminhtml\System\Message;

use BriizPay\PayByBank\Model\Onboarding;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Notification\MessageInterface;

/**
 * The admin notification for a store that has installed the module and gone no
 * further.
 *
 * Without this, nothing tells a merchant the module is there. The config
 * section explains itself well enough once it is open, but a merchant who does
 * not already know BriizPay exists has no reason to go to Payment Methods and
 * look, and the module is most often installed by somebody else.
 *
 * It stops showing itself as soon as a valid key is saved, so it
 * cannot become the notification nobody reads.
 */
class NotConfigured implements MessageInterface
{
    public function __construct(
        private readonly Onboarding $onboarding,
        private readonly UrlInterface $url
    ) {
    }

    public function getIdentity(): string
    {
        return 'briizpay_pay_by_bank_not_configured';
    }

    public function isDisplayed(): bool
    {
        return !$this->onboarding->isConnected();
    }

    public function getText(): string
    {
        $link = $this->url->getUrl('adminhtml/system_config/edit', ['section' => 'payment']);

        return $this->onboarding->messageText()
            . ' <a href="' . $link . '">' . __('Set up BriizPay') . '</a>';
    }

    public function getSeverity(): int
    {
        return self::SEVERITY_NOTICE;
    }
}
