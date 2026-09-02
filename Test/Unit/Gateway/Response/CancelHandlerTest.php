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

namespace Buckaroo\Magento2\Test\Unit\Gateway\Response;

use Buckaroo\Magento2\Gateway\Response\CancelHandler;

class CancelHandlerTest extends AbstractResponseHandlerTest
{
    /**
     * @var CancelHandler
     */
    private $cancelHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cancelHandler = new CancelHandler();
    }

    /**
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
