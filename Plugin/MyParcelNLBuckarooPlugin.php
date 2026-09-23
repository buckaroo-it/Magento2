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
