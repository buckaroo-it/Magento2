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
    protected CustomerSession $customerSession;

    /**
     * @var QuoteRepository
     */
    protected QuoteRepository $quoteRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $customerRepository;
    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var Quote
     */
    protected Quote $quote;

    /**
     * @var ShippingAddressManagementInterface
     */
    private ShippingAddressManagementInterface $shippingAddressManagement;

    /**
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param QuoteRepository $quoteRepository
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param BuckarooLoggerInterface $logger
     */
    public function __construct(
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        QuoteRepository $quoteRepository,
        ShippingAddressManagementInterface $shippingAddressManagement,
        BuckarooLoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->customerRepository = $customerRepository;
        $this->quoteRepository = $quoteRepository;
        $this->shippingAddressManagement = $shippingAddressManagement;
        $this->logger = $logger;
    }

    /**
     * Add address from paypal express to quote
     *
     * @param ShippingAddressRequestInterface $shippingAddress
     * @param Quote $cart
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function addAddressToQuote(ShippingAddressRequestInterface $shippingAddress, Quote $cart): Quote
    {
        if ($this->customerSession->isLoggedIn()) {
            $cart->assignCustomerWithAddressChange(
                $this->customerRepository->getById($this->customerSession->getCustomerId())
            );
        }

        $address = $cart->getShippingAddress();

        $address->setCountryId($shippingAddress->getCountryCode());
        $address->setPostcode($shippingAddress->getPostalCode());
        $address->setCity($shippingAddress->getCity());
        $address->setRegion($shippingAddress->getState());
        $this->maybeFillAnyMissingAddressFields($shippingAddress, $cart);

        $this->quoteRepository->save($cart);

        return $cart;
    }

    /**
     * Fill any fields missing from the addresses
     *
     * @param ShippingAddressRequestInterface $shippingAddress
     * @param Quote $quote
     * @return void
     */
    protected function maybeFillAnyMissingAddressFields(ShippingAddressRequestInterface $shippingAddress, Quote $quote)
    {
        $this->maybeFillShippingAddressFields($quote);
        $this->maybeFillBillingAddressFields($shippingAddress, $quote);
    }

    /**
     * If we didn't find any default shipping address we fill the empty fields required for quote validation
     *
     * @param Quote $quote
     * @return void
     */
    protected function maybeFillShippingAddressFields(Quote $quote)
    {
        $address = $quote->getShippingAddress();
        if ($address->getId() === null) {
            $address->setFirstname('unknown');
            $address->setLastname('unknown');
            $address->setTelephone('unknown');
            $address->setEmail('no-reply@example.com');
            $address->setStreet('unknown');
            $quote->setShippingAddress($address);
        }
    }

    /**
     * Set Shipping Address on SaveOrder
     *
     * @param Quote $quote
     * @param array $data
     * @return true
     * @throws ExpressMethodsException
     */
    public function setShippingAddress(Quote &$quote, array $data): bool
    {
        $this->logger->addDebug(sprintf(
            '[SET_SHIPPING_ADDRESS] | [Service] | [%s:%s] - Set Shipping Address | data: %s',
            __METHOD__, __LINE__,
            var_export($data, true)
        ));

        $shippingAddress = $this->processAddressFromWallet($data);
        $quote->getShippingAddress()->addData($shippingAddress);

        $errors = $quote->getShippingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'shipping');
    }

    /**
     * Process Address From Wallet
     *
     * @param array $wallet
     * @param string $type
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function processAddressFromWallet(array $wallet, string $type = 'shipping'): array
    {
        $address = [
            'prefix'     => '',
            'firstname'  => $wallet['givenName'] ?? 'Test',
            'middlename' => '',
            'lastname'   => $wallet['familyName'] ?? 'Acceptatie',
            'street'     => [
                '0' => $wallet['addressLines'][0] ?? 'Hoofdstraat',
                '1' => $wallet['addressLines'][1] ?? '80'
            ],
            'city'       => $wallet['locality'] ?? 'Heerenveen',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '8441ER',
            'region'     => $wallet['administrativeArea'] ?? 'Friesland',
            'region_id'  => '',
            'postcode'   => $wallet['postalCode'] ?? '8441ER',
            'telephone'  => $wallet['phoneNumber'] ?? 'N/A',
            'fax'        => '',
            'vat_id'     => ''
        ];
        $address['street'] = implode("\n", $address['street']);
        if ($type == 'shipping') {
            $address['email'] = $wallet['emailAddress'] ?? '';
        }

        return $address;
    }

    /**
     * Return true or throw an error if post code is not valid
     *
     * @param array|bool $errors
     * @param string $addressType
     * @return true
     * @throws ExpressMethodsException
     */
    protected function setCommonAddressProceed($errors, string $addressType): bool
    {
        $this->logger->addDebug(sprintf(
            '[SET_SHIPPING_ADDRESS] | [Service] | [%s:%s] - Set Shipping Address | errors: %s',
            __METHOD__, __LINE__,
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
     * If we didn't find any default billing address we fill the empty fields required for quote validation
     *
     * @param ShippingAddressRequestInterface $shippingAddress
     * @param Quote $quote
     *
     * @return void
     */
    protected function maybeFillBillingAddressFields(ShippingAddressRequestInterface $shippingAddress, Quote $quote)
    {
        $address = $quote->getBillingAddress();
        if ($address->getId() === null) {
            $address->setFirstname('unknown');
            $address->setLastname('unknown');
            $address->setTelephone('unknown');
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
     * Set Billing Address on SaveOrder
     *
     * @param Quote $quote
     * @param array $data
     * @return true
     * @throws ExpressMethodsException
     */
    public function setBillingAddress(Quote &$quote, array $data): bool
    {
        $billingAddress = $this->processAddressFromWallet($data, 'billing');
        $quote->getBillingAddress()->addData($billingAddress);

        $errors = $quote->getBillingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'billing');
    }

    /**
     * Assign address to quote
     *
     * @param AddressInterface $shippingAddress
     * @param Quote $cart
     * @return Quote
     * @throws ExpressMethodsException
     */
    public function assignAddressToQuote(AddressInterface $shippingAddress, Quote $cart): Quote
    {
        try {
            $this->shippingAddressManagement->assign($cart->getId(), $shippingAddress);
        } catch (\Exception $e) {
            $this->logger->addError(sprintf(
                '[SET_SHIPPING_ADDRESS] | [Service] | [%s:%s] - Set Shipping Address | [ERROR]: %s',
                __METHOD__, __LINE__,
                $e->getMessage()
            ));
            throw new ExpressMethodsException('Assign Shipping Address to Quote failed.');
        }
        $this->quoteRepository->save($cart);

        return $cart;
    }
}
