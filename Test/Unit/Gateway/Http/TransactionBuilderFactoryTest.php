<?php
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
namespace TIG\Buckaroo\Test\Unit\Gateway\Http;

use Magento\Framework\DataObject;
use TIG\Buckaroo\Exception;
use TIG\Buckaroo\Test\BaseTest;
use Magento\Framework\ObjectManagerInterface;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface;

class TransactionBuilderFactoryTest extends BaseTest
{
    protected $instanceClass = TransactionBuilderFactory::class;

    /**
     * Test the happy path
     */
    public function testGet()
    {
        $model = $this->getFakeMock(TransactionBuilderInterface::class, true);

        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $objectManagerMock->expects($this->once())->method('get')->with('model1')->willReturn($model);

        $instance = $this->getInstance([
            'objectManager' => $objectManagerMock,
            'transactionBuilders' => [['type' => 'model1', 'model' => 'model1']]
        ]);

        $result = $instance->get('model1');

        $this->assertInstanceOf(TransactionBuilderInterface::class, $result);
    }

    /**
     * Test what happens when we request an invalid TransactionBuilder class.
     */
    public function testGetInvalidClass()
    {
        $instance = $this->getInstance(['transactionBuilders' => [['type' => '', 'model' => '']]]);

        try {
            $instance->get('model1');
        } catch (Exception $e) {
            $this->assertEquals('Unknown transaction builder type requested: model1.', $e->getMessage());
        }
    }

    /**
     * Test what happens when we request an TransactionBuilder which is not the correct instance.
     */
    public function testGetInvalidInstance()
    {
        $model = $this->getObject(DataObject::class);
        $objectManagerMock = $this->getFakeMock(ObjectManagerInterface::class)
            ->setMethods(['get'])
            ->getMockForAbstractClass();
        $objectManagerMock->expects($this->once())->method('get')->with('model1')->willReturn($model);

        $instance = $this->getInstance([
            'objectManager' => $objectManagerMock,
            'transactionBuilders' => [['type' => 'model1', 'model' => 'model1']]
        ]);

        try {
            $instance->get('model1');
        } catch (\LogicException $e) {
            $msg = 'The transaction builder must implement "TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface".';
            $this->assertEquals($msg, $e->getMessage());
        }
    }

    /**
     * Test what happens when we request an TransactionBuilder but there are no providers.
     */
    public function testGetNoProviders()
    {
        $instance = $this->getInstance();

        try {
            $instance->get('');
        } catch (\LogicException $e) {
            $this->assertEquals('Transaction builder adapter is not set.', $e->getMessage());
        }
    }
}
