<?php
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

namespace Mageplaza\Smtp\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Class Provider
 * @package Mageplaza\Smtp\Block\Adminhtml\System\Config
 */
class Provider extends Field
{
    /**
     * @var string
     */
    protected $_buttonLabel = '';

    /**
     * Set template
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Mageplaza_Smtp::system/config/provider.phtml');
    }

    /**
     * Get the button
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $buttonLabel  = !empty($originalData['button_label']) ? $originalData['button_label'] : $this->_buttonLabel;
        $this->addData(
            [
                'button_label' => __($buttonLabel),
                'html_id'      => $element->getHtmlId(),
                'provider'     => $this->getOptionProvider(),
                'data_info'    => json_encode($this->getOptionProvider())
            ]
        );

        return $this->_toHtml();
    }

    /**
     * Get list of all host
     *
     * @return array
     */
    private function getOptionProvider()
    {
        $options = [
            [
                'label' => __('- Choose a SMTP Provider -'),
                'host'  => ''
            ],
            [
                'label'    => __('Gmail, GSuite'),
                'host'     => 'smtp.gmail.com',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('Mailgun'),
                'host'     => 'smtp.mailgun.org',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Mandrill'),
                'host'     => 'smtp.mandrillapp.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Sendinblue'),
                'host'     => 'smtp-relay.sendinblue.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Sendgrid'),
                'host'     => 'smtp.sendgrid.net',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Elastic Email'),
                'host'     => 'smtp.elasticemail.com',
                'port'     => '2525',
                'protocol' => ''
            ],
            [
                'label'    => __('SparkPost'),
                'host'     => 'smtp.sparkpostmail.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Mailjet'),
                'host'     => 'in-v3.mailjet.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Postmark'),
                'host'     => 'smtp.postmarkapp.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('AOL Mail'),
                'host'     => 'smtp.aol.com',
                'port'     => '587',
                'protocol' => ''
            ],
            [
                'label'    => __('Comcast'),
                'host'     => 'smtp.comcast.net',
                'port'     => '587',
                'protocol' => ''
            ],
            [
                'label'    => __('GMX'),
                'host'     => 'mail.gmx.net',
                'port'     => '587',
                'protocol' => 'tls'
            ],

            [
                'label'    => __('Hotmail'),
                'host'     => 'smtp-mail.outlook.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Mail.com'),
                'host'     => 'smtp.mail.com',
                'port'     => '587',
                'protocol' => ''
            ],
            [
                'label'    => __('O2 Mail'),
                'host'     => 'smtp.o2.ie',
                'port'     => '25',
                'protocol' => ''
            ],
            [
                'label'    => __('Office365'),
                'host'     => 'smtp.office365.com',
                'port'     => '587',
                'protocol' => ''
            ],
            [
                'label'    => __('Orange'),
                'host'     => 'smtp.orange.net',
                'port'     => '25',
                'protocol' => ''
            ],
            [
                'label'    => __('Outlook'),
                'host'     => 'smtp-mail.outlook.com',
                'port'     => '587',
                'protocol' => 'tls'
            ],
            [
                'label'    => __('Yahoo Mail'),
                'host'     => 'smtp.mail.yahoo.com',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('Yahoo Mail Plus'),
                'host'     => 'plus.smtp.mail.yahoo.com',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('Yahoo AU/NZ'),
                'host'     => 'smtp.mail.yahoo.com.au',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('AT&T'),
                'host'     => 'smtp.att.yahoo.com',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('NTL @ntlworld.com'),
                'host'     => 'smtp.ntlworld.com',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('BT Connect'),
                'host'     => 'pop3.btconnect.com',
                'port'     => '25',
                'protocol' => ''
            ],
            [
                'label'    => __('Zoho Mail'),
                'host'     => 'smtp.zoho.com',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('Verizon'),
                'host'     => 'outgoing.verizon.net',
                'port'     => '465',
                'protocol' => 'ssl'
            ],
            [
                'label'    => __('BT Openworld'),
                'host'     => 'mail.btopenworld.com',
                'port'     => '25',
                'protocol' => ''
            ],
            [
                'label'    => __('O2 Online Deutschland'),
                'host'     => 'mail.o2online.de',
                'port'     => '25',
                'protocol' => ''
            ],
        ];

        return $options;
    }
}
