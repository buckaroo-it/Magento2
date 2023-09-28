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

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Controller\ResultFactory;

class Idin extends \Magento\Framework\App\Action\Action
{

    /**
     * @var Buckaroo\Magento2\Gateway\Http\TransactionBuilder\IdinBuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var \Buckaroo\Magento2\Gateway\GatewayInterface
     */
    protected $gateway;

    /**
     * @var Log
     */
    private $logger;

    /**
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory
     * @param \Buckaroo\Magento2\Gateway\GatewayInterface $gateway
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account $configProviderAccount
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Buckaroo\Magento2\Gateway\Http\TransactionBuilderFactory $transactionBuilderFactory,
        \Buckaroo\Magento2\Gateway\GatewayInterface $gateway,
        Log $logger
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
     * @return mixed $response
     * @throws \Exception
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
