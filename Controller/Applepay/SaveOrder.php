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
    private SaveOrderProcessor $processor;

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
