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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DriverPool;
use Magento\Framework\Filesystem\File\ReadFactory;
use TIG\Buckaroo\Model\Certificate as CertificateModel;
use TIG\Buckaroo\Model\CertificateFactory;
use TIG\Buckaroo\Model\Config\Backend\Certificate;

class CertificateTest extends \TIG\Buckaroo\Test\BaseTest
{
    protected $instanceClass = Certificate::class;

    protected $uploadFixture = [
        'name' => 'validfilename.pem',
        'tmp_name' => 'asdfkljasdljasfjldi',
        'content' => 'adef',
        'label' => 'certificatelabel',
    ];

    /**
     * Test with no value.
     */
    public function testNoValue()
    {
        $certificateFactoryMock = $this->getFakeMock(CertificateFactory::class)->getMock();
        $instance = $this->getInstance(['certificateFactory' => $certificateFactoryMock]);

        $result = $instance->save();
        $this->assertInstanceOf(Certificate::class, $result);
    }

    /**
     * Test the function with an invalid file extension
     */
    public function testWrongFileType()
    {
        $certificateFactoryMock = $this->getFakeMock(CertificateFactory::class)->getMock();

        $instance = $this->getInstance(['certificateFactory' => $certificateFactoryMock]);
        $instance->setData('fieldset_data', ['certificate_upload' => ['name' => 'wrongfilename.abc']]);

        try {
            $instance->save();
        } catch (LocalizedException $e) {
            $this->assertEquals('Disallowed file type.', $e->getMessage());
        }
    }

    /**
     * Test the path without a filename.
     */
    public function testMissingName()
    {
        $certificateFactoryMock = $this->getFakeMock(CertificateFactory::class)->getMock();

        $instance = $this->getInstance(['certificateFactory' => $certificateFactoryMock]);
        $instance->setData('fieldset_data', ['certificate_upload' => ['name' => 'validfilename.pem']]);

        try {
            $instance->save();
        } catch (LocalizedException $e) {
            $this->assertEquals('Enter a name for the certificate.', $e->getMessage());
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
