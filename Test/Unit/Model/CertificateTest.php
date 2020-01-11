<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact support@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
namespace TIG\Buckaroo\Test\Unit\Model;

use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Model\Certificate;

class CertificateTest extends BaseTest
{
    protected $instanceClass = Certificate::class;

    public function testSetCertificate()
    {
        $randValue = rand(1, 1000);

        $instance = $this->getInstance();
        $result = $instance->setCertificate($randValue);

        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertEquals($randValue, $instance->getCertificate());
    }

    public function testSetName()
    {
        $randValue = rand(1, 1000);

        $instance = $this->getInstance();
        $result = $instance->setName($randValue);

        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertEquals($randValue, $instance->getName());
    }

    public function testSetCreatedAt()
    {
        $randValue = rand(1, 1000);

        $instance = $this->getInstance();
        $result = $instance->setCreatedAt($randValue);

        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertEquals($randValue, $instance->getCreatedAt());
    }

    public function testSetSkipEncryptionOnSave()
    {
        $randValue = rand(1, 1000);

        $instance = $this->getInstance();
        $result = $instance->setSkipEncryptionOnSave($randValue);

        $this->assertInstanceOf(Certificate::class, $result);
        $this->assertEquals($randValue, $instance->isSkipEncryptionOnSave());
    }
}
