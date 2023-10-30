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

namespace Mageplaza\Smtp\Block\Adminhtml\AbandonedCart\Edit;

use Exception;
use IntlDateFormatter;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Config\Model\Config\Source\Email\Identity;
use Magento\Config\Model\Config\Source\Email\Template;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Address\Config as AddressConfig;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item;
use Magento\Tax\Model\Config as TaxConfig;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Model\ResourceModel\Log\CollectionFactory as LogCollectionFactory;
use Mageplaza\Smtp\Model\Source\AbandonedCartStatus;

/**
 * Class Form
 * @package Mageplaza\Smtp\Block\Adminhtml\AbandonedCart\Edit
 */
class Form extends Generic
{
    /**
     * @var string
     */
    protected $_template = 'Mageplaza_Smtp::view.phtml';

    /**
     * @var AddressConfig
     */
    protected $addressConfig;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var CatalogHelper
     */
    protected $catalogHelper;

    /**
     * @var Identity
     */
    protected $emailIdentity;

    /**
     * @var Template
     */
    protected $emailTemplate;

    /**
     * @var TaxConfig
     */
    protected $taxConfig;

    /**
     * @var LogCollectionFactory
     */
    protected $logCollectionFactory;

    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * Group service
     *
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * Form constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param FormFactory $formFactory
     * @param AddressConfig $addressConfig
     * @param PriceCurrencyInterface $priceCurrency
     * @param Identity $emailIdentity
     * @param Template $emailTemplate
     * @param TaxConfig $taxConfig
     * @param LogCollectionFactory $logCollectionFactory
     * @param EmailMarketing $helperEmailMarketing
     * @param GroupRepositoryInterface $groupRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        AddressConfig $addressConfig,
        PriceCurrencyInterface $priceCurrency,
        Identity $emailIdentity,
        Template $emailTemplate,
        TaxConfig $taxConfig,
        LogCollectionFactory $logCollectionFactory,
        EmailMarketing $helperEmailMarketing,
        GroupRepositoryInterface $groupRepository,
        array $data = []
    ) {
        $this->addressConfig = $addressConfig;
        $this->priceCurrency = $priceCurrency;
        $this->emailIdentity = $emailIdentity;
        $this->emailTemplate = $emailTemplate;
        $this->taxConfig = $taxConfig;
        $this->logCollectionFactory = $logCollectionFactory;
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->groupRepository = $groupRepository;

        parent::__construct($context, $registry, $formFactory, $data);
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    public function displayCartSubtotalInclTax($storeId)
    {
        return $this->taxConfig->displayCartSubtotalInclTax($storeId);
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    public function displayCartSubtotalExclTax($storeId)
    {
        return $this->taxConfig->displayCartSubtotalExclTax($storeId);
    }

    /**
     * @param int $storeId
     *
     * @return bool
     */
    public function displayCartSubtotalBoth($storeId)
    {
        return $this->taxConfig->displayCartSubtotalBoth($storeId);
    }

    /**
     * @return EmailMarketing
     */
    public function getHelperEmailMarketing()
    {
        return $this->helperEmailMarketing;
    }

    /**
     * @param Quote $quote
     * @param bool $inclTax
     *
     * @return float
     */
    public function getSubtotal(Quote $quote, $inclTax = false)
    {
        $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
        $subtotal = $inclTax ? $address->getSubtotalInclTax() : $address->getSubtotal();

        return $this->formatPrice($subtotal, $quote->getId());
    }

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->_coreRegistry->registry('abandonedCart');
    }

    /**
     * @return string
     */
    public function getSenderOptions()
    {
        return $this->createOptions($this->emailIdentity->toOptionArray());
    }

    /**
     * @param string $ids
     *
     * @return string
     */
    public function getSentDateLogs($ids)
    {
        $logDatesHtml = '';
        if ($ids) {
            $collection = $this->logCollectionFactory->create()->addFieldToFilter('id', ['in' => $ids]);
            if ($collection->getSize() > 0) {
                foreach ($collection as $log) {
                    $logDatesHtml .= $this->formatDate(
                        $log->getCreatedAt(),
                        IntlDateFormatter::MEDIUM,
                        true
                    );
                    $logDatesHtml .= '</br>';
                }
            }
        }

        return $logDatesHtml;
    }

    /**
     * @param array $options
     *
     * @return string
     */
    public function createOptions(array $options)
    {
        $html = '';
        foreach ($options as $option) {
            $html .= '<option value="' . $option['value'] . '">' . $option['label'] . '</option>';
        }

        return $html;
    }

    /**
     * @return string
     */
    public function getEmailTemplateOptions()
    {
        $this->emailTemplate->setPath('mpsmtp_abandoned_cart_email_templates');

        return $this->createOptions($this->emailTemplate->toOptionArray());
    }

    /**
     * @return Quote
     */
    public function getQuote()
    {
        return $this->_coreRegistry->registry('quote');
    }

    /**
     * @param Item $item
     *
     * @return string
     * @throws LocalizedException
     */
    public function getItemUnitPriceHtml(Item $item)
    {
        return $this->getBlockHtmlByName($item, 'item_unit_price');
    }

    /**
     * @param Item $item
     *
     * @return string
     * @throws LocalizedException
     */
    public function getItemRowTotalHtml(Item $item)
    {
        return $this->getBlockHtmlByName($item, 'item_row_total');
    }

    /**
     * @param Item $item
     *
     * @return string
     * @throws LocalizedException
     */
    public function getItemRowTotalWithDiscountHtml(Item $item)
    {
        return $this->getBlockHtmlByName($item, 'item_row_total_with_discount');
    }

    /**
     * @param Item $item
     * @param string $name
     *
     * @return string
     * @throws LocalizedException
     */
    public function getBlockHtmlByName(Item $item, $name)
    {
        $block = $this->getLayout()->getBlock($name);
        $block->setItem($item);

        return $block->toHtml();
    }

    /**
     * @param int|float $value
     * @param int $store
     *
     * @return float
     */
    public function formatPrice($value, $store)
    {
        return $this->priceCurrency->format(
            $value,
            true,
            PriceCurrencyInterface::DEFAULT_PRECISION,
            $store
        );
    }

    /**
     * @param int $status
     *
     * @return string
     */
    public function getStatus($status)
    {
        return AbandonedCartStatus::getOptionArray()[$status];
    }

    /**
     * Check if is single store mode
     *
     * @return bool
     */
    public function isSingleStoreMode()
    {
        return $this->_storeManager->isSingleStoreMode();
    }

    /**
     * @param Quote $quote
     *
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStoreName(Quote $quote)
    {
        $storeId = $quote->getStoreId();
        $store = $this->_storeManager->getStore($storeId);
        $name = [$store->getWebsite()->getName(), $store->getGroup()->getName(), $store->getName()];

        return implode('<br/>', $name);
    }

    /**
     * @param Address $address
     * @param int $storeId
     *
     * @return string
     */
    public function getFormattedAddress(Address $address, $storeId)
    {
        if (!$address->getCountryId()) {
            return '';
        }

        $allowedAddressHtmlTags = ['b', 'br', 'em', 'i', 'li', 'ol', 'p', 'strong', 'sub', 'sup', 'ul'];

        return $this->escapeHtml($this->format($address, $storeId), $allowedAddressHtmlTags);
    }

    /**
     * @param Address $address
     * @param int $storeId
     * @param string $type
     *
     * @return string|null
     */
    public function format(Address $address, $storeId, $type = 'html')
    {
        $this->addressConfig->setStore($storeId);
        $formatType = $this->addressConfig->getFormatByCode($type);
        if (!$formatType || !$formatType->getRenderer()) {
            return null;
        }

        return $formatType->getRenderer()->renderArray($address->getData());
    }

    /**
     * @param string|int $customerGroupId
     *
     * @return string
     */
    public function getCustomerGroupName($customerGroupId)
    {
        if ($customerGroupId !== null) {
            try {
                return $this->groupRepository->getById($customerGroupId)->getCode();
            } catch (Exception $e) {
                return '';
            }
        }

        return '';
    }
}
