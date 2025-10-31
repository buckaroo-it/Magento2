<?php
namespace Buckaroo\Magento2\Helper;

use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Service\CheckPaymentType;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\AbstractConfigProvider;
use Buckaroo\Magento2\Model\Config\Source\Business;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Customer extends AbstractHelper
{
    /**
     * @var Factory
     */
    protected $configProviderMethodFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var State
     */
    private $state;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckPaymentType
     */
    public $checkPaymentType;

    public function __construct(
        Context $context,
        Factory $configProviderMethodFactory,
        CustomerRepositoryInterface $customerRepository,
        State $state,
        CustomerSession $customerSession,
        CheckPaymentType $checkPaymentType
    ) {
        parent::__construct($context);
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->customerRepository = $customerRepository;
        $this->state = $state;
        $this->customerSession = $customerSession;
        $this->checkPaymentType = $checkPaymentType;
    }

    /**
     * Check if customer group is allowed for the payment method
     *
     * @param  string             $paymentMethod
     * @param  bool               $forceB2C
     * @throws BuckarooException
     * @throws LocalizedException
     * @return bool
     */
    public function checkCustomerGroup(string $paymentMethod, bool $forceB2C = false): bool
    {
        if (!$this->checkPaymentType->isBuckarooMethod($paymentMethod)) {
            return true;
        }

        $paymentMethodCode = $this->getBuckarooMethod($paymentMethod);
        $configProvider = $this->configProviderMethodFactory->get($paymentMethodCode);

        $configCustomerGroup = $this->determineCustomerGroup($paymentMethodCode, $configProvider, $forceB2C);

        if ($configCustomerGroup === null || $configCustomerGroup == Group::CUST_GROUP_ALL) {
            return true;
        }

        if ($configCustomerGroup == -1) {
            return false;
        }

        $configCustomerGroupArr = explode(',', $configCustomerGroup);

        return $this->checkCustomerGroupArea($configCustomerGroupArr);
    }

    /**
     * Determine the appropriate customer group configuration.
     *
     * @param  string                  $paymentMethodCode
     * @param  ConfigProviderInterface $configProvider
     * @param  bool                    $forceB2C
     * @return string|null
     */
    private function determineCustomerGroup(
        string $paymentMethodCode,
        ConfigProviderInterface $configProvider,
        bool $forceB2C
    ): ?string {
        if (!$forceB2C && $this->isSpecialPaymentMethod($paymentMethodCode, $configProvider)) {
            return $configProvider->getSpecificCustomerGroupB2B();
        }

        return $configProvider->getSpecificCustomerGroup();
    }

    /**
     * Check if the payment method is one of the special cases.
     *
     * @param  string                  $paymentMethodCode
     * @param  ConfigProviderInterface $configProvider
     * @return bool
     */
    private function isSpecialPaymentMethod(string $paymentMethodCode, ConfigProviderInterface $configProvider): bool
    {
        return ($paymentMethodCode == 'billink')
            || (($paymentMethodCode == 'afterpay' || $paymentMethodCode == 'afterpay2')
                && $configProvider->getBusiness() == Business::BUSINESS_B2B)
            || ($paymentMethodCode == 'payperemail' && $configProvider->isEnabledB2B());
    }

    /**
     * Check if the customer group is allowed based on the area (admin or front).
     *
     * @param  array              $configCustomerGroupArr
     * @throws LocalizedException
     * @return bool
     */
    private function checkCustomerGroupArea(array $configCustomerGroupArr): bool
    {
        if ($this->state->getAreaCode() == Area::AREA_ADMINHTML) {
            return $this->checkCustomerGroupAdminArea($configCustomerGroupArr);
        } else {
            return $this->checkCustomerGroupFrontArea($configCustomerGroupArr);
        }
    }

    /**
     * Checks if the customer group in the admin area is allowed to use the Buckaroo payment method.
     *
     * @param  array                 $configCustomerGroupArr
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @return bool
     */
    private function checkCustomerGroupAdminArea(array $configCustomerGroupArr): bool
    {
        if (($customerId = $this->_getRequest()->getParam('customer_id')) && ($customerId > 0)
            && ($customer = $this->customerRepository->getById($customerId))
            && $customerGroup = $customer->getGroupId()) {
                return in_array($customerGroup, $configCustomerGroupArr);
        }

        return true;
    }

    /**
     * Check if the current logged in customer's group matches with the allowed customer groups
     *
     * @param  array $configCustomerGroupArr
     * @return bool
     */
    private function checkCustomerGroupFrontArea(array $configCustomerGroupArr): bool
    {
        if ($this->customerSession->isLoggedIn()) {
            if ($customerGroup = $this->customerSession->getCustomer()->getGroupId()) {
                return in_array($customerGroup, $configCustomerGroupArr);
            }
        } else {
            if (!in_array(Group::NOT_LOGGED_IN_ID, $configCustomerGroupArr)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Extracts the Buckaroo payment method code from the full payment method code.
     *
     * @param  string $paymentMethod
     * @return string
     */
    public function getBuckarooMethod(string $paymentMethod): string
    {
        return strtolower(str_replace('buckaroo_magento2_', '', $paymentMethod));
    }
}
