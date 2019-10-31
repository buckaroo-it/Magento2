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
namespace TIG\Buckaroo\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use TIG\Buckaroo\Gateway\Http\Client\Soap;
use TIG\Buckaroo\Logging\Log;
use TIG\Buckaroo\Model\ConfigProvider\Predefined;
use TIG\Buckaroo\Model\ConfigProvider\Refund;

class Bpe3 implements \TIG\Buckaroo\Gateway\GatewayInterface
{
    /** @var Soap */
    protected $client;

    /** @var TransferBuilder */
    protected $transferBuilder;

    /** @var Predefined */
    protected $configProviderPredefined;

    /** @var Refund */
    protected $configProviderRefund;

    /** @var int */
    protected $mode;

    /** @var Log $logger */
    public $logger;

    /**
     * Bpe3 constructor.
     *
     * @param Soap            $client
     * @param TransferBuilder $transferBuilder
     * @param Predefined      $configProviderPredefined
     * @param Refund          $configProviderRefund
     * @param Log             $logger
     */
    public function __construct(
        Soap $client,
        TransferBuilder $transferBuilder,
        Predefined $configProviderPredefined,
        Refund $configProviderRefund,
        Log $logger
    ) {
        $this->client                   = $client;
        $this->transferBuilder          = $transferBuilder;
        $this->configProviderPredefined = $configProviderPredefined;
        $this->configProviderRefund     = $configProviderRefund;
        $this->logger                   = $logger;
    }

    /**
     * @param int $mode
     *
     * @return $this
     */
    public function setMode($mode)
    {
        $this->mode = $mode;

        return $this;
    }

    /**
     * @param \TIG\Buckaroo\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Exception
     */
    public function order(Transaction $transaction)
    {
        return $this->doRequest($transaction);
    }

    /**
     * @param \TIG\Buckaroo\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Exception
     */
    public function capture(Transaction $transaction)
    {
        return $this->doRequest($transaction);
    }

    /**
     * @param \TIG\Buckaroo\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Exception
     */
    public function authorize(Transaction $transaction)
    {
        return $this->doRequest($transaction);
    }

    /**
     * @param \TIG\Buckaroo\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Exception|\TIG\Buckaroo\Exception
     */
    public function refund(Transaction $transaction)
    {
        if ($this->configProviderRefund->getEnabled()) {
            return $this->doRequest($transaction);
        }

        $this->logger->addDebug('Failed to refund because the configuration is set to disabled');
        throw new \TIG\Buckaroo\Exception(__("Online refunds are currently disabled for Buckaroo payment methods."));
    }

    /**
     * @param \TIG\Buckaroo\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Exception
     */
    public function cancel(Transaction $transaction)
    {
        return $this->void($transaction);
    }

    /**
     * @param \TIG\Buckaroo\Gateway\Http\Transaction $transaction
     *
     * @return array
     * @throws \Exception
     */
    public function void(Transaction $transaction)
    {
        return $this->doRequest($transaction);
    }

    /**
     * @return string
     *
     * @throws \TIG\Buckaroo\Exception|\LogicException
     */
    protected function getWsdl()
    {
        if (!$this->mode) {
            throw new \LogicException("Cannot do a Buckaroo transaction when 'mode' is not set or set to 0.");
        }

        switch ($this->mode) {
            case \TIG\Buckaroo\Helper\Data::MODE_TEST:
                $wsdl = $this->configProviderPredefined->getWsdlTestWeb();
                break;
            case \TIG\Buckaroo\Helper\Data::MODE_LIVE:
                $wsdl = $this->configProviderPredefined->getWsdlLiveWeb();
                break;
            default:
                throw new \TIG\Buckaroo\Exception(
                    __(
                        "Invalid mode set: %1",
                        [
                            $this->mode
                        ]
                    )
                );
        }

        return $wsdl;
    }

    /**
     * @param Transaction $transaction
     *
     * @return array
     * @throws \Exception
     */
    public function doRequest(Transaction $transaction)
    {
        $clientConfig = [
            'wsdl' => $this->getWsdl()
        ];

        $transfer = $this->transferBuilder->setClientConfig($clientConfig);
        $transfer->setHeaders($transaction->getHeaders());
        $transfer->setBody($transaction->getBody());
        $transfer->setAuthUsername(null); // The authorization is done by the request headers and encryption.
        $transfer->setAuthPassword(null);
        $transfer->setMethod($transaction->getMethod());
        $transfer->setUri(''); // The URI is part of the wsdl file.
        $transfer->shouldEncode(false);

        $transfer = $transfer->build();

        $this->client->setStore($transaction->getStore());

        return $this->client->placeRequest($transfer);
    }
}
