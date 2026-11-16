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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\PayRequestInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory;
use Buckaroo\Magento2\Api\PayWithGiftcardInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\PartialPayment\ApplyService;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\GiftcardFactory;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentMethods;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentUnavailableException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ChangeQuoteControlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * REST entry point that applies a giftcard to a cart identified by its masked id.
 *
 * The work is done by the shared partial payment service; this class finds the cart, checks the
 * caller may change it and turns the outcome into the response.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Pay implements PayWithGiftcardInterface
{
    /**
     * @var ApplyService
     */
    private $applyService;

    /**
     * @var GiftcardFactory
     */
    private $giftcardFactory;

    /**
     * @var PartialPaymentMethods
     */
    private $partialPaymentMethods;

    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var ChangeQuoteControlInterface
     */
    private $changeQuoteControl;

    /**
     * @var PayResponseSetInterfaceFactory
     */
    private $payResponseFactory;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @param ApplyService                   $applyService
     * @param GiftcardFactory                $giftcardFactory
     * @param PartialPaymentMethods          $partialPaymentMethods
     * @param QuoteIdMaskFactory             $quoteIdMaskFactory
     * @param CartRepositoryInterface        $cartRepository
     * @param ChangeQuoteControlInterface    $changeQuoteControl
     * @param PayResponseSetInterfaceFactory $payResponseFactory
     * @param BuckarooLoggerInterface        $logger
     */
    public function __construct(
        ApplyService $applyService,
        GiftcardFactory $giftcardFactory,
        PartialPaymentMethods $partialPaymentMethods,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        ChangeQuoteControlInterface $changeQuoteControl,
        PayResponseSetInterfaceFactory $payResponseFactory,
        BuckarooLoggerInterface $logger
    ) {
        $this->applyService = $applyService;
        $this->giftcardFactory = $giftcardFactory;
        $this->partialPaymentMethods = $partialPaymentMethods;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->changeQuoteControl = $changeQuoteControl;
        $this->payResponseFactory = $payResponseFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function pay(string $cartId, string $giftcardId, PayRequestInterface $payment)
    {
        if ($payment->getCardNumber() === null) {
            throw new WebapiException(__('Parameter `card_number` is required'));
        }
        if ($payment->getCardPin() === null) {
            throw new WebapiException(__('Parameter `card_pin` is required'));
        }

        try {
            $result = $this->applyService->apply(
                $this->getQuote($cartId),
                $this->giftcardFactory->create([
                    'cardId'     => $giftcardId,
                    'cardNumber' => (string)$payment->getCardNumber(),
                    'pin'        => (string)$payment->getCardPin(),
                ])
            );
        } catch (PartialPaymentRejectedException|PartialPaymentUnavailableException $e) {
            throw new WebapiException(__($e->getMessage()));
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[Giftcard] | [Webapi] | [%s:%s] - Apply giftcard failed | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                (string)$e
            ));
            throw new WebapiException(
                __('The giftcard could not be applied. Please check the card number and PIN and try again.')
            );
        }

        $transaction = $result->getTransaction();
        if ($transaction->getData('label') === null) {
            $transaction->setData('label', $this->partialPaymentMethods->getLabel($transaction->getData('servicecode')));
        }

        return $this->payResponseFactory->create()->setData([
            'remainderAmount' => $result->getRemainderAmount(),
            'alreadyPaid'     => $result->getAlreadyPaid(),
            'transaction'     => $transaction,
        ]);
    }

    /**
     * Active cart behind the masked id, if the caller may change it
     *
     * @param string $cartId
     *
     * @throws PartialPaymentRejectedException
     *
     * @return Quote
     */
    private function getQuote(string $cartId): Quote
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            /** @var Quote $quote */
            $quote = $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        } catch (\Throwable $e) {
            throw new PartialPaymentRejectedException(__("The cart isn't active."), $e instanceof \Exception ? $e : null);
        }

        if (!$this->changeQuoteControl->isAllowed($quote)) {
            throw new PartialPaymentRejectedException(__("The cart isn't active."));
        }

        return $quote;
    }
}
