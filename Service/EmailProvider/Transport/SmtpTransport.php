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
use Magento\Framework\Exception\MailException;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

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
     * Send email via SMTP using Symfony Mailer
     *
     * @param array $emailData
     * @param int|null $storeId
     *
     * @return array
     * @throws MailException|\Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function send(array $emailData, $storeId = null): array
    {
        try {
            $transport = $this->createTransport($storeId);
            $email = $this->createEmail($emailData, $storeId);

            $mailer = new Mailer($transport);
            $sentMessage = $mailer->send($email);

            return $this->buildSuccessResponse($sentMessage, $storeId);
        } catch (\Exception $e) {
            return $this->handleException($e, $storeId);
        }
    }

    /**
     * Create Symfony SMTP transport
     *
     * @param int|null $storeId
     * @return EsmtpTransport
     * @throws \InvalidArgumentException
     */
    private function createTransport($storeId): EsmtpTransport
    {
        $host = $this->config->getSmtpHost($storeId);
        $port = $this->config->getSmtpPort($storeId);
        $encryption = $this->config->getSmtpEncryption($storeId);

        if (empty($host)) {
            throw new \InvalidArgumentException('SMTP host is not configured');
        }

        // Create transport with encryption if specified
        $secure = null;
        if ($encryption && $encryption !== 'none') {
            $secure = ($encryption === 'ssl') ? true : false; // true for SSL, false for TLS
        }

        $transport = new EsmtpTransport($host, $port, $secure);

        // Set authentication if configured
        $username = $this->config->getSmtpUsername($storeId);
        $password = $this->config->getSmtpPassword($storeId);

        if ($username) {
            $transport->setUsername($username);
            $transport->setPassword($password);
        }

        return $transport;
    }

    /**
     * Create a Symfony Email message
     *
     * @param array $emailData
     * @param int|null $storeId
     * @return Email
     */
    private function createEmail(array $emailData, $storeId): Email
    {
        $email = new Email();

        // Set from address
        $fromEmail = $this->config->getFromEmail($storeId) ?: $emailData['from_email'];
        $fromName = $this->config->getFromName($storeId) ?: $emailData['from_name'];
        $email->from(new Address($fromEmail, $fromName));

        // Set recipient
        $toName = $emailData['to_name'] ?? '';
        $email->to(new Address($emailData['to_email'], $toName));

        // Set reply-to if configured
        $replyTo = $this->config->getReplyTo($storeId);
        if ($replyTo) {
            $email->replyTo($replyTo);
        }

        // Set subject and body
        $email->subject($emailData['subject']);
        $email->html($emailData['body_html']);

        // Add text version if provided
        if (!empty($emailData['body_text'])) {
            $email->text($emailData['body_text']);
        }

        return $email;
    }

    /**
     * Build success response
     *
     * @param mixed $sentMessage
     * @param int|null $storeId
     * @return array
     */
    private function buildSuccessResponse($sentMessage, $storeId): array
    {
        $providerName = $this->config->getProviderName($storeId) ?? 'SMTP Provider';
        $messageId = method_exists($sentMessage, 'getMessageId') ?
                     $sentMessage->getMessageId() : null;

        return [
            'success' => true,
            'message' => "Email sent via {$providerName} SMTP",
            'message_id' => $messageId,
        ];
    }

    /**
     * Handle exception and wrap in MailException
     *
     * @param \Exception $e
     * @param int|null $storeId
     * @throws MailException
     * @return never
     */
    private function handleException(\Exception $e, $storeId)
    {
        $providerName = $this->config->getProviderName($storeId) ?? 'External Provider';
        $errorMsg = "{$providerName} SMTP error: " . $e->getMessage();
        $this->logger->addError($errorMsg);
        throw new MailException(__($errorMsg), $e);
    }
}
