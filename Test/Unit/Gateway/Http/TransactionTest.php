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

use TIG\Buckaroo\Test\BaseTest;
use TIG\Buckaroo\Gateway\Http\Transaction;

class TransactionTest extends BaseTest
{
    protected $instanceClass = Transaction::class;

    /**
     * Test the Transaction class.
     */
    public function testGetBody()
    {
        $body = ['this', 'is', 'the', 'body'];

        $instance = $this->getInstance();
        $instance->setBody($body);

        $this->assertEquals($body, $instance->getBody());
    }

    public function testGetHeaders()
    {
        $headers = ['this', 'is', 'the', 'header'];

        $instance = $this->getInstance();
        $instance->setHeaders($headers);

        $this->assertEquals($headers, $instance->getHeaders());
    }

    public function testGetMethod()
    {
        $method = 'post';

        $instance = $this->getInstance();
        $instance->setMethod($method);

        $this->assertEquals($method, $instance->getMethod());
    }
}
