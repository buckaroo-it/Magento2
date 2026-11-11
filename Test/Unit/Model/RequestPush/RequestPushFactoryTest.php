<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\RequestPush;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\RequestPush\HttppostPushRequest;
use Buckaroo\Magento2\Model\RequestPush\JsonPushRequest;
use Buckaroo\Magento2\Model\RequestPush\RequestPushFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;

/**
 * A JSON push is read from its body, the content its signature is validated over.
 */
class RequestPushFactoryTest extends \Buckaroo\Magento2\Test\BaseTest
{
    protected $instanceClass = RequestPushFactory::class;

    private const BODY = ['Transaction' => ['Key' => 'SIGNED-KEY', 'Invoice' => '300000015']];
    private const REQUEST_DATA = ['Transaction' => ['Key' => 'SIGNED-KEY', 'Invoice' => '300000099']];

    public function testAJsonPushIsReadFromTheBodyOnly(): void
    {
        $created = [];
        $factory = $this->buildFactory('application/json', $created);

        $factory->create();

        $this->assertSame([[JsonPushRequest::class, ['requestData' => self::BODY]]], $created);
    }

    public function testAFormPushIsReadFromThePostedFields(): void
    {
        $created = [];
        $factory = $this->buildFactory('application/x-www-form-urlencoded', $created);

        $factory->create();

        $this->assertSame([[HttppostPushRequest::class, ['requestData' => ['brq_invoicenumber' => '300000015']]]], $created);
    }

    /**
     * A shopper returns from Buckaroo by posting a form; a JSON body is a server-to-server push
     * and is never read on a return.
     */
    public function testAReturnIsOnlyEverReadFromThePostedFields(): void
    {
        $created = [];
        $factory = $this->buildFactory('application/json', $created);

        $factory->createFromFormPost();

        $this->assertSame([[HttppostPushRequest::class, ['requestData' => ['brq_invoicenumber' => '300000015']]]], $created);
    }

    private function buildFactory(string $contentType, array &$created): RequestPushFactory
    {
        $request = $this->getFakeMock(Request::class)
            ->onlyMethods(['getContentType', 'getBodyParams', 'getRequestData', 'getPostValue'])
            ->getMock();
        $request->method('getContentType')->willReturn($contentType);
        $request->method('getBodyParams')->willReturn(self::BODY);
        $request->method('getRequestData')->willReturn(self::REQUEST_DATA);
        $request->method('getPostValue')->willReturn(['brq_invoicenumber' => '300000015']);

        $objectManager = $this->getFakeMock(ObjectManagerInterface::class)->getMock();
        $objectManager->method('create')->willReturnCallback(function ($type, $arguments) use (&$created) {
            $created[] = [$type, $arguments];
            return $this->getFakeMock($type)->getMock();
        });

        return new RequestPushFactory(
            $objectManager,
            $request,
            $this->getFakeMock(BuckarooLoggerInterface::class)->getMock()
        );
    }
}
