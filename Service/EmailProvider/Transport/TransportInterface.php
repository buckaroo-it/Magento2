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
