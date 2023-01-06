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

namespace Buckaroo\Magento2\Controller\Checkout;

use Magento\Quote\Model\Quote;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Controller\ResultFactory;
use Buckaroo\Magento2\Model\Giftcard\Api\ApiException;
use Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface;
use Buckaroo\Magento2\Model\Giftcard\Response\Giftcard as GiftcardResponse;

class Giftcard extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Request\GiftcardInterface
     */
    protected $giftcardRequest;

    /**
     * @var \Buckaroo\Magento2\Model\Giftcard\Response\Giftcard
     */
    protected $giftcardResponse;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param GiftcardInterface $giftcardRequest
     * @param GiftcardResponse $giftcardResponse
     * @param Log $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        GiftcardInterface $giftcardRequest,
        GiftcardResponse $giftcardResponse,
        Log $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->giftcardRequest = $giftcardRequest;
        $this->giftcardResponse = $giftcardResponse;
        $this->logger = $logger;
    }

    /**
     * Process action
     *
     * @return \Magento\Framework\App\ResponseInterface
     * @throws \Exception
     */
    public function execute()
    {
        if ($this->getRequest()->getParam('cardNumber') === null) {
            return $this->displayError(__('A card number is required'));
        }

        if ($this->getRequest()->getParam('pin') === null) {
            return $this->displayError(__('A card pin is required'));
        }

        if ($this->getRequest()->getParam('card') === null) {
            return $this->displayError(__('A card type is required'));
        }

        try {
            $quote = $this->checkoutSession->getQuote();

            return $this->getGiftcardResponse(
                $quote,
                $this->build($quote)->send()
            );
        } catch (ApiException $th) {
            $this->logger->addDebug(__METHOD__ . (string)$th);
            return $this->displayError($th->getMessage());
        } catch (\Throwable $th) {
            $this->logger->addDebug(__METHOD__ . (string)$th);
            return $this->displayError(__('Unknown buckaroo error has occurred'));
        }
    }

    protected function displayError($message)
    {
        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            "error" => $message
        ]);
    }

    protected function getGiftcardResponse(Quote $quote, $response)
    {
        $this->giftcardResponse->set($response, $quote);

        if ($this->giftcardResponse->getErrorMessage() !== null) {
            throw new ApiException($this->giftcardResponse->getErrorMessage());
        }


        $buttonMessage = '';

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

            $buttonMessage = __(
                'Pay remaining amount: %1 %2',
                $remainingAmount,
                $this->giftcardResponse->getCurrency()
            );
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            'RemainderAmount' => $remainingAmount,
            'alreadyPaid' => $this->giftcardResponse->getAlreadyPaid(),
            'PayRemainingAmountButton' => $buttonMessage,
            'message' => $textMessage
        ]);
    }

    /**
     * Build giftcard request
     *
     * @param Quote $quote
     *
     * @return GiftcardInterface
     */
    protected function build(Quote $quote)
    {
        return $this->giftcardRequest
            ->setCardId($this->getRequest()->getParam('card'))
            ->setCardNumber($this->getRequest()->getParam('cardNumber'))
            ->setPin($this->getRequest()->getParam('pin'))
            ->setQuote($quote);
    }
}
