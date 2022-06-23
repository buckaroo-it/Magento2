<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Buckaroo\Magento2\Gateway\Http;

use Buckaroo\Magento2\Gateway\Http\Bpe3;
use Buckaroo\Magento2\Gateway\Http\Client\Soap;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\ConfigProvider\Predefined;
use Buckaroo\Magento2\Model\ConfigProvider\Refund;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Buckaroo\Magento2\Gateway\Request\MockDataRequest;

class IdealTransferFactory implements TransferFactoryInterface
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
     * @var \Buckaroo\Magento2\Helper\Data
     */
    public $helper;

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
        Log $logger,
        \Buckaroo\Magento2\Helper\Data $helper
    ) {
        $this->client                   = $client;
        $this->transferBuilder          = $transferBuilder;
        $this->configProviderPredefined = $configProviderPredefined;
        $this->configProviderRefund     = $configProviderRefund;
        $this->logger                   = $logger;
        $this->helper                   = $helper;
        $this->setMode(
            $this->helper->getMode('ideal')
        );
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
     * Builds gateway transfer object
     *
     * @param array $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        $this->logger->addDebug(__METHOD__.'|1|');
        $this->logger->addDebug(var_export($request, true));

        $header = $request['header'];
        $method = $request['method'];

        unset($request['header']);
        unset($request['method']);

        $clientConfig = [
            'wsdl' => $this->getWsdl()
        ];

        $transfer = $this->transferBuilder->setClientConfig($clientConfig);
        $transfer->setHeaders($header);
        $transfer->setBody($request);
        $transfer->setAuthUsername(null); // The authorization is done by the request headers and encryption.
        $transfer->setAuthPassword(null);
        $transfer->setMethod($method);
        $transfer->setUri(''); // The URI is part of the wsdl file.
        $transfer->shouldEncode(false);

        return $transfer->build();
    }

    /**
     * @return string
     *
     * @throws \Buckaroo\Magento2\Exception|\LogicException
     */
    protected function getWsdl()
    {
        if (!$this->mode) {
            throw new \LogicException("Cannot do a Buckaroo transaction when 'mode' is not set or set to 0.");
        }

        switch ($this->mode) {
            case \Buckaroo\Magento2\Helper\Data::MODE_TEST:
                $wsdl = $this->configProviderPredefined->getWsdlTestWeb();
                break;
            case \Buckaroo\Magento2\Helper\Data::MODE_LIVE:
                $wsdl = $this->configProviderPredefined->getWsdlLiveWeb();
                break;
            default:
                throw new \Buckaroo\Magento2\Exception(
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
}
