<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class ActiveAccountValidator extends AbstractValidator
{
    /**
     * @var ConfigProviderFactory
     */
    private ConfigProviderFactory $configProviderFactory;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param ConfigProviderFactory $configProviderFactory
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        ConfigProviderFactory  $configProviderFactory
    ) {
        $this->configProviderFactory = $configProviderFactory;
        parent::__construct($resultFactory);
    }

    /**
     * Validate if Buckaroo Module is enabled
     *
     * @param array $validationSubject
     * @return \Magento\Payment\Gateway\Validator\ResultInterface
     * @throws \Buckaroo\Magento2\Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate(array $validationSubject)
    {
        $isValid = true;

        /**
         * @var Account $accountConfig
         */
        $accountConfig = $this->configProviderFactory->get('account');
        if ($accountConfig->getActive() == 0) {
            $isValid = false;
        }

        return $this->createResult($isValid);
    }
}
