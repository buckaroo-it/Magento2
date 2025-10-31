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
use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Helper\Data as BuckarooHelper;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Pospayment;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Helper\Data as PaymentHelper;
use \Buckaroo\Magento2\Helper\Customer;

class AvailableBasedOnPOSValidator extends AbstractValidator
{
    /**
     * @var Pospayment
     */
    public $pospaymentConfiguration;

    /**
     * @var BuckarooHelper
     */
    public $helper;

    /**
     * @var PaymentHelper
     */
    private $paymentHelper;

    /**
     * @var Customer
     */
    public $customerHelper;

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param Pospayment             $pospaymentConfiguration
     * @param BuckarooHelper         $helper
     * @param PaymentHelper          $paymentHelper
     * @param Customer               $customerHelper
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        Pospayment $pospaymentConfiguration,
        BuckarooHelper $helper,
        PaymentHelper $paymentHelper,
        Customer $customerHelper
    ) {
        parent::__construct($resultFactory);
        $this->pospaymentConfiguration = $pospaymentConfiguration;
        $this->helper = $helper;
        $this->paymentHelper = $paymentHelper;
        $this->customerHelper = $customerHelper;
    }

    /**
     * Available Based on Costumer Group
     *
     * @param  array              $validationSubject
     * @throws Exception
     * @throws LocalizedException
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $isValid = true;

        if (!isset($validationSubject['paymentMethodInstance']) || !isset($validationSubject['quote'])) {
            return $this->createResult(
                false,
                [__('Payment method instance does not exist')]
            );
        }

        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $paymentMethodCode = $paymentMethodInstance->getCode();

        $quote = SubjectReader::readQuote($validationSubject);

        if ($paymentMethodCode !== Pospayment::CODE && $this->pospaymentConfiguration->getActive()) {
            $posPaymentMethodInstance = $this->paymentHelper->getMethodInstance(Pospayment::CODE);
            if ($posPaymentMethodInstance->isAvailable($quote)) {
                $isValid = false;
                if ($this->checkPosOtherPaymentMethods($paymentMethodCode)) {
                    $isValid = true;
                }
            }
        }

        return $this->createResult($isValid);
    }

    /**
     * Check if payment method should be display with POS
     *
     * @param  string $paymentMethodCode
     * @return bool
     */
    private function checkPosOtherPaymentMethods(string $paymentMethodCode): bool
    {
        $otherPaymentMethods = $this->pospaymentConfiguration->getOtherPaymentMethods();
        if ($otherPaymentMethods && in_array(
            $this->customerHelper->getBuckarooMethod($paymentMethodCode),
            explode(',', $otherPaymentMethods)
        )) {
            return true;
        }

        return false;
    }
}
