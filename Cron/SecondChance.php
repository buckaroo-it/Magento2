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

use Magento\Store\Model\ScopeInterface;

class SecondChance
{
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
                $now          = new \DateTime();
                $secondChance = $this->secondChanceFactory->create();
                $collection   = $secondChance->getCollection()
                    ->addFieldToFilter(
                        'status',
                        array('eq' => '')
                    )
                    ->addFieldToFilter(
                        'store_id',
                        array('eq' => $store->getId())
                    )
                    ->addFieldToFilter('created_at', ['lteq' => date('Y-m-d H:i:s', strtotime('-' . $this->accountConfig->getSecondChanceTiming($store) . ' hour', strtotime($now->format('Y-m-d H:i:s'))))]);
                foreach ($collection as $item) {
                    $order = $this->orderFactory->create()->loadByIncrementId($item->getOrderId());
                    if (in_array($order->getState(), ['canceled', 'processing', 'new'])) {
                        if ($this->accountConfig->getNoSendSecondChance($store)) {
                            if ($this->checkOrderProductsIsInStock($order)) {
                                $this->sendMail($order, $item);
                            }
                        } else {
                            $this->sendMail($order, $item);
                        }
                    }
                    $item->setStatus('1');
                }
                $collection->save();
            }
        }
        return $this;
    }

    public function sendMail($order, $secondChance)
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

        $this->inlineTranslation->suspend();
        $this->transportBuilder->setTemplateIdentifier('buckaroo_second_chance')
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
            if (!$this->stockItemRepository->get($orderItem->getId())->getIsInStock()) {
                return false;
            }
        }
        return true;
    }
}
