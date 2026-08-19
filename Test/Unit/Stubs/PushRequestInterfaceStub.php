<?php

namespace Buckaroo\Magento2\Test\Unit\Stubs;

/**
 * PHPUnit 12 replacement for MockBuilder::addMethods() on \Buckaroo\Magento2\Api\Data\PushRequestInterface.
 * Declares the magic methods tests need to configure on their doubles.
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
interface PushRequestInterfaceStub extends \Buckaroo\Magento2\Api\Data\PushRequestInterface
{
    public function getAmountCredit(...$args);

    public function getData(...$args);

    public function getEventparametersStatuscode(...$args);

    public function getEventparametersTransactionstatuscode(...$args);

    public function getInvoicekey(...$args);

    public function getOriginalRequest(...$args);

    public function getPrimaryService(...$args);

    public function getRelatedtransactionPartialpayment(...$args);

    public function getRelatedtransactionRefund(...$args);

    public function getSchemekey(...$args);

    public function getServiceCreditmanagement3Invoicekey(...$args);

    public function getServiceKlarnakpAutopaytransactionkey(...$args);

    public function getServiceKlarnakpCaptureid(...$args);

    public function getServiceKlarnakpReservationnumber(...$args);

    public function hasAdditionalInformation(...$args);

    public function hasPostData(...$args);
}
