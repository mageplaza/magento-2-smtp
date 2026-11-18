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
declare(strict_types=1);

namespace Mageplaza\Smtp\Test\Unit\Observer\Customer;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\TestFramework\Unit\Helper\MockCreationTrait;
use Mageplaza\Smtp\Helper\EmailMarketing;
use Mageplaza\Smtp\Observer\Customer\ModelSaveBefore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModelSaveBefore::class)]
class ModelSaveBeforeTest extends TestCase
{
    use MockCreationTrait;

    private CustomerFactory&MockObject $customerFactory;
    private EmailMarketing&MockObject $helper;
    private ModelSaveBefore $subject;

    private \Magento\Customer\Model\ResourceModel\Customer&MockObject $customerResource;

    protected function setUp(): void
    {
        $this->customerResource = $this->createMock(\Magento\Customer\Model\ResourceModel\Customer::class);
        $this->customerFactory = $this->createPartialMock(CustomerFactory::class, ['create']);
        $this->helper = $this->createMock(EmailMarketing::class);

        $this->subject = new ModelSaveBefore($this->customerFactory, $this->helper, $this->customerResource);
    }

    private function enableGate(): void
    {
        $this->helper->method('isEnableEmailMarketing')->willReturn(true);
        $this->helper->method('getSecretKey')->willReturn('secret');
        $this->helper->method('getAppID')->willReturn('app');
    }

    private function observerWithDataObject(DataObject $dataObject): Observer&MockObject
    {
        $event = new Event(['data_object' => $dataObject]);
        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    public function testExecuteDoesNothingWhenMarketingDisabled(): void
    {
        $this->helper->method('isEnableEmailMarketing')->willReturn(false);
        $this->customerFactory->expects($this->never())->method('create');

        $dataObject = new DataObject();
        $this->subject->execute($this->observerWithDataObject($dataObject));

        $this->assertNull($dataObject->getData('is_new_record'));
    }

    public function testExecuteMarksNewRecordWhenIdMissing(): void
    {
        $this->enableGate();
        $this->customerFactory->expects($this->never())->method('create');

        $dataObject = new DataObject();
        $this->subject->execute($this->observerWithDataObject($dataObject));

        $this->assertTrue($dataObject->getData('is_new_record'));
    }

    public function testExecuteLoadsOriginalCustomerWhenIdPresentAndIsCustomerInstance(): void
    {
        $this->enableGate();

        // getId() declared (AbstractModel), setCustomOrigObject() magic (__call -> setData) — one flat list.
        $customer = $this->createPartialMockWithReflection(Customer::class, ['getId', 'setCustomOrigObject']);
        $customer->method('getId')->willReturn(5);

        $loadedCustomer = $this->createMock(Customer::class);
        $this->customerResource->expects($this->once())->method('load')->with($loadedCustomer, 5);
        $this->customerFactory->expects($this->once())->method('create')->willReturn($loadedCustomer);

        $customer->expects($this->once())->method('setCustomOrigObject')->with($loadedCustomer);

        $this->subject->execute($this->observerWithDataObject($customer));
    }

    public function testExecuteDoesNothingWhenIdPresentButNotCustomerInstance(): void
    {
        $this->enableGate();
        $this->customerFactory->expects($this->never())->method('create');

        $dataObject = new DataObject(['id' => 5]);
        $this->subject->execute($this->observerWithDataObject($dataObject));

        $this->assertNull($dataObject->getData('is_new_record'));
        $this->assertNull($dataObject->getData('custom_orig_object'));
    }
}
