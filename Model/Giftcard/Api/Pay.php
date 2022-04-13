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
use Magento\Framework\App\RequestInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Api\PayWithGiftcardInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterfaceFactory;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface as GiftcardRequest;

class Pay implements PayWithGiftcardInterface
{
    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    protected $request;

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
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $cartRepository;

    /**
     * @var \Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterfaceFactory`
     */
    protected $payErrorFactory;


    /**
     * @var \Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface
     */
    protected $payResponse;



    public function __construct(
        RequestInterface $request,
        GiftcardRequest $giftcardRequest,
        GiftcardResponse $giftcardResponse,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PayResponseInterfaceFactory $payResponseFactory,
        Log $logger
    ) {
        $this->request = $request;
        $this->giftcardRequest = $giftcardRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->payResponse = $payResponseFactory->create();
        $this->logger = $logger;
        
    }
    /**
     * @inheritDoc
     */
    public function pay(string $cartId, string $giftcardId)
    {
        if ($this->request->getParam('card_number') === null) {
            $this->payResponse->setError(__('Parameter `card_number` is required'));
        }

        if ($this->request->getParam('card_pin') === null) {
            $this->payResponse->setError(__('Parameter `card_pin` is required'));
        }

        if ($this->payResponse->hasError()) {
            return $this->payResponse;
        }

        try {
            $quote = $this->getQuote($cartId);

            return $this->getResponse(
                $quote,
                $this->build($quote, $giftcardId)->send()
            );
        } catch (\Throwable $th) {
            $this->logger->debug(__METHOD__ . $th->getMessage());
            $this->payResponse->setError(__('Unknown buckaroo error has occurred'));
            return $this->payResponse;
        }
    }
    protected function getResponse(Quote $quote, $response)
    {
        $this->giftcardResponse->set($response);

        
        if ($this->giftcardResponse->getErrorMessage() !== null) {
            $this->payResponse->setError($this->giftcardResponse->getErrorMessage());
            return $this->payResponse;
        }

        $this->payResponse->setData([
            'remainderAmount' => $this->giftcardResponse->getRemainderAmount(),
            'alreadyPaid' => $this->giftcardResponse->getAlreadyPaid($quote),
        ]);

        return $this->payResponse;
    }
    /**
     * Build giftcard request
     *
     * @param Quote $quote
     * @param string $giftcardId
     *
     * @return GiftcardRequest
     */
    protected function build(Quote $quote, string $giftcardId)
    {

        return $this->giftcardRequest
            ->setCardId($giftcardId)
            ->setCardNumber($this->request->getParam('card_number'))
            ->setPin($this->request->getParam('card_pin'))
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
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        /** @var Quote $quote */
        return $this->cartRepository->getActive($quoteIdMask->getQuoteId());
    }
}
