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

namespace Mageplaza\Smtp\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\Store;
use Magento\Framework\App\Area;
use Magento\Framework\DataObject;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;


/**
 * Class Log
 * @package Mageplaza\Smtp\Model
 */
class Log extends AbstractModel
{
    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $_transportBuilder;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @param StateInterface $inlineTranslation
     * @param TransportBuilder $transportBuilder
     * @param ManagerInterface $messageManager
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        StateInterface $inlineTranslation,
        TransportBuilder $transportBuilder,
        ManagerInterface $messageManager,
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->_init('Mageplaza\Smtp\Model\ResourceModel\Log');
        $this->inlineTranslation = $inlineTranslation;
        $this->_transportBuilder = $transportBuilder;
        $this->messageManager = $messageManager;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Save email logs
     *
     * @param $message
     * @param $status
     */
    public function saveLog($message, $status)
    {
        if ($message) {
            $headers = $message->getHeaders();

            if (isset($headers['Subject']) && isset($headers['Subject'][0])) {
                $this->setSubject($headers['Subject'][0]);
            }

            if (isset($headers['From']) && isset($headers['From'][0])) {
                $this->setFrom($headers['From'][0]);
            }

            if (isset($headers['To'])) {
                $recipient = $headers['To'];
                if (isset($recipient['append'])) {
                    unset($recipient['append']);
                }
                $this->setTo(implode(', ', $recipient));
            }

            if (isset($headers['Cc'])) {
                $cc = $headers['Cc'];
                if (isset($cc['append'])) {
                    unset($cc['append']);
                }
                $this->setCc(implode(', ', $cc));
            }

            if (isset($headers['Bcc'])) {
                $bcc = $headers['Bcc'];
                if (isset($bcc['append'])) {
                    unset($bcc['append']);
                }
                $this->setBcc(implode(', ', $bcc));
            }

            $body = $message->getBodyHtml();
            if (is_object($body)) {
                $content = htmlspecialchars($body->getRawContent());
            } else {
                $content = htmlspecialchars($message->getBody()->getRawContent());
            }

            $this->setEmailContent($content)
                ->setStatus($status)
                ->save();
        }
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
