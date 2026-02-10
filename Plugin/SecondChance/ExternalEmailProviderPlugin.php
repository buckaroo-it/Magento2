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
     * @param ExternalEmailConfig $externalEmailConfig
     * @param SecondChanceConfig  $secondChanceConfig
     * @param EmailSender         $emailSender
     * @param Log                 $logger
     * @param TransportBuilder    $transportBuilder
     */
    public function __construct(
        ExternalEmailConfig $externalEmailConfig,
        SecondChanceConfig $secondChanceConfig,
        EmailSender $emailSender,
        Log $logger,
        TransportBuilder $transportBuilder
    ) {
        $this->externalEmailConfig = $externalEmailConfig;
        $this->secondChanceConfig = $secondChanceConfig;
        $this->emailSender = $emailSender;
        $this->logger = $logger;
        $this->transportBuilder = $transportBuilder;
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
            $this->logger->addDebug('External email provider disabled, using default Magento mail');
            return $proceed($order, $secondChance, $step);
        }

        $providerName = $this->externalEmailConfig->getProviderName($storeId);

        try {
            $this->logger->addDebug("External email provider ({$providerName}) enabled, intercepting second-chance email", [
                'order_id' => $order->getIncrementId(),
                'step' => $step
            ]);

            // Prepare email data by extracting from what would be sent
            $emailData = $this->prepareEmailData($order, $secondChance, $step);

            // Send via external email provider
            $result = $this->emailSender->send($emailData, $storeId);

            if ($result['success']) {
                $this->logger->addDebug("Second-chance email sent successfully via {$providerName}", [
                    'order_id' => $order->getIncrementId(),
                    'step' => $step,
                    'message_id' => $result['message_id'] ?? 'N/A'
                ]);
                return; // Success - don't call original method
            } else {
                // External provider failed
                $this->logger->addError("{$providerName} failed to send email", [
                    'order_id' => $order->getIncrementId(),
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                // Check if fallback is enabled
                if ($this->externalEmailConfig->isFallbackEnabled($storeId)) {
                    $this->logger->addDebug('Falling back to Magento mail system');
                    return $proceed($order, $secondChance, $step);
                } else {
                    throw new \Exception("{$providerName} failed and fallback is disabled: " . 
                                       ($result['error'] ?? 'Unknown error'));
                }
            }

        } catch (\Exception $e) {
            $this->logger->addError('Error in external email provider plugin', [
                'error' => $e->getMessage(),
                'order_id' => $order->getIncrementId()
            ]);

            // Check if fallback is enabled
            if ($this->externalEmailConfig->isFallbackEnabled($storeId)) {
                $this->logger->addDebug('Exception occurred, falling back to Magento mail');
                return $proceed($order, $secondChance, $step);
            } else {
                throw $e;
            }
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

        // Build template variables (same as original sendMail)
        $templateVars = [
            'order' => $order,
            'order_id' => $order->getId(),
            'checkout_url' => $checkoutUrl,
            'store' => $store,
            'customer_name' => $order->getCustomerName(),
            'customer_email' => $order->getCustomerEmail(),
            'step' => $step,
            'secondChanceToken' => $secondChance->getToken(),
        ];

        // Use TransportBuilder to render the template to HTML
        $this->transportBuilder
            ->setTemplateIdentifier($templateId)
            ->setTemplateOptions([
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $storeId,
            ])
            ->setTemplateVars($templateVars)
            ->setFrom([
                'name' => $senderName,
                'email' => $senderEmail,
            ]);

        // Get the message to extract rendered content
        $transport = $this->transportBuilder->getTransport();
        $message = $transport->getMessage();
        $bodyHtml = $this->extractHtmlFromMessage($message);
        $subject = $message->getSubject();

        return [
            'to_email' => $order->getCustomerEmail(),
            'to_name' => $order->getCustomerName(),
            'from_email' => $senderEmail,
            'from_name' => $senderName,
            'subject' => $subject,
            'body_html' => $bodyHtml,
        ];
    }

    /**
     * Extract HTML content from message
     *
     * @param mixed $message
     *
     * @return string
     */
    protected function extractHtmlFromMessage($message): string
    {
        try {
            $body = $message->getBody();

            if ($body instanceof \Laminas\Mime\Message) {
                $parts = $body->getParts();
                foreach ($parts as $part) {
                    if ($part->type === 'text/html') {
                        return $part->getRawContent();
                    }
                }
                if (!empty($parts)) {
                    return $parts[0]->getRawContent();
                }
            }

            if (is_string($body)) {
                return $body;
            }

            return (string) $body;

        } catch (\Exception $e) {
            $this->logger->addError('Failed to extract HTML from message: ' . $e->getMessage());
            return '';
        }
    }
}
