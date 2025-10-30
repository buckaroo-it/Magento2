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

namespace Buckaroo\Magento2\Model;

use Buckaroo\Magento2\Api\PushInterface;
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Helper\Data;
use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Logging\Log;
use Buckaroo\Magento2\Model\Config\Source\InvoiceHandlingOptions;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Buckaroo\Magento2\Model\Method\AbstractMethod;
use Buckaroo\Magento2\Model\Method\Afterpay;
use Buckaroo\Magento2\Model\Method\Afterpay2;
use Buckaroo\Magento2\Model\Method\Afterpay20;
use Buckaroo\Magento2\Model\Method\Creditcard;
use Buckaroo\Magento2\Model\Method\Creditcards;
use Buckaroo\Magento2\Model\Method\Klarnakp;
use Buckaroo\Magento2\Model\Method\Giftcards;
use Buckaroo\Magento2\Model\Method\Paypal;
use Buckaroo\Magento2\Model\Method\PayPerEmail;
use Buckaroo\Magento2\Model\Method\SepaDirectDebit;
use Buckaroo\Magento2\Model\Method\Transfer;
use Buckaroo\Magento2\Model\Method\Voucher;
use Buckaroo\Magento2\Model\Refund\Push as RefundPush;
use Buckaroo\Magento2\Model\Service\OrderCancellationService;
use Buckaroo\Magento2\Model\Validator\Push as ValidatorPush;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\FilterBuilder;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Framework\Filesystem\Driver\File;

class Push implements PushInterface
{
    public const BUCK_PUSH_CANCEL_AUTHORIZE_TYPE = 'I014';
    public const BUCK_PUSH_ACCEPT_AUTHORIZE_TYPE = 'I013';
    public const BUCK_PUSH_GROUPTRANSACTION_TYPE = 'I150';
    public const BUCK_PUSH_IDEAL_PAY = 'C021';

    public const BUCK_PUSH_TYPE_TRANSACTION        = 'transaction_push';
    public const BUCK_PUSH_TYPE_INVOICE            = 'invoice_push';
    public const BUCK_PUSH_TYPE_INVOICE_INCOMPLETE = 'incomplete_invoice_push';
    public const BUCK_PUSH_TYPE_DATAREQUEST        = 'datarequest_push';

    public const BUCKAROO_RECEIVED_TRANSACTIONS          = 'buckaroo_received_transactions';
    public const BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES = 'buckaroo_received_transactions_statuses';

    /**
     * @var Request $request
     */
    public $request;

    /**
     * @var ValidatorPush $validator
     */
    public $validator;

    /**
     * @var Order $order
     */
    public $order;

    /** @var Transaction */
    private $transaction;

    /**
     * @var OrderSender $orderSender
     */
    public $orderSender;

    /**
     * @var InvoiceSender $invoiceSender
     */
    public $invoiceSender;

    /**
     * @var array $postData
     */
    public $postData;

    /**
     * @var array originalPostData
     */
    public $originalPostData;

    /**
     * @var $refundPush
     */
    public $refundPush;

    /**
     * @var Data
     */
    public $helper;

    /**
     * @var Log $logging
     */
    public $logging;

    /**
     * @var OrderStatusFactory OrderStatusFactory
     */
    public $orderStatusFactory;

    /**
     * @var Account
     */
    public $configAccount;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    protected $groupTransaction;

    protected $forceInvoice = false;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    private $dontSaveOrderUponSuccessPush = false;

    protected $resourceConnection;

    private $isPayPerEmailB2BModePushInitial = false;

    protected $dirList;

    private $klarnakpConfig;
    private $afterpayConfig;

    private $fileSystemDriver;

    /**
     * @var LockManagerWrapper
     */
    protected $lockManager;

    protected $transactionRepository;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;

    /**
     * @var OrderCancellationService
     */
    private $orderCancellationService;

    /**
     * @param TransactionRepositoryInterface   $transactionRepository
     * @param SearchCriteriaBuilder            $searchCriteriaBuilder
     * @param FilterBuilder                    $filterBuilder
     * @param Order                            $order
     * @param TransactionInterface             $transaction
     * @param Request                          $request
     * @param ValidatorPush                    $validator
     * @param OrderSender                      $orderSender
     * @param InvoiceSender                    $invoiceSender
     * @param Data                             $helper
     * @param Account                          $configAccount
     * @param RefundPush                       $refundPush
     * @param Log                              $logging
     * @param Factory                          $configProviderMethodFactory
     * @param OrderStatusFactory               $orderStatusFactory
     * @param PaymentGroupTransaction          $groupTransaction
     * @param ObjectManagerInterface           $objectManager
     * @param ResourceConnection               $resourceConnection
     * @param DirectoryList                    $dirList
     * @param ConfigProvider\Method\Klarnakp   $klarnakpConfig
     * @param ConfigProvider\Method\Afterpay20 $afterpayConfig
     * @param File                             $fileSystemDriver
     * @param LockManagerWrapper               $lockManager
     * @param OrderCancellationService         $orderCancellationService
     */
    public function __construct(
        TransactionRepositoryInterface $transactionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        FilterBuilder $filterBuilder,
        Order $order,
        TransactionInterface $transaction,
        Request $request,
        ValidatorPush $validator,
        OrderSender $orderSender,
        InvoiceSender $invoiceSender,
        Data $helper,
        Account $configAccount,
        RefundPush $refundPush,
        Log $logging,
        Factory $configProviderMethodFactory,
        OrderStatusFactory $orderStatusFactory,
        PaymentGroupTransaction $groupTransaction,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        ResourceConnection $resourceConnection,
        \Magento\Framework\Filesystem\DirectoryList $dirList,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Klarnakp $klarnakpConfig,
        \Buckaroo\Magento2\Model\ConfigProvider\Method\Afterpay20 $afterpayConfig,
        File $fileSystemDriver,
        LockManagerWrapper $lockManager,
        OrderCancellationService $orderCancellationService
    ) {
        $this->transactionRepository       = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->order                       = $order;
        $this->transaction                 = $transaction;
        $this->request                     = $request;
        $this->validator                   = $validator;
        $this->orderSender                 = $orderSender;
        $this->invoiceSender               = $invoiceSender;
        $this->helper                      = $helper;
        $this->configAccount               = $configAccount;
        $this->refundPush                  = $refundPush;
        $this->logging                     = $logging;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->orderStatusFactory          = $orderStatusFactory;

        $this->groupTransaction   = $groupTransaction;
        $this->objectManager      = $objectManager;
        $this->resourceConnection = $resourceConnection;
        $this->dirList            = $dirList;
        $this->klarnakpConfig     = $klarnakpConfig;
        $this->afterpayConfig     = $afterpayConfig;
        $this->fileSystemDriver   = $fileSystemDriver;
        $this->lockManager = $lockManager;
        $this->orderCancellationService = $orderCancellationService;
    }

    /**
     * {@inheritdoc}
     *
     * @todo Once Magento supports variable parameters, modify this method to no longer require a Request object
     */
    public function receivePush()
    {
        $this->getPostData();
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->originalPostData, true));

        $this->logging->addDebug(__METHOD__ . '|1_2|');
        $orderIncrementID = $this->getOrderIncrementId();
        $this->logging->addDebug(__METHOD__ . '|Lock Name| - ' . var_export($orderIncrementID, true));
        $lockAcquired = $this->lockManager->lockOrder($orderIncrementID, 5);

        if (!$lockAcquired) {
            $this->logging->addDebug(__METHOD__ . '|lock not acquired|');
            throw new Exception(
                __('Lock push not acquired for order %1', $orderIncrementID)
            );
        }

        try {
            $response = $this->pushProcess();
            return $response;
        } catch (\Throwable $e) {
            $this->logging->addDebug(__METHOD__ . '|Exception|' . $e->getMessage());
            throw $e;
        } finally {
            $this->lockManager->unlockOrder($orderIncrementID);
            $this->logging->addDebug(__METHOD__ . '|Lock released|');
        }
    }

    /**
     * Check if the transaction is a fast checkout.
     *
     * @return bool
     */
    private function isFastCheckout()
    {
        return isset($this->postData['brq_service_ideal_transactionflow']) &&
            $this->postData['brq_service_ideal_transactionflow'] === 'Fast_Checkout';
    }

    /**
     * Extract and update order addresses if it's a fast checkout.
     */
    private function updateOrderAddressesIfFastCheckout()
    {
        $shippingAddress = $this->extractAddress('shippingaddress');
        $billingAddress = $this->extractAddress('invoiceaddress');

        // Update telephone numbers from contact info
        $contactPhoneNumber = $this->extractContactPhoneNumber();

        if ($shippingAddress && $billingAddress) {
            // Update the phone numbers from contact info if available
            if ($contactPhoneNumber) {
                $shippingAddress['telephone'] = $contactPhoneNumber;
                $billingAddress['telephone'] = $contactPhoneNumber;
            }
            $this->updateOrderWithAddresses($shippingAddress, $billingAddress);
        }
    }

    /**
     * Extract contact phone number from post data.
     *
     * @return string|null
     */
    private function extractContactPhoneNumber()
    {
        $phoneKey = 'brq_service_ideal_contactdetailsphonenumber';
        return isset($this->postData[$phoneKey]) ? urldecode($this->postData[$phoneKey]) : null;
    }

    /**
     * Extract address from post data based on address type.
     *
     * @param  string     $addressType
     * @return array|null
     */
    private function extractAddress($addressType)
    {
        $address = [];
        $prefix = 'brq_service_ideal_' . $addressType;

        $fieldsMap = [
            'firstname' => 'firstname',
            'lastname' => 'lastname',
            'street' => 'street',
            'housenumber' => 'housenumber',
            'addition' => 'addition',
            'postalcode' => 'postcode',
            'city' => 'city',
            'countryname' => 'country_id',
            'companyname' => 'company',
        ];

        foreach ($fieldsMap as $key => $field) {
            $paramKey = $prefix . $key;
            if (isset($this->postData[$paramKey])) {
                $decodedValue = urldecode($this->postData[$paramKey]);
                $address[$field] = $decodedValue;
            }
        }

        // Append house number to street if both are available
        if (isset($address['street']) && isset($address['housenumber'])) {
            $address['street'] .= ' ' . $address['housenumber'];

            // Add the addition (like 'A') if it exists
            if (isset($address['addition'])) {
                $address['street'] .= ' ' .$address['addition'];
                unset($address['addition']);
            }
            unset($address['housenumber']);
        }

        if (!empty($address['country_id'])) {
            $address['country_id'] = "NL";
        }

        return !empty($address) ? $address : null;
    }

    /**
     * Update order with extracted shipping and billing addresses.
     *
     * @param array $shippingAddress
     * @param array $billingAddress
     */
    private function updateOrderWithAddresses($shippingAddress, $billingAddress)
    {
        $orderId = $this->order->getEntityId();
        if ($orderId) {
            $this->logging->addDebug(__METHOD__ . '|Updating order addresses|');

            $orderAddressRepository = $this->objectManager->get('\Magento\Sales\Api\OrderAddressRepositoryInterface');

            try {
                $order = $this->order->load($orderId);

                $orderShippingAddress = $order->getShippingAddress();
                if ($orderShippingAddress) {
                    $orderShippingAddress->addData($shippingAddress);
                    $orderAddressRepository->save($orderShippingAddress);
                }

                $orderBillingAddress = $order->getBillingAddress();
                if ($orderBillingAddress) {
                    $orderBillingAddress->addData($billingAddress);
                    $orderAddressRepository->save($orderBillingAddress);
                }

                $this->updateCustomerInformation($order, $billingAddress);

            } catch (\Exception $e) {
                $this->logging->addDebug(__METHOD__ . '|Failed to update addresses|');
                $this->logging->addDebug(__METHOD__ . '|' . $e->getMessage());
            }
        } else {
            $this->logging->addDebug(__METHOD__ . '|Order ID not found|');
        }
    }

    /**
     * Update guest customer information.
     *
     * @param Order $order
     * @param array $billingAddress
     */
    private function updateCustomerInformation(Order $order, array $billingAddress)
    {
        if ($this->isGuestOrder($order)) {
            $this->updateGuestInformation($order, $billingAddress);
        } else {
            $this->updateRegisteredCustomerInformation($order);
        }

        $order->save();
    }

    private function isGuestOrder(Order $order): bool
    {
        return !$order->getCustomerId();
    }

    private function updateGuestInformation(Order $order, array $billingAddress): void
    {
        try {
            $customerEmail = $this->postData['brq_service_ideal_contactdetailsemail'] ?? $order->getCustomerEmail();
            $order->setCustomerEmail($customerEmail);
            $order->setCustomerFirstname($billingAddress['firstname'] ?? $order->getCustomerFirstname());
            $order->setCustomerLastname($billingAddress['lastname'] ?? $order->getCustomerLastname());
        } catch (\Exception $e) {
            $this->logging->addError('Error updating guest information: '. $e->getMessage());
        }
    }

    private function updateRegisteredCustomerInformation(Order $order): void
    {
        try {
            $customer = $order->getCustomer();
            if ($customer) {
                $order->setCustomerFirstname($customer->getFirstname() ?? $order->getCustomerFirstname());
                $order->setCustomerLastname($customer->getLastname() ?? $order->getCustomerLastname());
                $order->setCustomerEmail($customer->getEmail() ?? $order->getCustomerEmail());
            }
        } catch (\Exception $e) {
            $this->logging->addError('Error updating registered customer information: '. $e->getMessage());
        }
    }

    private function pushProcess()
    {
        $this->logging->addDebug(__METHOD__ . '|1_3|');

        if ($this->isFailedGroupTransaction()) {
            $this->handleGroupTransactionFailed();
            return true;
        }

        if ($this->isGroupTransactionInfo()) {
            if ($this->isCanceledGroupTransaction()) {
                $this->cancelGroupTransactionOrder();
                return true;
            }
            if ($this->isGroupTransactionFailed()) {
                $this->savePartGroupTransaction();
            } else {
                return true;
            }
        }

        $this->loadOrder();

        if ($this->skipHandlingForFailedGroupTransactions()) {
            return true;
        }

        if (!$this->isPushNeeded()) {
            return true;
        }

        //Check if the push can be processed and if the order can be updated IMPORTANT
        // => use the original post data.
        $validSignature = $this->validator->validateSignature(
            $this->originalPostData,
            $this->postData,
            $this->order ? $this->order->getStore() : null
        );

        $transactionType = $this->getTransactionType();
        //Validate status code and return response
        $postDataStatusCode = $this->getStatusCode();
        $this->logging->addDebug(__METHOD__ . '|1_5|' . var_export($postDataStatusCode, true));

        $this->logging->addDebug(__METHOD__ . '|1_10|' . var_export($transactionType, true));

        $response = $this->validator->validateStatusCode($postDataStatusCode);

        //Check if the push have PayLink
        $this->receivePushCheckPayLink($response, $validSignature);

        $payment = $this->order->getPayment();

        if ($this->pushCheckPayPerEmailCancel($response, $validSignature, $payment)) {
            return true;
        }

        //Check second push for PayPerEmail
        $receivePushCheckPayPerEmailResult = $this->receivePushCheckPayPerEmail(
            $response,
            $validSignature,
            $payment
        );

        $skipFirstPush = $payment->getAdditionalInformation('skip_push');

        $this->logging->addDebug(__METHOD__ . '|1_20|' . var_export($skipFirstPush, true));

        /**
         * Buckaroo Push is send before Response, for correct flow we skip the first push
         * for some payment methods
         * @todo when buckaroo changes the push / response order this can be removed
         */
        if ($skipFirstPush > 0) {
            $payment->setAdditionalInformation('skip_push', (int)$skipFirstPush - 1);
            $payment->save();
            throw new Exception(
                __('Skipped handling this push, first handle response, action will be taken on the next push.')
            );
        }

        if ($this->receivePushCheckDuplicates()) {
            throw new Exception(__('Skipped handling this push, duplicate'));
        }

        $this->logging->addDebug(__METHOD__ . '|2|' . var_export($response, true));

        $canUpdateOrder = $this->canUpdateOrderStatus($response);

        $this->logging->addDebug(__METHOD__ . '|3|' . var_export($canUpdateOrder, true));

        //Check if the push is a refund request or cancel authorize
        if (isset($this->postData['brq_amount_credit'])) {
            if ($response['status'] !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                && $this->order->isCanceled()
                && $this->postData['brq_transaction_type'] == self::BUCK_PUSH_CANCEL_AUTHORIZE_TYPE
                && $validSignature
            ) {
                return $this->processCancelAuthorize();
            } elseif ($response['status'] !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                && !$this->order->hasInvoices()
            ) {
                throw new Exception(
                    __('Refund failed ! Status : %1 and the order does not contain an invoice', $response['status'])
                );
            } elseif ($response['status'] !== 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
                && $this->order->hasInvoices()
            ) {
                //don't proceed failed refund push
                $this->logging->addDebug(__METHOD__ . '|10|');
                $this->setOrderNotificationNote(
                    __('push notification for refund has no success status, ignoring.')
                );
                return true;
            }
            return $this->refundPush->receiveRefundPush($this->postData, $validSignature, $this->order);
        }

        //Last validation before push can be completed
        if (!$validSignature) {
            $this->logging->addDebug('Invalid push signature');
            throw new Exception(__('Signature from push is incorrect'));
            // If the signature is valid but the order cant be updated,
            // try to add a notification to the order comments.
        } elseif ($validSignature && !$canUpdateOrder) {
            $this->logging->addDebug('Order can not receive updates');
            if ($receivePushCheckPayPerEmailResult) {
                $config = $this->configProviderMethodFactory->get(
                    PayPerEmail::PAYMENT_METHOD_CODE
                );
                if ($config->getEnabledB2B()) {
                    $this->logging->addDebug(__METHOD__ . '|$this->order->getState()|' . $this->order->getState());
                    if ($this->order->getState() === Order::STATE_COMPLETE) {
                        $this->order->setState(Order::STATE_PROCESSING);
                        $this->order->save();
                    }
                    return true;
                }
            }
            $this->setOrderNotificationNote(__('The order has already been processed.'));
            throw new Exception(
                __('Signature from push is correct but the order can not receive updates')
            );
        }

        if ($this->isFastCheckout()) {
            $this->updateOrderAddressesIfFastCheckout();
        }

        if (!$this->isGroupTransactionInfo()) {
            $this->setTransactionKey();
        }
        if (isset($this->postData['brq_statusmessage'])) {
            if ($this->order->getState() === Order::STATE_NEW &&
                !isset($this->postData['add_frompayperemail']) &&
                !$this->hasPostData('brq_transaction_method', 'transfer') &&
                !isset($this->postData['brq_relatedtransaction_partialpayment']) &&
                (isset($this->postData['brq_statuscode']) && $this->postData['brq_statuscode'] == 190)
            ) {
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->addCommentToStatusHistory(
                    $this->postData['brq_statusmessage'],
                    $this->helper->getOrderStatusByState($this->order, Order::STATE_PROCESSING)
                );
                $this->logging->addDebug(__METHOD__ . '|4|');
            } else {
                $this->order->addCommentToStatusHistory($this->postData['brq_statusmessage']);
                $this->logging->addDebug(__METHOD__ . '|5|');
            }
        }

        if ((!in_array($payment->getMethod(), [Giftcards::PAYMENT_METHOD_CODE, Voucher::PAYMENT_METHOD_CODE]))
            && $this->isGroupTransactionPart()) {
            $this->savePartGroupTransaction();
            return true;
        }

        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_INVOICE:
                $this->processCm3Push();
                break;
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                throw new Exception(
                    __('Skipped handling this invoice push because it is too soon.')
                );
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
            default:
                $this->processPush($response);
                break;
        }

        $this->logging->addDebug(__METHOD__ . '|5|');
        if (!$this->dontSaveOrderUponSuccessPush) {
            $this->logging->addDebug(__METHOD__ . '|5-1|');
            $this->order->save();
        }

        $this->logging->addDebug(__METHOD__ . '|6|');

        return true;
    }

    private function receivePushCheckDuplicates($receivedStatusCode = null, $trxId = null)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($this->order->getPayment()->getMethod(), true));

        $save = false;
        if (!$receivedStatusCode) {
            $save = true;
            if (empty($this->postData['brq_statuscode'])) {
                return false;
            }
            $receivedStatusCode = $this->postData['brq_statuscode'];
        }
        if (!$trxId) {
            if (empty($this->postData['brq_transactions'])) {
                return false;
            }
            $trxId = $this->postData['brq_transactions'];
        }
        $payment = $this->order->getPayment();
        $ignoredPaymentMethods = [
            Giftcards::PAYMENT_METHOD_CODE,
            Transfer::PAYMENT_METHOD_CODE,
        ];

        $isRefund = isset($this->postData['brq_amount_credit']) && $this->postData['brq_amount_credit'] > 0;

        if ($payment
            && $payment->getMethod()
            && $receivedStatusCode
            && ($this->getTransactionType() == self::BUCK_PUSH_TYPE_TRANSACTION)
            && ((!in_array($payment->getMethod(), $ignoredPaymentMethods)) || $isRefund)
        ) {
            $this->logging->addDebug(__METHOD__ . '|5|');

            $receivedTrxStatuses = $payment->getAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES
            );
            $this->logging->addDebug(__METHOD__ . '|10|' .
                var_export([$receivedTrxStatuses, $receivedStatusCode], true));
            if ($receivedTrxStatuses
                && is_array($receivedTrxStatuses)
                && !empty($trxId)
                && isset($receivedTrxStatuses[$trxId])
                && ($receivedTrxStatuses[$trxId] == $receivedStatusCode)
            ) {
                $orderStatus = $this->helper->getOrderStatusByState($this->order, Order::STATE_NEW);
                $statusCode = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
                if (($this->order->getState() == Order::STATE_NEW)
                    && ($this->order->getStatus() == $orderStatus)
                    && ($receivedStatusCode == $statusCode)
                ) {
                    $this->logging->addDebug(__METHOD__ . '|13|Allowing duplicate success push for order in NEW state');
                    return false;
                }

                $this->logging->addDebug(__METHOD__ . '|15|Duplicate push detected, skipping');
                return true;
            }
            if ($save) {
                $this->logging->addDebug(__METHOD__ . '|17|');
                $this->setReceivedTransactionStatuses();
                $payment->save();
            }
        }
        $this->logging->addDebug(__METHOD__ . '|20|');
        return false;
    }

    /**
     * Get and store the postdata parameters
     */
    private function getPostData()
    {
        $postData = $this->request->getPostValue();

        /** Magento may adds the SID session parameter, depending on the store configuration.
         * We don't need or want to use this parameter, so remove it from the retrieved post data. */
        unset($postData['SID']);

        //Set original postdata before setting it to case lower.
        $this->originalPostData = $postData;

        //Create post data array, change key values to lower case.
        $postDataLowerCase = array_change_key_case($postData, CASE_LOWER);
        $this->postData    = $postDataLowerCase;
    }

    /**
     * Check if it is needed to handle the push message based on postdata
     * @return bool
     */
    private function isPushNeeded()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if ($this->hasPostData('add_initiated_by_magento', 1)
            && $this->hasPostData('add_service_action_from_magento', ['refund'])
        ) {
            $statusCodeSuccess = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
            if ($this->hasPostData('brq_statuscode', $statusCodeSuccess)
                && !empty($this->postData['brq_relatedtransaction_refund'])) {
                if ($this->receivePushCheckDuplicates(
                    $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_APPROVAL'),
                    $this->postData['brq_relatedtransaction_refund']
                )) {
                    $this->logging->addDebug(__METHOD__ . '|4|');
                    return true;
                }
            }
            $this->logging->addDebug(__METHOD__ . '|5|');
            return false;

        }

        $types = ['capture', 'cancelauthorize', 'cancelreservation'];
        if ($this->hasPostData('add_initiated_by_magento', 1)
            && $this->hasPostData('add_service_action_from_magento', $types)
            && empty($this->postData['brq_relatedtransaction_refund'])
        ) {
            return false;
        }

        if ($this->hasPostData('add_initiated_by_magento', 1)
            && $this->hasPostData('brq_transaction_method', ['klarnakp', 'KlarnaKp'])
            && $this->hasPostData('add_service_action_from_magento', 'pay')
            && isset($this->postData['brq_service_klarnakp_captureid'])
        ) {
            return false;
        }

        $statusCodePendingProcessing = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING');
        if ($this->hasPostData('brq_statuscode', $statusCodePendingProcessing)
            && $this->hasPostData('brq_transaction_method', 'payconiq')) {
            return false;
        }

        return true;
    }

    /**
     * @param       $name
     * @param       $value
     * @return bool
     */
    private function hasPostData($name, $value)
    {
        if (is_array($value) &&
            isset($this->postData[$name]) &&
            in_array($this->postData[$name], $value)
        ) {
            return true;
        }

        if (isset($this->postData[$name]) &&
            $this->postData[$name] == $value
        ) {
            return true;
        }

        return false;
    }

    /**
     * Try to load the order from the Push Data
     */
    private function loadOrder()
    {
        $brqOrderId = $this->getOrderIncrementId();

        //Check if the order can receive further status updates
        $this->order->loadByIncrementId((string) $brqOrderId);

        if (!$this->order->getId()) {
            $this->logging->addDebug('Order could not be loaded by brq_invoicenumber or brq_ordernumber');
            // try to get order by transaction id on payment.
            $this->order = $this->getOrderByTransactionKey();
        }
    }

    private function saveAndReloadOrder()
    {
        $this->order->save();
        $this->loadOrder();
    }

    /**
     * @return int|string
     */
    private function getStatusCode()
    {
        $transactionType = $this->getTransactionType();
        $statusCode      = 0;
        switch ($transactionType) {
            case self::BUCK_PUSH_TYPE_TRANSACTION:
            case self::BUCK_PUSH_TYPE_DATAREQUEST:
                if (isset($this->postData['brq_statuscode'])) {
                    $statusCode = $this->postData['brq_statuscode'];
                }
                break;
            case self::BUCK_PUSH_TYPE_INVOICE:
            case self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE:
                if (isset($this->postData['brq_eventparameters_statuscode'])) {
                    $statusCode = $this->postData['brq_eventparameters_statuscode'];
                }

                if (isset($this->postData['brq_eventparameters_transactionstatuscode'])) {
                    $statusCode = $this->postData['brq_eventparameters_transactionstatuscode'];
                }
                break;
        }

        $statusCodeSuccess = $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS');
        if (isset($this->postData['brq_statuscode'])
            && ($this->postData['brq_statuscode'] == $statusCodeSuccess)
            && !$statusCode
        ) {
            $statusCode = $statusCodeSuccess;
        }

        return $statusCode;
    }

    /**
     * @return bool|string
     */
    public function getTransactionType()
    {
        //If an order has an invoice key, then it should only be processed by invoice pushes
        $payment = $this->order->getPayment();
        if (!$payment) {
            $this->logging->addDebug(__METHOD__ . '|Payment object is null for order ' . $this->order->getIncrementId());
            return false;
        }

        $savedInvoiceKey = (string)$payment->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if (isset($this->postData['brq_invoicekey'])
            && isset($this->postData['brq_schemekey'])
            && strlen($savedInvoiceKey) > 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE;
        }

        if (isset($this->postData['brq_invoicekey'])
            && isset($this->postData['brq_schemekey'])
            && strlen($savedInvoiceKey) == 0
        ) {
            return self::BUCK_PUSH_TYPE_INVOICE_INCOMPLETE;
        }

        if (isset($this->postData['brq_datarequest'])) {
            return self::BUCK_PUSH_TYPE_DATAREQUEST;
        }

        if (!isset($this->postData['brq_invoicekey'])
            && !isset($this->postData['brq_service_creditmanagement3_invoicekey'])
            && !isset($this->postData['brq_datarequest'])
            && strlen($savedInvoiceKey) <= 0
        ) {
            return self::BUCK_PUSH_TYPE_TRANSACTION;
        }

        return false;
    }

    /**
     * Cancel authorize processing.
     *
     * @return bool
     */
    public function processCancelAuthorize()
    {
        try {
            $this->setTransactionKey();
        } catch (Exception $e) {
            $this->logging->addDebug($e->getLogMessage());
        }

        $this->logging->addDebug('Order autorize has been canceld, trying to update payment transactions');

        return true;
    }

    /**
     * Process the push according the response status
     *
     * @param $response
     *
     * @throws Exception
     */
    public function processPush($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($response['status'], true));
        $payment = $this->order->getPayment();

        if (!$payment->getMethodInstance()->canProcessPostData($payment, $this->postData)) {
            return;
        }

        if ($this->giftcardPartialPayment()) {
            return;
        }

        $newStatus = $this->orderStatusFactory->get($this->postData['brq_statuscode'], $this->order);

        $this->logging->addDebug(__METHOD__ . '|5|' . var_export($newStatus, true));

        if ($this->isPayPerEmailB2BModePushInitial($response)) {
            $response['status'] = 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS';
            $newStatus          = $this->configAccount->getOrderStatusSuccess();
            $this->logging->addDebug(__METHOD__ . '|15|' . var_export([$response['status'], $newStatus], true));
            $this->isPayPerEmailB2BModePushInitial = true;
        }

        switch ($response['status']) {
            case 'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_FAILED':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED':
                $this->processFailedPush($newStatus, $response['message']);
                break;
            case 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS':
                if ($this->order->getPayment()->getMethod() == Paypal::PAYMENT_METHOD_CODE) {
                    $paypalConfig = $this->configProviderMethodFactory
                        ->get(Paypal::PAYMENT_METHOD_CODE);

                    /**
                     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Paypal $paypalConfig
                     */
                    $newSellersProtectionStatus = $paypalConfig->getSellersProtectionIneligible();
                    if ($paypalConfig->getSellersProtection() && !empty($newSellersProtectionStatus)) {
                        $newStatus = $newSellersProtectionStatus;
                    }
                }
                $this->processSucceededPush($newStatus, $response['message']);
                break;
            case 'BUCKAROO_MAGENTO2_STATUSCODE_NEUTRAL':
                $this->setOrderNotificationNote($response['message']);
                break;
            case 'BUCKAROO_MAGENTO2_STATUSCODE_PAYMENT_ON_HOLD':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING':
            case 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_USER_INPUT':
                $this->processPendingPaymentPush($newStatus, $response['message']);
                break;
        }
    }

    public function processCm3Push()
    {
        $invoiceKey = $this->postData['brq_invoicekey'];
        $savedInvoiceKey = $this->order->getPayment()->getAdditionalInformation('buckaroo_cm3_invoice_key');

        if ($invoiceKey != $savedInvoiceKey) {
            return;
        }

        // Skip invoice creation for PayPerEmail in pending state
        if ($this->isPayPerEmailB2BModePush() || $this->isPayPerEmailB2CModePush()) {
            $statusCode = $this->getStatusCode();
            if ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER')) {
                $this->logging->addDebug(__METHOD__ . '|Skipping CM3 invoice creation for pending PayPerEmail push|');
                return;
            }
        }

        if ($this->updateCm3InvoiceStatus()) {
            $this->sendCm3ConfirmationMail();
        }
    }

    private function updateCm3InvoiceStatus()
    {
        $isPaid     = filter_var(strtolower($this->postData['brq_ispaid']), FILTER_VALIDATE_BOOLEAN);
        $canInvoice = ($this->order->canInvoice() && !$this->order->hasInvoices());
        $store      = $this->order->getStore();

        $amount        = (float) ($this->postData['brq_amountdebit']);
        $amount        = $this->order->getBaseCurrency()->formatTxt($amount);
        $statusMessage = 'Payment push status : Creditmanagement invoice with a total amount of '
            . $amount . ' has been paid';

        if (!$isPaid && !$canInvoice) {
            $statusMessage = 'Payment push status : Creditmanagement invoice has been (partially) refunded';
        }

        if (!$isPaid && $canInvoice) {
            $statusMessage = 'Payment push status : Waiting for consumer';
        }

        if ($isPaid && $canInvoice) {
            $originalKey                        = AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
            $this->postData['brq_transactions'] = $this->order->getPayment()->getAdditionalInformation($originalKey);
            $this->postData['brq_amount']       = $this->postData['brq_amountdebit'];

            if (!$this->saveInvoice()) {
                return false;
            }
        }

        $this->updateOrderStatus($this->order->getState(), $this->order->getStatus(), $statusMessage);

        return true;
    }

    private function sendCm3ConfirmationMail()
    {
        $store         = $this->order->getStore();
        $cm3StatusCode = 0;

        if (isset($this->postData['brq_invoicestatuscode'])) {
            $cm3StatusCode = $this->postData['brq_invoicestatuscode'];
        }

        /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
        $paymentMethod   = $this->order->getPayment()->getMethodInstance();
        $configOrderMail = $this->configAccount->getOrderConfirmationEmail($store)
            || $paymentMethod->getConfigData('order_email', $store);

        if (!$this->order->getEmailSent() && $cm3StatusCode == 10 && $configOrderMail) {
            $this->orderSender->send($this->order);
        }
    }

    /**
     * @return bool
     */
    private function giftcardPartialPayment()
    {
        $payment = $this->order->getPayment();

        if ($payment->getMethod() != Giftcards::PAYMENT_METHOD_CODE) {
            return false;
        }

        $isPartialAmount = isset($this->postData['brq_amount']) && $this->postData['brq_amount'] < $this->order->getGrandTotal();

        $hasPartialFlag = !empty($this->postData['brq_relatedtransaction_partialpayment']);

        $isCaptureTransaction = (
            (isset($this->postData['brq_transaction_type']) && $this->postData['brq_transaction_type'] === 'C800') ||
            (isset($this->postData['brq_mutationtype']) && strtolower($this->postData['brq_mutationtype']) === 'collecting')
        );

        $isPartialPayment = ($isPartialAmount && $hasPartialFlag) || ($isPartialAmount && $isCaptureTransaction);

        if (!$isPartialPayment) {
            return false;
        }

        $this->logging->addDebug(__METHOD__ . '|PARTIAL_GIFTCARD_DETECTED|' . var_export([
            'payment_amount' => $this->postData['brq_amount'] ?? 'not_set',
            'order_total' => $this->order->getGrandTotal(),
            'has_partial_flag' => $hasPartialFlag,
            'is_capture' => $isCaptureTransaction,
        ], true));

        if ($this->groupTransaction->isGroupTransaction($this->postData['brq_invoicenumber'])) {
            return false;
        }

        if (!$this->isGroupTransactionInfoType()) {
            $transactionKey = $hasPartialFlag
                ? $this->postData['brq_relatedtransaction_partialpayment']
                : ($this->postData['brq_transactions'] ?? '');

            $payment->setAdditionalInformation(
                AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
                $transactionKey
            );

            $this->addGiftcardPartialPaymentToPaymentInformation();
        }

        return true;
    }

    protected function addGiftcardPartialPaymentToPaymentInformation()
    {
        $payment = $this->order->getPayment();

        $transactionAmount = (isset($this->postData['brq_amount'])) ? $this->postData['brq_amount'] : 0;
        $transactionKey    = (isset($this->postData['brq_transactions'])) ? $this->postData['brq_transactions'] : '';
        $transactionMethod = (isset($this->postData['brq_transaction_method'])) ?
            $this->postData['brq_transaction_method'] : '';

        $transactionData = $payment->getAdditionalInformation(AbstractMethod::BUCKAROO_ALL_TRANSACTIONS);

        $transactionArray = [];
        if (is_array($transactionData) && count($transactionData) > 0) {
            $transactionArray = $transactionData;
        }

        if (!empty($transactionKey) && $transactionAmount > 0) {
            $transactionArray[$transactionKey] = [$transactionMethod, $transactionAmount];

            $payment->setAdditionalInformation(
                AbstractMethod::BUCKAROO_ALL_TRANSACTIONS,
                $transactionArray
            );
        }
    }

    /**
     * Makes sure the order transactionkey has been set.
     */
    protected function setTransactionKey()
    {
        $payment        = $this->order->getPayment();
        $originalKey    = AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY;
        $transactionKey = $this->getTransactionKey();

        if (!$payment->getAdditionalInformation($originalKey) && strlen($transactionKey) > 0) {
            $payment->setAdditionalInformation($originalKey, $transactionKey);
        }
    }

    /**
     * Store additional transaction information to track multiple payments manually
     * Multiple Buckaroo pushes can resolve into incorrect
     */
    protected function setReceivedPaymentFromBuckaroo()
    {
        if (empty($this->postData['brq_transactions'])) {
            return;
        }

        $payment = $this->order->getPayment();

        if (!$payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS)) {
            $payment->setAdditionalInformation(
                self::BUCKAROO_RECEIVED_TRANSACTIONS,
                [$this->postData['brq_transactions'] => (float) ($this->postData['brq_amount'])]
            );
        } else {
            $buckarooTransactionKeysArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            $buckarooTransactionKeysArray[$this->postData['brq_transactions']] =
                (float) ($this->postData['brq_amount']);

            $payment->setAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS, $buckarooTransactionKeysArray);
        }
    }

    /**
     * It updates the BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES payment additional information
     * with the current received tx status.
     */
    protected function setReceivedTransactionStatuses(): void
    {
        $txId = $this->postData['brq_transactions'];
        $statusCode = $this->postData['brq_statuscode'];

        if (empty($txId) || empty($statusCode)) {
            return;
        }

        $payment = $this->order->getPayment();

        $receivedTxStatuses = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES) ?? [];
        $receivedTxStatuses[$txId] = $statusCode;

        $payment->setAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS_STATUSES, $receivedTxStatuses);
    }

    /**
     * @return string
     */
    private function getTransactionKey()
    {
        $trxId = '';

        if (isset($this->postData['brq_transactions']) && !empty($this->postData['brq_transactions'])) {
            $trxId = $this->postData['brq_transactions'];
        }

        if (isset($this->postData['brq_datarequest']) && !empty($this->postData['brq_datarequest'])) {
            $trxId = $this->postData['brq_datarequest'];
        }

        if (isset($this->postData['brq_service_klarna_autopaytransactionkey'])
            && !empty($this->postData['brq_service_klarna_autopaytransactionkey'])
        ) {
            $trxId = $this->postData['brq_service_klarna_autopaytransactionkey'];
        }

        if (isset($this->postData['brq_service_klarnakp_autopaytransactionkey'])
            && !empty($this->postData['brq_service_klarnakp_autopaytransactionkey'])
        ) {
            $trxId = $this->postData['brq_service_klarnakp_autopaytransactionkey'];
        }

        if (!empty($this->postData['brq_relatedtransaction_refund'])
            && isset($this->postData['brq_relatedtransaction_refund'])
        ) {
            $trxId = $this->postData['brq_relatedtransaction_refund'];
        }

        return $trxId;
    }

    /**
     * Sometimes the push does not contain the order id, when thats the case try to get the order by his payment,
     * by using its own transactionkey.
     *
     * @return Order
     * @throws Exception|LocalizedException
     */
    protected function getOrderByTransactionKey()
    {
        $trxId = $this->getTransactionKey();

        $this->transaction->load($trxId, 'txn_id');
        $order = $this->transaction->getOrder();

        if (!$order) {
            throw new Exception(__('There was no order found by transaction Id'));
        }

        return $order;
    }

    /**
     * Checks if the order can be updated by checking its state and status.
     *
     * @param mixed $response
     * @return bool
     * @throws LocalizedException
     */
    protected function canUpdateOrderStatus($response)
    {
        // Define the final states where the order should not normally be updated.
        $finalStates = [
            Order::STATE_COMPLETE,
            Order::STATE_CANCELED,
            Order::STATE_CLOSED,
            Order::STATE_HOLDED,
        ];

        $currentState = $this->order->getState();
        $this->logging->addDebug(__METHOD__ . '|Current state|' . $currentState);

        // If the order is not in a final state, allow the update.
        if (!in_array($currentState, $finalStates)) {
            return true;
        }

        if ($currentState === Order::STATE_CANCELED
            && $response['status'] === 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && !isset($this->postData['brq_relatedtransaction_partialpayment'])
        ) {
            $this->order->load($this->order->getId());

            $this->logging->addDebug(__METHOD__ . '|Re-opening canceled order|');

            $this->order->setState(Order::STATE_PROCESSING)
                ->setStatus(Order::STATE_PROCESSING)
                ->setData('buckaroo_reopened', true);

            foreach ($this->order->getAllItems() as $item) {
                $item->setQtyCanceled(0);
            }

            $this->forceInvoice = true;
            return true;
        }

        return false;
    }

    /**
     * @param $newStatus
     * @param $message
     *
     * @throws \Exception
     * @return bool
     */
    public function processFailedPush($newStatus, $message)
    {
        $this->logging->addDebug(sprintf(
            '[%s:%s] - Process the failed push response from Buckaroo | newStatus: %s',
            __METHOD__,
            __LINE__,
            var_export($newStatus, true)
        ));

        if (($this->order->getState() === Order::STATE_PROCESSING)
            && ($this->order->getStatus() === Order::STATE_PROCESSING)
        ) {
            $this->logging->addDebug('['. __METHOD__ .':'. __LINE__ . '] - Do not update to failed if we had a success');
            return false;
        }

        $description = 'Payment status : ' . $message;

        if (isset($this->postData['brq_service_antifraud_action'])) {
            $description .= $this->postData['brq_service_antifraud_action'] .
                ' ' .
                $this->postData['brq_service_antifraud_check'] .
                ' ' .
                $this->postData['brq_service_antifraud_details'];
        }

        $store = $this->order->getStore();
        $buckarooCancelOnFailed = $this->configAccount->getCancelOnFailed($store);
        $payment = $this->order->getPayment();

        // Handle PayPerEmail cancellations
        if ($payment->getMethod() == PayPerEmail::PAYMENT_METHOD_CODE) {
            $this->logging->addDebug(__METHOD__ . '|Handling PayPerEmail cancellation|');
            if ($this->order->canCancel()) {
                $this->orderCancellationService->cancelOrder($this->order, sprintf('PayPerEmail transaction failure: %s', $message), true);

                // Void any existing invoices
                foreach ($this->order->getInvoiceCollection() as $invoice) {
                    if ($invoice->canVoid()) {
                        $invoice->void();
                        $invoice->save();
                        $this->logging->addDebug(__METHOD__ . '|Voided invoice: ' . $invoice->getIncrementId());
                    }
                }
            }
            return true;
        }

        if ($buckarooCancelOnFailed && $this->order->canCancel()) {
            $this->logging->addDebug(sprintf(
                '[%s:%s] - Process the failed push response from Buckaroo. Cancel Order: %s',
                __METHOD__,
                __LINE__,
                $message
            ));

            // BUCKM2-78: Never automatically cancelauthorize via push for afterpay
            // setting parameter which will cause to stop the cancel process on
            // Buckaroo/Model/Method/AbstractMethod.php:880
            $methods = [
                'buckaroo_magento2_afterpay',
                'buckaroo_magento2_afterpay2',
                'buckaroo_magento2_klarna',
                'buckaroo_magento2_klarnakp',
            ];
            if (in_array($payment->getMethodInstance()->getCode(), $methods)) {
                $payment->setAdditionalInformation('buckaroo_failed_authorize', 1);
                $payment->save();
            }

            $this->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description);

            try {
                $this->orderCancellationService->cancelOrder($this->order, $description, true);
            } catch (\Throwable $th) {
                $this->logging->addError(sprintf(
                    '[%s:%s] - Process failed push from Buckaroo. Cancel Order| [ERROR]: %s',
                    __METHOD__,
                    __LINE__,
                    $th->getMessage()
                ));
            }
            return true;
        }

        $force = false;
        if (($payment->getMethodInstance()->getCode() == 'buckaroo_magento2_mrcash')
            && ($this->order->getState() === Order::STATE_NEW)
            && ($this->order->getStatus() === 'pending')
        ) {
            $force = true;
        }
        $this->updateOrderStatus(Order::STATE_CANCELED, $newStatus, $description, $force);

        return true;
    }

    /**
     * @param $newStatus
     * @param $message
     *
     * @return bool
     */
    public function processSucceededPush($newStatus, $message)
    {
        $this->logging->addDebug(__METHOD__ . '|1|' . var_export($newStatus, true));

        if (!empty($this->postData['brq_relatedtransaction_partialpayment'])) {
            $this->logging->addDebug(__METHOD__ . '|1_1|' . '|Partial payment detected state change skipped');
            return true;
        }

        $amount = $this->order->getTotalDue();

        if (isset($this->postData['brq_amount']) && !empty($this->postData['brq_amount'])) {
            $this->logging->addDebug(__METHOD__ . '|11|');
            $amount = (float) ($this->postData['brq_amount']);
        }

        if (isset($this->postData['brq_service_klarna_reservationnumber'])
            && !empty($this->postData['brq_service_klarna_reservationnumber'])
        ) {
            $reservationNumber = $this->postData['brq_service_klarna_reservationnumber'];
            $this->logging->addDebug(__METHOD__ . '|Saving Klarna reservation number: ' . $reservationNumber . ' for order: ' . $this->order->getIncrementId());

            $this->order->setBuckarooReservationNumber($reservationNumber);

            try {
                $this->order->save();

                // Verify it was saved correctly
                $this->order = $this->order->load($this->order->getId());
                $savedReservationNumber = $this->order->getBuckarooReservationNumber();

                if ($savedReservationNumber !== $reservationNumber) {
                    $this->logging->addError(__METHOD__ . '|Klarna reservation number save failed - Order: ' . $this->order->getIncrementId() . ', Expected: ' . $reservationNumber . ', Got: ' . ($savedReservationNumber ?: 'NULL'));
                }
            } catch (\Exception $e) {
                $this->logging->addError(__METHOD__ . '|Failed to save Klarna reservation number: ' . $e->getMessage() . ' - Order: ' . $this->order->getIncrementId());
            }
        }

        if (isset($this->postData['brq_service_klarnakp_reservationnumber'])
            && !empty($this->postData['brq_service_klarnakp_reservationnumber'])
        ) {
            $reservationNumber = $this->postData['brq_service_klarnakp_reservationnumber'];
            $this->logging->addDebug(__METHOD__ . '|Saving KlarnaKP reservation number: ' . $reservationNumber . ' for order: ' . $this->order->getIncrementId());

            $this->order->setBuckarooReservationNumber($reservationNumber);

            try {
                $this->order->save();

                // Verify it was saved correctly
                $this->order = $this->order->load($this->order->getId());
                $savedReservationNumber = $this->order->getBuckarooReservationNumber();

                if ($savedReservationNumber !== $reservationNumber) {
                    $this->logging->addError(__METHOD__ . '|KlarnaKP reservation number save failed - Order: ' . $this->order->getIncrementId() . ', Expected: ' . $reservationNumber . ', Got: ' . ($savedReservationNumber ?: 'NULL'));
                }
            } catch (\Exception $e) {
                $this->logging->addError(__METHOD__ . '|Failed to save KlarnaKP reservation number: ' . $e->getMessage() . ' - Order: ' . $this->order->getIncrementId());
            }
        }

        if (isset($this->postData['brq_service_klarnakp_reservationnumber'])) {
            $this->updateTransactionIsClosed($this->order);
        }

        $store = $this->order->getStore();

        $payment = $this->order->getPayment();

        /**
         * @var \Magento\Payment\Model\MethodInterface $paymentMethod
         */
        $paymentMethod = $payment->getMethodInstance();

        if (!$this->order->getEmailSent()
            && (
                $this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->logging->addDebug(__METHOD__ . '|sendemail|' .
                var_export($this->configAccount->getOrderConfirmationEmailSync($store), true));
            $this->orderSender->send($this->order, $this->configAccount->getOrderConfirmationEmailSync($store));
        }

        /** force state eventhough this can lead to a transition of the order
         *  like new -> processing
         */
        $forceState = false;
        $state      = Order::STATE_PROCESSING;

        $this->logging->addDebug(__METHOD__ . '|2|');

        // Check if this is a capture transaction (C800) that should create an invoice
        $isCapture = isset($this->postData['brq_transaction_type']) && $this->postData['brq_transaction_type'] === 'C800';
        $isCaptureMutation = isset($this->postData['brq_mutationtype']) && strtolower($this->postData['brq_mutationtype']) === 'collecting';

        if ($isCapture || $isCaptureMutation) {
            $this->logging->addDebug(__METHOD__ . '|CAPTURE_DETECTED|' . var_export([
                'brq_transaction_type' => $this->postData['brq_transaction_type'] ?? 'not_set',
                'brq_mutationtype' => $this->postData['brq_mutationtype'] ?? 'not_set',
            ], true));

            // Force invoice creation for capture transactions
            $description = 'Capture status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been captured.';

            if (!$this->saveInvoice()) {
                $this->logging->addDebug(__METHOD__ . '|CAPTURE_INVOICE_FAILED|');
                return false;
            }

            $this->updateOrderStatus($state, $newStatus, $description, $forceState);
            $this->logging->addDebug(__METHOD__ . '|CAPTURE_COMPLETE|');
            return true;
        }

        if ($paymentMethod->canPushInvoice($this->postData)) {
            $this->logging->addDebug(__METHOD__ . '|3|');
            $description = 'Payment status : <strong>' . $message . "</strong><br/>";
            if ($this->hasPostData('brq_transaction_method', 'transfer')) {
                //keep amount fetched from brq_amount
                $description .= 'Amount of ' . $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
            } else {
                $amount = $this->order->getBaseTotalDue();
                $description .= 'Total amount of ' .
                    $this->order->getBaseCurrency()->formatTxt($amount) . ' has been paid';
            }
        } else {
            $description = 'Authorization status : <strong>' . $message . "</strong><br/>";
            $description .= 'Total amount of ' . $this->order->getBaseCurrency()->formatTxt($this->order->getTotalDue())
                . ' has been authorized. Please create an invoice to capture the authorized amount.';
            $forceState = true;
        }

        if ($this->isPayPerEmailB2BModePushInitial) {
            $description = '';
        }

        $this->dontSaveOrderUponSuccessPush = false;
        if ($paymentMethod->canPushInvoice($this->postData)) {
            $this->logging->addDebug(__METHOD__ . '|4|');

            if (!$this->isPayPerEmailB2BModePushInitial && $this->isPayPerEmailB2BModePushPaid()) {
                $this->logging->addDebug(__METHOD__ . '|4_1|');
                //Fix for suspected fraud when the order currency does not match with the payment's currency
                $amount = ($payment->isSameCurrency() && $payment->isCaptureFinal($this->order->getGrandTotal())) ?
                    $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
                $payment->registerCaptureNotification($amount);
                $payment->save();
                $this->order->setState('complete');
                $this->order->addStatusHistoryComment($description, 'complete');
                $this->order->save();

                if ($transactionKey = $this->getTransactionKey()) {
                    foreach ($this->order->getInvoiceCollection() as $invoice) {
                        $invoice->setTransactionId($transactionKey)->save();
                    }
                }
                return true;
            }

            if ($this->hasPostData('add_initiated_by_magento', 1) &&
                (
                    $this->hasPostData('brq_transaction_method', 'KlarnaKp') &&
                    $this->hasPostData('add_service_action_from_magento', 'pay') &&
                    empty($this->postData['brq_service_klarnakp_reservationnumber']) &&
                    $this->klarnakpConfig->getCreateInvoiceAfterShipment()
                ) ||
                (
                    $this->hasPostData('brq_transaction_method', 'afterpay') &&
                    $this->hasPostData('add_service_action_from_magento', 'capture') &&
                    $this->afterpayConfig->getCreateInvoiceAfterShipment()
                )
            ) {
                $this->logging->addDebug(__METHOD__ . '|5_1|');
                $this->dontSaveOrderUponSuccessPush = true;
                return true;
            } else {
                $this->logging->addDebug(__METHOD__ . '|6|');

                if ($this->hasPostData('brq_transaction_method', 'transfer')) {
                    //invoice only in case of full or last remained amount
                    $this->logging->addDebug(__METHOD__ . '|61|' . var_export([
                            $this->order->getId(),
                            $amount,
                            $this->order->getTotalDue(),
                            $this->order->getTotalPaid(),
                        ], true));

                    $saveInvoice = true;
                    if (($amount < $this->order->getTotalDue())
                        || (($amount == $this->order->getTotalDue()) && ($this->order->getTotalPaid() > 0))
                    ) {
                        $this->logging->addDebug(__METHOD__ . '|64|');

                        $forceState = true;
                        if ($amount < $this->order->getTotalDue()) {
                            $this->logging->addDebug(__METHOD__ . '|65|');
                            $state       = Order::STATE_NEW;
                            $newStatus   = $this->orderStatusFactory->get(
                                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_PENDING_PROCESSING'),
                                $this->order
                            );
                            $saveInvoice = false;
                        }

                        $this->saveAndReloadOrder();

                        $this->order->setTotalDue($this->order->getTotalDue() - $amount);
                        $this->order->setBaseTotalDue($this->order->getTotalDue() - $amount);

                        $totalPaid = $this->order->getTotalPaid() + $amount;
                        $this->order->setTotalPaid(
                            $totalPaid > $this->order->getGrandTotal() ? $this->order->getGrandTotal() : $totalPaid
                        );

                        $baseTotalPaid = $this->order->getBaseTotalPaid() + $amount;
                        $this->order->setBaseTotalPaid(
                            $baseTotalPaid > $this->order->getBaseGrandTotal() ?
                                $this->order->getBaseGrandTotal() : $baseTotalPaid
                        );

                        $this->saveAndReloadOrder();

                        $connection = $this->resourceConnection->getConnection();
                        $connection->update(
                            $connection->getTableName('sales_order'),
                            [
                                'total_due'       => $this->order->getTotalDue(),
                                'base_total_due'  => $this->order->getTotalDue(),
                                'total_paid'      => $this->order->getTotalPaid(),
                                'base_total_paid' => $this->order->getBaseTotalPaid(),
                            ],
                            $connection->quoteInto('entity_id = ?', $this->order->getId())
                        );

                    }

                    if ($saveInvoice) {
                        if (!$this->saveInvoice()) {
                            return false;
                        }
                    }

                } else {

                    if (!$this->saveInvoice()) {
                        return false;
                    }

                }

            }
        }

        if (!empty($this->postData['brq_service_klarna_autopaytransactionkey'])
            && ($this->postData['brq_statuscode'] == 190)
        ) {
            $this->saveInvoice();
        }

        if (!empty($this->postData['brq_service_klarnakp_autopaytransactionkey'])
            && ($this->postData['brq_statuscode'] == 190)
        ) {
            $this->saveInvoice();
        }

        if ($this->groupTransaction->isGroupTransaction($this->postData['brq_invoicenumber'])) {
            $forceState = true;
        }

        $this->logging->addDebug(__METHOD__ . '|8|');

        $this->processSucceededPushAuth($payment);

        $this->updateOrderStatus($state, $newStatus, $description, $forceState);

        $this->logging->addDebug(__METHOD__ . '|9|');

        return true;
    }

    protected function updateTransactionIsClosed(Order $order)
    {
        // Only re-open if the order is currently canceled.
        if ($order->getState() !== Order::STATE_CANCELED && !$order->getData('buckaroo_reopened')) {
            $this->logging->addDebug(__METHOD__ . '| Order is not canceled (current state: ' . $order->getState() . '), skipping re-opening.');
            return;
        }

        // 1) Re-open the order
        $this->logging->addDebug(__METHOD__ . '| Re-opening canceled order ID: ' . $order->getId());

        // 2) Switch to "processing" (or "pending") and reset canceled item quantities
        $order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING);
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyCanceled() > 0) {
                $item->setQtyCanceled(0);
            }
        }
        $order->addStatusHistoryComment(
            __('Order was re-opened from canceled state after a successful Klarna push.')
        );
        $order->save();

        // 3) Re-open the payment object
        $payment = $order->getPayment();
        if ($payment) {
            // Force Magento to see the parent transaction as still "open"
            $payment->setIsTransactionClosed(false);
            $payment->setShouldCloseParentTransaction(false);
            $payment->save();
        }

        // 4) Load all transactions for this order and set is_closed=0
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('order_id', $order->getId())
                ->create();
            $transactionList = $this->transactionRepository->getList($searchCriteria);

            foreach ($transactionList->getItems() as $txn) {
                if ($txn->getIsClosed()) {
                    $txn->setIsClosed(0);
                    $this->transactionRepository->save($txn);
                    $this->logging->addDebug(__METHOD__ . '|Re-open transaction ' . $txn->getTxnId());
                }
            }
        } catch (\Exception $e) {
            $this->logging->addError(__METHOD__ . '|Could not re-open transactions: ' . $e->getMessage());
        }
    }

    /**
     * @param $newStatus
     * @param $message
     *
     * @return bool
     */
    public function processPendingPaymentPush($newStatus, $message)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');

        $store   = $this->order->getStore();
        $payment = $this->order->getPayment();

        /** @var \Magento\Payment\Model\MethodInterface $paymentMethod */
        $paymentMethod = $payment->getMethodInstance();

        // Transfer has a slightly different flow where a successful order has a 792 status code instead of an 190 one
        if (!$this->order->getEmailSent()
            && in_array($payment->getMethod(), [Transfer::PAYMENT_METHOD_CODE,
                SepaDirectDebit::PAYMENT_METHOD_CODE,
                PayPerEmail::PAYMENT_METHOD_CODE,
            ])
            && (
                $this->configAccount->getOrderConfirmationEmail($store)
                || $paymentMethod->getConfigData('order_email', $store)
            )
        ) {
            $this->logging->addDebug(__METHOD__ . '|sendemail|');
            $this->orderSender->send($this->order);
        }

        $description = 'Payment push status : ' . $message;

        // $this->updateOrderStatus(Order::STATE_PROCESSING, $newStatus, $description);

        return true;
    }

    /**
     * Try to add an notification note to the order comments.
     *
     * @param $message
     */
    protected function setOrderNotificationNote($message)
    {
        $note = 'Buckaroo attempted to update this order, but failed: ' . $message;
        try {
            $this->order->addStatusHistoryComment($note);
            $this->order->save();
        } catch (Exception $e) {
            $this->logging->addDebug($e->getLogMessage());
        }
    }

    /**
     * Updates the order state and add a comment.
     *
     * @param             $orderState
     * @param             $description
     * @param             $newStatus
     * @param             $force
     * @throws \Exception
     */
    protected function updateOrderStatus($orderState, $newStatus, $description, $force = false)
    {
        $this->logging->addDebug(sprintf(
            '[ORDER] | [Service] | [%s:%s] - Updates the order state and add a comment | data: %s',
            __METHOD__,
            __LINE__,
            var_export([
                'orderState' => $orderState,
                'newStatus'  => $newStatus,
                'description' => $description,
            ], true)
        ));
        if ($this->order->getState() == $orderState || $force) {
            if ($this->dontSaveOrderUponSuccessPush) {
                $this->order->addCommentToStatusHistory($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->setStatus($newStatus)
                    ->save();
            } else {
                $this->order->addCommentToStatusHistory($description, $newStatus);
            }
        } else {
            if ($this->dontSaveOrderUponSuccessPush) {
                $this->order->addCommentToStatusHistory($description)
                    ->setIsCustomerNotified(false)
                    ->setEntityName('invoice')
                    ->save();
            } else {
                $this->order->addCommentToStatusHistory($description);
            }
        }
    }

    /**
     * Creates and saves the invoice and adds for each invoice the buckaroo transaction keys
     * Only when the order can be invoiced and has not been invoiced before.
     *
     * @return bool
     * @throws Exception|LocalizedException
     */
    protected function saveInvoice()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if (!$this->forceInvoice) {
            if (!$this->order->canInvoice() || $this->order->hasInvoices()) {
                $this->logging->addDebug('Order can not be invoiced');
                return false;
            }
        }

        // Skip invoice creation for pending PayPerEmail transactions
        if ($this->isPayPerEmailB2BModePush() || $this->isPayPerEmailB2CModePush()) {
            $statusCode = $this->getStatusCode();
            if ($statusCode == $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER')) {
                $this->logging->addDebug(__METHOD__ . '|Skipping invoice creation for pending PayPerEmail transaction|');
                return false;
            }
        }

        $this->logging->addDebug(__METHOD__ . '|5|');

        if (!$this->isGroupTransactionInfoType()) {
            $this->addTransactionData();
        }

        $payment = $this->order->getPayment();
        $invoiceHandlingConfig = $this->configAccount->getInvoiceHandling($this->order->getStore());

        if ($invoiceHandlingConfig == InvoiceHandlingOptions::SHIPMENT) {
            $payment->setAdditionalInformation(InvoiceHandlingOptions::INVOICE_HANDLING, $invoiceHandlingConfig);
            $payment->save();

            if ($this->hasPostData('brq_transaction_method', 'transfer')) {
                $this->order->setIsInProcess(true);
                $this->order->save();
            }

            return true;
        }

        $invoiceAmount = 0;
        if (!empty($this->postData['brq_amount'])) {
            $invoiceAmount = (float) ($this->postData['brq_amount']);
        }
        if (($payment->getMethod() == Giftcards::PAYMENT_METHOD_CODE)
            && $invoiceAmount != $this->order->getGrandTotal()
        ) {
            $this->setReceivedPaymentFromBuckaroo();

            $payment->registerCaptureNotification($invoiceAmount, true);
            $payment->save();

            $receivedPaymentsArray = $payment->getAdditionalInformation(self::BUCKAROO_RECEIVED_TRANSACTIONS);

            if (!is_array($receivedPaymentsArray)) {
                return;
            }

            $payment->capture(); //creates invoice
            $payment->save();
        } elseif ($this->isPayPerEmailB2BModePushInitial) {
            $this->logging->addDebug(__METHOD__ . '|10|');
            $invoice = $this->order->prepareInvoice()->register();
            $invoice->setOrder($this->order);
            $this->order->addRelatedObject($invoice);
            $payment->setCreatedInvoice($invoice);
            $payment->setShouldCloseParentTransaction(true);
        } else {
            $this->logging->addDebug(__METHOD__ . '|15|');
            //Fix for suspected fraud when the order currency does not match with the payment's currency
            $amount = ($payment->isSameCurrency()
                && $payment->isCaptureFinal($this->order->getGrandTotal())) ?
                $this->order->getGrandTotal() : $this->order->getBaseTotalDue();
            $payment->registerCaptureNotification($amount);
            $payment->save();
        }

        $this->logging->addDebug(__METHOD__ . '|20|');

        $transactionKey = $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            return true;
        }

        $this->logging->addDebug(__METHOD__ . '|25|');

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        foreach ($this->order->getInvoiceCollection() as $invoice) {
            $invoice->setTransactionId($transactionKey)->save();

            if (!empty($this->postData['brq_invoicenumber'])) {
                if ($this->groupTransaction->isGroupTransaction($this->postData['brq_invoicenumber'])) {
                    $this->logging->addDebug(__METHOD__ . '|27|');
                    $invoice->setState(2);
                }
            }

            if (!$invoice->getEmailSent() && $this->configAccount->getInvoiceEmail($this->order->getStore())) {
                $this->logging->addDebug(__METHOD__ . '|30|sendinvoiceemail');
                $this->invoiceSender->send($invoice, true);
            }
        }

        $this->logging->addDebug(__METHOD__ . '|35|');

        $this->order->setIsInProcess(true);
        $this->order->save();

        $this->dontSaveOrderUponSuccessPush = true;

        return true;
    }

    /**
     * @param  mixed                                           $transactionKey
     * @param  mixed                                           $datas
     * @throws LocalizedException
     * @return Order\Payment
     */
    public function addTransactionData($transactionKey = false, $datas = false)
    {
        /**
         * @var \Magento\Sales\Model\Order\Payment $payment
         */
        $payment = $this->order->getPayment();

        $transactionKey = $transactionKey ?: $this->getTransactionKey();

        if (strlen($transactionKey) <= 0) {
            throw new Exception(__('There was no transaction ID found'));
        }

        /**
         * Save the transaction's response as additional info for the transaction.
         */
        $postData = $datas ?: $this->postData;
        $rawInfo  = $this->helper->getTransactionAdditionalInfo($postData);

        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            $rawInfo
        );

        $rawDetails = $payment->getAdditionalInformation(Transaction::RAW_DETAILS);
        $rawDetails = $rawDetails ?: [];
        $rawDetails[$transactionKey] = $rawInfo;
        $payment->setAdditionalInformation(Transaction::RAW_DETAILS, $rawDetails);

        /**
         * Save the payment's transaction key.
         */
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        if ($this->hasPostData('brq_transaction_method', 'KlarnaKp')) {
            $payment->setTransactionId($transactionKey);
        } else {
            $payment->setTransactionId($transactionKey . '-capture');
        }
        /**
         * @noinspection PhpUndefinedMethodInspection
         */
        $payment->setParentTransactionId($transactionKey);
        $payment->setAdditionalInformation(
            \Buckaroo\Magento2\Model\Method\AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
            $transactionKey
        );

        return $payment;
    }

    private function isGroupTransactionInfoType()
    {
        if (!empty($this->postData['brq_transaction_type'])
            && ($this->postData['brq_transaction_type'] == self::BUCK_PUSH_GROUPTRANSACTION_TYPE)
        ) {
            return true;
        }
        return false;
    }

    private function isGroupTransactionInfo()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        if ($this->isGroupTransactionInfoType()) {
            if ($this->postData['brq_statuscode'] !=
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
            ) {
                return true;
            }
        }
        return false;
    }

    private function isGroupTransactionPart()
    {
        if (isset($this->postData['brq_transactions'])) {
            return $this->groupTransaction->getGroupTransactionByTrxId($this->postData['brq_transactions']);
        }
        return false;
    }

    private function isGroupTransactionFailed()
    {
        if ($this->isGroupTransactionInfoType()) {
            if ($this->postData['brq_statuscode'] ==
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED')
            ) {
                return true;
            }
        }
        return false;
    }

    private function savePartGroupTransaction()
    {
        $items = $this->groupTransaction->getGroupTransactionByTrxId($this->postData['brq_transactions']);
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $key => $item) {
                $item2['status']    = $this->postData['brq_statuscode'];
                $item2['entity_id'] = $item['entity_id'];
                $this->groupTransaction->updateGroupTransaction($item2);
            }
        }
    }

    private function receivePushCheckPayLink($response, $validSignature)
    {
        if (isset($this->postData['add_frompaylink'])
            && $response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS'
            && $validSignature
        ) {
            $payment = $this->order->getPayment();
            $payment->setMethod('buckaroo_magento2_payperemail');
            $payment->save();
            $this->order->save();
            return true;
        }
        return false;
    }

    private function pushCheckPayPerEmailCancel($response, $validSignature, $payment)
    {
        $failedStatuses = [
            'BUCKAROO_MAGENTO2_STATUSCODE_TECHNICAL_ERROR',
            'BUCKAROO_MAGENTO2_STATUSCODE_VALIDATION_FAILURE',
            'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_MERCHANT',
            'BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER',
            'BUCKAROO_MAGENTO2_STATUSCODE_FAILED',
            'BUCKAROO_MAGENTO2_STATUSCODE_REJECTED',
        ];
        $status = $this->helper->getStatusByValue($this->postData['brq_statuscode'] ?? '');
        if ((isset($this->originalPostData['ADD_fromPayPerEmail'])
                || ($payment->getMethod() == 'buckaroo_magento2_payperemail'))
            && isset($this->originalPostData['brq_transaction_method'])
            && (
                (in_array($response['status'], $failedStatuses))
                || (in_array($status, $failedStatuses))
            ) && $validSignature
        ) {
            $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
            if ($config->getEnabledCronCancelPPE()) {
                $this->logging->addDebug(__METHOD__ . '|Skipping cancellation due to cron configuration|');
                return true;
            }

            // Cancel the order and void any invoices
            if ($this->order->canCancel()) {
                $this->logging->addDebug(__METHOD__ . '|Canceling order for PayPerEmail failure|');
                $this->orderCancellationService->cancelOrder($this->order, sprintf('PayPerEmail transaction failure: %s', $this->postData['brq_statusmessage']), true);

                // Void any existing invoices
                foreach ($this->order->getInvoiceCollection() as $invoice) {
                    if ($invoice->canVoid()) {
                        $invoice->void();
                        $invoice->save();
                        $this->logging->addDebug(__METHOD__ . '|Voided invoice: ' . $invoice->getIncrementId());
                    }
                }
            }

            return true;
        }
        return false;
    }

    private function receivePushCheckPayPerEmail($response, $validSignature, $payment)
    {
        $status = $this->helper->getStatusByValue($this->postData['brq_statuscode'] ?? '');
        if ((isset($this->postData['add_frompayperemail'])
                || ($payment->getMethod() == 'buckaroo_magento2_payperemail'))
            && isset($this->postData['brq_transaction_method'])
            && (
                ($response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
                || ($status == 'BUCKAROO_MAGENTO2_STATUSCODE_SUCCESS')
            ) && $validSignature
        ) {
            if ($this->postData['brq_transaction_method'] != 'payperemail') {
                $brq_transaction_method = strtolower($this->postData['brq_transaction_method']);
                $payment                = $this->order->getPayment();
                $payment->setAdditionalInformation('isPayPerEmail', $brq_transaction_method);

                $options = new \Buckaroo\Magento2\Model\Config\Source\PaymentMethods\PayPerEmail();
                foreach ($options->toOptionArray() as $item) {
                    if (($item['value'] == $brq_transaction_method) && isset($item['code'])) {
                        $payment->setMethod($item['code']);
                        $payment->setAdditionalInformation(
                            AbstractMethod::BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY,
                            $this->getTransactionKey()
                        );
                        if ($item['code'] == 'buckaroo_magento2_creditcards') {
                            $payment->setAdditionalInformation('customer_creditcardcompany', $brq_transaction_method);
                        }
                    }
                }
                $payment->save();
                $this->order->save();
                return true;
            }
        }
        return false;
    }

    public function isPayPerEmailB2BModePush()
    {
        if (isset($this->postData['add_frompayperemail'])
            && isset($this->postData['brq_transaction_method'])
            && ($this->postData['brq_transaction_method'] == 'payperemail')
        ) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
            if ($config->getEnabledB2B()) {
                $this->logging->addDebug(__METHOD__ . '|5|');
                return true;
            }
        }
        return false;
    }

    public function isPayPerEmailB2CModePush()
    {
        if (isset($this->postData['add_frompayperemail'])
            && isset($this->postData['brq_transaction_method'])
            && ($this->postData['brq_transaction_method'] == 'payperemail')
        ) {
            $this->logging->addDebug(__METHOD__ . '|1|');
            $config = $this->configProviderMethodFactory->get(PayPerEmail::PAYMENT_METHOD_CODE);
            if (!$config->getEnabledB2B()) {
                $this->logging->addDebug(__METHOD__ . '|5|');
                return true;
            }
        }
        return false;
    }

    public function isPayPerEmailB2BModePushInitial($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        return $this->isPayPerEmailB2BModePush()
            && ($response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');
    }

    public function isPayPerEmailB2CModePushInitial($response)
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        return $this->isPayPerEmailB2CModePush()
            && ($response['status'] == 'BUCKAROO_MAGENTO2_STATUSCODE_WAITING_ON_CONSUMER');
    }

    public function isPayPerEmailB2BModePushPaid()
    {
        $this->logging->addDebug(__METHOD__ . '|1|');
        return $this->isPayPerEmailB2BModePush();
    }

    private function getOrderIncrementId()
    {
        $brqOrderId = false;

        if (isset($this->postData['brq_invoicenumber']) && strlen($this->postData['brq_invoicenumber']) > 0) {
            $brqOrderId = $this->postData['brq_invoicenumber'];
        }

        if (isset($this->postData['brq_ordernumber']) && strlen($this->postData['brq_ordernumber']) > 0) {
            $brqOrderId = $this->postData['brq_ordernumber'];
        }

        return $brqOrderId;
    }

    private function processSucceededPushAuth($payment)
    {
        $authPaymentMethods = [
            Afterpay::PAYMENT_METHOD_CODE,
            Afterpay2::PAYMENT_METHOD_CODE,
            Afterpay20::PAYMENT_METHOD_CODE,
            Creditcard::PAYMENT_METHOD_CODE,
            Creditcards::PAYMENT_METHOD_CODE,
            Klarnakp::PAYMENT_METHOD_CODE,
        ];

        if (in_array($payment->getMethod(), $authPaymentMethods)) {
            if ((
                ($payment->getMethod() == Klarnakp::PAYMENT_METHOD_CODE)
                    || (
                        !empty($this->postData['brq_transaction_type'])
                        && in_array($this->postData['brq_transaction_type'], ['I038', 'I880'])
                    )
            ) && !empty($this->postData['brq_statuscode'])
                && ($this->postData['brq_statuscode'] == 190)
            ) {
                $this->logging->addDebug(__METHOD__ . '|88|' . var_export($payment->getMethod(), true));
                $this->order->setState(Order::STATE_PROCESSING);
                $this->order->save();
            }
        }
    }

    /**
     * Handle push from main group transaction fail
     */
    protected function handleGroupTransactionFailed()
    {
        try {
            $this->cancelOrder(
                $this->postData['brq_invoicenumber']
            );
            $this->groupTransaction->setGroupTransactionsStatus(
                $this->postData['brq_transactions'],
                $this->postData['brq_statuscode']
            );
        } catch (\Throwable $th) {
            $this->logging->addDebug(__METHOD__ . '|'.(string)$th);
        }
    }

    /**
     * Check if is a failed transaction
     *
     * @return bool
     */
    protected function isFailedGroupTransaction()
    {
        return $this->hasPostData(
            'brq_transaction_type',
            self::BUCK_PUSH_GROUPTRANSACTION_TYPE
        ) &&
            $this->hasPostData(
                'brq_statuscode',
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_FAILED')
            );
    }


    /**
     * Ship push handling for a failed transaction
     */
    protected function skipHandlingForFailedGroupTransactions()
    {
        return
            $this->order !== null &&
            $this->order->getId() !== null &&
            $this->order->getState() == Order::STATE_CANCELED &&
            (
                $this->hasPostData(
                    'brq_transaction_type',
                    'V202'
                ) ||

                $this->hasPostData(
                    'brq_transaction_type',
                    'V203'
                ) ||
                $this->hasPostData(
                    'brq_transaction_type',
                    'V204'
                )
            );
    }
    /**
     * Get quote by increment/reserved order id
     *
     * @param string $reservedOrderId
     *
     * @return Quote|null
     */
    protected function getQuoteByReservedOrderId(string $reservedOrderId)
    {
        /** @var \Magento\Quote\Model\QuoteFactory */
        $quoteFactory = $this->objectManager->get('Magento\Quote\Model\QuoteFactory');
        /** @var \Magento\Quote\Model\ResourceModel\Quote */
        $quoteResourceModel = $this->objectManager->get('Magento\Quote\Model\ResourceModel\Quote');

        $quote = $quoteFactory->create();

        $quoteResourceModel->load($quote, $reservedOrderId, 'reserved_order_id');
        if (!$quote->isEmpty()) {
            return $quote;
        }
    }

    /**
     * Create order from found quote by reserved order id
     *
     * @param Quote $quote
     *
     * @return AbstractExtensibleModel|OrderInterface|object|null
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function createOrder(Quote $quote)
    {
        /** @var QuoteManagement $quoteManagement */
        $quoteManagement = $this->objectManager->get('Magento\Quote\Model\QuoteManagement');

        return $quoteManagement->submit($quote);
    }

    /**
     * Cancel order for failed group transaction
     *
     * @param string $reservedOrderId
     * @param mixed $historyComment
     * @throws LocalizedException
     */
    protected function cancelOrder(string $reservedOrderId, $historyComment = 'Giftcard has expired')
    {
        $order = $this->order->loadByIncrementId($reservedOrderId);

        if ($order->getEntityId() === null) {
            $order = $this->createOrderFromQuote($reservedOrderId);
        }

        /** @var OrderManagementInterface $orderManagement */
        $orderManagement = $this->objectManager->get('Magento\Sales\Api\OrderManagementInterface');

        if ($order instanceof OrderInterface &&
            $order->getEntityId() !== null &&
            $order->getState() !== Order::STATE_CANCELED
        ) {
            $orderManagement->cancel($order->getEntityId());

            $order->addCommentToStatusHistory(
                __($historyComment)
            )
                ->setIsCustomerNotified(false)
                ->setEntityName('invoice')
                ->save();
        }
    }

    /**
     * Create order from quote
     *
     * @param  string                                                                                              $reservedOrderId
     * @return AbstractExtensibleModel|OrderInterface|object|null
     * @throws LocalizedException
     * @throws \Exception
     */
    protected function createOrderFromQuote(string $reservedOrderId)
    {
        $quote = $this->getQuoteByReservedOrderId($reservedOrderId);
        if (!$quote instanceof Quote) {
            return;
        }

        //fix missing email validation
        if ($quote->getCustomerEmail() == null) {

            $quote->setCustomerEmail(
                $quote->getBillingAddress()->getEmail()
            );
        }

        $order = $this->createOrder($quote);

        //keep the quote active but remove the canceled order from it
        $quote->setIsActive(true);
        $quote->setOrigOrderId(null);
        $quote->setReservedOrderId(null);
        $quote->save();
        return $order;
    }

    /**
     * Cancel order when group transaction is canceled
     */
    public function cancelGroupTransactionOrder()
    {
        if (isset($this->postData['brq_invoicenumber']) &&
            is_string($this->postData['brq_invoicenumber'])
        ) {
            $this->cancelOrder(
                $this->postData['brq_invoicenumber'],
                'Inline giftcard order was canceled'
            );
        }
    }

    /**
     * Check if the request is a canceled group transaction
     *
     * @return bool
     */
    public function isCanceledGroupTransaction()
    {
        return $this->hasPostData(
            'brq_transaction_type',
            self::BUCK_PUSH_GROUPTRANSACTION_TYPE
        ) &&
            $this->hasPostData(
                'brq_statuscode',
                $this->helper->getStatusCode('BUCKAROO_MAGENTO2_STATUSCODE_CANCELLED_BY_USER')
            );
    }
}
