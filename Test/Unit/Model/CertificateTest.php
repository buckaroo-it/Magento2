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
