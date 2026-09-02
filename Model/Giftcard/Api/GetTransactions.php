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

namespace Buckaroo\Magento2\Model\Giftcard\Api;

use Buckaroo\Magento2\Api\Data\Giftcard\GetTransactionsResponseInterfaceFactory;
use Buckaroo\Magento2\Api\GiftcardTransactionInterface;

class GetTransactions implements GiftcardTransactionInterface
{
    /**
     * @var GetTransactionsResponseInterfaceFactory
     */
    protected $responseFactory;

    /**
     * @param GetTransactionsResponseInterfaceFactory $responseFactory
     */
    public function __construct(GetTransactionsResponseInterfaceFactory $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    /**
     * @inheritdoc
     */
    public function get(string $cartId)
    {
        try {
            return $this->responseFactory->create(["cartId" => $cartId]);
        } catch (NoQuoteException $th) {
            throw $th;
        } catch (\Throwable $th) {
            throw new ApiException(__('Unknown buckaroo error has occurred'), 0, $th);
        }
    }
}
