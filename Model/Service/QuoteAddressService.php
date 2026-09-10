<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * support@buckaroo.nl so we can send you a copy immediately.
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

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ShippingAddressManagementInterface;

class QuoteAddressService
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var BuckarooLoggerInterface
     */
    private $logger;

    /**
     * @var ShippingAddressManagementInterface
     */
    private $shippingAddressManagement;

    /**
     * @param CustomerSession                    $customerSession
     * @param CustomerRepositoryInterface        $customerRepository
     * @param QuoteRepository                    $quoteRepository
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param BuckarooLoggerInterface            $logger
     */
    public function __construct(
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        QuoteRepository $quoteRepository,
        ShippingAddressManagementInterface $shippingAddressManagement,
        BuckarooLoggerInterface $logger
    ) {
        $this->customerSession           = $customerSession;
        $this->customerRepository        = $customerRepository;
        $this->quoteRepository           = $quoteRepository;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->logger                    = $logger;
    }

    /**
     * Add address from express (wallet) to quote.
     *
     * @param ShippingAddressRequestInterface $shippingAddress
     * @param Quote                           $cart
     * @param bool                            $fillMissingFields Whether to fill missing fields with dummy data (default: false)
     *
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @return Quote
     */
    public function addAddressToQuote(ShippingAddressRequestInterface $shippingAddress, Quote $cart, bool $fillMissingFields = false): Quote
    {
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerRepository->getById($this->customerSession->getCustomerId());
            $cart->assignCustomerWithAddressChange($customer);
        }

        $address = $cart->getShippingAddress();
        $address->setCountryId($shippingAddress->getCountryCode());
        $address->setPostcode($shippingAddress->getPostalCode());
        $address->setCity($shippingAddress->getCity());
        $address->setRegion($shippingAddress->getState());

        // Fill any missing fields on both shipping and billing addresses. Express wallets
        // (Apple Pay and Google Pay) only supply locality/postcode/country while the shopper is
        // still choosing a shipping option, so callers in those flows pass true.
        if ($fillMissingFields) {
            $this->maybeFillAnyMissingAddressFields($shippingAddress, $cart);
        }

        $this->quoteRepository->save($cart);

        return $cart;
    }

    /**
     * Fill any fields missing from the addresses.
     *
     * @param ShippingAddressRequestInterface $shippingAddress
     * @param Quote                           $quote
     */
    protected function maybeFillAnyMissingAddressFields(
        ShippingAddressRequestInterface $shippingAddress,
        Quote $quote
    ): void {
        $this->maybeFillShippingAddressFields($quote);
        $this->maybeFillBillingAddressFields($shippingAddress, $quote);
    }

    /**
     * If no default shipping address is found, fill in empty fields required for quote validation.
     *
     * @param Quote $quote
     */
    protected function maybeFillShippingAddressFields(Quote $quote): void
    {
        $address = $quote->getShippingAddress();

        // Express wallets only supply locality/postcode/country, so the remaining required
        // fields are filled with placeholders. Each field is filled only when it is empty, so
        // this stays correct when the wallet sends a new address for an already saved quote
        // address (the shopper changing address in the payment sheet).
        $this->fillPlaceholderAddressFields($address);

        $quote->setShippingAddress($address);
    }

    /**
     * Fill the fields Magento requires for quote validation, without overwriting real data.
     *
     * @param \Magento\Quote\Model\Quote\Address $address
     */
    private function fillPlaceholderAddressFields($address): void
    {
        if (!$address->getFirstname()) {
            $address->setFirstname('unknown');
        }

        if (!$address->getLastname()) {
            $address->setLastname('unknown');
        }

        if (!$address->getEmail()) {
            $address->setEmail('no-reply@example.com');
        }

        if (!array_filter((array)$address->getStreet())) {
            $address->setStreet('unknown');
        }

        if (!$address->getTelephone()) {
            $address->setTelephone('0000000000');
        }
    }

    /**
     * Process address data from the wallet.
     *
     * @param array       $wallet
     * @param string      $type
     * @param string|null $phone
     *
     * @return array
     */
    public function processAddressFromWallet(array $wallet, string $type = 'shipping', ?string $phone = null): array
    {
        $address = [
            'prefix'     => '',
            'firstname'  => $wallet['givenName'] ?? '',
            'middlename' => '',
            'lastname'   => $wallet['familyName'] ?? '',
            'street'     => [
                $wallet['addressLines'][0] ?? '',
                $wallet['addressLines'][1] ?? null
            ],
            'city'       => $wallet['locality'] ?? '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '',
            'region'     => $wallet['administrativeArea'] ?? '',
            'region_id'  => '',
            'postcode'   => $wallet['postalCode'] ?? '',
            'telephone'  => $wallet['phoneNumber'] ?? 'N/A',
            'fax'        => '',
            'vat_id'     => ''
        ];

        if ($phone !== null && !isset($wallet['phoneNumber'])) {
            $address['telephone'] = $phone;
        }

        // Combine street lines into one string.
        $address['street'] = implode("\n", $address['street']);

        if ($type === 'shipping') {
            $address['email'] = $wallet['emailAddress'] ?? '';
        }

        return $address;
    }

    /**
     * Validate address data; throw an error if required fields are missing.
     *
     * @param array|bool $errors
     * @param string     $addressType
     *
     * @throws ExpressMethodsException
     *
     * @return bool
     */
    protected function setCommonAddressProceed($errors, string $addressType): bool
    {
        $this->logger->addDebug(sprintf(
            '[SET_COMMON_ADDRESS] | [%s:%s] - Address validation errors: %s',
            __METHOD__,
            __LINE__,
            var_export($errors, true)
        ));

        if ($errors && is_array($errors)) {
            foreach ($errors as $error) {
                if (($arguments = $error->getArguments()) && !empty($arguments['fieldName'])) {
                    if ($arguments['fieldName'] === 'postcode') {
                        throw new ExpressMethodsException(
                            'Error: ' . $addressType . ' address: postcode is required.'
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * If no default billing address exists, fill in empty fields required for quote validation.
     *
     * @param ShippingAddressRequestInterface $shippingAddress
     * @param Quote                           $quote
     */
    protected function maybeFillBillingAddressFields(
        ShippingAddressRequestInterface $shippingAddress,
        Quote $quote
    ): void {
        $address = $quote->getBillingAddress();

        $this->fillPlaceholderAddressFields($address);

        if (!$address->getCountryId()) {
            $address->setCountryId($shippingAddress->getCountryCode());
        }

        if (!$address->getPostcode()) {
            $address->setPostcode($shippingAddress->getPostalCode());
        }

        if (!$address->getCity()) {
            $address->setCity($shippingAddress->getCity());
        }

        if (!$address->getRegion()) {
            $address->setRegion($shippingAddress->getState());
        }

        $quote->setBillingAddress($address);
    }

    /**
     * Set Billing Address on SaveOrder.
     *
     * @param Quote       $quote
     * @param array       $data
     * @param string|null $phone
     *
     * @throws ExpressMethodsException
     *
     * @return bool
     */
    public function setBillingAddress(Quote &$quote, array $data, ?string $phone = null): bool
    {
        $billingAddress = $this->processAddressFromWallet($data, 'billing', $phone);
        $quote->getBillingAddress()->addData($billingAddress);

        $errors = $quote->getBillingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'billing');
    }

    /**
     * Assign the given address to the quote.
     *
     * @param AddressInterface $shippingAddress
     * @param Quote            $cart
     *
     * @throws ExpressMethodsException
     *
     * @return Quote
     */
    public function assignAddressToQuote(AddressInterface $shippingAddress, Quote $cart): Quote
    {
        try {
            $this->shippingAddressManagement->assign($cart->getId(), $shippingAddress);
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[ASSIGN_ADDRESS] | [%s:%s] - Failed to assign shipping address: %s',
                __METHOD__,
                __LINE__,
                $e->getMessage()
            ));
            throw new ExpressMethodsException('Assign Shipping Address to Quote failed.');
        }
        $this->quoteRepository->save($cart);
        return $cart;
    }

    /**
     * Set Shipping Address on SaveOrder.
     *
     * @param Quote $quote
     * @param array $data
     *
     * @throws ExpressMethodsException
     *
     * @return bool
     */
    public function setShippingAddress(Quote &$quote, array $data): bool
    {
        $this->logger->addDebug(sprintf(
            '[SET_SHIPPING_ADDRESS] | [%s:%s] - Data: %s',
            __METHOD__,
            __LINE__,
            var_export($data, true)
        ));

        $shippingAddress = $this->processAddressFromWallet($data);
        $quote->getShippingAddress()->addData($shippingAddress);

        $errors = $quote->getShippingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'shipping');
    }
}
