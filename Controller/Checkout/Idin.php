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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Transaction\Response\TransactionResponse;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

class Idin extends Action implements HttpPostActionInterface
{
    /**
     * @var BuilderInterface
     */
    protected $requestDataBuilder;

    /**
     * @var TransferFactoryInterface
     */
    protected $transferFactory;

    /**
     * @var ClientInterface
     */
    protected $clientInterface;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param Context                  $context
     * @param BuilderInterface         $requestDataBuilder
     * @param TransferFactoryInterface $transferFactory
     * @param ClientInterface          $clientInterface
     * @param BuckarooLoggerInterface  $logger
     */
    public function __construct(
        Context $context,
        BuilderInterface $requestDataBuilder,
        TransferFactoryInterface $transferFactory,
        ClientInterface $clientInterface,
        BuckarooLoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->requestDataBuilder = $requestDataBuilder;
        $this->transferFactory = $transferFactory;
        $this->clientInterface = $clientInterface;
    }

    /**
     * Process action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $data = $this->getRequest()->getParams();
        $response = ['error' => 'Unknown buckaroo error occurred'];

        if (empty($data['issuer'])) {
            return $this->json(
                ['error' => 'Issuer not valid']
            );
        }

        try {
            $transferO = $this->transferFactory->create(
                $this->requestDataBuilder->build($data)
            );

            $response = $this->clientInterface->placeRequest($transferO);

            if (isset($response["object"]) && $response["object"] instanceof TransactionResponse) {
                if ($response["object"]->isSuccess() || $response["object"]->isPendingProcessing()) {
                    $response = $response["object"]->toArray();
                } else {
                    $response = ['error' => $response['object']->getSomeError()];
                }
            } else {
                $response = ['error' => 'TransactionResponse is not valid'];
            }
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[iDIN] | [Controller] | [%s:%s] - Validate iDIN | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
        }

        return $this->json($response);
    }

    /**
     * Return json response
     *
     * @param array $data
     *
     * @return Json
     */
    protected function json(array $data): Json
    {
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($data);
    }
}
