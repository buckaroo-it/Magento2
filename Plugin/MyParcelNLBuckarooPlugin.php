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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;

class MyParcelNLBuckarooPlugin
{
    /**
     * @var Session
     */
    protected Session $checkoutSession;

    /**
     * @var RequestInterface
     */
    protected RequestInterface $request;

    /**
     * @var Json
     */
    protected Json $json;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @param Session $checkoutSession
     * @param RequestInterface $request
     * @param Json $json
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
     * Saves the MyParcelNL delivery options data to the checkout session
     * before executing the getFromDeliveryOptions method.
     *
     * @return void
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
