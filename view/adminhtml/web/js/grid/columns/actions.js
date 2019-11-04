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
 * @copyright   Copyright (c) Mageplaza (http://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

define([
    'jquery',
    'Magento_Ui/js/grid/columns/actions',
    'Magento_Ui/js/modal/modal'
], function ($, Column) {
    'use strict';

    return Column.extend({
        modal: {},

        /**
         * @inheritDoc
         */
        defaultCallback: function (actionIndex, recordId, action) {
            if (actionIndex !== 'view') {
                return this._super();
            }

            if (typeof this.modal[action.rowIndex] === 'undefined') {
                var row = this.rows[action.rowIndex],
                    modalHtml = '<iframe srcdoc="' + row['email_content'] + '" style="width: 100%; height: 100%"></iframe>';
                this.modal[action.rowIndex] = $('<div/>')
                    .html(modalHtml)
                    .modal({
                        type: 'slide',
                        title: row['subject'],
                        modalClass: 'mpsmtp-modal-email',
                        innerScroll: true,
                        buttons: []
                    });
            }

            this.modal[action.rowIndex].trigger('openModal');
        }
    });
});

