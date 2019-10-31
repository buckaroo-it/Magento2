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

use TIG\Buckaroo\Model\Invoice;
use TIG\Buckaroo\Test\BaseTest;

class InvoiceTest extends BaseTest
{
    protected $instanceClass = Invoice::class;

    public function testGetInvoiceTransactionId()
    {
        $rand = rand(0, 999);

        $instance = $this->getInstance();
        $instance->setInvoiceTransactionId($rand);

        $result = $instance->getInvoiceTransactionId();

        $this->assertEquals($rand, $result);
    }

    public function testGetInvoiceNumber()
    {
        $rand = rand(0, 999);

        $instance = $this->getInstance();
        $instance->setInvoiceNumber($rand);

        $result = $instance->getInvoiceNumber();

        $this->assertEquals($rand, $result);
    }
}
