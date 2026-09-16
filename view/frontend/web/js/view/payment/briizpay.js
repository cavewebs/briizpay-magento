define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'briizpay',
        component: 'BriizPay_PayByBank/js/view/payment/method-renderer/briizpay-method'
    });

    return Component.extend({});
});
