<?php
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

namespace Mageplaza\Smtp\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Phrase;

/**
 * Class Host
 * @package Mageplaza\Smtp\Block\Adminhtml\System\Config
 */
class Host extends Field
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
        $this->setTemplate('Mageplaza_Smtp::system/config/host.phtml');
    }

    /**
     * Get the button
     *
     * @param AbstractElement $element
     *
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

        return $element->getElementHtml() . $this->_toHtml();
    }

    /**
     * @param $key
     *
     * @return Phrase
     */
    public function getLabel($key)
    {
        switch ($key) {
            case 'host':
                return __('Server Name (host)');
            case 'port':
                return __('Port');
            case 'protocol':
                return __('Protocol');
            case 'tls':
                return __('Transport Layer Security (TLS)');
            case 'ssl':
                return __('Secure Sockets Layer (SSL)');
            case '':
                return __('None');
            default:
                return $key;
        }
    }

    /**
     * Get list of all host
     *
     * $options = [
     *      'id' => [
     *          'label' => __('Label'),
     *          'info'  => [
     *              'host' => 'smtp.provider.com',
     *              'port' => '465',
     *              'protocol' => 'ssl'
     *          ],
     *          'note'  => [
     *              'global'     => __('Global note'), //This note will be show above the 'Load Setting' button
     *              'host'       => __('Host note'), //This note will be show under Host field
     *              'port'       => __('Port note'), //This note will be show under Port field
     *              'protocol'   => __('Protocol note'), //This note will be show under Protocol field
     *          ]
     *      ],
     *      'id1' => [
     *          'label' => __('Label1'),
     *          'info'  => [
     *              'area1' => [
     *                  'label' => __('Area Label'),
     *                  'info'  => [
     *                      'host' => 'smtp.provider.com',
     *                      'port' => '465',
     *                      'protocol' => 'ssl'
     *                  ],
     *                  'note'  => [
     *                      'global'     => __('Global note'), //This note will be show above the 'Load Setting' button
     *                      'host'       => __('Host note'), //This note will be show under Host field
     *                      'port'       => __('Port note'), //This note will be show under Port field
     *                      'protocol'   => __('Protocol note'), //This note will be show under Protocol field
     *                  ]
     *              ]
     *          ]
     *      ]
     * ]
     *
     * @return array
     */
    public function getOptionProvider()
    {
        $options = [
            'gmail'       => [
                'label' => __('Gmail, GSuite'),
                'info'  => [
                    'host'     => 'smtp.gmail.com',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'amazon'      => [
                'label' => __('Amazon SES'),
                'info'  => [
                    'us-east-virginia' => [
                        'label' => __('US East (N. Virginia)'),
                        'info'  => [
                            'host'     => 'email-smtp.us-east-1.amazonaws.com',
                            'port'     => '587',
                            'protocol' => 'tls'
                        ]
                    ],
                    'us-east-oregon'   => [
                        'label' => __('US West (Oregon)'),
                        'info'  => [
                            'host'     => 'email-smtp.us-west-2.amazonaws.com',
                            'port'     => '587',
                            'protocol' => 'tls'
                        ]
                    ],
                    'eu-ireland'       => [
                        'label' => __('EU (Ireland)'),
                        'info'  => [
                            'host'     => 'email-smtp.eu-west-1.amazonaws.com',
                            'port'     => '587',
                            'protocol' => 'tls'
                        ]
                    ]
                ]
            ],
            'mailgun'     => [
                'label' => __('Mailgun'),
                'info'  => [
                    'host'     => 'smtp.mailgun.org',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'migomail'    => [
                'label' => __('Migomail'),
                'info'  => [
                    'host'     => 'sn1.migomta.one',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'mandrill'    => [
                'label' => __('Mandrill'),
                'info'  => [
                    'host'     => 'smtp.mandrillapp.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'sendinblue'  => [
                'label' => __('Sendinblue'),
                'info'  => [
                    'host'     => 'smtp-relay.sendinblue.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'sendgrid'    => [
                'label' => __('Sendgrid'),
                'info'  => [
                    'host'     => 'smtp.sendgrid.net',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'elastic'     => [
                'label' => __('Elastic Email'),
                'info'  => [
                    'host'     => 'smtp.elasticemail.com',
                    'port'     => '2525',
                    'protocol' => ''
                ]
            ],
            'sparkpost'   => [
                'label' => __('SparkPost'),
                'info'  => [
                    'host'     => 'smtp.sparkpostmail.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'mailjet'     => [
                'label' => __('Mailjet'),
                'info'  => [
                    'host'     => 'in-v3.mailjet.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'postmark'    => [
                'label' => __('Postmark'),
                'info'  => [
                    'host'     => 'smtp.postmarkapp.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'aol'         => [
                'label' => __('AOL Mail'),
                'info'  => [
                    'host'     => 'smtp.aol.com',
                    'port'     => '587',
                    'protocol' => ''
                ]
            ],
            'comcast'     => [
                'label' => __('Comcast'),
                'info'  => [
                    'host'     => 'smtp.comcast.net',
                    'port'     => '587',
                    'protocol' => ''
                ]
            ],
            'gmx'         => [
                'label' => __('GMX'),
                'info'  => [
                    'host'     => 'mail.gmx.net',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'hotmail'     => [
                'label' => __('Hotmail'),
                'info'  => [
                    'host'     => 'smtp-mail.outlook.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'mailcom'     => [
                'label' => __('Mail.com'),
                'info'  => [
                    'host'     => 'smtp.mail.com',
                    'port'     => '587',
                    'protocol' => ''
                ]
            ],
            '02mail'      => [
                'label' => __('O2 Mail'),
                'info'  => [
                    'host'     => 'smtp.o2.ie',
                    'port'     => '25',
                    'protocol' => ''
                ]
            ],
            'office365'   => [
                'label' => __('Office365'),
                'info'  => [
                    'host'     => 'smtp.office365.com',
                    'port'     => '587',
                    'protocol' => ''
                ]
            ],
            'orange'      => [
                'label' => __('Orange'),
                'info'  => [
                    'host'     => 'smtp.orange.net',
                    'port'     => '25',
                    'protocol' => ''
                ]
            ],
            'outlook'     => [
                'label' => __('Outlook'),
                'info'  => [
                    'host'     => 'smtp-mail.outlook.com',
                    'port'     => '587',
                    'protocol' => 'tls'
                ]
            ],
            'yahoo'       => [
                'label' => __('Yahoo Mail'),
                'info'  => [
                    'host'     => 'smtp.mail.yahoo.com',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'yahooplus'   => [
                'label' => __('Yahoo Mail Plus'),
                'info'  => [
                    'host'     => 'plus.smtp.mail.yahoo.com',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'yahooau'     => [
                'label' => __('Yahoo AU/NZ'),
                'info'  => [
                    'host'     => 'smtp.mail.yahoo.com.au',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'at&t'        => [
                'label' => __('AT&T'),
                'info'  => [
                    'host'     => 'smtp.att.yahoo.com',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'ntlworld'    => [
                'label' => __('NTL @ntlworld.com'),
                'info'  => [
                    'host'     => 'smtp.ntlworld.com',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'btconnect'   => [
                'label' => __('BT Connect'),
                'info'  => [
                    'host'     => 'pop3.btconnect.com',
                    'port'     => '25',
                    'protocol' => ''
                ]
            ],
            'zoho'        => [
                'label' => __('Zoho Mail'),
                'info'  => [
                    'host'     => 'smtp.zoho.com',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'verizon'     => [
                'label' => __('Verizon'),
                'info'  => [
                    'host'     => 'outgoing.verizon.net',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'btopenworld' => [
                'label' => __('BT Openworld'),
                'info'  => [
                    'host'     => 'mail.btopenworld.com',
                    'port'     => '25',
                    'protocol' => ''
                ]
            ],
            'o2online'    => [
                'label' => __('O2 Online Deutschland'),
                'info'  => [
                    'host'     => 'mail.o2online.de',
                    'port'     => '25',
                    'protocol' => ''
                ]
            ],
            '1&1webmail'  => [
                'label' => __('1&1 Webmail'),
                'info'  => [
                    'host'     => 'smtp.1and1.com',
                    'port'     => '587',
                    'protocol' => ''
                ]
            ],
            'ovh'         => [
                'label' => __('OVH'),
                'info'  => [
                    'host'     => 'ssl0.ovh.net',
                    'port'     => '465',
                    'protocol' => 'ssl'
                ]
            ],
            'smtp2go'     => [
                'label' => __('SMTP2GO'),
                'info'  => [
                    'host'     => 'mail.smtp2go.com',
                    'port'     => '2525',
                    'protocol' => 'tls'
                ]
            ]
        ];

        return $options;
    }
}
