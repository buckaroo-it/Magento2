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
use Buckaroo\Magento2\Service\Applepay\Add as AddService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class Add extends AbstractApplepay
{
    /**
     * @var AddService
     */
    protected $addService;

    /**
     * @param JsonFactory             $resultJsonFactory
     * @param RequestInterface        $request
     * @param BuckarooLoggerInterface $logger
     * @param AddService              $addService
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        BuckarooLoggerInterface $logger,
        AddService $addService
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->addService = $addService;
    }

    /**
     * Execute adding a product to the cart.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $params = $this->getParams();
        $this->logger->addDebug(sprintf(
            '[ApplePay] | [Controller] | [%s:%s] - Add Product to Cart | Request Params: %s',
            __METHOD__,
            __LINE__,
            var_export($params, true)
        ));

        $data = $this->addService->process($params);
        $errorMessage = $data['error'] ?? false;

        $this->logger->addDebug(sprintf(
            '[ApplePay] | [Controller] | [%s:%s] - Add Product to Cart | Response Data: %s',
            __METHOD__,
            __LINE__,
            var_export($data, true)
        ));

        return $this->commonResponse($data, $errorMessage);
    }
}
