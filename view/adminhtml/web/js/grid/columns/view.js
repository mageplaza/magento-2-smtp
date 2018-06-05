/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Mageplaza.com license sliderConfig is
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
 * @copyright   Copyright (c) 2017-2018 Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'Magento_Ui/js/grid/columns/thumbnail',
    'ko',
    'jquery',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/confirm'
], function (Column, ko, $, modal, confirmation) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Mageplaza_Smtp/grid/cells/view',
            fieldClass: {
                'data-grid-thumbnail-cell': true
            }
        },
        modal: {},
        getAction: function (row) {
            var data = [];
            $.each(row.view, function (index, value) {
                data.push({label: value.label, class: value.class});
            });
            debugger;
            return data;
        },
        preview: function (row) {
            if (event.target.className == 'action-menu-item mpview') {
                var emailId = row.id;
                if (typeof this.modal[emailId] === 'undefined') {
                    var modalHtml = '<iframe srcdoc="' + row['email_content'] + '" style="width: 100%; height: 100%"></iframe>';
                    this.modal[emailId] = $('<div/>')
                        .html(modalHtml)
                        .modal({
                            type: 'slide',
                            title: row['subject'],
                            modalClass: 'mpsmtp-modal-email',
                            innerScroll: true,
                            buttons: []
                        });
                }
                this.modal[emailId].trigger('openModal');

            } else if (event.target.className == 'action-menu-item mpresend') {
                this.confirm(row.view.resend);
            } else if (event.target.className == 'action-menu-item mpdelete') {
                this.confirm(row.view.delete);
            }
        },
        confirm: function (data) {
            confirmation({
                title: data.confirm.title,
                content: data.confirm.message,
                actions: {
                    confirm: function () {
                        window.location.href = data.href;
                    },
                    cancel: function () {
                    },
                    always: function () {
                    }
                }
            });
        }
    });
});

