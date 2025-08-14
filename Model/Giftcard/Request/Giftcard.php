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

namespace Buckaroo\Magento2\Model\Giftcard\Request;

use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Giftcard implements GiftcardInterface
{

    public const TCS_ACQUIRER = 'tcs';
    public const FASHIONCHEQUE_ACQUIRER = 'fashioncheque';
    /**
     * @var StoreInterface
     */
    protected $store;
    /**
     * @var CartInterface
     */
    protected $quote;
    /**
     * @var string
     */
    protected $cardId;
    /**
     * @var string
     */
    protected $cardNumber;
    /**
     * Card pin
     *
     * @var string
     */
    protected $pin;
    /**
     * @var CommandPoolInterface
     */
    private CommandPoolInterface $commandPool;
    /**
     * @var PaymentDataObjectFactory
     */
    private PaymentDataObjectFactory $paymentDataObjectFactory;
    /**
     * @var GiftcardRepositoryInterface
     */
    private GiftcardRepositoryInterface $giftcardRepository;

    /**
     * @param StoreManagerInterface $storeManager
     * @param CommandPoolInterface $commandPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param GiftcardRepositoryInterface $giftcardRepository
     * @throws NoSuchEntityException
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CommandPoolInterface $commandPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        GiftcardRepositoryInterface $giftcardRepository
    ) {
        $this->store = $storeManager->getStore();
        $this->commandPool = $commandPool;
        $this->paymentDataObjectFactory = $paymentDataObjectFactory;
        $this->giftcardRepository = $giftcardRepository;
    }

    /**
     * Send giftcard request
     *
     * @return mixed
     * @throws GiftcardException
     */
    public function send()
    {
        if ($this->cardId === null) {
            throw new GiftcardException("Giftcard id is required");
        }
        if ($this->cardNumber === null) {
            throw new GiftcardException("Giftcard number is required");
        }
        if ($this->pin === null) {
            throw new GiftcardException("Giftcard pin is required");
        }
        if ($this->quote === null) {
            throw new GiftcardException("Quote is required");
        }

        try {
            // Create a mock payment object for the data object factory
            $payment = $this->createMockPayment();

            $command = $this->commandPool->get('giftcard_inline');
            $command->execute([
                'payment' => $this->paymentDataObjectFactory->create($payment),
                'amount' => $this->quote->getGrandTotal()
            ]);

            // The command execution will handle the response through response handlers
            // For now, return a success indicator
            return ['status' => 'success'];
        } catch (CommandException $e) {
            throw new GiftcardException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a mock payment object with giftcard data
     *
     * @return InfoInterface
     */
    private function createMockPayment(): InfoInterface
    {
        /** @var Payment $payment */
        $payment = $this->quote->getPayment();

        // Set giftcard data in additional information
        $payment->setAdditionalInformation('giftcard_id', $this->cardId);
        $payment->setAdditionalInformation('giftcard_number', $this->cardNumber);
        $payment->setAdditionalInformation('giftcard_pin', $this->pin);

        return $payment;
    }

    /**
     * Set card number
     *
     * @param string $cardNumber
     * @return GiftcardInterface
     */
    public function setCardNumber(string $cardNumber): GiftcardInterface
    {
        $this->cardNumber = trim(preg_replace('/([\s-]+)/', '', $cardNumber));
        return $this;
    }

    /**
     * Set card pin
     *
     * @param string $pin
     * @return GiftcardInterface
     */
    public function setPin(string $pin): GiftcardInterface
    {
        $this->pin = trim($pin);
        return $this;
    }

    /**
     * Set card type
     *
     * @param string $cardId
     * @return GiftcardInterface
     */
    public function setCardId(string $cardId): GiftcardInterface
    {
        $this->cardId = $cardId;
        return $this;
    }

    /**
     * Set quote
     *
     * @param CartInterface $quote
     * @return GiftcardInterface
     */
    public function setQuote(CartInterface $quote): GiftcardInterface
    {
        $this->quote = $quote;
        return $this;
    }


}
