<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Phrase;

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
     * Retrieve proper push processor for the specified transaction method.
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

        // Check if is Group Transaction Push
        if ($pushTransactionType->isGroupTransaction()) {
            $pushProcessorClass = $this->pushProcessors['group_transaction'];
        }

        // Check if is Credit Management Push
        $pushType = $pushTransactionType->getPushType();
        if ($pushType == PushTransactionType::BUCK_PUSH_TYPE_INVOICE) {
            $pushProcessorClass = $this->pushProcessors['credit_managment'];
        } elseif ($pushType == PushTransactionType::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE) {
            throw new BuckarooException(
                __('Skipped handling this invoice push because it is too soon.')
            );
        }

        // Check if is Refund or Cancel Authorize Push
        if ($pushTransactionType->getServiceAction() == 'refund') {
            $pushProcessorClass = $this->pushProcessors['refund'];
        } elseif ($pushTransactionType->getServiceAction() == 'cancel_authorize') {
            $pushProcessorClass = $this->pushProcessors['cancel_authorize'];
        }

        return $pushProcessorClass;
    }
}