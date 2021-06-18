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

namespace Mageplaza\Smtp\Block;

use Magento\Catalog\Block\Product\Context;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Magento\Framework\Registry;

/**
 * Class Script
 * @package Mageplaza\Smtp\Block
 */
class Script extends Template
{
    /**
     * @var EmailMarketing
     */
    protected $helperEmailMarketing;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Script constructor.
     *
     * @param Context $context
     * @param EmailMarketing $helperEmailMarketing
     * @param Session $checkoutSession
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        EmailMarketing $helperEmailMarketing,
        Session $checkoutSession,
        Registry $registry,
        array $data = []
    ) {
        $this->helperEmailMarketing = $helperEmailMarketing;
        $this->checkoutSession      = $checkoutSession;
        $this->registry             = $registry;
        parent::__construct($context, $data);
    }

    /**
     * @return EmailMarketing
     */
    public function getHelperEmailMarketing()
    {
        return $this->helperEmailMarketing;
    }

    /**
     * @return bool
     */
    public function isSuccessPage()
    {
        $fullActionName = $this->getRequest()->getFullActionName();
        $pages          = ['checkout_onepage_success', 'mpthankyoupage_index_index'];

        return in_array($fullActionName, $pages);
    }

    /**
     * @return Order
     */
    public function getCurrentOrder()
    {
        return $this->checkoutSession->getLastRealOrder();
    }

    /**
     * @return string
     */
    public function toHtml()
    {
        if (!$this->helperEmailMarketing->isEnableEmailMarketing()) {
            return '';
        }

        return parent::toHtml();
    }

    /**
     * @return array|false
     * @throws NoSuchEntityException
     */
    public function productAbandoned()
    {
        if ($this->getRequest()->getFullActionName() === 'catalog_product_view') {
            $product  = $this->registry->registry('current_product');
            $imageUrl = $this->_storeManager->getStore()
                    ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $product->getImage();

            return [
                'collections' => [],
                'id'          => $product->getId(),
                'image'       => $imageUrl,
                'price'       => $product->getFinalPrice(),
                'productType' => $product->getTypeId(),
                'tags'        => [],
                'title'       => $product->getName(),
                'url'         => $product->getProductUrl(),
                'vendor'      => 'magento'
            ];
        }

        return false;
    }
}
