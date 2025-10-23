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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Api\PayWithGiftcardInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayRequestInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface as GiftcardRequest;

class Pay implements PayWithGiftcardInterface
{
    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    protected $giftcardRequest;

    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Response\Giftcard
     */
    protected $giftcardResponse;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var \Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory
     */
    protected $payResponseFactory;

    /**
     * @var Log
     */
    private Log $logger;


    public function __construct(
        GiftcardRequest $giftcardRequest,
        GiftcardResponse $giftcardResponse,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PayResponseSetInterfaceFactory $payResponseFactory,
        Log $logger
    ) {
        $this->giftcardRequest = $giftcardRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->payResponseFactory = $payResponseFactory;
        $this->logger = $logger;
    }
    /**
     * @inheritDoc
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
    protected function getResponse(Quote $quote, $response)
    {

        $this->giftcardResponse->set($response, $quote);

        if ($this->giftcardResponse->getErrorMessage() !== null) {
            throw new ApiException($this->giftcardResponse->getErrorMessage());
        }
        return $this->payResponseFactory->create()->setData([
            'remainderAmount' => $this->giftcardResponse->getRemainderAmount(),
            'alreadyPaid' => $this->giftcardResponse->getAlreadyPaid($quote),
            'transaction' => $this->giftcardResponse->getCreatedTransaction(),
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

    /**
     * Get quote from masked cart id
     *
     * @param string $cartId
     *
     * @return Quote
     */
    protected function getQuote(string $cartId)
    {
        try {
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
            /** @var Quote $quote */
            return $this->cartRepository->getActive($quoteIdMask->getQuoteId());
        } catch (\Throwable $th) {
            throw new NoQuoteException(__("The cart isn't active."), 0, $th);
        }
    }
}
