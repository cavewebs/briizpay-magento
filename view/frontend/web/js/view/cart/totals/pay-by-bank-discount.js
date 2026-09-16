/**
 * The same line in the basket page's totals, where there are no checkout steps
 * to wait for.
 */
define([
    'BriizPay_PayByBank/js/view/checkout/summary/pay-by-bank-discount'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BriizPay_PayByBank/cart/totals/pay-by-bank-discount'
        },

        isDisplayed: function () {
            return this.getPureValue() !== 0;
        }
    });
});
