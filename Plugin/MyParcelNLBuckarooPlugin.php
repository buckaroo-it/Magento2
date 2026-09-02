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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;

class MyParcelNLBuckarooPlugin
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @param Session                 $checkoutSession
     * @param RequestInterface        $request
     * @param Json                    $json
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        Session $checkoutSession,
        RequestInterface $request,
        Json $json,
        BuckarooLoggerInterface $logger
    ) {
        $this->checkoutSession    = $checkoutSession;
        $this->request = $request;
        $this->json = $json;
        $this->logger = $logger;
    }

    /**
     * Saves the MyParcelNL delivery options data to the checkout session before getFromDeliveryOptions runs.
     */
    public function beforeGetFromDeliveryOptions()
    {
        // @codingStandardsIgnoreLine
        if ($result = file_get_contents('php://input')) {
            if ($jsonDecoded = $this->json->unserialize($result)) {
                $this->logger->addDebug(sprintf(
                    '[MyParcelNL] | [Plugin] | [%s:%s] - Set Pickup Location | deliveryOptions: %s',
                    __METHOD__,
                    __LINE__,
                    var_export($jsonDecoded, true)
                ));
                if (!empty($jsonDecoded['deliveryOptions']) &&
                    !empty($jsonDecoded['deliveryOptions'][0]['deliveryType']) &&
                    ($jsonDecoded['deliveryOptions'][0]['deliveryType'] == 'pickup') &&
                    !empty($jsonDecoded['deliveryOptions'][0]['pickupLocation'])
                ) {
                    $this->checkoutSession->setMyParcelNLBuckarooData(
                        $this->json->serialize($jsonDecoded['deliveryOptions'][0]['pickupLocation'])
                    );
                }
            }
        }
    }
}
