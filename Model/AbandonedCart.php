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

use Exception;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;

/**
 * Class AbandonedCart
 * @package Mageplaza\Smtp\Model
 */
class AbandonedCart extends AbstractModel
{
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var Quote
     */
    protected $quote;

    /**
     * Group service
     *
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * AbandonedCart constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param QuoteFactory $quoteFactory
     * @param GroupRepositoryInterface $groupRepository
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        QuoteFactory $quoteFactory,
        GroupRepositoryInterface $groupRepository,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->quoteFactory    = $quoteFactory;
        $this->groupRepository = $groupRepository;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @return void
     */
    public function _construct()
    {
        $this->_init(ResourceModel\AbandonedCart::class);
    }

    /**
     * @return Quote
     */
    public function getQuote()
    {
        if (!$this->quote) {
            $quoteId = $this->getQuoteId();

            $this->quote = $this->quoteFactory->create()->load($quoteId);
        }

        return $this->quote;
    }

    /**
     * @return string
     */
    public function getCustomerGroupName()
    {
        $customerGroupId = $this->getQuote()->getCustomerGroupId();
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
