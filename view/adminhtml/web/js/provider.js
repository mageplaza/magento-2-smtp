/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
 * available through the world-wide-web at this URL:
 * https://mageplaza.com/LICENSE.txt
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Mageplaza
 * @package     Mageplaza_Smtp
 * @copyright   Copyright (c) 2017 Mageplaza (https://www.mageplaza.com/)
 * @license     http://mageplaza.com/LICENSE.txt
 */
define([
    "jquery",
    "jquery/ui"
], function ($) {
    "use strict";

    $.widget('mageplaza.smtpProvider', {
        options: {
            jsonDataInfo: {}
        },

        ids: {
            hostElm: '#smtp_configuration_option_host',
            portElm: '#smtp_configuration_option_port',
            protocolElm: '#smtp_configuration_option_protocol',
            authenticationElm: '#smtp_configuration_option_authentication'
        },

        _create: function () {
            var self = this,
                elem = self.element.next();

            elem.click(function (e) {
                e.preventDefault();
                self._autoFill();
            });
        },

        _autoFill: function () {
            var dataInfo = this.options.jsonDataInfo,
                value = parseInt(this.element.val());

            if (value) {
                var data = dataInfo[value];
                if (data) {
                    $(this.ids.hostElm).val(data.host);
                    $(this.ids.protocolElm).val(data.protocol);
                    $(this.ids.portElm).val(data.port);
                    $(this.ids.authenticationElm).val('login');
                }
            }
        }
    });

    return $.mageplaza.smtpProvider;
});
