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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Gateway\GatewayInterface;
use Buckaroo\Magento2\Gateway\Http\TransactionBuilder\IdinBuilderInterface;
use Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class Idin extends \Magento\Framework\App\Action\Action
{
    /**
     * @var IdinBuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var GatewayInterface
     */
    protected $gateway;

    /**
     * @var Log
     */
    private $logger;

    /**
     * @param Context $context
     * @param TransactionBuilderFactory $transactionBuilderFactory
     * @param GatewayInterface $gateway
     * @param Log $logger
     * @throws Exception
     */
    public function __construct(
        Context                   $context,
        TransactionBuilderFactory $transactionBuilderFactory,
        GatewayInterface          $gateway,
        Log                       $logger
    ) {
        parent::__construct($context);
        $this->transactionBuilder = $transactionBuilderFactory->get('idin');
        $this->gateway            = $gateway;
        $this->logger             = $logger;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $data = $this->getRequest()->getParams();

        if (!isset($data['issuer']) || empty($data['issuer'])) {
            return $this->json(
                ['error' => 'Issuer not valid']
            );
        }

        try {
            $response = $this->sendIdinRequest($data['issuer']);
        } catch (\Throwable $th) {
            $this->logger->debug($th->getMessage());
            return $this->json(
                ['error' => 'Unknown buckaroo error occurred']
            );
        }

        return $this->json($response);
    }
    /**
     * Return json response
     *
     * @param array $data
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function json($data)
    {
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($data);
    }
    /**
     * Send idin request
     *
     * @param string $issuer
     *
     * @throws \Exception
     * @return mixed      $response
     */
    protected function sendIdinRequest($issuer)
    {
        $transaction = $this->transactionBuilder
            ->setIssuer($issuer)
            ->build();

        return $this->gateway->setMode($this->transactionBuilder->getMode())
            ->authorize($transaction)[0];
    }
}
