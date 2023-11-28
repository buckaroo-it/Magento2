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

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Quote\Model\Quote\Payment;

class IdealIssuerValidator extends IssuerValidator
{

    /**
     * Validate issuer
     *
     * @param array $validationSubject
     * @return ResultInterface
     * @throws LocalizedException
     */
    public function validate(array $validationSubject): ResultInterface
    {
        /** @var Payment $paymentInfo */
        $paymentInfo = $validationSubject['payment'];

        /** @var \Buckaroo\Magento2\Model\ConfigProvider\Method\Ideal */
        $idealConfig = $this->getConfig($paymentInfo);
        if ($idealConfig->canShowIssuers()) {
            return parent::validate($validationSubject);
        }
        return $this->createResult(true);
    }

}
