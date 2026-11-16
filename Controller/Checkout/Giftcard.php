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

namespace Buckaroo\Magento2\Controller\Checkout;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\PartialPayment\ApplyResult;
use Buckaroo\Magento2\Model\PartialPayment\ApplyService;
use Buckaroo\Magento2\Model\PartialPayment\Instrument\GiftcardFactory;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentUnavailableException;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Phrase;

/**
 * Applies an inline giftcard to the shopper's cart (Luma checkout).
 *
 * The work is done by the shared partial payment service; this controller reads the posted card
 * and keeps the JSON response the checkout expects.
 */
class Giftcard extends Action implements HttpPostActionInterface
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var BuckarooLoggerInterface
     */
    protected $logger;

    /**
     * @var ApplyService
     */
    private $applyService;

    /**
     * @var GiftcardFactory
     */
    private $giftcardFactory;

    /**
     * @param Context                 $context
     * @param Session                 $checkoutSession
     * @param ApplyService            $applyService
     * @param GiftcardFactory         $giftcardFactory
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        ApplyService $applyService,
        GiftcardFactory $giftcardFactory,
        BuckarooLoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->applyService = $applyService;
        $this->giftcardFactory = $giftcardFactory;
        $this->logger = $logger;
    }

    /**
     * Apply the posted giftcard
     *
     * @return Json
     */
    public function execute(): Json
    {
        try {
            $quote = $this->checkoutSession->getQuote();
            $result = $this->applyService->apply(
                $quote,
                $this->giftcardFactory->create([
                    'cardId'     => (string)$this->getRequest()->getParam('card'),
                    'cardNumber' => (string)$this->getRequest()->getParam('cardNumber'),
                    'pin'        => (string)$this->getRequest()->getParam('pin'),
                ])
            );
        } catch (PartialPaymentRejectedException|PartialPaymentUnavailableException $e) {
            return $this->displayError($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->addError(sprintf(
                '[Giftcard] | [Controller] | [%s:%s] - Apply Inline Giftcard | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                (string)$e
            ));
            return $this->displayError(
                (string)__('The giftcard could not be applied. Please check the card number and PIN and try again.')
            );
        }

        return $this->createResponse($result);
    }

    /**
     * Error response the checkout shows to the shopper
     *
     * @param Phrase|string $message
     *
     * @return Json
     */
    protected function displayError($message): Json
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $result->setData([
            'error' => (string)$message
        ]);
    }

    /**
     * Response for an accepted giftcard, in the shape the Luma giftcard component reads
     *
     * @param ApplyResult $result
     *
     * @return Json
     */
    private function createResponse(ApplyResult $result): Json
    {
        if ($result->isFullyPaid()) {
            $message = __('You paid successfully.');
            $buttonMessage = '';
        } else {
            $message = __(
                'A partial payment of %1 %2 was successfully performed on a requested amount. Remainder amount %3 %4',
                $result->getCurrency(),
                $result->getAmountDebit(),
                $result->getRemainderAmount(),
                $result->getCurrency()
            );
            $buttonMessage = __('Pay remaining amount: %1 %2', $result->getRemainderAmount(), $result->getCurrency());
        }

        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        return $json->setData([
            'RemainderAmount'          => $result->getRemainderAmount(),
            'alreadyPaid'              => $result->getAlreadyPaid(),
            'PayRemainingAmountButton' => (string)$buttonMessage,
            'message'                  => (string)$message,
        ]);
    }
}
