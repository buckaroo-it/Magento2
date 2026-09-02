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
declare(strict_types=1);

namespace Buckaroo\Magento2\Service;

use Buckaroo\Magento2\Gateway\Http\Client\TransactionType;
use Buckaroo\Magento2\Gateway\Request\CreditManagement\BuilderComposite;

/**
 * Service to validate if a transaction operation is a post-transaction operation
 * that should bypass payment method active status checks.
 *
 * Post-transaction operations (refund, capture, cancel) need to work even if the
 * payment method is currently disabled, as they operate on historical transactions.
 *
 */
class TransactionOperationValidator
{
    /**
     * List of post-transaction actions that should skip active payment method check
     *
     * @var array
     */
    private $postTransactionActions;

    /**
     * @param array $postTransactionActions Configurable list of actions via di.xml
     */
    public function __construct(
        array $postTransactionActions = []
    ) {
        // Default post-transaction actions if not configured
        $this->postTransactionActions = !empty($postTransactionActions) ? $postTransactionActions : [
            TransactionType::REFUND,
            TransactionType::CAPTURE,
            TransactionType::CANCEL,
            TransactionType::CANCEL_RESERVE,
        ];
    }

    /**
     * Determines if the operation should skip payment method active status check
     *
     * Returns true for:
     * - Refund operations
     * - Capture operations
     * - Cancel/void operations (cancelAuthorize, cancelReserve)
     * - Credit management refunds and voids
     *
     * @param string $action The transaction action (e.g., 'refund', 'capture', 'cancelAuthorize')
     * @param array $data Request data that may contain credit management information
     *
     * @return bool True if should skip active check, false otherwise
     */
    public function shouldSkipActiveCheck(string $action, array $data = []): bool
    {
        // Check if action is in the list of post-transaction actions
        if (in_array($action, $this->postTransactionActions, true)) {
            return true;
        }

        // Check for credit management operations
        if ($this->isCreditManagementPostTransaction($data)) {
            return true;
        }

        return false;
    }

    /**
     * Check if request contains credit management post-transaction operations
     *
     * @param array $data
     *
     * @return bool
     */
    private function isCreditManagementPostTransaction(array $data): bool
    {
        return $this->isCreditManagementOfType($data, BuilderComposite::TYPE_REFUND)
            || $this->isCreditManagementOfType($data, BuilderComposite::TYPE_VOID);
    }

    /**
     * Check if we have credit management information of type
     *
     * @param array $data
     * @param string $type
     *
     * @return bool
     */
    private function isCreditManagementOfType(array $data, string $type): bool
    {
        return isset($data[$type])
            && is_array($data[$type])
            && count($data[$type]) > 0;
    }

    /**
     * Get list of configured post-transaction actions
     *
     * @return array
     */
    public function getPostTransactionActions(): array
    {
        return $this->postTransactionActions;
    }
}
