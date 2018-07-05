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
    "jquery",
    "mage/translate",
    "jquery/ui"
], function ($, $t) {
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

            this.hostNoteEl = $(this.ids.hostElm).next('p.note').find('span');
            this.note = this.hostNoteEl.html();

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

                    if(data.host.indexOf('amazonaws') !== -1){
                        this.hostNoteEl.html($t('Please change this host name to suit your location'));
                    } else {
                        this.hostNoteEl.html(this.note);
                    }
                }
            }
        }
    });

    return $.mageplaza.smtpProvider;
});
