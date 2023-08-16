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

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;

/**
 * Factory class for creating and retrieving appropriate push processors based on transaction type.
 */
class PushProcessorsFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var array
     */
    protected array $pushProcessors;

    /**
     * @var ?PushProcessorInterface
     */
    protected ?PushProcessorInterface $pushProcessor = null;

    /**
     * @var OrderRequestService
     */
    private OrderRequestService $orderRequestService;

    /**
     * @param OrderRequestService $orderRequestService
     * @param ObjectManagerInterface $objectManager
     * @param array $pushProcessors
     */
    public function __construct(
        OrderRequestService $orderRequestService,
        ObjectManagerInterface $objectManager,
        array $pushProcessors = []
    ) {
        $this->objectManager = $objectManager;
        $this->pushProcessors = $pushProcessors;
        $this->orderRequestService = $orderRequestService;
    }

    /**
     * Retrieve the appropriate push processor for a given transaction type.
     *
     * @param PushTransactionType|null $pushTransactionType
     * @return ?PushProcessorInterface
     * @throws BuckarooException
     */
    public function get(?PushTransactionType $pushTransactionType): ?PushProcessorInterface
    {
        if (!$this->pushProcessor instanceof PushProcessorInterface) {
            if (empty($this->pushProcessors)) {
                throw new \LogicException('Push processors is not set.');
            }

            $pushProcessorClass = $this->getPushProcessorClass($pushTransactionType);
            if (empty($pushProcessorClass)) {
                throw new BuckarooException(new Phrase('Unknown Push Processor type'));
            }

            $this->pushProcessor = $this->objectManager->get($pushProcessorClass);

        }
        return $this->pushProcessor;
    }

    /**
     * Determine the class of the push processor based on the provided transaction type.
     *
     * @param PushTransactionType|null $pushTransactionType
     * @return mixed
     * @throws BuckarooException
     */
    private function getPushProcessorClass(?PushTransactionType $pushTransactionType)
    {
        // Set Default Push Processor
        $pushProcessorClass = $this->pushProcessors['default'];

        // Set Push Processor by Payment Method
        $paymentMethod = $pushTransactionType->getPaymentMethod();
        $pushProcessorClass = $this->pushProcessors[$paymentMethod] ?? $pushProcessorClass;

        if ($pushTransactionType->isFromPayPerEmail()) {
            return $this->pushProcessors['payperemail'];
        }

        // Check if is Group Transaction Push
        if ($pushTransactionType->isGroupTransaction()) {
            return $this->pushProcessors['group_transaction'];
        }

        // Check if is Credit Management Push
        $pushType = $pushTransactionType->getPushType();
        if ($pushType == PushTransactionType::BUCK_PUSH_TYPE_INVOICE) {
            return $this->pushProcessors['credit_managment'];
        }

        if ($pushType == PushTransactionType::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE) {
            throw new BuckarooException(
                __('Skipped handling this invoice push because it is too soon.')
            );
        }

        // Check if is Refund or Cancel Authorize Push
        if ($pushTransactionType->getServiceAction() == 'refund') {
            return $this->pushProcessors['refund'];
        }

        if ($pushTransactionType->getServiceAction() == 'cancel_authorize') {
            return $this->pushProcessors['cancel_authorize'];
        }

        return $pushProcessorClass;
    }
}