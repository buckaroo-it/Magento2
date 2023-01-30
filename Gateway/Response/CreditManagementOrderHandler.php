<?php

namespace Buckaroo\Magento2\Gateway\Response;

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CreditManagementOrderHandler implements HandlerInterface
{
    public const INVOICE_KEY = 'buckaroo_cm3_invoice_key';

    protected TransactionResponse $response;

    /**
     * @throws LocalizedException
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
     * @param array $services
     *
     * @return string|null
     */
    protected function getServiceInvoice()
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
    private function getCreditManagementService(array $services)
    {
        foreach ($services as $service) {
            if ($service['Name'] === "CreditManagement3") {
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
    private function getInvoiceKey(array $service)
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
