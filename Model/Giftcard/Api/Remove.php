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
use Buckaroo\Magento2\Model\Giftcard\RemoveException;
use Buckaroo\Magento2\Api\Data\Giftcard\RemoveInterface;
use Buckaroo\Magento2\Model\Giftcard\Api\NoQuoteException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\Giftcard\Remove as GiftcardRemover;
use Buckaroo\Magento2\Api\Data\Giftcard\RemoveResponseInterface;
use Buckaroo\Magento2\Api\Data\Giftcard\RemoveResponseInterfaceFactory;

class Remove implements RemoveInterface
{

    /**
     * @var \Buckaroo\Magento2\Api\Data\Giftcard\RemoveResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @var \Magento\Quote\Model\QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var \Magento\Framework\Controller\ResultFactory
     */
    protected $resultFactory;

    /**
     * @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards
     */
    protected $giftcardConfig;

    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    public function __construct(
        RemoveResponseInterfaceFactory $responseFactory,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        CartRepositoryInterface $cartRepository,
        GiftcardRemover $giftcardRemover,
        Giftcards $giftcardConfig,
        
        Log $logger
    ) {
        $this->responseFactory = $responseFactory;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->cartRepository = $cartRepository;
        $this->giftcardRemover = $giftcardRemover;
        $this->giftcardConfig = $giftcardConfig;
        $this->logger = $logger;
    }

    public function remove(string $cartId, string $transactionId): RemoveResponseInterface
    {

        if (!$this->giftcardConfig->isRemoveButtonEnabled()) {
            return $this->renderError(
                __('Cannot remove giftcard, function disabled')
            );
        }

        try {
            $quote = $this->getQuote($cartId);
            $this->giftcardRemover->remove(
                $transactionId,
                $quote->getReservedOrderId()
            );
        } catch (RemoveException $th) {
            return $this->renderError($th->getMessage());
        } catch (NoQuoteException $th) {
            return $this->renderError($th->getMessage());
        } catch (\Throwable $th) {
            $this->logger->addDebug((string)$th);
            return $this->renderError(
                __('Cannot remove giftcard, internal server error')
            );
        }
        return $this->responseFactory->create()->setData([
            'error' => false,
            'message' => __('Giftcard successfully removed')
        ]);
    }
    /**
     * Render any errors
     *
     * @param string $errorMessage
     *
     * @return \Magento\Framework\App\ResponseInterface
     */
    protected function renderError(string $errorMessage)
    {
        return $this->responseFactory
            ->setData([
                'error' => true,
                'message' => $errorMessage
            ]);
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
