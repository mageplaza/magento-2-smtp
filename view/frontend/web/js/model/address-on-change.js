/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */
define([
    'jquery',
    'ko',
    'mage/translate',
    'uiRegistry',
    'Magento_Checkout/js/model/quote',
    'Mageplaza_Smtp/js/action/send-address'
], function (
    $,
    ko,
    $t,
    uiRegistry,
    quote,
    sendAddress
) {
    'use strict';

    var elements         = ['firstname', 'lastname', 'company', 'street', 'country_id', 'region_id', 'city', 'postcode', 'telephone'],
        observedElements = [];

    return {
        validateAddressTimeout: 0,
        validateDelay: 1000,

        /**
         * Perform postponed binding for fieldset elements
         *
         * @param {String} formPath
         */
        initFields: function (formPath) {
            var self = this;

            $.each(elements, function (index, field) {
                uiRegistry.async(formPath + '.' + field)(self.smtpBindHandler.bind(self));
            });
        },

        /**
         * @param {Object} element
         * @param {Number} delay
         */
        smtpBindHandler: function (element, delay) {
            var self = this;

            delay = typeof delay === 'undefined' ? self.validateDelay : delay;

            if (element.component.indexOf('/group') !== -1) {
                $.each(element.elems(), function (index, elem) {
                    uiRegistry.async(elem.name)(function () {
                        self.smtpBindHandler(elem);
                    });
                });
            } else if (element && element.hasOwnProperty('value')) {
                element.on('value', function () {
                    clearTimeout(self.validateAddressTimeout);
                    self.validateAddressTimeout = setTimeout(function () {
                        sendAddress(JSON.stringify(self.collectObservedData()), self.isOsc());
                    }, delay);
                });

                observedElements.push(element);
            }
        },
        /**
         * Collect observed fields data to object
         *
         * @returns {*}
         */
        collectObservedData: function () {
            var observedValues = {};

            $.each(observedElements, function (index, field) {
                var value = field.value();

                if ($.type(value) === 'undefined') {
                    value = '';
                }
                observedValues[field.dataScope] = value;
            });

            return observedValues;
        },
        isOsc: function () {
            return !!window.checkoutConfig.oscConfig;
        }
    };
});
