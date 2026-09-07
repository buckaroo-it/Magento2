<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License
 * It is available through the world-wide-web at this URL:
 * https://tldrlegal.com/license/mit-license
 * If you are unable to obtain it through the world-wide-web, please email
 * to support@buckaroo.nl, so we can send you a copy immediately.
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
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

use Buckaroo\Magento2\Service\CheckPaymentType;
use Magento\Framework\Escaper;
use Magento\Sales\Model\Order;

class OrderStatusHistoryCommentPlugin
{
    private const BUCKAROO_REFUND_COMMENT_PATTERN =
        '/^We refunded .*?\b(?:offline|online)\./ui';

    /**
     * @var CheckPaymentType
     */
    private $checkPaymentType;

    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * Constructor.
     *
     * @param CheckPaymentType $checkPaymentType
     * @param Escaper $escaper
     */
    public function __construct(
        CheckPaymentType $checkPaymentType,
        Escaper $escaper
    ) {
        $this->checkPaymentType = $checkPaymentType;
        $this->escaper = $escaper;
    }

    /**
     * Normalize Buckaroo Plaza refund notes.
     *
     * Plaza refunds should always be shown as "online" and include the Plaza Transaction ID.
     * Magento Admin offline refunds (no Plaza key) are left unchanged.
     *
     * @param Order $subject
     * @param string|\Magento\Framework\Phrase $comment
     * @param bool|string $status
     * @return array
     */
    public function beforeAddStatusHistoryComment(Order $subject, $comment, $status = false): array
    {
        if (!$this->checkPaymentType->isBuckarooPayment($subject->getPayment())) {
            return [$comment, $status];
        }

        $commentText = (string)$comment;
        if (preg_match(self::BUCKAROO_REFUND_COMMENT_PATTERN, $commentText) !== 1) {
            return [$comment, $status];
        }

        $transactionId = $this->getRefundTransactionId($subject);
        if (empty($transactionId)) {
            return [$comment, $status];
        }

        $updatedComment = preg_replace('/\boffline\b/ui', 'online', $commentText, 1) ?: $commentText;

        $hasTransactionId = preg_match('/Transaction\s*ID\s*:\s*"/ui', $updatedComment) === 1;
        if (!$hasTransactionId) {
            return [$this->appendTransactionId($updatedComment, $transactionId), $status];
        }

        $updatedComment = preg_replace(
            '/Transaction\s*ID\s*:\s*"[^"]*"/ui',
            'Transaction ID: "' . $this->buildTransactionIdLink($transactionId) . '"',
            $updatedComment,
            1
        ) ?: $updatedComment;

        return [$updatedComment, $status];
    }

    /**
     * Get the Buckaroo refund transaction key stored on the order payment.
     *
     * @param Order $order
     * @return string|null
     */
    private function getRefundTransactionId(Order $order): ?string
    {
        $payment = $order->getPayment();
        if ($payment && method_exists($payment, 'getAdditionalInformation')) {
            $refundAdditionalInfo = $payment->getAdditionalInformation('buckaroo_refund_transaction_key');
            if (!empty($refundAdditionalInfo)) {
                return (string)$refundAdditionalInfo;
            }
        }
        return null;
    }

    /**
     * Append the transaction id link to the comment text.
     *
     * @param string $commentText
     * @param string $transactionId
     * @return string
     */
    private function appendTransactionId(string $commentText, string $transactionId): string
    {
        return rtrim($commentText) . ' Transaction ID: "' . $this->buildTransactionIdLink($transactionId) . '"';
    }

    /**
     * Build the Buckaroo Plaza transaction details link for the given transaction id.
     *
     * @param string $transactionId
     * @return string
     */
    private function buildTransactionIdLink(string $transactionId): string
    {
        $url = $this->escaper->escapeUrl(
            'https://plaza.buckaroo.nl/Transaction/Transactions/Details?transactionKey=' . $transactionId
        );

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            $url,
            $this->escaper->escapeHtml($transactionId)
        );
    }
}
