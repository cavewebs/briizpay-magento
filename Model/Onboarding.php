<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

/**
 * What a store is told before BriizPay is connected.
 *
 * An unconnected module is harmless: the method stays hidden at checkout until
 * a valid key is saved. It is also silent, and that is what this fixes. The
 * config section shows a form and one line of group comment written for
 * someone already holding a key, so a merchant whose developer installed
 * the module, or who found it in Marketplace, is asked for an API key
 * by a screen that never says what BriizPay is or what the store gains by
 * using it. The people most likely to read it are the ones who know least
 * about us.
 *
 * The copy sits here rather than in the block because two places show it: the
 * config section, and the admin system message for a store that has not opened
 * that section yet. It is also the part the tests can reach, and a block needs
 * Magento loaded.
 *
 * Leading with what the merchant gets is deliberate. A merchant choosing a
 * payment method is deciding about their takings and their costs, and "your
 * customer pays from their banking app" is the mechanism: on its own it reads
 * as a reason to do nothing.
 *
 * @api
 */
class Onboarding
{
    public const SIGNUP_URL = 'https://app.briizpay.com/register';
    public const CONNECT_URL = 'https://app.briizpay.com/dashboard/integrations';

    public function __construct(
        private readonly Config $config
    ) {
    }

    /** A key that could work is enough: this screen is not the place to find out it was revoked. */
    public function isConnected(?int $storeId = null): bool
    {
        return $this->config->hasValidApiKey($storeId);
    }

    /**
     * Connected, but the webhook signing secret is missing.
     *
     * Said out loud rather than left to a field comment. The webhook
     * is what marks an order paid, so this configuration takes a customer's
     * money and leaves the order sitting unpaid, and nothing at the checkout
     * looks wrong while it happens.
     */
    public function needsWebhookSecret(?int $storeId = null): bool
    {
        return $this->isConnected($storeId) && $this->config->getWebhookSecret($storeId) === '';
    }

    public function headline(): string
    {
        return (string) __('Get paid straight into your bank account');
    }

    public function summary(): string
    {
        // phpcs:ignore Generic.Files.LineLength -- a translatable string is one literal; concatenating it hides it from i18n:collect-phrases
        return (string) __('BriizPay is a UK Open Banking payment method. Your customer approves the payment in their own banking app and the money arrives in your bank account, usually within seconds.');
    }

    /** @return string[] */
    public function benefits(): array
    {
        return [
            // phpcs:ignore Generic.Files.LineLength -- a translatable string is one unit; splitting it hides it from i18n:collect-phrases
            (string) __('Keep more of each sale. A flat fee rather than a percentage, so the cost does not grow with the basket.'),
            (string) __('Paid into your own bank account rather than held and settled days later.'),
            // phpcs:ignore Generic.Files.LineLength -- see above
            (string) __('Reduces your exposure to card chargebacks, because the customer authorises the payment inside their own bank.'),
            // phpcs:ignore Generic.Files.LineLength -- see above
            (string) __('It runs alongside your card gateway. Nothing is replaced, shoppers simply get another way to pay.'),
        ];
    }

    public function customerExperience(): string
    {
        // phpcs:ignore Generic.Files.LineLength -- a translatable string is one literal; concatenating it hides it from i18n:collect-phrases
        return (string) __('At checkout they choose Pay by bank, pick their bank from a list, approve the payment in their banking app, and come back to your store. There are no card details to type, and none for you to store.');
    }

    /**
     * The account step is first, because a store with no BriizPay account
     * cannot act on any instruction that begins inside our dashboard, and that
     * is the state this screen is most often read in.
     *
     * @return string[]
     */
    public function steps(): array
    {
        return [
            (string) __('Create a BriizPay account and complete the short business verification.'),
            // phpcs:ignore Generic.Files.LineLength -- see above
            (string) __('In BriizPay, open Integrations, Magento, enter this store\'s address and connect. You get an API key and a webhook signing secret, shown once.'),
            (string) __('Paste both into the fields below, set Enabled to Yes, and save.'),
        ];
    }

    public function footnote(): string
    {
        // phpcs:ignore Generic.Files.LineLength -- a translatable string is one literal; concatenating it hides it from i18n:collect-phrases
        return (string) __('Nothing changes for your shoppers while you set this up: BriizPay stays hidden at checkout until a key is saved. A key beginning bzp_sk_sandbox_ runs against the BriizPay sandbox, where no real money moves, so you can place a test order first.');
    }

    public function webhookHeading(): string
    {
        return (string) __('Add your webhook signing secret to finish');
    }

    public function webhookBody(): string
    {
        // phpcs:ignore Generic.Files.LineLength -- a translatable string is one literal; concatenating it hides it from i18n:collect-phrases
        return (string) __('BriizPay marks an order paid by notifying your store, not by the customer returning to it. Until the signing secret is saved, a customer can pay and the order will stay unpaid. Copy it from Integrations, Magento in the BriizPay dashboard.');
    }

    /** The one line the admin bell shows, which has no room for the full introduction. */
    public function messageText(): string
    {
        // phpcs:ignore Generic.Files.LineLength -- a translatable string is one literal; concatenating it hides it from i18n:collect-phrases
        return (string) __('BriizPay is installed but not connected. Take payments straight into your own bank account for a flat fee instead of a percentage of every sale, alongside the cards you already take.');
    }

    /** Translated when Magento is loaded, as Model/LineItems.php does. */
}
