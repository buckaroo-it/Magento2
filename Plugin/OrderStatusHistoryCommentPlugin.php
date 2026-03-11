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
use Magento\Sales\Model\Order;

class OrderStatusHistoryCommentPlugin
{
    private const OFFLINE_REFUND_COMMENT_PATTERN =
        '/^(?<message>We refunded .*? offline\.) Transaction ID: ".+"$/u';

    /**
     * @var CheckPaymentType
     */
    private $checkPaymentType;

    public function __construct(CheckPaymentType $checkPaymentType)
    {
        $this->checkPaymentType = $checkPaymentType;
    }

    /**
     * Strip the appended transaction identifier from Buckaroo offline refund comments.
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
        if (preg_match(self::OFFLINE_REFUND_COMMENT_PATTERN, $commentText, $matches) !== 1) {
            return [$comment, $status];
        }

        return [$matches['message'], $status];
    }
}
