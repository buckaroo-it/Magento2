<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\RequestPush;

use Buckaroo\Magento2\Model\RequestPush\JsonPushRequest;
use Buckaroo\Magento2\Model\Validator\PushSDK;
use PHPUnit\Framework\TestCase;

class JsonPushRequestTest extends TestCase
{
    /**
     * validate() used to discard its $store argument, so the SDK client was always built from the
     * ambient store - the default store view on the push route. That is finding #2, and it meant
     * the signature was checked against the wrong secret key on a multi-store install.
     */
    public function testForwardsTheStoreToTheValidatorInsteadOfDroppingIt(): void
    {
        $validator = $this->createMock(PushSDK::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($this->isType('array'), 2)
            ->willReturn(true);

        $request = new JsonPushRequest(['Transaction' => []], $validator);

        $this->assertTrue($request->validate(2));
    }

    public function testForwardsANullStoreUnchanged(): void
    {
        $validator = $this->createMock(PushSDK::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($this->isType('array'), null)
            ->willReturn(false);

        $request = new JsonPushRequest(['Transaction' => []], $validator);

        $this->assertFalse($request->validate());
    }
}
