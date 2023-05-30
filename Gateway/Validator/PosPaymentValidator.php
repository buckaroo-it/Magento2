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

use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Framework\HTTP\Header;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class PosPaymentValidator extends AbstractValidator
{
    /**
     * @var Header
     */
    private Header $header;

    /**
     * @var CookieManagerInterface
     */
    private CookieManagerInterface $cookieManager;

    /**
     * @var BuckarooLog
     */
    private BuckarooLog $buckarooLog;

    /**
     * @var array
     */
    private array $errorMessages = [];

    /**
     * @param ResultInterfaceFactory $resultFactory
     * @param CookieManagerInterface $cookieManager
     * @param BuckarooLog $buckarooLog
     * @param Header $header
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        CookieManagerInterface $cookieManager,
        BuckarooLog $buckarooLog,
        Header $header
    ) {
        $this->header = $header;
        $this->cookieManager = $cookieManager;
        $this->buckarooLog = $buckarooLog;
        parent::__construct($resultFactory);
    }

    /**
     * Validate POS payment method
     *
     * @param array $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $this->validatePayment($validationSubject);
        $this->validateTerminalId();
        $this->validateUserAgent($validationSubject);

        if (empty($this->errorMessages)) {
            return $this->createResult(true);
        } else {
            return $this->createResult(
                false,
                $this->errorMessages
            );
        }
    }

    /**
     * Validate if payment instance exists
     *
     * @param array $validationSubject
     * @return void
     */
    private function validatePayment(array $validationSubject)
    {
        if (!isset($validationSubject['payment'])) {
            $this->errorMessages[] = __('Payment method instance does not exist');
        }
    }

    /**
     * Validate if POS Terminal ID is set
     *
     * @return void
     */
    private function validateTerminalId()
    {
        if (!$this->getPosPaymentTerminalId()) {
            $this->errorMessages[] = __('POS Terminal Id it is not set.');
        }
    }

    /**
     * Get the POS Payment Terminal ID
     *
     * @return string|null
     */
    private function getPosPaymentTerminalId(): ?string
    {
        $terminalId = $this->cookieManager->getCookie('Pos-Terminal-Id');
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');
        $this->buckarooLog->addDebug(var_export($terminalId, true));
        return $terminalId;
    }

    /**
     * Validate if User Agent is set as expected
     *
     * @param array $validationSubject
     * @return void
     */
    private function validateUserAgent(array $validationSubject)
    {
        $paymentMethodInstance = $validationSubject['payment'];

        $userAgent = $this->header->getHttpUserAgent();
        $userAgentConfiguration = trim((string)$paymentMethodInstance->getPayment()->getConfigData('user_agent'));

        $this->buckarooLog->addDebug(var_export([$userAgent, $userAgentConfiguration], true));

        if (strlen($userAgentConfiguration) > 0 && $userAgent != $userAgentConfiguration) {
            $this->errorMessages[] = __('Wrong User Agent configuration');
        }
    }
}
