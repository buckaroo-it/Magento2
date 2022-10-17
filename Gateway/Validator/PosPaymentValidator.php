<?php

namespace Buckaroo\Magento2\Gateway\Validator;

use Buckaroo\Magento2\Logging\Log as BuckarooLog;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class PosPaymentValidator extends AbstractValidator
{
    private \Magento\Framework\HTTP\Header $header;
    private CookieManagerInterface $cookieManager;
    private BuckarooLog $buckarooLog;

    /**
     * @param ResultInterfaceFactory $resultFactory
     */
    public function __construct(
        ResultInterfaceFactory         $resultFactory,
        CookieManagerInterface         $cookieManager,
        BuckarooLog                    $buckarooLog,
        \Magento\Framework\HTTP\Header $header
    ) {
        $this->header = $header;
        $this->cookieManager = $cookieManager;
        $this->buckarooLog = $buckarooLog;
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject): ResultInterface
    {
        if (!isset($validationSubject['payment'])) {
            return $this->createResult(
                false,
                [__('Payment method instance does not exist')]
            );
        }

        $paymentMethodInstance = $validationSubject['payment'];

        if (!$this->getPosPaymentTerminalId()) {
            return $this->createResult(
                false,
                [__('POS Terminal Id it is not set.')]
            );
        }

        $userAgent = $this->header->getHttpUserAgent();
        $userAgentConfiguration = trim((string)$paymentMethodInstance->getConfigData('user_agent'));

        $this->buckarooLog->addDebug(var_export([$userAgent, $userAgentConfiguration], true));

        if (strlen($userAgentConfiguration) > 0 && $userAgent != $userAgentConfiguration) {
            return $this->createResult(
                false,
                [__('Wrong User Agent configuration')]
            );
        }

        return $this->createResult(true);
    }

    /**
     * @return null|string
     */
    private function getPosPaymentTerminalId(): ?string
    {
        $terminalId = $this->cookieManager->getCookie('Pos-Terminal-Id');
        $this->buckarooLog->addDebug(__METHOD__ . '|1|');
        $this->buckarooLog->addDebug(var_export($terminalId, true));
        return $terminalId;
    }
}
