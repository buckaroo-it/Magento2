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
namespace Buckaroo\Magento2\Cron;

use Buckaroo\Magento2\Model\Method\Transfer;
use Magento\Store\Model\ScopeInterface;

class SecondChance
{
    const XPATH_SECOND_CHANCE_TEMPLATE          = 'buckaroo_magento2/account/second_chance_template';
    const XPATH_SECOND_CHANCE_TEMPLATE2         = 'buckaroo_magento2/account/second_chance_template2';
    const XPATH_SECOND_CHANCE_DEFAULT_TEMPLATE  = 'buckaroo_second_chance';
    const XPATH_SECOND_CHANCE_DEFAULT_TEMPLATE2 = 'buckaroo_second_chance2';
    const XPATH_SECOND_CHANCE_FINAL_STATUS      = 10;
    /**
     * @var \Buckaroo\Magento2\Model\SecondChanceFactory
     */
    protected $secondChanceFactory;

    protected $accountConfig;

    protected $dateTime;

    protected $orderFactory;

    protected $transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var Log $logging
     */
    public $logging;

    protected $scopeConfig;

    protected $storeManager;

    /**
     * @var Renderer
     */
    protected $addressRenderer;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    private $paymentHelper;

    private $storeRepository;

    protected $stockItemRepository;

    /**
     * @param \Magento\Checkout\Model\Session\Proxy                $checkoutSession
     * @param \Buckaroo\Magento2\Model\ConfigProvider\Account      $accountConfig
     * @param \Buckaroo\Magento2\Model\SecondChanceFactory         $secondChanceFactory
     * @param \Magento\Framework\Stdlib\DateTime\DateTime          $dateTime
     */
    public function __construct(
        \Buckaroo\Magento2\Model\ConfigProvider\Account $accountConfig,
        \Buckaroo\Magento2\Model\SecondChanceFactory $secondChanceFactory,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order\Address\Renderer $addressRenderer,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Sales\Model\Order\Email\Container\ShipmentIdentity $identityContainer,
        \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository,
        \Buckaroo\Magento2\Logging\Log $logging
    ) {
        $this->accountConfig       = $accountConfig;
        $this->secondChanceFactory = $secondChanceFactory;
        $this->dateTime            = $dateTime;
        $this->orderFactory        = $orderFactory;
        $this->inlineTranslation   = $inlineTranslation;
        $this->transportBuilder    = $transportBuilder;
        $this->scopeConfig         = $scopeConfig;
        $this->storeManager        = $storeManager;
        $this->addressRenderer     = $addressRenderer;
        $this->paymentHelper       = $paymentHelper;
        $this->identityContainer   = $identityContainer;
        $this->storeRepository     = $storeRepository;
        $this->stockItemRepository = $stockItemRepository;
        $this->logging             = $logging;
    }

    public function execute()
    {
        $stores = $this->storeRepository->getList();
        foreach ($stores as $store) {
            if ($this->accountConfig->getSecondChance($store)) {
                $now = new \DateTime();

                foreach ([2, 1] as $step) {
                    $this->logging->addDebug(__METHOD__ . '|secondChance step|' . $step);

                    if ($step == 1) {
                        $timing = $this->accountConfig->getSecondChanceTiming($store);
                    } else {
                        $timing = $this->accountConfig->getSecondChanceTiming($store) + $this->accountConfig->getSecondChanceTiming2($store);
                    }

                    $this->logging->addDebug(__METHOD__ . '|secondChance timing|' . $timing);
                    $secondChance = $this->secondChanceFactory->create();
                    $collection   = $secondChance->getCollection()
                        ->addFieldToFilter(
                            'status',
                            array('eq' => ($step == 2) ? 1 : '')
                        )
                        ->addFieldToFilter(
                            'store_id',
                            array('eq' => $store->getId())
                        )
                        ->addFieldToFilter('created_at', ['lteq' => new \Zend_Db_Expr('NOW() - INTERVAL '.$timing.' DAY')])
                        ->addFieldToFilter('created_at', ['gteq' => new \Zend_Db_Expr('NOW() - INTERVAL 5 DAY')]);

                    foreach ($collection as $item) {
                        $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());

                        $payment = $order->getPayment();
                        if (in_array($payment->getMethod(), [Transfer::PAYMENT_METHOD_CODE])) {
                            $this->setFinalStatus($item);
                            continue;
                        }

                        if ($item->getLastOrderId() != null && $last_order = $this->orderFactory->create()->loadByIncrementId($item->getLastOrderId())) {
                            if ($last_order->hasInvoices()) {
                                $this->setFinalStatus($item);
                                continue;
                            }
                        }

                        if ($order->hasInvoices()) {
                            $this->setFinalStatus($item);
                        } else {
                            if ($this->accountConfig->getNoSendSecondChance($store)) {
                                $this->logging->addDebug(__METHOD__ . '|getNoSendSecondChance|');
                                if ($this->checkOrderProductsIsInStock($order)) {
                                    $this->logging->addDebug(__METHOD__ . '|checkOrderProductsIsInStock|');
                                    $this->sendMail($order, $item, $step);
                                }
                            } else {
                                $this->logging->addDebug(__METHOD__ . '|else getNoSendSecondChance|');
                                $this->sendMail($order, $item, $step);
                            }
                        }
                    }
                    $collection->save();
                }

            }
        }
        return $this;
    }

    public function sendMail($order, $secondChance, $step)
    {
        $vars = [
            'order'                    => $order,
            'billing'                  => $order->getBillingAddress(),
            'payment_html'             => $this->getPaymentHtml($order),
            'store'                    => $order->getStore(),
            'formattedShippingAddress' => $this->getFormattedShippingAddress($order),
            'formattedBillingAddress'  => $this->getFormattedBillingAddress($order),
            'secondChanceToken'        => $secondChance->getToken(),
        ];

        if ($step == 1) {
            $templateId = $this->scopeConfig->getValue(self::XPATH_SECOND_CHANCE_TEMPLATE, ScopeInterface::SCOPE_STORE) ?? self::XPATH_SECOND_CHANCE_DEFAULT_TEMPLATE;
        } else {
            $templateId = $this->scopeConfig->getValue(self::XPATH_SECOND_CHANCE_TEMPLATE2, ScopeInterface::SCOPE_STORE) ?? self::XPATH_SECOND_CHANCE_DEFAULT_TEMPLATE2;
        }

        $this->logging->addDebug(__METHOD__ . '|TemplateIdentifier|' . $templateId);

        $this->inlineTranslation->suspend();
        $this->transportBuilder->setTemplateIdentifier($templateId)
            ->setTemplateOptions(
                [
                    'area'  => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $order->getStore()->getId(),
                ]
            )->setTemplateVars(
            $vars
        )->setFrom([
            'email' => $this->scopeConfig->getValue('trans_email/ident_sales/email', ScopeInterface::SCOPE_STORE),
            'name'  => $this->scopeConfig->getValue('trans_email/ident_sales/name', ScopeInterface::SCOPE_STORE),
        ])->addTo($order->getCustomerEmail());

        if (!isset($transport)) {
            $transport = $this->transportBuilder->getTransport();
        }

        try {
            $transport->sendMessage();
            $this->inlineTranslation->resume();
            $secondChance->setStatus($step);
            $secondChance->save();
            $this->logging->addDebug(__METHOD__ . '|secondChanceEmail is sended to|' . $order->getCustomerEmail());
        } catch (\Exception $exception) {
            $this->logging->addDebug(__METHOD__ . '|log failed email send|' . $exception->getMessage());
        }
    }

    /**
     * Render shipping address into html.
     *
     * @param Order $order
     * @return string|null
     */
    protected function getFormattedShippingAddress($order)
    {
        return $order->getIsVirtual()
        ? null
        : $this->addressRenderer->format($order->getShippingAddress(), 'html');
    }

    /**
     * Render billing address into html.
     *
     * @param Order $order
     * @return string|null
     */
    protected function getFormattedBillingAddress($order)
    {
        return $this->addressRenderer->format($order->getBillingAddress(), 'html');
    }

    /**
     * Returns payment info block as HTML.
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     *
     * @return string
     * @throws \Exception
     */
    private function getPaymentHtml(\Magento\Sales\Api\Data\OrderInterface $order)
    {
        return $this->paymentHelper->getInfoBlockHtml(
            $order->getPayment(),
            $this->identityContainer->getStore()->getStoreId()
        );
    }

    public function checkOrderProductsIsInStock($order)
    {
        foreach ($order->getAllItems() as $orderItem) {
            $productStock = $this->stockItemRepository->get($orderItem->getProductId());
            if (!$productStock->getIsInStock()) {
                $this->logging->addDebug(__METHOD__ . '|not getIsInStock|' . $orderItem->getProductId());
                return false;
            }
        }
        return true;
    }

    public function setFinalStatus($item)
    {
        $item->setStatus(self::XPATH_SECOND_CHANCE_FINAL_STATUS);
        return $item->save();
    }
}