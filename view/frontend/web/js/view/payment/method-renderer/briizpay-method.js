/**
 * BriizPay at checkout.
 *
 * Place order saves the order as usual, then, instead of the success page, goes
 * to /briizpay/checkout/redirect, which creates the payment for that order and
 * sends the customer straight to choosing their bank. The order is identified by
 * the server session, never by anything sent from here.
 *
 * With the pay by bank discount on, the method's name says what it saves, and
 * choosing it, or choosing something else after it, tells the server straight
 * away so the totals beside the payment step show the price the customer will
 * pay. Luma only sends the chosen method at Place order, which would leave the
 * discount out of sight until after the decision it is there to influence.
 *
 * None of this decides the price. The server works the discount out from the
 * method the order is placed with, whatever was or was not sent from here.
 */
define([
    'jquery',
    'mage/storage',
    'mage/translate',
    'Magento_Checkout/js/view/payment/default',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/url-builder',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Catalog/js/price-utils'
], function ($, storage, $t, Component, quote, urlBuilder, customer, getTotalsAction, priceUtils) {
    'use strict';

    var CODE = 'briizpay',
        SEGMENT = 'briizpay_saving';

    /** @return {Boolean} the totals on screen include the discount */
    function discountShown() {
        var totals = quote.getTotals()();

        return !!(totals && (totals.total_segments || []).some(function (segment) {
            return segment.code === SEGMENT && parseFloat(segment.value) !== 0;
        }));
    }

    /** @return {Number} what paying by bank would save on this basket */
    function offer() {
        var totals = quote.getTotals()(),
            attributes = totals && totals.extension_attributes;

        return attributes ? parseFloat(attributes.briizpay_discount_offer) || 0 : 0;
    }

    return Component.extend({
        defaults: {
            template: 'BriizPay_PayByBank/payment/briizpay'
        },

        redirectAfterPlaceOrder: false,

        /** A request for new totals is in flight. */
        syncing: false,

        /** The method last sent, so a server that will not change is not asked again and again. */
        lastSent: null,

        initialize: function () {
            this._super();

            if (this.getConfig().discountOffered) {
                quote.paymentMethod.subscribe(this.syncDiscount, this);
                this.syncDiscount(quote.paymentMethod());
            }

            return this;
        },

        getConfig: function () {
            return window.checkoutConfig.payment.briizpay || {};
        },

        getDescription: function () {
            return this.getConfig().description || '';
        },

        /** @return {Array} the bank logos' addresses, empty when turned off or missing */
        getLogoUrls: function () {
            var config = this.getConfig();

            return config.showLogo && Array.isArray(config.logoUrls) ? config.logoUrls : [];
        },

        /** "BriizPay - Pay by bank and save £1.20", while there is a saving to name. */
        getTitle: function () {
            var title = this._super(),
                saving = offer(),
                format = quote.getPriceFormat();

            if (!this.getConfig().discountOffered || saving <= 0) {
                return title;
            }

            return $t('%1 and save %2')
                .replace('%1', title)
                .replace('%2', priceUtils.formatPriceLocale ?
                    priceUtils.formatPriceLocale(saving, format) :
                    priceUtils.formatPrice(saving, format));
        },

        /**
         * Send the chosen method when the totals on screen do not match it:
         * BriizPay chosen without the discount showing, or another method
         * chosen with it still showing. Anything else changes nothing and asks
         * for nothing.
         *
         * The method is sent on its own, without the billing address, which
         * may be half filled in at this point, and failures are not shown: a
         * method that needs more details (a purchase order number) is refused
         * until the customer gives them, and they will be asked for them at
         * Place order in the usual way.
         *
         * @param {Object|null} method
         */
        syncDiscount: function (method) {
            var code = method && method.method,
                self = this,
                serviceUrl,
                payload;

            if (!code || this.syncing || code === this.lastSent || (code === CODE) === discountShown()) {
                return;
            }

            if (code === CODE && offer() <= 0) {
                return;
            }

            payload = {
                cartId: quote.getQuoteId(),
                paymentMethod: {method: code}
            };

            if (customer.isLoggedIn()) {
                serviceUrl = urlBuilder.createUrl('/carts/mine/set-payment-information', {});
            } else {
                serviceUrl = urlBuilder.createUrl('/guest-carts/:cartId/set-payment-information', {
                    cartId: quote.getQuoteId()
                });
                payload.email = quote.guestEmail;
            }

            this.syncing = true;
            this.lastSent = code;
            storage.post(serviceUrl, JSON.stringify(payload), true, 'application/json', {})
                .always(function () {
                    var loaded = $.Deferred();

                    getTotalsAction([], loaded);
                    loaded.always(function () {
                        self.syncing = false;
                        // The customer may have changed their mind while
                        // this was on its way.
                        self.syncDiscount(quote.paymentMethod());
                    });
                });
        },

        afterPlaceOrder: function () {
            window.location.replace(this.getConfig().redirectUrl);
        }
    });
});
