/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://www.mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define(
    [
        'jquery',
        'uiComponent',
        'ko',
        'Magento_Customer/js/model/customer',
        'Magento_Customer/js/action/check-email-availability',
        'Magento_Customer/js/action/login',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/full-screen-loader',
        'mage/validation',
        'Mageplaza_Smtp/js/action/check-email-availability',
    ],
    function (
        $,
        Component,
        ko,
        customer,
        checkEmailAvailability,
        loginAction,
        quote,
        checkoutData,
        fullScreenLoader,
        validation,
        smtpCheckEmail
    ) {
        'use strict';

        var mixin = {
            /**
             * Check email existing.
             */
            checkEmailAvailability: function () {
                this.validateRequest();
                this.isEmailCheckComplete = $.Deferred();
                this.isLoading(true);
                this.checkRequest = checkEmailAvailability(this.isEmailCheckComplete, this.email());
                smtpCheckEmail(this.email());

                $.when(this.isEmailCheckComplete).done(function () {
                    this.isPasswordVisible(false);
                    checkoutData.setCheckedEmailValue('');
                }.bind(this)).fail(function () {
                    this.isPasswordVisible(true);
                    checkoutData.setCheckedEmailValue(this.email());
                }.bind(this)).always(function () {
                    this.isLoading(false);
                }.bind(this));
            }
        };

        return function (target) {
            return target.extend(mixin);
        };
    }
);
