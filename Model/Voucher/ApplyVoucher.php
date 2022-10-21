<?php

namespace Buckaroo\Magento2\Model\Voucher;

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Checkout\Model\Session;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Buckaroo\Magento2\Api\ApplyVoucherInterface;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Model\Giftcard\Api\NoQuoteException;
use Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequestInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;


class ApplyVoucher implements ApplyVoucherInterface
{


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
     * @var \Buckaroo\Magento2\Model\Voucher\ApplyVoucherRequestInterface
     */
    protected $voucherRequest;


    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    public function __construct(
        ApplyVoucherRequestInterface $voucherRequest,
        GiftcardResponse $giftcardResponse,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PayResponseSetInterfaceFactory $payResponseFactory,
        Session $checkoutSession,
        Log $logger
    ) {
        $this->voucherRequest = $voucherRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->payResponseFactory = $payResponseFactory;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    public function apply(string $voucherCode)
    {
        try {
            $quote = $this->getQuote();

            return $this->getResponse(
                $quote,
                $this->build($quote, $voucherCode)->send()
            );
        } catch (ApiException $th) {
            throw $th;
        } catch (NoQuoteException $th) {
            throw $th;
        } catch (\Throwable $th) {
            $this->logger->addDebug((string)$th);
            throw new ApiException(__('Unknown buckaroo error has occurred'), 0, $th);
        }
    }

    protected function getResponse(Quote $quote, $response)
    {

        $this->giftcardResponse->set($response, $quote);

        if ($this->giftcardResponse->getErrorMessage() !== null) {
            throw new ApiException($this->giftcardResponse->getErrorMessage());
        }
        return $this->payResponseFactory->create()->setData(
            array_merge(
                [
                    'remainderAmount' => $this->giftcardResponse->getRemainderAmount(),
                    'alreadyPaid' => $this->giftcardResponse->getAlreadyPaid($quote),
                    'transaction' => $this->giftcardResponse->getCreatedTransaction()
                ],
                $this->getUserMessages()
            )
        );
    }


    protected function getUserMessages()
    {

        $remainingAmountMessage = '';

        $remainingAmount = $this->giftcardResponse->getRemainderAmount();
        $textMessage = __("Your paid successfully. Please finish your order");

        if ($remainingAmount > 0) {
            $textMessage = __(
                'A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4',
                $this->giftcardResponse->getCurrency(),
                $this->giftcardResponse->getAmountDebit(),
                $this->giftcardResponse->getRemainderAmount(),
                $this->giftcardResponse->getCurrency()
            );

            $remainingAmountMessage = __(
                'Pay remaining amount: %1 %2',
                $remainingAmount,
                $this->giftcardResponse->getCurrency()
            );
        }
        return [
            'remainingAmountMessage' => $remainingAmountMessage,
            'message' => $textMessage
        ];
    }
    /**
     * Build giftcard request
     *
     * @param Quote $quote
     * @param string $giftcardId
     *
     * @return VoucherRequest
     */
    protected function build(Quote $quote, string $voucherCode)
    {

        return $this->voucherRequest
            ->setVoucherCode($voucherCode)
            ->setQuote($quote);
    }

    /**
     * Get quote from session
     *
     * @return Quote
     */
    protected function getQuote()
    {
        try {
            return $this->checkoutSession->getQuote();
        } catch (\Throwable $th) {
            throw new NoQuoteException(__("The cart isn't active."), 0, $th);
        }
    }
}
