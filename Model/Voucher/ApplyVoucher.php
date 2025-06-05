<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Model\Voucher;

use Buckaroo\Magento2\Api\ApplyVoucherInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\PayResponseSetInterfaceFactory;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Model\Giftcard\Api\NoQuoteException;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\Exception;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ApplyVoucher implements ApplyVoucherInterface
{
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
     * @var ApplyVoucherRequestInterface
     */
    protected $voucherRequest;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var GiftcardResponse
     */
    private GiftcardResponse $giftcardResponse;

    /**
     * @var BuckarooLoggerInterface
     */
    private BuckarooLoggerInterface $logger;

    /**
     * @param ApplyVoucherRequestInterface $voucherRequest
     * @param GiftcardResponse $giftcardResponse
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param CartRepositoryInterface $cartRepository
     * @param PayResponseSetInterfaceFactory $payResponseFactory
     * @param Session $checkoutSession
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        ApplyVoucherRequestInterface $voucherRequest,
        GiftcardResponse $giftcardResponse,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        PayResponseSetInterfaceFactory $payResponseFactory,
        Session $checkoutSession,
        BuckarooLoggerInterface $logger
    ) {
        $this->voucherRequest = $voucherRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
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
            $quote = $this->getQuote();

            return $this->getResponse(
                $quote,
                $this->build($quote, $voucherCode)->send()
            );
        } catch (ApiException $th) {
            $this->renderException($th->getMessage());
        } catch (NoQuoteException $th) {
            $this->renderException($th->getMessage());
        } catch (\Throwable $th) {
            $this->logger->addDebug((string)$th);
            $this->renderException('Unknown buckaroo error has occurred');
        }

        return $this->payResponseFactory->create();
    }

    /**
     * Get quote from session
     *
     * @return Quote
     * @throws NoQuoteException
     */
    protected function getQuote()
    {
        try {
            return $this->checkoutSession->getQuote();
        } catch (\Throwable $th) {
            throw new NoQuoteException(__("The cart isn't active."), 0, $th);
        }
    }

    /**
     * Get response based on the quote and API response.
     *
     * @param Quote $quote
     * @param mixed $response
     * @return mixed
     * @throws ApiException
     * @throws LocalizedException
     */
    protected function getResponse(Quote $quote, $response)
    {

        $this->giftcardResponse->set($response, $quote);

        if ($this->giftcardResponse->getErrorMessage() !== null) {
            $message = $this->giftcardResponse->getErrorMessage();
            $messageParts = explode(":", $message);

            if (isset($messageParts[1])) {
                $message = $messageParts[1];
            }
            throw new ApiException($message);
        }
        return $this->payResponseFactory->create()->setData(
            array_merge(
                [
                    'remainderAmount' => $this->giftcardResponse->getRemainderAmount(),
                    'alreadyPaid'     => $this->giftcardResponse->getAlreadyPaid($quote),
                    'transaction'     => $this->giftcardResponse->getCreatedTransaction()
                ],
                $this->getUserMessages()
            )
        );
    }

    /**
     * Get response based on the quote and API response.
     *
     * @return array
     */
    protected function getUserMessages(): array
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
            'message'                => $textMessage
        ];
    }

    /**
     * Build gift card request
     *
     * @param Quote $quote
     * @param string $voucherCode
     *
     * @return ApplyVoucherRequestInterface
     */
    protected function build(Quote $quote, string $voucherCode): ApplyVoucherRequestInterface
    {
        return $this->voucherRequest
            ->setVoucherCode($voucherCode)
            ->setQuote($quote);
    }

    /**
     * Render and throw an exception with the provided message.
     *
     * @param string $message
     * @return void
     * @throws Exception
     */
    public function renderException(string $message)
    {
        throw new Exception(
            new Phrase($message)
        );
    }
}
