<?php
/**
 * Mageplaza
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the mageplaza.com license that is
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
 * @copyright   Copyright (c) 2017-2018 Mageplaza (https://www.mageplaza.com/)
 * @license     https://www.mageplaza.com/LICENSE.txt
 */

namespace Mageplaza\Smtp\Controller\Adminhtml\Resend;

use Magento\Store\Model\Store;
use Magento\Backend\App\Action;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Mageplaza\Smtp\Model\LogFactory;
use Magento\Backend\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class Email
 * @package Mageplaza\Smtp\Controller\Adminhtml\Resend
 */
class Email extends Action
{
    /**
     * Recipient email config path
     */
    const XML_PATH_EMAIL_RECIPIENT = 'smtp/email/send_email';
    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var LogFactory
     */
    protected $logFactory;

    /**
     * Email constructor.
     * @param Context $context
     * @param TransportBuilder $transportBuilder
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param LogFactory $logFactory
     */
    public function __construct(
        Context $context,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        LogFactory $logFactory
    ) {
        parent::__construct($context);
        $this->_transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->logFactory = $logFactory;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
        $logId = $this->getRequest()->getParam('id');

        if (!$logId) {
            $this->_redirect('*/smtp/log');
            return;
        }

        $data = $this->logFactory->create()->load($logId)->getData();
        $data['email_content'] = htmlspecialchars_decode($data['email_content']);

        $this->resendEmail($data);

        $this->messageManager->addSuccess(
            __('Email re-sent successfully!')
        );
        $this->_redirect('*/smtp/log');
        return;
    }

    /**
     * @param $data
     */
    public function resendEmail($data){
        $this->inlineTranslation->suspend();
        try {
            $dataObject = new DataObject();
            $dataObject->setData($data);

            $sender = $this->extractEmailInfo($dataObject->getFrom());

            $this->_transportBuilder
                ->setTemplateIdentifier('resend_email_template') // this code we have mentioned in the email_templates.xml
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND, // this is using frontend area to get the template file
                        'store' => Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars(['data' => $dataObject])
                ->setFrom($sender);

            /** Add receiver emails*/
            $recipient = $this->extractRecipientInfo($dataObject->getTo());
            foreach ($recipient as $rec){
                $this->_transportBuilder->addTo($rec);
            }

            /** Add cc emails*/
            if($dataObject->getCc()) {
                $ccEmails = $this->extractRecipientInfo($dataObject->getCc());
                foreach ($ccEmails as $cc) {
                    $this->_transportBuilder->addCc($cc);
                }
            }

            /** Add Bcc emails*/
            if($dataObject->getBcc()) {
                $bccEmails = $this->extractRecipientInfo($dataObject->getBcc());
                foreach ($bccEmails as $bcc) {
                    $this->_transportBuilder->addBcc($bcc);
                }
            }

            $transport = $this->_transportBuilder->getTransport();

            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->messageManager->addError(
                __('We can\'t process your request right now. '.$e->getMessage())
            );
            $this->_redirect('*/smtp/log');
            return;
        }
    }

    /**
     * @param $string
     * @return array
     */
    public function extractEmailInfo($string){
        $pattern = '/[a-z0-9_\-\+\.]+@[a-z0-9\-]+\.([a-z]{2,4})(?:\.[a-z]{2})?/i';
        preg_match_all($pattern, $string, $matches);
        $email = $matches[0];
        \Zend_Debug::dump($email);
        $nameArr = explode(" <" . $email[0], $string);
        $name = $nameArr[0];
        return ['name' => $name, 'email' => $email[0]];
    }

    /**
     * @param $emailList
     * @return array|null
     */
    public function extractRecipientInfo($emailList){
        $emailArray = explode(',', $emailList);
        $data = null;
        foreach ($emailArray as $string){
            $emailInfo = $this->extractEmailInfo($string);
            $data[] = [$emailInfo['name'] => $emailInfo['email']];
        }
        return $data;
    }
}
