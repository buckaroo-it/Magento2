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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\PayRequestInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory;
use Buckaroo\Magento2\Api\PayWithGiftcardInterface;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface as GiftcardRequest;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;

class Pay implements PayWithGiftcardInterface
{
    /**
     * @var GiftcardRequest
     */
    protected $giftcardRequest;

    /**
     * @var GiftcardResponse
     */
    protected $giftcardResponse;

    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var PayResponseSetInterfaceFactory
     */
    protected $payResponseFactory;

    /**
     * @param GiftcardRequest                $giftcardRequest
     * @param GiftcardResponse               $giftcardResponse
     * @param QuoteIdMaskFactory             $quoteIdMaskFactory
     * @param CartRepositoryInterface        $cartRepository
     * @param PayResponseSetInterfaceFactory $payResponseFactory
     */
    public function __construct(
        GiftcardRequest $giftcardRequest,
        GiftcardResponse $giftcardResponse,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PayResponseSetInterfaceFactory $payResponseFactory
    ) {
        $this->giftcardRequest = $giftcardRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->payResponseFactory = $payResponseFactory;
    }

    /**
     * @inheritdoc
     */
    public function pay(string $cartId, string $giftcardId, PayRequestInterface $payment)
    {
        if ($payment->getCardNumber() === null) {
            throw new ApiException(__('Parameter `card_number` is required'));
        }

        if ($payment->getCardPin() === null) {
            throw new ApiException(__('Parameter `card_pin` is required'));
        }

        try {
            $quote = $this->getQuote($cartId);

            return $this->getResponse(
                $quote,
                $this->build($quote, $giftcardId, $payment)->send()
            );
        } catch (ApiException $th) {
            throw $th;
        } catch (NoQuoteException $th) {
            throw $th;
        } catch (\Throwable $th) {
            throw new ApiException(__('Unknown buckaroo error has occurred'), 0, $th);
        }
    }

    /**
     * Get quote from masked cart id
     *
     * @param string $cartId
     *
     * @throws NoQuoteException
     *
     * @return Quote
     */
    protected function getQuote(string $cartId): Quote
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            /** @var Quote $quote */
            return $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        } catch (\Throwable $th) {
            throw new NoQuoteException(__("The cart isn't active."), 0, $th);
        }
    }

    /**
     * Get Giftcard Response
     *
     * @param Quote $quote
     * @param mixed $response
     *
     * @throws ApiException
     *
     * @return mixed
     */
    protected function getResponse(Quote $quote, $response)
    {
        $this->giftcardResponse->set($response, $quote);

        if ($this->giftcardResponse->getErrorMessage() !== null) {
            throw new ApiException($this->giftcardResponse->getErrorMessage());
        }
        return $this->payResponseFactory->create()->setData([
            'remainderAmount' => $this->giftcardResponse->getRemainderAmount(),
            'alreadyPaid'     => $this->giftcardResponse->getAlreadyPaid($quote),
            'transaction'     => $this->giftcardResponse->getCreatedTransaction()
        ]);
    }

    /**
     * Build giftcard request
     *
     * @param Quote               $quote
     * @param string              $giftcardId
     * @param PayRequestInterface $payment
     *
     * @return GiftcardRequest
     */
    protected function build(Quote $quote, string $giftcardId, PayRequestInterface $payment)
    {
        return $this->giftcardRequest
            ->setCardId($giftcardId)
            ->setCardNumber($payment->getCardNumber())
            ->setPin($payment->getCardPin())
            ->setQuote($quote);
    }
}
