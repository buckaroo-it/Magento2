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

namespace Buckaroo\Magento2\Service\CreditManagement;

use Buckaroo\Magento2\Exception;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class ServiceParameters
{
    /**
     * @var ServiceParameters\CreateCombinedInvoice
     */
    private $createCombinedInvoice;

    /**
     * @var ServiceParameters\CreateCreditNote
     */
    private $createCreditNote;

    /**
     * @param ServiceParameters\CreateCombinedInvoice $createCombinedInvoice
     * @param ServiceParameters\CreateCreditNote      $createCreditNote
     */
    public function __construct(
        ServiceParameters\CreateCombinedInvoice $createCombinedInvoice,
        ServiceParameters\CreateCreditNote $createCreditNote
    ) {
        $this->createCombinedInvoice = $createCombinedInvoice;
        $this->createCreditNote = $createCreditNote;
    }

    /**
     * Generates parameters for creating a combined invoice
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param string                              $configProviderType
     * @param array                               $filterParameter
     *
     * @throws Exception
     *
     * @return array
     */
    public function getCreateCombinedInvoice($payment, string $configProviderType, array $filterParameter = []): array
    {
        $requestParameter = $this->createCombinedInvoice->get($payment, $configProviderType);

        return $this->filterParameter($requestParameter, $filterParameter);
    }

    /**
     * Generates parameters for creating a credit note
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param array                               $filterParameter
     *
     * @return array
     */
    public function getCreateCreditNote($payment, array $filterParameter = []): array
    {
        $requestParameter = $this->createCreditNote->get($payment);
        return $this->filterParameter($requestParameter, $filterParameter);
    }

    /**
     * Filters request parameters based on the provided filter parameters.
     *
     * @param array $requestParameters
     * @param array $filterParameter
     *
     * @return array
     */
    public function filterParameter(array $requestParameters, array $filterParameter): array
    {
        if (!isset($requestParameters['RequestParameter'])) {
            return $requestParameters;
        }

        $filteredRequest = array_filter(
            $requestParameters['RequestParameter'],
            function ($parameter) use ($filterParameter) {
                $valueToTest = [];
                $valueToTest['Name'] = $parameter['Name'];

                if (isset($parameter['Group'])) {
                    $valueToTest['Group'] = $parameter['Group'];
                }

                if (in_array($valueToTest, $filterParameter)) {
                    return false;
                }

                return true;
            }
        );

        $requestParameters['RequestParameter'] = array_values($filteredRequest);

        return $requestParameters;
    }
}
