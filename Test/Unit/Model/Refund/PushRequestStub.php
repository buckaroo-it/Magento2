<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Test\Unit\Model\Refund;

use Buckaroo\Magento2\Api\Data\PushRequestInterface;

abstract class PushRequestStub implements PushRequestInterface
{
    public function hasAdditionalInformation($key, $value)
    {
        return $this->getAdditionalInformation($key) === $value;
    }

    public function getAmountCredit()
    {
        return 0.0;
    }

    public function validate($store = null): bool
    {
        return true;
    }

    public function getAmountDebit()
    {
        return null;
    }

    public function getAmount()
    {
        return null;
    }

    public function getCurrency(): ?string
    {
        return null;
    }

    public function getCustomerName(): ?string
    {
        return null;
    }

    public function getDescription(): ?string
    {
        return null;
    }

    public function getInvoiceNumber(): ?string
    {
        return null;
    }

    public function getMutationType(): ?string
    {
        return null;
    }

    public function getOrderNumber(): ?string
    {
        return null;
    }

    public function getPayment(): ?string
    {
        return null;
    }

    public function getStatusCode(): ?string
    {
        return null;
    }

    public function getStatusCodeDetail(): ?string
    {
        return null;
    }

    public function getStatusMessage(): ?string
    {
        return null;
    }

    public function isTest(): bool
    {
        return false;
    }

    public function getTransactionMethod(): ?string
    {
        return null;
    }

    public function getTransactionType(): ?string
    {
        return null;
    }

    public function getTransactions(): ?string
    {
        return null;
    }

    public function setTransactions($transactions)
    {
    }

    public function setAmount($amount): void
    {
    }

    public function getAdditionalInformation(string $propertyName): ?string
    {
        return null;
    }
}
