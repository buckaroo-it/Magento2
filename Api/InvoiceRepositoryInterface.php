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
namespace TIG\Buckaroo\Api;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use TIG\Buckaroo\Api\Data\InvoiceInterface;

interface InvoiceRepositoryInterface
{
    /**
     * @param InvoiceInterface $invoice
     * @return InvoiceInterface
     * @throws CouldNotSaveException
     */
    public function save(InvoiceInterface $invoice);

    /**
     * @param int|string $invoiceId
     * @return InvoiceInterface
     * @throws NoSuchEntityException
     */
    public function getById($invoiceId);

    /**
     * @param SearchCriteria $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteria $searchCriteria);

    /**
     * @param InvoiceInterface $invoice
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(InvoiceInterface $invoice);

    /**
     * @param $invoiceId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById($invoiceId);
}
