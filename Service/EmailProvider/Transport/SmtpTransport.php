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

namespace Buckaroo\Magento2\Service\EmailProvider\Transport;

use Buckaroo\Magento2\Model\ConfigProvider\ExternalEmailProvider as EmailProviderConfig;
use Buckaroo\Magento2\Logging\Log;
use Laminas\Mail\Message;
use Laminas\Mail\Transport\Smtp as LaminasSmtp;
use Laminas\Mail\Transport\SmtpOptions;
use Magento\Framework\Exception\MailException;

class SmtpTransport implements TransportInterface
{
    /**
     * @var EmailProviderConfig
     */
    protected $config;

    /**
     * @var Log
     */
    protected $logger;

    /**
     * @param EmailProviderConfig $config
     * @param Log                 $logger
     */
    public function __construct(
        EmailProviderConfig $config,
        Log $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Send email via SMTP
     *
     * @param array $emailData
     * @param int|null $storeId
     *
     * @return array
     * @throws MailException
     */
    public function send(array $emailData, $storeId = null): array
    {
        try {
            // Create Laminas Mail message
            $message = new Message();
            $message->setEncoding('UTF-8');

            // Set sender
            $fromEmail = $this->config->getFromEmail($storeId) ?: $emailData['from_email'];
            $fromName = $this->config->getFromName($storeId) ?: $emailData['from_name'];
            $message->setFrom($fromEmail, $fromName);

            // Set recipient
            $message->addTo($emailData['to_email'], $emailData['to_name'] ?? '');

            // Set reply-to if configured
            $replyTo = $this->config->getReplyTo($storeId);
            if ($replyTo) {
                $message->setReplyTo($replyTo);
            }

            // Set subject and body
            $message->setSubject($emailData['subject']);
            
            // Create multipart message for HTML with text fallback
            $html = new \Laminas\Mime\Part($emailData['body_html']);
            $html->type = 'text/html';
            $html->charset = 'UTF-8';
            $html->encoding = \Laminas\Mime\Mime::ENCODING_QUOTEDPRINTABLE;

            $mimeMessage = new \Laminas\Mime\Message();
            $mimeMessage->setParts([$html]);

            $message->setBody($mimeMessage);

            // Configure SMTP options
            $host = $this->config->getSmtpHost($storeId);
            $port = $this->config->getSmtpPort($storeId);
            $encryption = $this->config->getSmtpEncryption($storeId);
            $username = $this->config->getSmtpUsername($storeId);
            $password = $this->config->getSmtpPassword($storeId);

            if (empty($host)) {
                throw new \Exception('SMTP host is not configured');
            }

            $options = new SmtpOptions([
                'host' => $host,
                'port' => $port,
            ]);

            // Set encryption if not 'none'
            if ($encryption && $encryption !== 'none') {
                $options->setConnectionClass('login');
                $options->setConnectionConfig([
                    'ssl' => $encryption,
                    'username' => $username,
                    'password' => $password,
                ]);
            } elseif ($username) {
                // No encryption but has username
                $options->setConnectionClass('login');
                $options->setConnectionConfig([
                    'username' => $username,
                    'password' => $password,
                ]);
            }

            // Create transport and send
            $transport = new LaminasSmtp($options);
            $transport->send($message);

            $providerName = $this->config->getProviderName($storeId);
            return [
                'success' => true,
                'message' => "Email sent via {$providerName} SMTP",
                'message_id' => $message->getHeaders()->get('Message-ID') ? 
                              $message->getHeaders()->get('Message-ID')->getFieldValue() : null,
            ];

        } catch (\Exception $e) {
            $providerName = $this->config->getProviderName($storeId) ?? 'External Provider';
            $errorMsg = "{$providerName} SMTP error: " . $e->getMessage();
            $this->logger->addError($errorMsg);
            throw new MailException(__($errorMsg), $e);
        }
    }
}
