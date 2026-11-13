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

namespace Buckaroo\Magento2\Model\Voucher;

use Buckaroo\Magento2\Api\ApplyVoucherInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\PartialPayment\ApplyResult;
use Buckaroo\Magento2\Model\PartialPayment\ApplyService;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\VoucherFactory;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentMethods;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentUnavailableException;
use Magento\Checkout\Model\Session;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * REST entry point that applies a voucher to the shopper's current cart (checkout session).
 *
 * Luma and Hyvä checkouts call it from the browser. The work is done by the shared partial
 * payment service; this class only finds the cart and turns the outcome into the response.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyVoucher implements ApplyVoucherInterface
{
    /**
     * @var ApplyService
     */
    private $applyService;

    /**
     * @var VoucherFactory
     */
    private $voucherFactory;

    /**
     * @var PartialPaymentMethods
     */
    private $partialPaymentMethods;

    /**
     * @var PayResponseSetInterfaceFactory
     */
    private $payResponseFactory;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param ApplyService                   $applyService
     * @param VoucherFactory                 $voucherFactory
     * @param PartialPaymentMethods          $partialPaymentMethods
     * @param PayResponseSetInterfaceFactory $payResponseFactory
     * @param Session                        $checkoutSession
     * @param BuckarooLoggerInterface        $logger
     */
    public function __construct(
        ApplyService $applyService,
        VoucherFactory $voucherFactory,
        PartialPaymentMethods $partialPaymentMethods,
        PayResponseSetInterfaceFactory $payResponseFactory,
        Session $checkoutSession,
        BuckarooLoggerInterface $logger
    ) {
        $this->applyService = $applyService;
        $this->voucherFactory = $voucherFactory;
        $this->partialPaymentMethods = $partialPaymentMethods;
        $this->payResponseFactory = $payResponseFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function apply(string $voucherCode): PayResponseInterface
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $result = $this->applyService->apply(
                $quote,
                $this->voucherFactory->create(['code' => $voucherCode])
            );
        } catch (PartialPaymentRejectedException|PartialPaymentUnavailableException $e) {
            throw new WebapiException(__($e->getMessage()));
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[VOUCHER] | [Webapi] | [%s:%s] - Apply voucher failed | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                (string)$e
            ));
            throw new WebapiException(
                __('The voucher could not be redeemed. Please check the code and try again.')
            );
        }

        return $this->createResponse($result);
    }

    /**
     * REST response for an accepted voucher
     *
     * @param ApplyResult $result
     *
     * @return PayResponseInterface
     */
    private function createResponse(ApplyResult $result): PayResponseInterface
    {
        $transaction = $result->getTransaction();
        if ($transaction->getData('label') === null) {
            $transaction->setData('label', $this->partialPaymentMethods->getLabel($transaction->getData('servicecode')));
        }

        return $this->payResponseFactory->create()->setData([
            'remainderAmount'        => $result->getRemainderAmount(),
            'alreadyPaid'            => $result->getAlreadyPaid(),
            'transaction'            => $transaction,
            'message'                => $this->getMessage($result),
            'remainingAmountMessage' => $result->isFullyPaid()
                ? ''
                : __('Pay remaining amount: %1 %2', $result->getRemainderAmount(), $result->getCurrency()),
        ]);
    }

    /**
     * Message shown to the shopper after an accepted voucher
     *
     * @param ApplyResult $result
     *
     * @return Phrase
     */
    private function getMessage(ApplyResult $result): Phrase
    {
        if ($result->isFullyPaid()) {
            return __('You paid successfully.');
        }

        return __(
            'A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4',
            $result->getCurrency(),
            $result->getAmountDebit(),
            $result->getRemainderAmount(),
            $result->getCurrency()
        );
    }
}
