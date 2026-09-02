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
namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;

abstract class AbstractApplepay implements HttpPostActionInterface
{
    /**
     * @var BuckarooLoggerInterface $logger
     */
    public $logger;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @param JsonFactory             $resultJsonFactory
     * @param RequestInterface        $request
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request           = $request;
        $this->logger            = $logger;
    }

    /**
     * Retrieve request parameters.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->request->getParams();
    }

    /**
     * Gather totals from quote totals.
     *
     * @param Address|null   $address
     * @param AddressTotal[] $quoteTotals
     *
     * @return array
     */
    public function gatherTotals(?Address $address, array $quoteTotals): array
    {
        $shippingTotalInclTax = 0;
        if ($address !== null) {
            $shippingTotalInclTax = $address->getData('shipping_incl_tax');
        }

        return [
            'subtotal'    => isset($quoteTotals['subtotal']) ? $quoteTotals['subtotal']->getValue() : 0,
            'discount'    => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : 0,
            'shipping'    => $shippingTotalInclTax,
            'grand_total' => isset($quoteTotals['grand_total']) ? $quoteTotals['grand_total']->getValue() : 0,
        ];
    }

    /**
     * Create a common JSON response.
     *
     * @param array|string $data
     * @param string|bool  $errorMessage
     *
     * @return Json
     */
    protected function commonResponse($data, $errorMessage): Json
    {
        if ($errorMessage || empty($data)) {
            $response = ['success' => false, 'error' => $errorMessage];
        } else {
            $response = ['success' => true, 'data' => $data];
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}
