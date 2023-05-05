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
    protected TransactionResponse $response;

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
