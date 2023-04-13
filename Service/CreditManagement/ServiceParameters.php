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

namespace Buckaroo\Magento2\Service\CreditManagement;

use Buckaroo\Magento2\Exception;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class ServiceParameters
{
    /**
     * @var ServiceParameters\CreateCombinedInvoice
     */
    private ServiceParameters\CreateCombinedInvoice $createCombinedInvoice;

    /**
     * @var ServiceParameters\CreateCreditNote
     */
    private ServiceParameters\CreateCreditNote $createCreditNote;

    /**
     * @param ServiceParameters\CreateCombinedInvoice $createCombinedInvoice
     * @param ServiceParameters\CreateCreditNote $createCreditNote
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
     * @param string $configProviderType
     * @param array $filterParameter
     * @return array
     * @throws Exception
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
     * @param array $filterParameter
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
