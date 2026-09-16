/**
 * The pay by bank discount as its own line in the checkout's order summary.
 *
 * Read from the totals the server sends back, never worked out here, so the
 * line shows exactly what the order will be charged.
 */
define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/totals'
], function (Component, totals) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BriizPay_PayByBank/checkout/summary/pay-by-bank-discount',
            code: 'briizpay_saving'
        },

        /** @return {Number} negative while the discount applies, otherwise 0 */
        getPureValue: function () {
            var segment = totals.getSegment(this.code);

            return segment ? parseFloat(segment.value) || 0 : 0;
        },

        getTitle: function () {
            var segment = totals.getSegment(this.code);

            return segment && segment.title ? segment.title : this.title;
        },

        getValue: function () {
            return this.getFormattedPrice(this.getPureValue());
        },

        isDisplayed: function () {
            return this.isFullMode() && this.getPureValue() !== 0;
        }
    });
});
