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
    'jquery',
    'Magento_Ui/js/modal/modal',
    'Magento_Ui/js/modal/confirm'
], function (Column, $) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'Mageplaza_Smtp/grid/cells/view',
            fieldClass: {
                'data-grid-thumbnail-cell': true
            }
        },
        modal: {},
        preview: function (row) {
            if(event.target.className == 'action-menu-item mpview') {
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
            } else if(event.target.className == 'action-menu-item mpresend'){
                require([
                    'Magento_Ui/js/modal/confirm'
                ], function(confirmation) {

                    confirmation({
                        title: row.view.resend.confirm.title,
                        content: row.view.resend.confirm.message,
                        actions: {
                            confirm: function(){
                                window.location.href = row.view.resend.href;
                            },
                            cancel: function(){},
                            always: function(){}
                        }
                    });
                });
            } else if(event.target.className == 'action-menu-item mpdelete'){
                require([
                    'Magento_Ui/js/modal/confirm'
                ], function(confirmation) {

                    confirmation({
                        title: row.view.delete.confirm.title,
                        content: row.view.delete.confirm.message,
                        actions: {
                            confirm: function(){
                                window.location.href = row.view.delete.href;
                            },
                            cancel: function(){},
                            always: function(){}
                        }
                    });
                });
            }
        }
    });
});

