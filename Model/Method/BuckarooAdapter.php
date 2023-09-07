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

namespace Buckaroo\Magento2\Model\Method;

use Buckaroo\Magento2\Api\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Factory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Pricing\Helper\Data;
use Magento\Payment\Gateway\Command\CommandManagerInterface;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Payment\Gateway\Config\ValueHandlerPoolInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectFactory;
use Magento\Payment\Gateway\Validator\ValidatorPoolInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Adapter;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class BuckarooAdapter extends Adapter
{
    public const BUCKAROO_ORIGINAL_TRANSACTION_KEY_KEY = 'buckaroo_original_transaction_key';
    public const BUCKAROO_ALL_TRANSACTIONS             = 'buckaroo_all_transactions';
    public const BUCKAROO_PAYMENT_IN_TRANSIT           = 'buckaroo_payment_in_transit';
    public const PAYMENT_FROM                          = 'buckaroo_payment_from';
    public const PAYMENT_ATTEMPTS_REACHED_MESSAGE      = 'buckaroo_payment_attempts_reached_message';

    /**
     * @var bool
     */
    public static bool $requestOnVoid = true;
    /**
     * @var bool
     */
    public bool $usesRedirect = true;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode;

    /**
     * @var Factory
     */
    public Factory $configProviderMethodFactory;

    /**
     * @var Data
     */
    public Data $priceHelper;

    /**
     * @var ObjectManagerInterface
     */
    protected ObjectManagerInterface $objectManager;

    /**
     * @var RequestInterface|null
     */
    protected ?RequestInterface $request;
    /**
     * @var int
     */
    protected int $payRemainder = 0;

    /**
     * @param ManagerInterface $eventManager
     * @param ValueHandlerPoolInterface $valueHandlerPool
     * @param PaymentDataObjectFactory $paymentDataObjectFactory
     * @param string $code
     * @param string $formBlockType
     * @param string $infoBlockType
     * @param ObjectManagerInterface $objectManager
     * @param Factory $configProviderMethodFactory
     * @param Data $priceHelper
     * @param RequestInterface|null $request
     * @param CommandPoolInterface|null $commandPool
     * @param ValidatorPoolInterface|null $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param LoggerInterface|null $logger
     * @param bool $usesRedirect
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ManagerInterface $eventManager,
        ValueHandlerPoolInterface $valueHandlerPool,
        PaymentDataObjectFactory $paymentDataObjectFactory,
        $code,
        $formBlockType,
        $infoBlockType,
        ObjectManagerInterface $objectManager,
        Factory $configProviderMethodFactory,
        Data $priceHelper,
        RequestInterface $request = null,
        CommandPoolInterface $commandPool = null,
        ValidatorPoolInterface $validatorPool = null,
        CommandManagerInterface $commandExecutor = null,
        LoggerInterface $logger = null,
        bool $usesRedirect = true
    ) {
        parent::__construct(
            $eventManager,
            $valueHandlerPool,
            $paymentDataObjectFactory,
            $code,
            $formBlockType,
            $infoBlockType,
            $commandPool,
            $validatorPool,
            $commandExecutor,
            $logger
        );

        $this->buckarooPaymentMethodCode = $this->setBuckarooPaymentMethodCode();
        $this->objectManager = $objectManager;
        $this->request = $request;
        $this->usesRedirect = $usesRedirect;
        $this->configProviderMethodFactory = $configProviderMethodFactory;
        $this->priceHelper = $priceHelper;
    }

    /**
     * Set Buckaroo Payment
     *
     * @return array|string|string[]
     */
    protected function setBuckarooPaymentMethodCode()
    {
        return str_replace('buckaroo_magento2_', '', $this->getCode());
    }

    /**
     * @inheritdoc
     */
    public function isAvailable(CartInterface $quote = null)
    {
        if (null == $quote) {
            return false;
        }

        try {
            $validator = $this->getValidatorPool()->get('buckaroo_availability');
            $result = $validator->validate(
                [
                    'paymentMethodInstance' => $this,
                    'quote'                 => $quote
                ]
            );
            if (!$result->isValid()) {
                return false;
            }
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        } catch (\Exception $e) {
            // pass
        }

        return parent::isAvailable($quote);
    }

    /**
     * @inheritdoc
     */
    public function order(InfoInterface $payment, $amount)
    {
        $commandCode = 'order';
        if ($this->getConfigData('api_version')) {
            $commandCode .= strtolower($this->getConfigData('api_version'));
        }

        $reflection = new \ReflectionClass($this);
        $method = $reflection->getMethod('executeCommand');
        $method->setAccessible(true);

        $method->invoke($this, $commandCode, ['payment' => $payment, 'amount' => $amount]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function cancel(InfoInterface $payment)
    {
        if (!self::$requestOnVoid) {
            return $this;
        }

        return parent::cancel($payment);
    }

    /**
     * @inheritdoc
     */
    public function void(InfoInterface $payment)
    {
        if (!self::$requestOnVoid) {
            return $this;
        }

        return parent::void($payment);
    }

    /**
     * @inheritdoc
     */
    public function canUseForCountry($country): bool
    {
        try {
            $validator = $this->getValidatorPool()->get('country');
        } catch (\Exception $e) {
            return true;
        }

        $result = $validator->validate([
            'methodInstance' => $this,
            'country'        => $country,
            'storeId'        => $this->getStore()
        ]);

        return $result->isValid();
    }

    /**
     * Can process post data received on push or on redirect
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param PushRequestInterface $postData
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canProcessPostData($payment, PushRequestInterface $postData): bool
    {
        return true;
    }

    /**
     * Can create invoice on push
     *
     * @param PushRequestInterface $responseData
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canPushInvoice(PushRequestInterface $responseData): bool
    {
        if ($this->getConfigData('payment_action') == 'authorize') {
            return false;
        }

        return true;
    }

    /**
     * Get payment method title
     *
     * @return string
     * @throws BuckarooException
     */
    public function getTitle(): string
    {
        $title = $this->getConfigData('title');


        if (!is_string($title) || strlen(trim($title)) === 0) {
            $title = DefaultTitles::get($this->buckarooPaymentMethodCode);
        }

        if (!$this->configProviderMethodFactory->has($this->buckarooPaymentMethodCode)) {
            return $title;
        }

        $paymentFee = trim((string)$this->configProviderMethodFactory
            ->get($this->buckarooPaymentMethodCode)
            ->getPaymentFee());

        if (!$paymentFee || (float)$paymentFee < 0.01) {
            return $title;
        }

        if (strpos($paymentFee, '%') === false) {
            $title .= ' + ' . $this->priceHelper->currency(number_format((float)$paymentFee, 2), true, false);
        } else {
            $title .= ' + ' . $paymentFee;
        }

        return $title;
    }
}
