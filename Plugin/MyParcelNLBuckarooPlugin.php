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
use Magento\Framework\App\Request\Http;
use Magento\Framework\Serialize\Serializer\Json;

class MyParcelNLBuckarooPlugin
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Http
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
     * @param Http                    $request
     * @param Json                    $json
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        Session $checkoutSession,
        Http $request,
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
        $body = $this->request->getContent();
        if (empty($body)) {
            return;
        }

        try {
            $jsonDecoded = $this->json->unserialize($body);
        } catch (\InvalidArgumentException $e) {
            return;
        }

        if (!is_array($jsonDecoded) || empty($jsonDecoded['deliveryOptions'][0])) {
            return;
        }

        $this->logger->addDebug(sprintf(
            '[MyParcelNL] | [Plugin] | [%s:%s] - Set Pickup Location | fields: %s',
            __METHOD__,
            __LINE__,
            var_export(array_keys($jsonDecoded), true)
        ));

        $deliveryOption = $jsonDecoded['deliveryOptions'][0];
        if (!empty($deliveryOption['deliveryType'])
            && $deliveryOption['deliveryType'] === 'pickup'
            && !empty($deliveryOption['pickupLocation'])
        ) {
            $this->checkoutSession->setMyParcelNLBuckarooData(
                $this->json->serialize($deliveryOption['pickupLocation'])
            );
        }
    }
}
