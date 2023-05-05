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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;

use Buckaroo\Magento2\Gateway\Response\CancelHandler;

class CancelHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var CancelHandler
     */
    private CancelHandler $cancelHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cancelHandler = new CancelHandler();
    }

    /**
     * @return void
     */
    public function testHandle(): void
    {
        $this->orderPaymentMock
            ->expects($this->once())
            ->method('setAdditionalInformation')
            ->with('voided_by_buckaroo', true);

        $this->cancelHandler->handle(['payment' => $this->getPaymentDOMock()], $this->getTransactionResponse());
    }
}
