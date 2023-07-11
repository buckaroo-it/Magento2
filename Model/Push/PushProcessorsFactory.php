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

            $pushProcessorClass = $this->pushProcessors['default'];

            $paymentMethod = $pushTransactionType->getPaymentMethod();
            $pushProcessorClass = $this->pushProcessors[$paymentMethod] ?? $pushProcessorClass;

            if (empty($pushProcessorClass)) {
                throw new BuckarooException(
                    new Phrase('Unknown ConfigProvider type requested: %1.', [$paymentMethod])
                );
            }

            if ($pushTransactionType->isGroupTransaction()) {
                $pushProcessorClass = $this->pushProcessors['group_transaction'];
            }

            $transactionType = $pushTransactionType->getTransactionType();

            if ($transactionType == PushTransactionType::BUCK_PUSH_TYPE_INVOICE) {
                $pushProcessorClass = $this->pushProcessors['credit_managment'];
            } elseif ($transactionType == PushTransactionType::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE) {
                throw new BuckarooException(
                    __('Skipped handling this invoice push because it is too soon.')
                );
            }

            $this->pushProcessor = $this->objectManager->get($pushProcessorClass);

        }
        return $this->pushProcessor;
    }
}