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

use Buckaroo\Magento2\Helper\StoreId;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use Buckaroo\Magento2\Api\Data\PushRequestInterface;
use Buckaroo\Magento2\Exception as BuckarooException;
use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\ConfigProvider\Factory;
use Buckaroo\Magento2\Model\ConfigProvider\Method\CapayableIn3;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
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

    /** Actual payment method used for PayPerEmail orders – used for refunds */
    public const BUCKAROO_ACTUAL_PAYMENT_METHOD = 'buckaroo_actual_payment_method';

    /** Refundable transaction key for PayPerEmail orders */
    public const BUCKAROO_ACTUAL_PAYMENT_TRANSACTION_KEY = 'buckaroo_actual_payment_transaction_key';

    /** Key of the capture (Pay) transaction – the refund target for Klarna methods */
    public const BUCKAROO_CAPTURE_TRANSACTION_KEY = 'buckaroo_capture_transaction_key';

    /** Set once a capture has been registered, so shipments capture offline */
    public const BUCKAROO_ALREADY_CAPTURED = 'buckaroo_already_captured';

    /** Key of the Klarna MOR Reserve DataRequest – never a valid refund target */
    public const BUCKAROO_DATAREQUEST_KEY = 'buckaroo_datarequest_key';
    /**
     * @var bool
     */
    public static $requestOnVoid = true;
    /**
     * @var bool
     */
    public $usesRedirect = true;

    /**
     * @var string
     */
    public $buckarooPaymentMethodCode;

    /**
     * @var Factory
     */
    public $configProviderMethodFactory;

    /**
     * @var Data
     */
    public $priceHelper;

    /**
     * @var RequestInterface|null
     */
    protected $request;

    /**
     * @var BuckarooLoggerInterface
     */
    private $buckarooLogger;
    /**
     * @var int
     */
    protected $payRemainder = 0;

    /**
     * @param ManagerInterface             $eventManager
     * @param ValueHandlerPoolInterface    $valueHandlerPool
     * @param PaymentDataObjectFactory     $paymentDataObjectFactory
     * @param string                       $code
     * @param string                       $formBlockType
     * @param string                       $infoBlockType
     * @param Factory                      $configProviderMethodFactory
     * @param Data                         $priceHelper
     * @param BuckarooLoggerInterface      $buckarooLogger
     * @param RequestInterface|null        $request
     * @param CommandPoolInterface|null    $commandPool
     * @param ValidatorPoolInterface|null  $validatorPool
     * @param CommandManagerInterface|null $commandExecutor
     * @param LoggerInterface|null         $logger
     * @param bool                         $usesRedirect
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
        Factory $configProviderMethodFactory,
        Data $priceHelper,
        BuckarooLoggerInterface $buckarooLogger,
        ?RequestInterface $request = null,
        ?CommandPoolInterface $commandPool = null,
        ?ValidatorPoolInterface $validatorPool = null,
        ?CommandManagerInterface $commandExecutor = null,
        ?LoggerInterface $logger = null,
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
        $this->buckarooLogger = $buckarooLogger;
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
    public function isAvailable(?CartInterface $quote = null)
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
        } catch (\Exception $e) {
            // Fail open (method stays available) but no longer silently: a throwing
            // availability validator means broken configuration, not an unavailable method
            $this->buckarooLogger->addError(sprintf(
                '[AVAILABILITY] | [Adapter] | [%s:%s] - Availability validator failed for %s | [ERROR]: %s',
                __METHOD__,
                __LINE__,
                $this->getCode(),
                $e->getMessage()
            ));
        }

        return parent::isAvailable($quote);
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
            'storeId'        => $this->getResolvedStoreId()
        ]);

        return $result->isValid();
    }

    /**
     * Read a method config value in the store the payment belongs to
     *
     * The parent resolves $storeId === null against the ambient store. For an order payment that
     * is the wrong store in every context that matters: an admin capture or refund runs in the
     * admin scope, a push runs on a REST route with no store cookie, and cron has no store at all.
     * Callers all over the module ask for getConfigData('field') without a store - the store is
     * simply not in their hands - so defaulting it here closes the whole class of bug at once
     * rather than at each call site.
     *
     * An explicit $storeId still wins, so callers that do know better are unaffected.
     *
     * @param string   $field
     * @param int|null $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        return parent::getConfigData($field, $storeId ?? $this->getResolvedStoreId());
    }

    /**
     * Resolve the current store to its integer id when possible.
     *
     * Magento only calls setStore() on a *quote* payment. An *order* payment never gets one, so
     * on the admin order view, the order grid and the invoice/creditmemo create pages getStore()
     * is empty and every getConfigData() read below would fall through to the ambient store —
     * the default store view, not the store the order was placed in. Deriving the store from the
     * payment's own order or quote closes that gap the way core's
     * Payment\Helper\Data::getInfoBlockHtml() does with an explicit setStore().
     *
     * @return int|null
     */
    private function getResolvedStoreId(): ?int
    {
        $storeId = StoreId::normalize($this->getStore());

        if ($storeId !== null) {
            return $storeId;
        }

        return $this->getStoreIdFromInfoInstance();
    }

    /**
     * Derive the store id from the order or quote the payment belongs to.
     *
     * @return int|null
     */
    private function getStoreIdFromInfoInstance(): ?int
    {
        try {
            $info = $this->getInfoInstance();
        } catch (\Exception $exception) {
            return null;
        }

        if ($info instanceof OrderPayment && $info->getOrder() !== null) {
            return StoreId::normalize($info->getOrder()->getStoreId());
        }

        if ($info instanceof QuotePayment && $info->getQuote() !== null) {
            return StoreId::normalize($info->getQuote()->getStoreId());
        }

        return null;
    }

    /**
     * Can process post data received on push or on redirect
     *
     * @param OrderPaymentInterface|InfoInterface $payment
     * @param PushRequestInterface                $postData
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
     *
     * @return bool
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function canPushInvoice(PushRequestInterface $responseData): bool
    {
        if ($this->getConfigData('payment_action', $this->getResolvedStoreId()) == 'authorize') {
            return false;
        }

        return true;
    }

    /**
     * Get payment method title
     *
     * @throws BuckarooException
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getTitle(): string
    {
        $storeId = $this->getResolvedStoreId();
        $title = $this->getConfigData('title', $storeId);

        $configProvider = $this->configProviderMethodFactory
            ->get($this->buckarooPaymentMethodCode);

        if (!is_string($title) || strlen(trim($title)) === 0) {
            $title = DefaultTitles::get($this->buckarooPaymentMethodCode);
        }

        if (strpos($this->buckarooPaymentMethodCode, "capayable") !== false &&
            method_exists($configProvider, 'isV2') &&
            $configProvider->isV2() &&
            $title === CapayableIn3::DEFAULT_NAME
        ) {
            $title = CapayableIn3::V2_NAME;
        }

        if (!$this->configProviderMethodFactory->has($this->buckarooPaymentMethodCode)) {
            return $title;
        }

        $paymentFee = trim((string)$configProvider->getPaymentFee($storeId));

        return $this->addPaymentFee($title, $paymentFee);
    }

    /**
     * Add payment fee to the given title
     *
     * @param string $title
     * @param string $paymentFee
     *
     * @return string
     */
    protected function addPaymentFee(string $title, string $paymentFee): string
    {
        if (!empty($paymentFee) && (float)$paymentFee >= 0.01) {
            if (strpos($paymentFee, '%') === false) {
                return $title . ' + ' . $this->priceHelper->currency(number_format((float)$paymentFee, 2), true, false);
            } else {
                return $title . ' + ' . $paymentFee;
            }
        }

        return $title;
    }
}
