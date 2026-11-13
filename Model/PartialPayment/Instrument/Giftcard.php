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

namespace Buckaroo\Magento2\Model\PartialPayment\Instrument;

use Buckaroo\Magento2\Api\GiftcardRepositoryInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards as GiftcardsConfig;
use Buckaroo\Magento2\Model\PartialPayment\PartialPaymentRejectedException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;

/**
 * A giftcard (card id, number and PIN) applied inline in the checkout
 */
class Giftcard implements InstrumentInterface
{
    public const TCS_ACQUIRER = 'tcs';
    public const FASHIONCHEQUE_ACQUIRER = 'fashioncheque';

    /**
     * Request field names of the card number and PIN for acquirers and cards that differ from the default
     */
    private const CARD_FIELDS = [
        self::FASHIONCHEQUE_ACQUIRER => ['number' => 'fashionChequeCardNumber', 'pin' => 'fashionChequePin'],
        self::TCS_ACQUIRER           => ['number' => 'tcsCardnumber', 'pin' => 'tcsValidationCode'],
    ];

    /**
     * @var string
     */
    private $cardId;

    /**
     * @var string
     */
    private $cardNumber;

    /**
     * @var string
     */
    private $pin;

    /**
     * @var GiftcardRepositoryInterface
     */
    private $giftcardRepository;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param GiftcardRepositoryInterface $giftcardRepository
     * @param ScopeConfigInterface        $scopeConfig
     * @param string                      $cardId     service code of the giftcard, e.g. vvvgiftcard
     * @param string                      $cardNumber
     * @param string                      $pin
     */
    public function __construct(
        GiftcardRepositoryInterface $giftcardRepository,
        ScopeConfigInterface $scopeConfig,
        string $cardId,
        string $cardNumber,
        string $pin
    ) {
        $this->giftcardRepository = $giftcardRepository;
        $this->scopeConfig = $scopeConfig;
        $this->cardId = trim($cardId);
        $this->cardNumber = trim((string)preg_replace('/[\s-]+/', '', $cardNumber));
        $this->pin = trim($pin);
    }

    /**
     * @inheritdoc
     */
    public function getMethodCode(): string
    {
        return GiftcardsConfig::CODE;
    }

    /**
     * @inheritdoc
     */
    public function getServiceCode(): string
    {
        return $this->cardId;
    }

    /**
     * @inheritdoc
     */
    public function validate(Quote $quote): void
    {
        if ($this->cardId === '') {
            throw new PartialPaymentRejectedException(__('A card type is required'));
        }
        if ($this->cardNumber === '') {
            throw new PartialPaymentRejectedException(__('A card number is required'));
        }
        if ($this->pin === '') {
            throw new PartialPaymentRejectedException(__('A card pin is required'));
        }
        if (!$this->isAllowed($quote)) {
            throw new PartialPaymentRejectedException(__('This giftcard is not accepted in this store.'));
        }
    }

    /**
     * @inheritdoc
     */
    public function getRequestFields(Quote $quote): array
    {
        $fieldNames = $this->getCardFieldNames();

        return [
            'payment_method'        => 'giftcards',
            'name'                  => $this->cardId,
            'email'                 => $this->getCustomerEmail($quote),
            $fieldNames['number']   => $this->cardNumber,
            $fieldNames['pin']      => $this->pin,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getFallbackRejectionMessage(): Phrase
    {
        return __('The giftcard could not be applied. Please check the card number and PIN and try again.');
    }

    /**
     * @inheritdoc
     */
    public function formatRejectionMessage(string $message): string
    {
        return trim($message);
    }

    /**
     * Whether the giftcard is one the store accepts
     *
     * @param Quote $quote
     *
     * @return bool
     */
    private function isAllowed(Quote $quote): bool
    {
        $allowed = (string)$this->scopeConfig->getValue(
            'payment/' . GiftcardsConfig::CODE . '/' . GiftcardsConfig::XPATH_GIFTCARDS_ALLOWED_GIFTCARDS,
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );

        return in_array($this->cardId, array_map('trim', explode(',', $allowed)), true);
    }

    /**
     * Request field names of the card number and PIN
     *
     * By acquirer, by card, then the Intersolve fields every other giftcard uses (custom giftcards
     * use the generic ones).
     *
     * @return array{number: string, pin: string}
     */
    private function getCardFieldNames(): array
    {
        $acquirer = $this->giftcardRepository->getByServiceCode($this->cardId)->getAcquirer();
        if ($acquirer !== null && isset(self::CARD_FIELDS[$acquirer])) {
            return self::CARD_FIELDS[$acquirer];
        }

        if (isset(self::CARD_FIELDS[$this->cardId])) {
            return self::CARD_FIELDS[$this->cardId];
        }

        if (stripos($this->cardId, 'customgiftcard') === false) {
            return ['number' => 'intersolveCardnumber', 'pin' => 'intersolvePIN'];
        }

        return ['number' => 'cardNumber', 'pin' => 'pin'];
    }

    /**
     * Shopper email for the giftcard provider: billing address, customer, then quote
     *
     * @param Quote $quote
     *
     * @return string
     */
    private function getCustomerEmail(Quote $quote): string
    {
        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress && $billingAddress->getEmail()) {
            return (string)$billingAddress->getEmail();
        }

        $customer = $quote->getCustomer();
        if ($customer && $customer->getEmail()) {
            return (string)$customer->getEmail();
        }

        return (string)$quote->getCustomerEmail();
    }
}
