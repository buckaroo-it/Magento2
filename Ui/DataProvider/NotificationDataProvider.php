<?php
/*
*
*
*          ..::..
*     ..::::::::::::..
*   ::'''''':''::'''''::
*   ::..  ..:  :  ....::
*   ::::  :::  :  :   ::
*   ::::  :::  :  ''' ::
*   ::::..:::..::.....::
*     ''::::::::::::''
*          ''::''
*
*
* NOTICE OF LICENSE
*
* This source file is subject to the Creative Commons License.
* It is available through the world-wide-web at this URL:
* http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
* If you are unable to obtain it through the world-wide-web, please send an email
* to servicedesk@tig.nl so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please contact servicedesk@tig.nl for more information.
*
* @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
* @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
*
*/
namespace TIG\Buckaroo\Ui\DataProvider;

use TIG\Buckaroo\Ui\DataProvider\Modifier\Notifications;

class NotificationDataProvider extends \Magento\Ui\DataProvider\AbstractDataProvider
{
    /** @var Notifications $modifier */
    private $modifier;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param Notifications $modifier
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        Notifications $modifier,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $meta,
            $data
        );

        $this->modifier = $modifier;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getMeta()
    {
        return $this->modifier->modifyMeta($this->meta);
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
    }
}
