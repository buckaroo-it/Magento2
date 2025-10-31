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

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Magento\Framework\Exception\LocalizedException;

class DefaultProcessorPlugin
{
    /**
     * @var Creditcard
     */
    protected $configProviderCreditcard;

    /**
     * @param Creditcard $configProviderCreditcard
     */
    public function __construct(
        Creditcard $configProviderCreditcard
    ) {
        $this->configProviderCreditcard = $configProviderCreditcard;
    }

    /**
     * After Process Succeeded Push
     *
     * @param DefaultProcessor $subject
     * @param bool             $result
     * @param string           $newStatus
     * @param string           $message
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     *
     * @throws LocalizedException
     *
     * @return bool
     */
    public function afterProcessSucceededPush(
        DefaultProcessor $subject,
        $result
    ) {
        $order = $this->getProtectedProperty($subject, 'order');
        $pushRequest = $this->getProtectedProperty($subject, 'pushRequest');

        if (!$order || !$pushRequest) {
            return $result;
        }

        $payment = $order->getPayment();
        $method = $payment->getMethod();

        if (strpos($method, 'buckaroo_magento2') === false) {
            return $result;
        }

        /** @var BuckarooAdapter $paymentMethodInstance */
        $paymentMethodInstance = $payment->getMethodInstance();
        $card = $paymentMethodInstance->getInfoInstance()->getAdditionalInformation('card_type');

        if (empty($card)) {
            return $result;
        }

        $authenticationFunction = 'getService' . ucfirst($card) . 'Authentication';
        $enrolledFunction = 'getService' . ucfirst($card) . 'Enrolled';

        if (!\is_object($pushRequest)
            || !\method_exists($pushRequest, $authenticationFunction)
            || !\method_exists($pushRequest, $enrolledFunction)
        ) {
            return $result;
        }

        if (empty($pushRequest->$authenticationFunction())
            || empty($pushRequest->$enrolledFunction())
        ) {
            return $result;
        }

        $authentication = $pushRequest->$authenticationFunction();

        if ($authentication == 'U' || $authentication == 'N') {
            switch ($card) {
                case 'maestro':
                    $putOrderOnHold = (bool)$this->configProviderCreditcard->getMaestroUnsecureHold();
                    break;
                case 'visa':
                    $putOrderOnHold = (bool)$this->configProviderCreditcard->getVisaUnsecureHold();
                    break;
                case 'mastercard':
                    $putOrderOnHold = (bool)$this->configProviderCreditcard->getMastercardUnsecureHold();
                    break;
                default:
                    $putOrderOnHold = false;
                    break;
            }

            if ($putOrderOnHold) {
                $order
                    ->hold()
                    ->addCommentToStatusHistory(
                        __('Order has been put on hold, because it is unsecure.')
                    );

                $order->save();
            }
        }

        $paymentMethodInstance->getInfoInstance()->setAdditionalInformation(
            'buckaroo_mpi_status',
            [
                'enrolled'       => $pushRequest->$enrolledFunction(),
                'authentication' => $pushRequest->$authenticationFunction(),
            ]
        );

        return $result;
    }

    /**
     * @param object $subject
     * @param string $name
     *
     * @return mixed|null
     */
    private function getProtectedProperty(object $subject, string $name)
    {
        $ref = new \ReflectionClass($subject);
        do {
            if ($ref->hasProperty($name)) {
                $prop = $ref->getProperty($name);
                $prop->setAccessible(true);
                return $prop->getValue($subject);
            }
            $ref = $ref->getParentClass();
        } while ($ref);
        return null;
    }
}

