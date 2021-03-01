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
namespace Buckaroo\Magento2\Model\Plugin;

use Buckaroo\Magento2\Logging\Log;

class MyParcelNLBuckarooPlugin
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;

    /**
     * @var Log
     */
    protected $logger;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Serialize\Serializer\Json $json,
        Log $logger
    ) {
        $this->checkoutSession    = $checkoutSession;
        $this->request = $request;
        $this->json = $json;
        $this->logger = $logger;
    }

    public function beforeGetFromDeliveryOptions()
    {
        $this->logger->addDebug(__METHOD__ . '|1|');
        // @codingStandardsIgnoreLine
        if ($result = file_get_contents('php://input')) {
            if ($jsonDecoded = $this->json->unserialize($result)) {

                $this->logger->addDebug(__METHOD__ . '|2|' . var_export($jsonDecoded, true));

                if (!empty($jsonDecoded['deliveryOptions']) &&
                    !empty($jsonDecoded['deliveryOptions'][0]['deliveryType']) &&
                    ($jsonDecoded['deliveryOptions'][0]['deliveryType'] == 'pickup') &&
                    !empty($jsonDecoded['deliveryOptions'][0]['pickupLocation'])
                ) {
                    $this->checkoutSession->setMyParcelNLBuckarooData(
                        $this->json->serialize($jsonDecoded['deliveryOptions'][0]['pickupLocation'])
                    );
                    $this->logger->addDebug(__METHOD__ . '|3|');
                }
            }
        }
    }
}
