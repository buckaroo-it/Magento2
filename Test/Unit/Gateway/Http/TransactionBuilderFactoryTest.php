<?php
/**
 *                  ___________       __            __
 *                  \__    ___/____ _/  |_ _____   |  |
 *                    |    |  /  _ \\   __\\__  \  |  |
 *                    |    | |  |_| ||  |   / __ \_|  |__
 *                    |____|  \____/ |__|  (____  /|____/
 *                                              \/
 *          ___          __                                   __
 *         |   |  ____ _/  |_   ____ _______   ____    ____ _/  |_
 *         |   | /    \\   __\_/ __ \\_  __ \ /    \ _/ __ \\   __\
 *         |   ||   |  \|  |  \  ___/ |  | \/|   |  \\  ___/ |  |
 *         |___||___|  /|__|   \_____>|__|   |___|  / \_____>|__|
 *                  \/                           \/
 *                  ________
 *                 /  _____/_______   ____   __ __ ______
 *                /   \  ___\_  __ \ /  _ \ |  |  \\____ \
 *                \    \_\  \|  | \/|  |_| ||  |  /|  |_| |
 *                 \______  /|__|    \____/ |____/ |   __/
 *                        \/                       |__|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license   http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Gateway\Http;

use Mockery as m;
use TIG\Buckaroo\Exception;
use TIG\Buckaroo\Test\BaseTest;
use Magento\Framework\ObjectManagerInterface;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderFactory;
use TIG\Buckaroo\Gateway\Http\TransactionBuilderInterface;

class TransactionBuilderFactoryTest extends BaseTest
{
    /**
     * @var TransactionBuilderFactory
     */
    protected $object;

    /**
     * @var m\MockInterface|ObjectManagerInterface
     */
    protected $objectManager;

    public function setUp()
    {
        parent::setUp();

        $this->objectManager = m::mock(ObjectManagerInterface::class);
    }

    public function getTransactionBuilder($transactionBuilders = [])
    {
        $object = $this->objectManagerHelper->getObject(
            TransactionBuilderFactory::class,
            [
            'objectManager' => $this->objectManager,
            'transactionBuilders' => $transactionBuilders,
            ]
        );

        return $object;
    }

    /**
     * Test the happy path
     */
    public function testGet()
    {
        $model = m::mock(TransactionBuilderInterface::class);
        $this->objectManager->shouldReceive('get')->with('model1')->andReturn($model);

        $object = $this->getTransactionBuilder(
            [
            [
                'type' => 'model1',
                'model' => 'model1',
            ]
            ]
        );
        $result = $object->get('model1');

        $this->assertInstanceOf(TransactionBuilderInterface::class, $result);
    }

    /**
     * Test what happens when we request an invalid TransactionBuilder class.
     */
    public function testGetInvalidClass()
    {
        $object = $this->getTransactionBuilder(
            [
            [
                'type' => '',
                'model' => '',
            ]
            ]
        );

        try {
            $object->get('model1');
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf(Exception::class, $e);
        }
    }

    /**
     * Test what happens when we request an TransactionBuilder which is not the correct instance.
     */
    public function testGetInvalidInstance()
    {
        $model = m::mock();
        $this->objectManager->shouldReceive('get')->with('model1')->andReturn($model);

        $object = $this->getTransactionBuilder(
            [
            [
                'type' => 'model1',
                'model' => 'model1',
            ]
            ]
        );

        try {
            $object->get('model1');
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }

    /**
     * Test what happens when we request an TransactionBuilder but there are no providers.
     */
    public function testGetNoProviders()
    {
        $object = $this->getTransactionBuilder();

        try {
            $object->get('');
            $this->fail();
        } catch (\Exception $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }
}
