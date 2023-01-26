<?php

namespace Buckaroo\Magento2\Gateway\Response;

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
        $this->validate($handlingSubject, $response);

        $this->response = $response['object'];

        $invoiceKey = $this->getServiceInvoice();
        if ($invoiceKey !== null) {
            $handlingSubject['payment']
                ->getPayment()
                ->setAdditionalInformation(self::INVOICE_KEY, $invoiceKey);
        }
    }

    /**
     * Validate data from request
     *
     * @param array $handlingSubject
     * @param array $response
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validate(array $handlingSubject, array $response)
    {
        $this->validatePayment($handlingSubject);
        $this->validateResponse($response);
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

    private function validatePayment(array $handlingSubject)
    {
        if (
            !isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }
    }

    private function validateResponse(array $response)
    {
        if (
            !isset($response['object'])
            || !$response['object'] instanceof TransactionResponse
        ) {
            throw new \InvalidArgumentException('Data must be an instance of "TransactionResponse"');
        }
    }
}
