<?php

namespace Buckaroo\Magento2\Model\Push;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Api\PushProcessorInterface;
use Buckaroo\Magento2\Service\Push\OrderRequestService;
use Magento\Framework\ObjectManagerInterface;

class PushProcessorsFactory
{
    private const BUCK_PUSH_TYPE_TRANSACTION = 'transaction_push';
    private const BUCK_PUSH_TYPE_INVOICE = 'invoice_push';
    private const BUCK_PUSH_TYPE_INVOICE_INCOMPLETE = 'incomplete_invoice_push';
    private const BUCK_PUSH_TYPE_DATAREQUEST = 'datarequest_push';

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
     * @param PushRequestInterface|null $pushRequest
     * @return ?PushProcessorInterface
     * @throws BuckarooException
     */
    public function get(?PushRequestInterface $pushRequest): ?PushProcessorInterface
    {
        if (!$this->pushProcessor instanceof PushProcessorInterface) {
            if (empty($this->pushProcessors)) {
                throw new \LogicException('Push processors is not set.');
            }

            $pushProcessorClass = $this->getProcessorClass($pushRequest);

            $order = $this->orderRequestService->getOrderByRequest($pushRequest);

            $transactionType = $this->getTransactionType($pushRequest, $order);

            if ($transactionType == self::BUCK_PUSH_TYPE_INVOICE) {
                $pushProcessorClass = $this->pushProcessors['credit_managment'];
            } elseif ($transactionType == self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE) {
                throw new BuckarooException(
                    __('Skipped handling this invoice push because it is too soon.')
                );
            }

            $transactionMethod = $pushRequest->getTransactionMethod();

            $pushProcessorClass = $this->pushProcessors[$transactionMethod] ?? $pushProcessorClass;

            if (empty($pushProcessorClass)) {
                throw new BuckarooException(
                    new Phrase(
                        'Unknown ConfigProvider type requested: %1.',
                        [$transactionMethod]
                    )
                );
            }
            $this->pushProcessor = $this->objectManager->get($pushProcessorClass);

        }
        return $this->pushProcessor;
    }

    /**
     * @param PushRequestInterface|null $pushRequest
     * @return void
     */
    private function getProcessorClass($pushRequest) {
        $pushProcessorClass = $this->pushProcessors['default'];
    }

    /**
     * Determine the transaction type based on push request data and the saved invoice key.
     *
     * @return bool|string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getTransactionType($pushRequest, $order)
    {
        //If an order has an invoice key, then it should only be processed by invoice pushes
        $savedInvoiceKey = (string)$order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (!empty($pushRequest->getInvoicekey())
            && !empty($pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) > 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE;
        }

        if (!empty($pushRequest->getInvoicekey())
            && !empty($pushRequest->getSchemekey())
            && strlen($savedInvoiceKey) == 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE;
        }

        if (!empty($pushRequest->getDatarequest())) {
            return self::BUCK_PUSH_TYPE_DATAREQUEST;
        }

        if (empty($pushRequest->getInvoicekey())
            && empty($pushRequest->getServiceCreditmanagement3Invoicekey())
            && empty($pushRequest->getDatarequest())
            && strlen($savedInvoiceKey) <= 0
        ) {
            return self::BUCK_PUSH_TYPE_TRANSACTION;
        }

        return false;
    }
}