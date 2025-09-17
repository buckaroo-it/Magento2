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

namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\ExpressMethodsException;
use Buckaroo\Magento2\Service\Applepay\SaveOrderProcessor;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

class SaveOrder extends AbstractApplepay
{
    /**
     * @var SaveOrderProcessor
     */
    private SaveOrderProcessor $processor;

    public function __construct(
        JsonFactory            $resultJsonFactory,
        RequestInterface       $request,
        BuckarooLoggerInterface $logger,
        SaveOrderProcessor $processor
    ) {
        parent::__construct($resultJsonFactory, $request, $logger);
        $this->processor = $processor;
    }


    /**
     * @throws ExpressMethodsException
     * @throws LocalizedException
     */
    public function execute(): Json
    {
        $payload = $this->getParams();

        if (!$payload || empty($payload['payment']) || empty($payload['extra'])) {
            return $this->commonResponse([], true);
        }

        $data = $this->processor->place($payload);

        return $this->commonResponse($data, false);
    }

    /**
     * Submit the quote.
     *
     * @param Quote $quote
     * @param array|string $extra
     * @param array $payment
     * @return void
     * @throws LocalizedException
     */
    private function submitQuote($quote, $extra, $payment): void
    {
        try {
            $emailAddress = $quote->getShippingAddress()->getEmail();
            if ($quote->getIsVirtual()) {
                $emailAddress = $payment['shippingContact']['emailAddress'] ?? null;
            }

            // If customer is not logged in, mark as guest.
            if (!($this->customerSession->getCustomer() && $this->customerSession->getCustomer()->getId())) {
                $quote->setCheckoutMethod('guest')
                    ->setCustomerId(null)
                    ->setCustomerEmail($emailAddress)
                    ->setCustomerIsGuest(true)
                    ->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
            }

            $paymentInstance = $quote->getPayment();
            $paymentInstance->setMethod(Applepay::PAYMENT_METHOD_CODE);
            $quote->setPayment($paymentInstance);

            // Invoice handling.
            $invoiceHandlingConfig = $this->accountConfig->getInvoiceHandling($this->order->getStore());
            if ($invoiceHandlingConfig == InvoiceHandlingOptions::SHIPMENT) {
                $paymentInstance->setAdditionalInformation(InvoiceHandlingOptions::INVOICE_HANDLING, $invoiceHandlingConfig);
                $paymentInstance->save();
                $quote->setPayment($paymentInstance);
            }

            // Force totals recalculation.
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals()->save();

            // Assign additional payment data.
            $obj = $this->objectFactory->create();
            $obj->setData($extra);
            $quote->getPayment()->getMethodInstance()->assignData($obj);

            // Submit the quote.
            $this->quoteManagement->submit($quote);
        } catch (\Throwable $th) {
            $this->logger->addError(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Submit Quote | ERROR: %s',
                __METHOD__,
                __LINE__,
                $th->getMessage()
            ));
        }
    }

    /**
     * Handle the response after order submission.
     *
     * @return array
     */
    private function handleResponse(): array
    {
        $data = [];
        if ($this->registry && $this->registry->registry('buckaroo_response')) {
            $data = $this->registry->registry('buckaroo_response')[0];
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order Handle Response | Response Data: %s',
                __METHOD__,
                __LINE__,
                var_export($data, true)
            ));

            if (!empty($data->RequiredAction->RedirectURL)) {
                // Test mode response.
                $data = [
                    'RequiredAction' => $data->RequiredAction
                ];
            } else {
                //live mode
                if (!empty($data->Status->Code->Code) &&
                    ($data->Status->Code->Code == '190') &&
                    !empty($data->Order)
                ) {
                    $data = $this->processBuckarooResponse($data);
                }
            }
        }

        return $data;
    }

    /**
     * Process Buckaroo response and set order and quote data on session.
     *
     * @param mixed $data
     * @return array
     */
    private function processBuckarooResponse($data): array
    {
        $this->order->loadByIncrementId($data->Order);
        if ($this->order->getId()) {
            $this->checkoutSession
                ->setLastQuoteId($this->order->getQuoteId())
                ->setLastSuccessQuoteId($this->order->getQuoteId())
                ->setLastOrderId($this->order->getId())
                ->setLastRealOrderId($this->order->getIncrementId())
                ->setLastOrderStatus($this->order->getStatus());

            $store = $this->order->getStore();
            $url = $store->getBaseUrl() . '/' . $this->accountConfig->getSuccessRedirect($store);
            $this->logger->addDebug(sprintf(
                '[ApplePay] | [Controller] | [%s:%s] - Save Order - Redirect URL: %s',
                __METHOD__,
                __LINE__,
                $url
            ));
            $data = [
                'RequiredAction' => [
                    'RedirectURL' => $url
                ]
            ];
        }
        return $data;
    }
}
