<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to support@buckaroo.nl so we can send you a copy immediately.
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
namespace Buckaroo\Magento2\Model\Method;

class Pospayment extends AbstractMethod
{
    /** Payment Code*/
    const PAYMENT_METHOD_CODE = 'buckaroo_magento2_pospayment';

    /** @var string */
    public $buckarooPaymentMethodCode = 'pospayment';

    /** @var string */
    protected $_code                    = self::PAYMENT_METHOD_CODE;

    /** @var bool */
    protected $_canRefund               = false;

    /** @var bool */
    protected $_canVoid                 = false;

    /** @var bool */
    protected $_canRefundInvoicePartial = false;

    /**
     * {@inheritdoc}
     */
    public function getOrderTransactionBuilder($payment)
    {
        $transactionBuilder = $this->transactionBuilderFactory->get('order');

        $services = [
            'Name'             => 'pospayment',
            'Action'           => 'Pay',
            'Version'          => 2,
            'RequestParameter' => [
                [
                    '_'    => $this->getPosPaymentTerminalId(),
                    'Name' => 'TerminalID',
                ],
            ],
        ];

        $transactionBuilder->setOrder($payment->getOrder())
            ->setServices($services)
            ->setMethod('TransactionRequest');

        return $transactionBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getCaptureTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthorizeTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getRefundTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getVoidTransactionBuilder($payment)
    {
        return false;
    }

    /**
     * @return false|string
     */
    private function getPosPaymentTerminalId()
    {
        $cookieManager = $this->objectManager->get(\Magento\Framework\Stdlib\CookieManagerInterface::class);
        $terminalId = $cookieManager->getCookie('Pos-Terminal-Id');
        $this->logger2->addDebug(__METHOD__.'|1|');
        $this->logger2->addDebug(var_export($terminalId, true));
        return $terminalId;
    }

    /**
     * Check whether payment method can be used
     *
     * @param  \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if (parent::isAvailable($quote)) {
            if (!$this->getPosPaymentTerminalId()) {
                return false;
            }

            $header = $this->objectManager->get(\Magento\Framework\HTTP\Header::class);
            $userAgent = $header->getHttpUserAgent();
            $userAgentConfiguration = trim((string)$this->getConfigData('user_agent'));

            $this->logger2->addDebug(var_export([$userAgent, $userAgentConfiguration], true));

            if (strlen($userAgentConfiguration) > 0 && $userAgent != $userAgentConfiguration) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function getOtherPaymentMethods()
    {
        return $this->getConfigData('other_payment_methods');
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface|\Magento\Payment\Model\InfoInterface $payment
     *
     * @return bool|string
     */
    public function getPaymentMethodName($payment)
    {
        return $this->buckarooPaymentMethodCode;
    }
}
