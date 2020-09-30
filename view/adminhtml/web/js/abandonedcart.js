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
 * @package     Mageplaza_SMTP
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */
define([
    'jquery',
    'Magento_Ui/js/modal/modal'
], function ($, modal) {
    "use strict";

    $.widget('mageplaza.abandonedcarts', {

        _create: function () {
            this.initObserve();
        },

        /**
         * Init observe
         */
        initObserve: function () {
            var self = this,
                popupSendEmailElement = $('#popup-send-email'),
                copyElement = $('#copy');

            $("#send").click(function () {
                $('#popup-send-email-details').show();
                $('#popup-send-email-preview').show();
                $('#preview').hide();
                $('#popup-send-email-back').hide();

                modal({
                    type: 'popup',
                    responsive: true,
                    innerScroll: true,
                    title: '',
                    buttons: []
                }, popupSendEmailElement);

                popupSendEmailElement.modal('openModal');
            });

            $('#popup-send-email-preview').click(function () {
                self.preview();
            });

            $('#popup-send-email form').submit(function(){
                $(this).find(':submit').attr('disabled','disabled');
            });


            $('#popup-send-email-back').click(function () {
                $('#popup-send-email-details').show();
                $('#popup-send-email-preview').show();
                $('#preview').hide();
                this.hide();
            });

            copyElement.click(function () {
               self.copyToClipboard();
               $('#link-tooltip').text(self.options.copied_message);

            });

            copyElement.mouseout(function () {
                $('#link-tooltip').text(self.options.tooltip);
            })

        },
        copyToClipboard: function(){
            var temp = $('<input>');

            $('body').append(temp);
            temp.val($('#recovery_link > span').text()).select();
            document.execCommand('copy');
            temp.remove();
        },

        /**
         * @param type
         * @param message
         * @returns {string}
         */
        getMessageHtml: function (type, message) {
            return '<div class="message message-' + type + '"> <span>' + message + '</span> </div>';
        },
        getParams: function () {
            return {
                from: $('#sender').val(),
                quote_id: this.options.quote_id,
                template_id: $('#email-template').val(),
                customer_name: this.options.customer_name,
                additional_message: $('#additional-message').val()
            }
        },
        preview: function () {
            var self = this;

            $.ajax({
                url: this.options.preview_url,
                data: this.getParams(),
                dataType: 'json',
                showLoader: true,
                success: function (result) {
                    if (result.status) {
                        var dstFrame = document.getElementById('iframe-preview'),
                            dstDoc   = dstFrame.contentDocument || dstFrame.contentWindow.document;

                        dstDoc.write(result.content);
                        dstDoc.close();
                        $('#popup-send-email-details').hide();
                        $('#popup-send-email-back').show();
                        $('#preview').show();
                        $('#popup-send-email-preview').hide();
                        $('#subject strong').text(result.subject);
                        $('#preview-from').text(result.from.email);
                    } else {
                        $('#popup-send-email #messages').html(self.getMessageHtml('error error', result.message));
                    }
                }
            });
        }
    });

    return $.mageplaza.abandonedcarts;
});
