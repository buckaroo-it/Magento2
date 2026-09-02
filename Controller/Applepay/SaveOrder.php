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
use Buckaroo\Magento2\Model\Service\ExpressMethodsException;
use Buckaroo\Magento2\Service\Applepay\SaveOrderProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class SaveOrder extends AbstractApplepay
{
    /**
     * @var SaveOrderProcessor
     */
    private $processor;

    /**
     * SaveOrder controller constructor.
     *
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param BuckarooLoggerInterface $logger
     * @param SaveOrderProcessor $processor
     */
    public function __construct(
        JsonFactory            $resultJsonFactory,
        RequestInterface       $request,
        BuckarooLoggerInterface $logger,
        SaveOrderProcessor $processor
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->processor = $processor;
    }

    /**
     * Place the Apple Pay order from the request payload.
     *
     * @return Json
     * @throws ExpressMethodsException
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $payload = $this->getParams();

        if (!$payload || empty($payload['payment']) || empty($payload['extra'])) {
            return $this->commonResponse([], true);
        }

        $data = $this->processor->place($payload);

        return $this->commonResponse($data, false);
    }
}
