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
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Model\ConfigProvider\Method\Creditcard;
use Buckaroo\Magento2\Model\Method\BuckarooAdapter;
use Buckaroo\Magento2\Model\Push\DefaultProcessor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;

class DefaultProcessorPlugin
{
    /**
     * @var Creditcard
     */
    protected $configProviderCreditcard;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param Creditcard $configProviderCreditcard
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Creditcard $configProviderCreditcard,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->configProviderCreditcard = $configProviderCreditcard;
        $this->orderRepository = $orderRepository;
    }

    /**
     * After Process Succeeded Push
     *
     * @param DefaultProcessor $subject
     * @param bool             $result
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

                $this->orderRepository->save($order);
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
     * Read a protected or private property value from an object via reflection.
     *
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
                return $prop->getValue($subject);
            }
            $ref = $ref->getParentClass();
        } while ($ref);
        return null;
    }
}
