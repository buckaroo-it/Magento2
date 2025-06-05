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

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Exception;
use Buckaroo\Magento2\Model\ConfigProvider\Account;
use Buckaroo\Magento2\Model\ConfigProvider\Factory as ConfigProviderFactory;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
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
        ConfigProviderFactory $configProviderFactory
    ) {
        $this->configProviderFactory = $configProviderFactory;
        parent::__construct($resultFactory);
    }

    /**
     * Validates if Buckaroo Module is enabled and has valid credentials
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        /**
         * @var Account $accountConfig
         */
        $accountConfig = $this->configProviderFactory->get('account');

        // Check if account is enabled
        if ($accountConfig->getActive() == 0) {
            $isValid = false;
        }

        // Check if credentials are configured
        if ($isValid) {
            $merchantKey = $accountConfig->getMerchantKey();
            $secretKey = $accountConfig->getSecretKey();

            if (empty($merchantKey) || empty($secretKey)) {
                $isValid = false;
            }
        }

        return $this->createResult($isValid);
    }
}
