<?php

namespace Buckaroo\Magento2\Model\Service;

use Buckaroo\Magento2\Api\Data\ExpressMethods\ShippingAddressRequestInterface;
use Buckaroo\Magento2\Logging\Log;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteRepository;
use Magento\Quote\Model\ShippingAddressManagementInterface;

class QuoteAddressService
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var \Buckaroo\Magento2\Logging\Log
     */
    protected $logger;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quote;

    /**
     * @var ShippingAddressManagementInterface
     */
    private $shippingAddressManagement;

    /**
     * @param CustomerSession $customerSession
     * @param CustomerRepositoryInterface $customerRepository
     * @param QuoteRepository $quoteRepository
     * @param ShippingAddressManagementInterface $shippingAddressManagement
     * @param Log $logger
     */
    public function __construct(
        CustomerSession $customerSession,
        CustomerRepositoryInterface $customerRepository,
        QuoteRepository $quoteRepository,
        ShippingAddressManagementInterface $shippingAddressManagement,
        Log $logger
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
     * @return Quote
     */
    public function addAddressToQuote(ShippingAddressRequestInterface $shippingAddress, $cart)
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
     * @param ShippingAddressRequestInterface $shipping_address
     * @param $quote
     * @return void
     */
    protected function maybeFillAnyMissingAddressFields(ShippingAddressRequestInterface $shipping_address, $quote)
    {
        $this->maybeFillShippingAddressFields($quote);
        $this->maybeFillBillingAddressFields($shipping_address, $quote);
    }

    /**
     * If we didn't find any default shipping address we fill the empty fields
     * required for quote validation
     *
     * @param Quote $quote
     * @return void
     */
    protected function maybeFillShippingAddressFields($quote)
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
     * If we didn't find any default billing address we fill the empty fields
     * required for quote validation
     *
     * @param ShippingAddressRequestInterface $shipping_address
     * @param Quote $quote
     *
     * @return void
     */
    protected function maybeFillBillingAddressFields(ShippingAddressRequestInterface $shipping_address, $quote)
    {
        $address = $quote->getBillingAddress();
        if ($address->getId() === null) {
            $address->setFirstname('unknown');
            $address->setLastname('unknown');
            $address->setTelephone('unknown');
            $address->setEmail('no-reply@example.com');
            $address->setStreet('unknown');
            $address->setCountryId($shipping_address->getCountryCode());
            $address->setPostcode($shipping_address->getPostalCode());
            $address->setCity($shipping_address->getCity());
            $address->setRegion($shipping_address->getState());
            $quote->setBillingAddress($address);
        }
    }

    public function assignAddressToQuote($shippingAddress, $cart)
    {
        try {
            $this->shippingAddressManagement->assign($cart->getId(), $shippingAddress);
        } catch (\Exception $e) {
            $this->logger->addDebug(__METHOD__ . '|9.1|' .  $e->getMessage());
            throw new ExpressMethodsException(__(
                'Assign Shipping Address to Quote failed.'
            ));
        }
        $this->quoteRepository->save($cart);

        return $cart;
    }

    /**
     * Set Shipping Address on SaveOrder
     */
    public function setShippingAddress(&$quote, $data)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $shippingAddress = $this->processAddressFromWallet($data);
        $quote->getShippingAddress()->addData($shippingAddress);
        $quote->setShippingAddress($quote->getShippingAddress());

        $errors = $quote->getShippingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'shipping');
    }

    /**
     * Set Billing Address on SaveOrder
     */
    public function setBillingAddress(&$quote, $data)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');

        $billingAddress = $this->processAddressFromWallet($data, 'billing');
        $quote->getBillingAddress()->addData($billingAddress);
        $quote->setBillingAddress($quote->getBillingAddress());

        $errors = $quote->getBillingAddress()->validate();
        return $this->setCommonAddressProceed($errors, 'billing');
    }

    protected function setCommonAddressProceed($errors, $addressType)
    {
        $this->logger->addDebug(__METHOD__ . '|1|');
        $this->logger->addDebug(var_export($errors, true));

        if ($errors && is_array($errors)) {
            foreach ($errors as $error) {
                if (($arguments = $error->getArguments()) && !empty($arguments['fieldName'])) {
                    if ($arguments['fieldName'] === 'postcode') {
                        $this->logger->addDebug(var_export($error->getArguments()['fieldName'], true));
                        throw new ExpressMethodsException(__(
                            'Error: ' . $addressType . ' address: postcode is required.'
                        ));
                    }
                }
            }
        }

        return true;
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
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = [
            'prefix' => '',
            'firstname' => $wallet['givenName'] ?? 'Test',
            'middlename' => '',
            'lastname' => $wallet['familyName'] ?? 'Acceptatie',
            'street' => [
                '0' => $wallet['addressLines'][0] ?? 'Hoofdstraat',
                '1' => $wallet['addressLines'][1] ?? '80'
            ],
            'city' => $wallet['locality'] ?? 'Heerenveen',
            'country_id' => isset($wallet['countryCode']) ? strtoupper($wallet['countryCode']) : '8441ER',
            'region' => $wallet['administrativeArea'] ?? 'Friesland',
            'region_id' => '',
            'postcode' => $wallet['postalCode'] ?? '8441ER',
            'telephone' => $wallet['phoneNumber'] ?? 'N/A',
            'fax' => '',
            'vat_id' => ''
        ];
        $address['street'] = implode("\n", $address['street']);
        if ($type == 'shipping') {
            $address['email'] = $wallet['emailAddress'] ?? '';
        }

        return $address;
    }
}
