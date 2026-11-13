<?php
/**
 * Buckaroo Magento 2 payment module (https://www.buckaroo.eu/)
 *
 * Copyright (c) Buckaroo B.V.
 * See LICENSE for license details.
 *
 * Support: support@buckaroo.nl
 *
 * @copyright Copyright (c) Buckaroo B.V.
 * @license   MIT
 */

namespace Buckaroo\Magento2\Service\EmailProvider\Transport;

interface TransportInterface
{
    /**
     * Send email via external email provider
     *
     * @param array $emailData Email data containing:
     *                         - to_email (string, required)
     *                         - to_name (string, optional)
     *                         - from_email (string, required)
     *                         - from_name (string, required)
     *                         - subject (string, required)
     *                         - body_html (string, required)
     *                         - body_text (string, optional)
     *                         - reply_to (string, optional)
     *                         - headers (array, optional)
     *                         - attachments (array, optional)
     * @param int|null $storeId
     *
     * @return array Result containing:
     *               - success (bool)
     *               - message (string)
     *               - message_id (string, optional)
     *               - error_code (string, optional)
     *
     * @throws \Magento\Framework\Exception\MailException
     */
    public function send(array $emailData, $storeId = null): array;
}
