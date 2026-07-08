<?php

namespace Buckaroo\Magento2\Service;

use Magento\Customer\Api\CustomerRepositoryInterface;

class CustomerAttributes
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->customerRepository = $customerRepository;
    }

    /**
     * Set a custom attribute value on the given customer and save it.
     *
     * @param int $customerId
     * @param string $attribute
     * @param string $value
     * @return void
     */
    public function setAttribute(int $customerId, string $attribute, string $value)
    {
        $customer = $this->customerRepository->getById($customerId);
        $customer->setCustomAttribute($attribute, $value);
        $this->customerRepository->save($customer);
    }
}
