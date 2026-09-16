/**
 * BriizPay at checkout.
 *
 * Place order saves the order as usual, then, instead of the success page, goes
 * to /briizpay/checkout/redirect, which creates the payment for that order and
 * sends the customer straight to choosing their bank. The order is identified by
 * the server session, never by anything sent from here.
 */
define([
    'Magento_Checkout/js/view/payment/default'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BriizPay_PayByBank/payment/briizpay'
        },

        redirectAfterPlaceOrder: false,

        getDescription: function () {
            var config = window.checkoutConfig.payment.briizpay || {};
            return config.description || '';
        },

        afterPlaceOrder: function () {
            var config = window.checkoutConfig.payment.briizpay || {};
            window.location.replace(config.redirectUrl);
        }
    });
});
