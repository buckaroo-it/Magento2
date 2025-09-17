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
declare(strict_types=1);

namespace Buckaroo\Magento2\Controller\ExpressPayment;

use Buckaroo\Magento2\Service\ExpressPayment\ProductValidationService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

class ValidateProduct implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    private JsonFactory $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var ProductValidationService
     */
    private ProductValidationService $productValidationService;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param ProductValidationService $productValidationService
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        ProductValidationService $productValidationService
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->productValidationService = $productValidationService;
    }

    /**
     * Execute product validation
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $productId = (int)$this->request->getParam('product_id');
            $qty = (float)($this->request->getParam('qty') ?: 1);
            $options = $this->request->getParam('options', []);

            if (!$productId) {
                return $result->setData([
                    'success' => false,
                    'error' => 'Product ID is required.'
                ]);
            }

            $validation = $this->productValidationService->validateProduct($productId, $options, $qty);

            $response = [
                'success' => $validation['is_valid'],
                'errors' => $validation['errors']
            ];

            if ($validation['is_valid']) {
                $response['product_data'] = [
                    'id' => $productId,
                    'qty' => $qty,
                    'options' => $options
                ];

                // Add configurable attributes if available
                if ($this->productValidationService->hasRequiredOptions($productId)) {
                    $response['configurable_attributes'] = $this->productValidationService->getConfigurableAttributes($productId);
                }
            }

            return $result->setData($response);

        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'error' => 'Validation failed: ' . $e->getMessage()
            ]);
        }
    }
}
