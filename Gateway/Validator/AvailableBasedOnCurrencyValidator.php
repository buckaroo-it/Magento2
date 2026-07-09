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

use Buckaroo\Magento2\Gateway\Helper\SubjectReader;
use Buckaroo\Magento2\Service\TransactionCurrencyResolver;
use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class AvailableBasedOnCurrencyValidator extends AbstractValidator
{
    /**
     * @var TransactionCurrencyResolver
     */
    private $transactionCurrencyResolver;

    /**
     * @param ResultInterfaceFactory      $resultFactory
     * @param TransactionCurrencyResolver $transactionCurrencyResolver
     */
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        TransactionCurrencyResolver $transactionCurrencyResolver
    ) {
        parent::__construct($resultFactory);
        $this->transactionCurrencyResolver = $transactionCurrencyResolver;
    }

    /**
     * Available Based on Currency
     *
     * Transactions are always sent in the quote/order currency, so the method is
     * available only when it supports that currency. Uses the same allowed-currencies
     * source as the request builders (admin configuration, or the method defaults
     * when not set), so a method is never offered for a quote it cannot transact —
     * the customer can switch the store currency to use it instead.
     *
     * @param array $validationSubject
     *
     * @return ResultInterface
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $paymentMethodInstance = SubjectReader::readPaymentMethodInstance($validationSubject);
        $quoteCurrency = SubjectReader::readQuote($validationSubject)->getCurrency();

        try {
            $isValid = $this->transactionCurrencyResolver->isCurrencyAllowed(
                $quoteCurrency->getQuoteCurrencyCode(),
                $paymentMethodInstance
            );
        } catch (\Exception $e) {
            return $this->createResult(false, [__($e->getMessage())]);
        }

        return $this->createResult($isValid);
    }
}
