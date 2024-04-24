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

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;

abstract class AbstractApplepay implements HttpPostActionInterface
{
    /**
     * @var BuckarooLoggerInterface $logger
     */
    public BuckarooLoggerInterface $logger;

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @var RequestInterface $request
     */
    protected RequestInterface $request;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * Get Params
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->request->getParams();
    }

    /**
     * Get totals
     *
     * @param Address|null $address
     * @param AddressTotal[] $quoteTotals
     * @return array
     */
    public function gatherTotals(?Address $address, array $quoteTotals): array
    {
        $shippingTotalInclTax = 0;
        if ($address !== null) {
            $shippingTotalInclTax = $address->getData('shipping_incl_tax');
        }

        return [
            'subtotal' => $quoteTotals['subtotal']->getValue(),
            'discount' => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping' => $shippingTotalInclTax,
            'grand_total' => $quoteTotals['grand_total']->getValue()
        ];
    }

    /**
     * Return Json Response from array
     *
     * @param array|string $data
     * @param string|bool $errorMessage
     * @return Json
     */
    protected function commonResponse($data, $errorMessage): Json
    {
        if ($errorMessage || empty($data)) {
            $response = ['success' => 'false', 'error' => $errorMessage];
        } else {
            $response = ['success' => 'true', 'data' => $data];
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}
