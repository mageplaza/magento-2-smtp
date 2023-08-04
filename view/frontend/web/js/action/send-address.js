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

define([
    'mage/storage',
    'Mageplaza_Smtp/js/model/resource-url-manager',
    'Magento_Checkout/js/model/quote'
], function (storage, resourceUrlManager, quote) {
    'use strict';

    return function (address, isOsc) {
        return storage.post(
            resourceUrlManager.getUrlForUpdateOrder(quote),
            JSON.stringify({
                address: address,
                isOsc: isOsc
            }),
            false
        );
    };
});
