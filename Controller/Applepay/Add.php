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

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Buckaroo\Magento2\Service\Applepay\Add as AddService;
use Magento\Framework\App\Action\Context;

class Add implements HttpPostActionInterface
{
    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;
    /**
     * @var Context
     */
    protected $context;
    /**
     * @var AddService|null
     */
    protected $addService;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param Context $context
     * @param AddService|null $addService
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Context $context,
        AddService $addService = null
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->context = $context;
        $this->addService = $addService;
    }

    /**
     * Add Applepay
     *
     * @return Json
     * @throws NoSuchEntityException
     */
    public function execute()
    {
        $data = $this->addService->process($this->context->getRequest());

        $errorMessage = $data['error'] ?? null;
        if ($errorMessage || empty($data)) {
            $response = ['success' => 'false', 'error' => $errorMessage];
        } else {
            $response = ['success' => 'true', 'data' => $data];
        }

        $resultJson = $this->resultJsonFactory->create();
        return $resultJson->setData($response);
    }
}
