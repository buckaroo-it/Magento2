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

namespace Buckaroo\Magento2\Ui\Component\Listing\Columns;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Nicelog extends Column
{
    /**
     * @var Escaper
     */
    private $escaper;

    /**
     * @param ContextInterface    $context
     * @param UiComponentFactory  $uiComponentFactory
     * @param Escaper             $escaper
     * @param array               $components
     * @param array               $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->escaper = $escaper;
    }

    /**
     * @inheritdoc
     */
    public function prepareDataSource(array $dataSource)
    {
        $dataSource = parent::prepareDataSource($dataSource);

        if (empty($dataSource['data']['items'])) {
            return $dataSource;
        }
        // The column is rendered with the raw-HTML cell template (ui/grid/cells/html) and the log
        // message can contain request-derived content, so the message must be escaped here.
        foreach ($dataSource['data']['items'] as & $item) {
            if (isset($item['message'])) {
                $item['message'] = "<pre>" . $this->escaper->escapeHtml($item['message']) . "</pre>";
            }
        }
        return $dataSource;
    }
}
