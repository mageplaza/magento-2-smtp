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
    'jquery',
    'underscore',
    'mage/translate'
], function ($, _, $t) {
    "use strict";

    return {
        options: {},
        currentResult: {},
        totalSync: 0,

        /**
         * @param classCss
         * @param message
         */
        showMessage: function (classCss, message) {
            var messageElement = this.getElement(".message");

            messageElement.removeClass('message-error message-success message-notice');
            this.getElement(".message-text strong").text(message);
            messageElement.addClass(classCss).show();
        },

        /**
         * @param value
         * @returns {*|n.fn.init|r.fn.init|jQuery.fn.init|jQuery|HTMLElement}
         */
        getElement: function (value) {
            return $(this.options.prefix + ' ' + value);
        },

        /**
         * @param start
         */
        syncData: function (start) {
            var end          = start + 100;
            var ids          = this.currentResult.ids.slice(start, end);
            var self         = this;
            var percent, percentText;
            var created_from = $('#datepicker-from').val(),
                created_to   = $('#datepicker-to').val(),
                days_range   = $('#email_marketing_general_synchronization_days_range').val();

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'post',
                dataType: 'json',
                data: {
                    ids: ids,
                    from: created_from,
                    to: created_to,
                    days_range: days_range
                },
                success: function (result) {
                    var inputLog = self.getElement('#mp-log-data').val();

                    inputLog += JSON.stringify(result.log) + '|';

                    if (result.status) {
                        percent = ids.length / self.currentResult.total * 100;

                        self.totalSync += result.total;
                        percent = percent.toFixed(2);

                        self.currentResult.percent += parseFloat(percent);
                        if (self.currentResult.percent > 100) {
                            self.currentResult.percent = 100;
                        }
                        self.getElement('#mp-log-data').val(inputLog);
                        self.getElement('#mp-console-log').val(self.formatLog(result.log, self));
                        percentText = self.currentResult.percent.toFixed(2) + '%';
                        if (percentText === '100.00%' || self.totalSync === self.currentResult.total) {
                            percentText = '100%';
                            $(self.options.buttonElement).removeClass('disabled');
                        }

                        self.getElement('.progress-bar').css('width', percentText);
                        self.getElement('#sync-percent').text(
                            percentText + ' (' + self.totalSync + '/' + self.currentResult.total + ')'
                        );

                        if (end < self.currentResult.total) {
                            self.syncData(end);
                        } else {
                            self.getElement('#syncing').hide();
                            self.showMessage('message-success', self.options.successMessage);
                        }
                    } else {
                        self.getElement('#mp-console-log').val(self.formatLog(result.log, self));
                        self.getElement('#mp-log-data').val(inputLog);
                        self.showMessage('message-error', result.message);
                        $(self.options.buttonElement).removeClass('disabled');
                    }
                }
            });
        },

        formatLog: function (log, self) {
            var rs = self.getElement('#mp-console-log').val();

            rs += log.message + '\n';

            _.each(log.data, function (item, index) {
                if (index === 'success') {
                    rs += ($t('Success: ') + item + '\n')
                }

                if (index === 'error') {
                    rs += ($t('Error: ') + item + '\n')
                }

                if (index === 'error_details') {
                    _.each(item, function (detail) {
                        rs += ($t('Item ID: ' + detail.id + '\n'))
                        rs += ($t('Error: ' + detail.message + '\n\n'))
                    })
                }
            });

            return rs;
        },

        /**
         * @param options
         */
        process: function (options) {
            var self              = this;
            options.buttonElement = '#email_marketing_general_synchronization button';
            this.options          = options;
            var created_from      = $('#datepicker-from').val(),
                created_to        = $('#datepicker-to').val(),
                days_range        = $('#email_marketing_general_synchronization_days_range').val();


            this.currentResult = {};
            $.ajax({
                url: this.options.estimateUrl,
                data: {
                    websiteId: this.options.websiteId,
                    storeId: this.options.storeId,
                    from: created_from,
                    to: created_to,
                    days_range: days_range
                },
                dataType: 'json',
                showLoader: true,
                success: function (result) {
                    window.onbeforeunload = (e) => {
                        e.preventDefault();
                        e.returnValue = $t('Changes you made may not be saved.');
                    };

                    if (result.status) {
                        self.currentResult = result;
                        self.getElement('.message').hide();
                        if (self.currentResult.total > 0) {
                            self.getElement('#console-log').show();
                        }
                        self.getElement('#mp-console-log').val('');
                        self.getElement('#mp-log-data').val('');

                        if (self.currentResult.total > 0) {
                            self.getElement('#sync-percent').text('0%');
                            self.getElement('.progress-bar').removeAttr('style');
                            self.currentResult.percent = 0;
                            self.getElement('#progress-content').show();
                            self.totalSync = 0;
                            self.getElement('#syncing').show();
                            $(self.options.buttonElement).addClass('disabled');
                            self.syncData(0);

                        } else {
                            self.showMessage('message-notice', result.message);
                            $(self.options.buttonElement).removeClass('disabled');
                            self.getElement('#progress-content').hide();
                        }

                    } else {
                        self.showMessage('message-error', result.message);
                        $(self.options.buttonElement).removeClass('disabled');
                        self.getElement('#progress-content').hide();
                    }
                }
            });
        },

        saveLog: function (console) {
            var self    = this;
            var log     = $(console).val();
            var content = 'status,message,success,error,detail' + '\n';
            var arrLog  = log.split('|');

            _.each(arrLog, function (item) {
                if (item) {
                    var data   = JSON.parse(item),
                        detail = '';

                    content += data.success + ',' + data.message + ',' + data.data.success + ',' + data.data.error + ',';
                    _.each(data.data.error_details, function (error) {
                        if (error) {
                            detail += JSON.stringify(error) + '\n';
                        }
                    })
                    var newDetail = detail.replace(',', ';');
                    newDetail     = newDetail.replace(/['"]+/g, '');
                    content += '"' + newDetail + '"' + '\n';
                }
            });

            var hiddenElement      = document.createElement('a');
            hiddenElement.href     = 'data:text/csv;charset=utf-8,' + encodeURI(content);
            hiddenElement.target   = '_blank';
            hiddenElement.download = 'mp-console-log.csv';
            hiddenElement.click();
        }
    };
});
