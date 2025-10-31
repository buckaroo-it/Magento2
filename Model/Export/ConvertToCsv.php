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

namespace Buckaroo\Magento2\Model\Export;

use Buckaroo\Magento2\Helper\PaymentGroupTransaction;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Giftcards;
use Buckaroo\Magento2\Model\ResourceModel\Giftcard\Collection;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\MetadataProvider;

class ConvertToCsv extends \Magento\Ui\Model\Export\ConvertToCsv
{
    /**
     * @var DirectoryList
     */
    protected $directory;

    /**
     * @var MetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var int|null
     */
    protected $pageSize = null;

    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var PaymentGroupTransaction
     */
    protected $groupTransaction;

    /**
     * @var Collection
     */
    protected $giftcardCollection;

    /**
     * @var Giftcards
     */
    protected $giftcardConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param  Filesystem               $filesystem
     * @param  Filter                   $filter
     * @param  MetadataProvider         $metadataProvider
     * @param  OrderRepositoryInterface $orderRepository
     * @param  PaymentGroupTransaction  $groupTransaction
     * @param  Collection               $giftcardCollection
     * @param  StoreManagerInterface    $storeManager
     * @param  Giftcards                $giftcardConfig
     * @param  int                      $pageSize
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        Filter $filter,
        MetadataProvider $metadataProvider,
        OrderRepositoryInterface $orderRepository,
        PaymentGroupTransaction $groupTransaction,
        Collection $giftcardCollection,
        StoreManagerInterface $storeManager,
        Giftcards $giftcardConfig,
        $pageSize = 200
    ) {
        $this->filter = $filter;
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->metadataProvider = $metadataProvider;
        $this->pageSize = $pageSize;
        $this->orderRepository = $orderRepository;
        $this->groupTransaction = $groupTransaction;
        $this->giftcardCollection = $giftcardCollection;
        $this->storeManager = $storeManager;
        $this->giftcardConfig = $giftcardConfig;
    }

    /**
     * Returns CSV file
     *
     * @throws LocalizedException
     * @return array
     */
    public function getCsvFile(): array
    {
        $component = $this->filter->getComponent();

        $name = uniqid();
        $file = 'export/' . $component->getName() . $name . '.csv';

        $this->filter->prepareComponent($component);
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();
        $fields = $this->metadataProvider->getFields($component);
        $options = $this->metadataProvider->getOptions();

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($this->metadataProvider->getHeaders($component));
        $i = 1;
        $searchCriteria = $dataProvider->getSearchCriteria()
            ->setCurrentPage($i)
            ->setPageSize($this->pageSize);
        $totalCount = (int)$dataProvider->getSearchResult()->getTotalCount();
        while ($totalCount > 0) {
            $items = $dataProvider->getSearchResult()->getItems();
            foreach ($items as $item) {
                if ($this->giftcardConfig->hasAdvancedExportGiftcards($this->storeManager->getStore())) {
                    $this->convertGiftCardsValue($item);
                }
                $this->metadataProvider->convertDate($item, $component->getName());
                $stream->writeCsv($this->metadataProvider->getRowData($item, $fields, $options));
            }
            $searchCriteria->setCurrentPage(++$i);
            $totalCount = $totalCount - $this->pageSize;
        }
        $stream->unlock();
        $stream->close();

        return [
            'type'  => 'filename',
            'value' => $file,
            'rm'    => true, // can delete file after use
        ];
    }

    /**
     * Convert giftcard values
     *
     * @param mixed $document
     */
    public function convertGiftCardsValue($document)
    {
        $item = $document->toArray();
        $orderId = $item['entity_id'];
        $order = $this->orderRepository->get($orderId);
        $orderIncrementId = $order->getIncrementId();
        if ($items = $this->groupTransaction->getGroupTransactionItems($orderIncrementId)) {
            $result = [$document->getDataByKey('payment_method')];
            foreach ($items as $giftcard) {
                $result[] = $giftcard['servicecode'];
            }
            $document->setData('payment_method', implode(",", $result));
        }
    }
}
