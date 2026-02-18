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
            $message = $this->createMessage($emailData, $storeId);
            $options = $this->createSmtpOptions($storeId);

            $transport = new LaminasSmtp($options);
            $transport->send($message);

            return $this->buildSuccessResponse($message, $storeId);
        } catch (\Exception $e) {
            return $this->handleException($e, $storeId);
        }
    }

    /**
     * Create an email message
     *
     * @param array $emailData
     * @param int|null $storeId
     * @return Message
     */
    private function createMessage(array $emailData, $storeId): Message
    {
        $message = new Message();
        $message->setEncoding('UTF-8');

        $fromEmail = $this->config->getFromEmail($storeId) ?: $emailData['from_email'];
        $fromName = $this->config->getFromName($storeId) ?: $emailData['from_name'];
        $message->setFrom($fromEmail, $fromName);

        $message->addTo($emailData['to_email'], $emailData['to_name'] ?? '');

        $replyTo = $this->config->getReplyTo($storeId);
        if ($replyTo) {
            $message->setReplyTo($replyTo);
        }

        $message->setSubject($emailData['subject']);
        $message->setBody($this->createMessageBody($emailData['body_html']));

        return $message;
    }

    /**
     * Create message body
     *
     * @param string $htmlContent
     * @return \Laminas\Mime\Message
     */
    private function createMessageBody($htmlContent): \Laminas\Mime\Message
    {
        $html = new \Laminas\Mime\Part($htmlContent);
        $html->type = 'text/html';
        $html->charset = 'UTF-8';
        $html->encoding = \Laminas\Mime\Mime::ENCODING_QUOTEDPRINTABLE;

        $mimeMessage = new \Laminas\Mime\Message();
        $mimeMessage->setParts([$html]);

        return $mimeMessage;
    }

    /**
     * Create SMTP options
     *
     * @param int|null $storeId
     * @return SmtpOptions
     * @throws \InvalidArgumentException
     */
    private function createSmtpOptions($storeId): SmtpOptions
    {
        $host = $this->config->getSmtpHost($storeId);
        if (empty($host)) {
            throw new \InvalidArgumentException('SMTP host is not configured');
        }

        $options = new SmtpOptions([
            'host' => $host,
            'port' => $this->config->getSmtpPort($storeId),
        ]);

        $this->configureAuthentication($options, $storeId);

        return $options;
    }

    /**
     * Configure SMTP authentication
     *
     * @param SmtpOptions $options
     * @param int|null $storeId
     */
    private function configureAuthentication(SmtpOptions $options, $storeId): void
    {
        $encryption = $this->config->getSmtpEncryption($storeId);
        $username = $this->config->getSmtpUsername($storeId);
        $password = $this->config->getSmtpPassword($storeId);

        if ($encryption && $encryption !== 'none') {
            $options->setConnectionClass('login');
            $options->setConnectionConfig([
                'ssl' => $encryption,
                'username' => $username,
                'password' => $password,
            ]);
        } elseif ($username) {
            $options->setConnectionClass('login');
            $options->setConnectionConfig([
                'username' => $username,
                'password' => $password,
            ]);
        }
    }

    /**
     * Build success response
     *
     * @param Message $message
     * @param int|null $storeId
     * @return array
     */
    private function buildSuccessResponse(Message $message, $storeId): array
    {
        $providerName = $this->config->getProviderName($storeId);
        $messageId = $message->getHeaders()->get('Message-ID') ?
                     $message->getHeaders()->get('Message-ID')->getFieldValue() : null;

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
