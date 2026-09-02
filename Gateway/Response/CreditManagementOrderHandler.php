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

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CreditManagementOrderHandler implements HandlerInterface
{
    public const INVOICE_KEY = 'buckaroo_cm3_invoice_key';

    /**
     * @var TransactionResponse
     */
    protected $response;

    /**
     * @inheritdoc
     */
    public function handle(array $handlingSubject, array $response)
    {
        $this->response = SubjectReader::readTransactionResponse($response);
        $payment = SubjectReader::readPayment($handlingSubject)->getPayment();

        $invoiceKey = $this->getServiceInvoice();
        if ($invoiceKey !== null) {
            $payment->setAdditionalInformation(self::INVOICE_KEY, $invoiceKey);
        }
    }

    /**
     * Get invoice key from response
     *
     * @return string|null
     */
    protected function getServiceInvoice(): ?string
    {
        $services = $this->response->data('Services');
        if (is_array($services) && count($services) > 0) {
            $service = $this->getCreditManagementService($services);
            if (is_array($service) && count($service) > 0) {
                return $this->getInvoiceKey($service);
            }
        }

        return null;
    }

    /**
     * Get service for credit management
     *
     * @param array $services
     *
     * @return array|null
     */
    private function getCreditManagementService(array $services): ?array
    {
        foreach ($services as $service) {
            if (isset($service['Name']) && $service['Name'] === "CreditManagement3") {
                return $service;
            }
        }
        return null;
    }

    /**
     * Get invoice key from service
     *
     * @param array $service
     *
     * @return string
     */
    private function getInvoiceKey(array $service): string
    {
        if (!isset($service['Parameters']) || !is_array($service['Parameters'])) {
            return '';
        }
        foreach ($service['Parameters'] as $parameter) {
            if ($parameter['Name'] === "InvoiceKey") {
                return $parameter['Value'];
            }
        }

        return '';
    }
}
