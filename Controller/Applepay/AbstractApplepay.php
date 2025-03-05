<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;

abstract class AbstractApplepay implements HttpPostActionInterface
{
    /**
     * @var Log
     */
    public Log $logger;

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @param JsonFactory     $resultJsonFactory
     * @param RequestInterface $request
     * @param Log             $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        Log $logger
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
     * @param Address|null     $address
     * @param AddressTotal[]   $quoteTotals
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
