<?php
// phpcs:ignoreFile
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@buckaroo.nl for more information.
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   https://tldrlegal.com/license/mit-license
 */
namespace Buckaroo\Magento2\Test\Unit\Model;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResults;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Model\Invoice;
use Buckaroo\Magento2\Model\InvoiceFactory;
use Buckaroo\Magento2\Model\InvoiceRepository;
use Buckaroo\Magento2\Model\ResourceModel\Invoice as InvoiceResource;
use Buckaroo\Magento2\Model\ResourceModel\Invoice\CollectionFactory;
use Buckaroo\Magento2\Test\BaseTest;

class InvoiceRepositoryTest extends BaseTest
{
    protected $instanceClass = InvoiceRepository::class;

    /**
     * @return array
     */
    public function saveProvider()
    {
        return [
            'successful save' => [true],
            'failed save' => [false]
        ];
    }

    /**
     * @param $canSave
     *
     * @dataProvider saveProvider
     */
    public function testSave($canSave)
    {
        $resourceMock = $this->getFakeMock(InvoiceResource::class)->setMethods(['save'])->getMock();
        $expectsSave = $resourceMock->expects($this->once())->method('save');

        if (!$canSave) {
            $expectsSave->willThrowException(new \Exception());
            $this->expectException(CouldNotSaveException::class);
        }

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(null)->getMock();

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $result = $instance->save($invoiceMock);

        $this->assertEquals($invoiceMock, $result);
    }

    /**
     * @return array
     */
    public function getByIdProvider()
    {
        return [
            'non-existing invoice' => [null],
            'existing invoice' => [123]
        ];
    }

    /**
     * @param $id
     *
     * @dataProvider getByIdProvider
     */
    public function testGetById($id)
    {
        if (!$id) {
            $this->expectException(NoSuchEntityException::class);
            $this->expectExceptionMessage(__('Invoice with id "%1" does not exist.', $id)->render());
        }

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(['load', 'getId'])->getMock();
        $invoiceMock->expects($this->once())->method('load')->willReturnSelf();
        $invoiceMock->expects($this->once())->method('getId')->willReturn($id);

        $invoiceFactoryMock = $this->getFakeMock(InvoiceFactory::class)->setMethods(['create'])->getMock();
        $invoiceFactoryMock->expects($this->once())->method('create')->willReturn($invoiceMock);

        $instance = $this->getInstance(['invoiceFactory' => $invoiceFactoryMock]);
        $result = $instance->getById($id);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertEquals($invoiceMock, $result);
    }

    public function testGetList()
    {
        $invoiceCollectionFactoryMock = $this->getFakeMock(CollectionFactory::class)
            ->setMethods(['create', 'getSize', 'setCurPage', 'setPageSize'])
            ->getMock();
        $invoiceCollectionFactoryMock->expects($this->once())->method('create')->willReturnSelf();

        $searchResultsObject = $this->getObject(SearchResults::class);
        $searchResultsMock = $this->getFakeMock(SearchResultsInterfaceFactory::class)
            ->setMethods(['create'])
            ->getMock();
        $searchResultsMock->expects($this->once())->method('create')->willReturn($searchResultsObject);

        $searchCriteria = $this->getObject(SearchCriteria::class);

        $instance = $this->getInstance([
            'invoiceCollectionFactory' => $invoiceCollectionFactoryMock,
            'searchResultsFactory' => $searchResultsMock
        ]);
        $result = $instance->getList($searchCriteria);

        $this->assertInstanceOf(SearchResultsInterface::class, $result);
    }

    /**
     * @return array
     */
    public function deleteProvider()
    {
        return [
            'successful delete' => [true],
            'failed delete' => [false]
        ];
    }

    /**
     * @param $canDelete
     *
     * @dataProvider deleteProvider
     */
    public function testDelete($canDelete)
    {
        $resourceMock = $this->getFakeMock(InvoiceResource::class)->setMethods(['delete'])->getMock();
        $expectsSave = $resourceMock->expects($this->once())->method('delete');

        if (!$canDelete) {
            $expectsSave->willThrowException(new \Exception());
            $this->expectException(CouldNotDeleteException::class);
        }

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(null)->getMock();

        $instance = $this->getInstance(['resource' => $resourceMock]);
        $result = $instance->delete($invoiceMock);

        $this->assertTrue($result);
    }

    public function testDeleteById()
    {
        $id = rand(0, 999);

        $invoiceMock = $this->getFakeMock(Invoice::class)->setMethods(['load', 'getId'])->getMock();
        $invoiceMock->expects($this->once())->method('load')->willReturnSelf();
        $invoiceMock->expects($this->once())->method('getId')->willReturn($id);

        $invoiceFactoryMock = $this->getFakeMock(InvoiceFactory::class)->setMethods(['create'])->getMock();
        $invoiceFactoryMock->expects($this->once())->method('create')->willReturn($invoiceMock);

        $instance = $this->getInstance(['invoiceFactory' => $invoiceFactoryMock]);
        $result = $instance->deleteById($id);

        $this->assertTrue($result);
    }
}
