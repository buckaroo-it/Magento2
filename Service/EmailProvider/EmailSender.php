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

namespace Buckaroo\Magento2\Service\EmailProvider;

use Buckaroo\Magento2\Model\ConfigProvider\ExternalEmailProvider as EmailProviderConfig;
use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\Exception\MailException;

/**
 * Main service for sending emails via external email provider with retry logic and logging
 */
class EmailSender
{
    /**
     * @var EmailProviderConfig
     */
    protected $config;

    /**
     * @var TransportFactory
     */
    protected $transportFactory;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @param EmailProviderConfig $config
     * @param TransportFactory    $transportFactory
     * @param Log                 $logger
     */
    public function __construct(
        EmailProviderConfig $config,
        TransportFactory $transportFactory,
        Log $logger
    ) {
        $this->config = $config;
        $this->transportFactory = $transportFactory;
        $this->logger = $logger;
    }

    /**
     * Send email via external email provider with retry logic
     *
     * @param array $emailData
     * @param int|null $storeId
     *
     * @return array Result with success status and details
     * @throws MailException
     */
    public function send(array $emailData, $storeId = null): array
    {
        $this->validateEmailData($emailData);

        $retryEnabled = $this->config->isRetryEnabled($storeId);
        $maxAttempts = $retryEnabled ? $this->config->getRetryAttempts($storeId) : 1;
        $attempt = 0;
        $lastException = null;
        $providerName = $this->config->getProviderName($storeId);

        // Try sending with retries
        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                $transport = $this->transportFactory->create($storeId);
                $result = $transport->send($emailData, $storeId);

                // Success - log and return
                if ($attempt > 1) {
                    $this->logger->addDebug("Email sent via {$providerName} after {$attempt} attempts");
                }

                return array_merge($result, [
                    'attempts' => $attempt,
                    'method' => $this->config->getMethod($storeId),
                    'provider' => $providerName,
                ]);

            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt == $maxAttempts) {
                    $this->logger->addError("{$providerName} send failed after {$maxAttempts} attempts: " . $e->getMessage());
                }

                if ($attempt < $maxAttempts) {
                    usleep(pow(2, $attempt - 1) * 1000000); // 1s, 2s, 4s, etc.
                }
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to send email after ' . $maxAttempts . ' attempts',
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'attempts' => $maxAttempts,
            'provider' => $providerName,
        ];
    }

    /**
     * Validate email data
     *
     * @param array $emailData
     *
     * @throws \InvalidArgumentException
     */
    protected function validateEmailData(array $emailData): void
    {
        $required = ['to_email', 'from_email', 'from_name', 'subject', 'body_html'];

        foreach ($required as $field) {
            if (empty($emailData[$field])) {
                throw new \InvalidArgumentException("Missing required email field: $field");
            }
        }

        if (!filter_var($emailData['to_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid recipient email: {$emailData['to_email']}");
        }

        if (!filter_var($emailData['from_email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Invalid sender email: {$emailData['from_email']}");
        }
    }
}
