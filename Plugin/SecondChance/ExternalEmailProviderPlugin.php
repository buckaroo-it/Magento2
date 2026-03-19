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

namespace Buckaroo\Magento2\Plugin\SecondChance;

use Buckaroo\Magento2\Model\SecondChanceRepository;
use Buckaroo\Magento2\Model\ConfigProvider\ExternalEmailProvider as ExternalEmailConfig;
use Buckaroo\Magento2\Model\ConfigProvider\SecondChance as SecondChanceConfig;
use Buckaroo\Magento2\Service\EmailProvider\EmailSender;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Model\Order;
use Magento\Framework\Mail\TemplateInterface;
use Magento\Framework\Mail\Template\FactoryInterface as TemplateFactory;
use Magento\Sales\Model\Order\Address\Renderer as AddressRenderer;
use Magento\Payment\Helper\Data as PaymentHelper;

/**
 * Plugin to intercept second-chance email sending and route through external email provider
 */
class ExternalEmailProviderPlugin
{
    /**
     * @var ExternalEmailConfig
     */
    protected $externalEmailConfig;

    /**
     * @var SecondChanceConfig
     */
    protected $secondChanceConfig;

    /**
     * @var EmailSender
     */
    protected $emailSender;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var TemplateFactory
     */
    protected $templateFactory;

    /**
     * @var AddressRenderer
     */
    protected $addressRenderer;

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @param ExternalEmailConfig $externalEmailConfig
     * @param SecondChanceConfig  $secondChanceConfig
     * @param EmailSender         $emailSender
     * @param Log                 $logger
     * @param TransportBuilder    $transportBuilder
     * @param TemplateFactory     $templateFactory
     * @param AddressRenderer     $addressRenderer
     * @param PaymentHelper       $paymentHelper
     */
    public function __construct(
        ExternalEmailConfig $externalEmailConfig,
        SecondChanceConfig $secondChanceConfig,
        EmailSender $emailSender,
        Log $logger,
        TransportBuilder $transportBuilder,
        TemplateFactory $templateFactory,
        AddressRenderer $addressRenderer,
        PaymentHelper $paymentHelper
    ) {
        $this->externalEmailConfig = $externalEmailConfig;
        $this->secondChanceConfig = $secondChanceConfig;
        $this->emailSender = $emailSender;
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
        $this->templateFactory = $templateFactory;
        $this->addressRenderer = $addressRenderer;
        $this->paymentHelper = $paymentHelper;
    }

    /**
     * Intercept sendMail to route through external email provider if enabled
     *
     * @param SecondChanceRepository $subject
     * @param callable $proceed
     * @param Order $order
     * @param mixed $secondChance
     * @param int $step
     *
     * @return void
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSendMail(
        SecondChanceRepository $subject,
        callable $proceed,
        $order,
        $secondChance,
        $step
    ) {
        $storeId = $order->getStoreId();

        // Check if external email provider is enabled for this store
        if (!$this->externalEmailConfig->isEnabled($storeId)) {
            return $proceed($order, $secondChance, $step);
        }

        $providerName = $this->externalEmailConfig->getProviderName($storeId);

        try {
            // Prepare email data
            $emailData = $this->prepareEmailData($order, $secondChance, $step);

            // Send via external email provider
            $result = $this->emailSender->send($emailData, $storeId);

            if ($result['success']) {
                // Success - log and return
                $this->logger->addDebug("Second-chance email sent via {$providerName}");
                return;
            }

            // External provider failed
            $this->logger->addError("{$providerName} failed to send email");

            // Check if fallback is enabled
            if ($this->externalEmailConfig->isFallbackEnabled($storeId)) {
                $this->logger->addDebug("Fallback: Using Magento mail for order {$order->getIncrementId()}");
                return $proceed($order, $secondChance, $step);
            }

            $this->logger->addError("{$providerName} failed and fallback is disabled: " .
                               ($result['error'] ?? 'Unknown error'));
            return $proceed($order, $secondChance, $step);
        } catch (\Exception $e) {
            // Log error
            $this->logger->addError('External email provider error: ' . $e->getMessage());

            // Check if fallback is enabled
            if ($this->externalEmailConfig->isFallbackEnabled($storeId)) {
                $this->logger->addDebug("Fallback: Using Magento mail after exception for order {$order->getIncrementId()}");
                return $proceed($order, $secondChance, $step);
            }

            throw $e;
        }
    }

    /**
     * Prepare email data from order and second-chance record
     *
     * @param Order $order
     * @param mixed $secondChance
     * @param int $step
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function prepareEmailData($order, $secondChance, $step): array
    {
        try {
            $storeId = $order->getStoreId();
            $store = $order->getStore();

            // Get sender info
            $senderName = $this->secondChanceConfig->getSecondChanceSenderName($storeId);
            $senderEmail = $this->secondChanceConfig->getSecondChanceSenderEmail($storeId);

            // Get checkout URL
            $checkoutUrl = $store->getUrl('buckaroo/checkout/secondchance', [
                'token' => $secondChance->getToken(),
                '_scope_to_url' => true
            ]);

            // Get template ID
            $templateId = $this->secondChanceConfig->getSecondChanceEmailTemplate($step, $storeId);

            // Get expected order ID from second chance record
            $baseOrderId = preg_replace('/(-\d+)+$/', '', $secondChance->getOrderId());
            $expectedOrderId = $secondChance->getLastOrderId();
            if (!$expectedOrderId) {
                $suffix = ($step == 1) ? '-1' : '-2';
                $expectedOrderId = $baseOrderId . $suffix;
            }

            // Build template variables (matching original sendMail exactly)
            $templateVars = [
                'order' => $order,
                'order_id' => $order->getId(),
                'base_order_id' => $baseOrderId,
                'expected_order_id' => $expectedOrderId,
                'billing' => $order->getBillingAddress(),
                'formattedBillingAddress' => $this->getFormattedAddress($order->getBillingAddress()),
                'formattedShippingAddress' => $this->getFormattedAddress($order->getShippingAddress()),
                'billing_address' => $this->getFormattedAddress($order->getBillingAddress()),
                'shipping_address' => $this->getFormattedAddress($order->getShippingAddress()),
                'payment_html' => $this->getPaymentHtml($order),
                'checkout_url' => $checkoutUrl,
                'store' => $store,
                'created_at_formatted' => $order->getCreatedAtFormatted(2),
                'secondChanceToken' => $secondChance->getToken(),
                'customer_name' => $order->getCustomerName(),
                'customer_email' => $order->getCustomerEmail(),
                'order_data' => [
                    'customer_name' => $order->getCustomerName(),
                    'is_not_virtual' => $order->getIsNotVirtual(),
                    'email_customer_note' => $order->getEmailCustomerNote(),
                    'frontend_status_label' => $order->getFrontendStatusLabel()
                ],
                'step' => $step
            ];

            // Render template
            $template = $this->templateFactory->get($templateId);
            $template->setVars($templateVars);
            $template->setOptions([
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $storeId,
            ]);

            $bodyHtml = $template->processTemplate();
            $subject = $template->getSubject();

            return [
                'to_email' => $order->getCustomerEmail(),
                'to_name' => $order->getCustomerName(),
                'from_email' => $senderEmail,
                'from_name' => $senderName,
                'subject' => $subject,
                'body_html' => $bodyHtml,
            ];
        } catch (\Exception $e) {
            $this->logger->addError("Failed to prepare email data: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get formatted address HTML
     *
     * @param \Magento\Sales\Model\Order\Address|null $address
     *
     * @return string
     */
    protected function getFormattedAddress($address): string
    {
        if ($address) {
            return $this->addressRenderer->format($address, 'html');
        }
        return '';
    }

    /**
     * Get payment method HTML
     *
     * @param Order $order
     *
     * @return string
     */
    protected function getPaymentHtml($order): string
    {
        try {
            $payment = $order->getPayment();
            return $this->paymentHelper->getInfoBlockHtml($payment, $order->getStoreId());
        } catch (\Exception $e) {
            $this->logger->addError('Error getting payment HTML: ' . $e->getMessage());
            return '';
        }
    }
}
