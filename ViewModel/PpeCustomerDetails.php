<?php

namespace Buckaroo\Magento2\ViewModel;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class PpeCustomerDetails implements ArgumentInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    protected CustomerRepositoryInterface $customerRepository;

    /**
     * @var BuckarooLoggerInterface
     */
    protected BuckarooLoggerInterface $logger;

    /**
     * @var Http
     */
    protected Http $request;

    /**
     * @var array
     */
    private array $staticCache = [];

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param BuckarooLoggerInterface $logger
     * @param Http $request
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        BuckarooLoggerInterface $logger,
        Http $request
    ) {
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->request = $request;
    }
    /**
     * Try to fetch customer details for PPE method in admin area
     *
     * @return array|null
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    //phpcs:ignore:Generic.Metrics.NestingLevel
    public function getPPeCustomerDetails(): ?array
    {
        $this->logger->addDebug(sprintf(
            '[Helper - PayPerEmail] | [Helper] | [%s:%s] - Get PPE Customer details | originalRequest: %s',
            __METHOD__,
            __LINE__,
            var_export($this->request->getParams(), true)
        ));
        if (($customerId = $this->request->getParam('customer_id')) && ((int)$customerId > 0)) {
            if (!isset($this->staticCache['getPPeCustomerDetails'])
                && ($customer = $this->customerRepository->getById((int)$customerId))
            ) {
                $billingAddress = null;
                if ($addresses = $customer->getAddresses()) {
                    foreach ($addresses as $address) {
                        if ($address->isDefaultBilling()) {
                            $billingAddress = $address;
                            break;
                        }
                    }
                }
                $this->logger->addDebug(sprintf(
                    '[Helper - PayPerEmail] | [Helper] | [%s:%s] - Get PPE Customer details | customerEmail: %s',
                    __METHOD__,
                    __LINE__,
                    $customer->getEmail()
                ));
                $this->staticCache['getPPeCustomerDetails'] = [
                    'email'      => $customer->getEmail(),
                    'firstName'  => $billingAddress ? $billingAddress->getFirstName() : '',
                    'lastName'   => $billingAddress ? $billingAddress->getLastName() : '',
                    'middleName' => $billingAddress ? $billingAddress->getMiddlename() : '',
                ];
            }
        }

        if ($order = $this->request->getParam('order')) {
            if (isset($order['billing_address'])) {
                $this->staticCache['getPPeCustomerDetails'] = [
                    'email'      => !empty($this->staticCache['getPPeCustomerDetails']['email']) ?
                        $this->staticCache['getPPeCustomerDetails']['email'] : '',
                    'firstName'  => $order['billing_address']['firstname'],
                    'lastName'   => $order['billing_address']['lastname'],
                    'middleName' => $order['billing_address']['middlename'],
                ];
            }
        }

        if (($payment = $this->request->getParam('payment'))
            && ($payment['method'] == 'buckaroo_magento2_payperemail')
        ) {
            $this->staticCache['getPPeCustomerDetails'] = [
                'email'      => $payment['customer_email'],
                'firstName'  => $payment['customer_billingFirstName'],
                'lastName'   => $payment['customer_billingLastName'],
                'middleName' => $payment['customer_billingMiddleName'],
            ];
        }

        return $this->staticCache['getPPeCustomerDetails'] ?? null;
    }
}
