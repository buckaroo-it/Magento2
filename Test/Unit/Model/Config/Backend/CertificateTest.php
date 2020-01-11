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
namespace TIG\Buckaroo\Test\Unit\Model\Config\Backend;

use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Filesystem\File\ReadFactory;
use TIG\Buckaroo\Model\Certificate as CertificateModel;
use TIG\Buckaroo\Model\CertificateFactory;
use TIG\Buckaroo\Model\Config\Backend\Certificate;

class CertificateTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Certificate::class;

    /**
     * @var \Mockery\MockInterface|\Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Mockery\MockInterface|ReadFactory
     */
    protected $scopeConfig;

    /**
     * @var \Mockery\MockInterface|\Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $readFactory;

    /**
     * @var Certificate
     */
    protected $object;

    protected $uploadFixture = [
        'name' => 'validfilename.pem',
        'tmp_name' => 'asdfkljasdljasfjldi',
        'content' => 'adef',
        'label' => 'certificatelabel',
    ];

    /**
     * Setup the base mocks.
     */
    public function setUp()
    {
        parent::setUp();

        $this->objectManager = \Mockery::mock(\Magento\Framework\ObjectManagerInterface::class);
        $this->readFactory = \Mockery::mock(ReadFactory::class);
        $this->scopeConfig = \Mockery::mock(\Magento\Framework\App\Config\ScopeConfigInterface::class);

        $this->object = $this->objectManagerHelper->getObject(
            Certificate::class,
            [
            'objectManager' => $this->objectManager,
            'readFactory' => $this->readFactory,
            'scopeConfig' => $this->scopeConfig,
            ]
        );
    }

    /**
     * Test with no value.
     *
     * @throws \Exception
     */
    public function testNoValue()
    {
        $this->assertInstanceOf(Certificate::class, $this->object->save());
    }

    /**
     * Test the function with an invalid file extension
     */
    public function testWrongFileType()
    {
        $this->object->setData('fieldset_data', ['certificate_upload'=>['name'=>'wrongfilename.abc']]);

        try {
            $this->object->save();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertNotFalse('Disallowed file type.', $e->getMessage());
            $this->assertInstanceOf(\Magento\Framework\Exception\LocalizedException::class, $e);
        }
    }

    /**
     * Test the path without a filename.
     */
    public function testMissingName()
    {
        $this->object->setData('fieldset_data', ['certificate_upload'=>['name'=>'validfilename.pem']]);

        try {
            $this->object->save();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('Enter a name for the certificate.', $e->getMessage());
            $this->assertInstanceOf(\Magento\Framework\Exception\LocalizedException::class, $e);
        }
    }

    public function testSave()
    {
        $readFactoryMock = $this->getFakeMock(ReadFactory::class)->setMethods(['create', 'readAll'])->getMock();
        $readFactoryMock->expects($this->once())
            ->method('create')
            ->with($this->uploadFixture['tmp_name'], DriverPool::FILE)
            ->willReturnSelf();
        $readFactoryMock->expects($this->once())->method('readAll')->willReturn($this->uploadFixture['content']);

        $certificateMock = $this->getFakeMock(CertificateModel::class)
            ->setMethods(['setCertificate', 'setName'])
            ->getMock();
        $certificateMock->expects($this->once())->method('setCertificate')->with($this->uploadFixture['content']);
        $certificateMock->expects($this->once())->method('setName')->with($this->uploadFixture['label']);

        $certificateFactoryMock = $this->getFakeMock(CertificateFactory::class)->setMethods(['create'])->getMock();
        $certificateFactoryMock->expects($this->once())->method('create')->willReturn($certificateMock);

        $instance = $this->getInstance([
            'readFactory' => $readFactoryMock,
            'certificateFactory' => $certificateFactoryMock
        ]);

        $instance->setData(
            'fieldset_data',
            [
                'certificate_upload'=> $this->uploadFixture,
                'certificate_label' => $this->uploadFixture['label'],
            ]
        );

        $result = $instance->save();
        $this->assertInstanceOf(Certificate::class, $result);
    }
}
