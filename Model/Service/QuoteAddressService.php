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
use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Logging\Log;
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
    private CustomerSession $customerSession;
    private QuoteRepository $quoteRepository;
    private CustomerRepositoryInterface $customerRepository;
    private Log $logger;
    private ShippingAddressManagementInterface $shippingAddressManagement;

    /**
     * @param CustomerSession                    $customerSession
     * @param CustomerRepositoryInterface        $customerRepository
     * @param QuoteRepository                    $quoteRepository
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param Log                                $logger
     */
    public function __construct(
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        QuoteRepository $quoteRepository,
        ShippingAddressManagementInterface $shippingAddressManagement,
        Log $logger
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
     * @param  ShippingAddressRequestInterface $shippingAddress
     * @param  Quote                           $cart
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return Quote
     */
    public function addAddressToQuote(ShippingAddressRequestInterface $shippingAddress, Quote $cart): Quote
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

        // Fill any missing fields on both shipping and billing addresses.
        $this->maybeFillAnyMissingAddressFields($shippingAddress, $cart);

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
        if ($address->getId() === null) {
            $address->setFirstname('unknown');
            $address->setLastname('unknown');
            $address->setEmail('no-reply@example.com');
            $address->setStreet('unknown');
            $quote->setShippingAddress($address);
        }
    }

    /**
     * Process address data from the wallet.
     *
     * @param  array       $wallet
     * @param  string      $type
     * @param  string|null $phone
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
                $wallet['addressLines'][1] ?? null,
            ],
            'city'       => $wallet['locality'] ?? '',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '',
            'region'     => $wallet['administrativeArea'] ?? '',
            'region_id'  => '',
            'postcode'   => $wallet['postalCode'] ?? '',
            'telephone'  => $wallet['phoneNumber'] ?? 'N/A',
            'fax'        => '',
            'vat_id'     => '',
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
     * @param  array|bool $errors
     * @param  string     $addressType
     * @throws Exception
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
                $arguments = $error->getArguments();
                if (!empty($arguments['fieldName']) && $arguments['fieldName'] === 'postcode') {
                    throw new Exception(
                        __('Error: %1 address: postcode is required.', $addressType)
                    );
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
        if ($address->getId() === null) {
            $address->setFirstname('unknown');
            $address->setLastname('unknown');
            $address->setEmail('no-reply@example.com');
            $address->setStreet('unknown');
            $address->setCountryId($shippingAddress->getCountryCode());
            $address->setPostcode($shippingAddress->getPostalCode());
            $address->setCity($shippingAddress->getCity());
            $address->setRegion($shippingAddress->getState());
            $quote->setBillingAddress($address);
        }
    }

    /**
     * Set Billing Address on SaveOrder.
     *
     * @param  Quote       $quote
     * @param  array       $data
     * @param  string|null $phone
     * @throws Exception
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
     * @param  AddressInterface $shippingAddress
     * @param  Quote            $cart
     * @throws Exception
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
            throw new Exception(__('Assign Shipping Address to Quote failed.'));
        }
        $this->quoteRepository->save($cart);
        return $cart;
    }

    /**
     * Set Shipping Address on SaveOrder.
     *
     * @param  Quote     $quote
     * @param  array     $data
     * @throws Exception
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
