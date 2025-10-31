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

namespace Buckaroo\Magento2\Ui\DataProvider\Modifier;

use Buckaroo\Magento2\Ui\Renderer\NotificationRenderer;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\Module\Dir\Reader;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Ui\DataProvider\Modifier\ModifierInterface;

/**
 * @see \Magento\ReleaseNotification\Ui\DataProvider\Modifier\Notifications
 */
class Notifications implements ModifierInterface
{
    /**
     * @var CacheInterface
     */
    private $cacheStorage;

    /**
     * @var ReadFactory
     */
    private $readFactory;

    /**
     * @var Reader
     */
    private $moduleReader;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var NotificationRenderer
     */
    private $renderer;

    /**
     * @param CacheInterface       $cacheStorage
     * @param ReadFactory          $readFactory
     * @param Reader               $moduleReader
     * @param SerializerInterface  $serializer
     * @param NotificationRenderer $render
     */
    public function __construct(
        CacheInterface $cacheStorage,
        ReadFactory $readFactory,
        Reader $moduleReader,
        SerializerInterface $serializer,
        NotificationRenderer $render
    ) {
        $this->cacheStorage = $cacheStorage;
        $this->readFactory = $readFactory;
        $this->moduleReader = $moduleReader;
        $this->serializer = $serializer;
        $this->renderer = $render;
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data): array
    {
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta): array
    {
        $modalContent = $this->getNotificationContent();

        if ($modalContent) {
            $pages = $modalContent['pages'];
            $pageCount = count($pages);
            $counter = 1;

            foreach ($pages as $page) {
                $meta = $this->buildNotificationMeta($meta, $page, $counter++ == $pageCount);
            }
        } else {
            $meta = $this->hideNotification($meta);
        }

        return $meta;
    }

    /**
     * Returns the json data
     *
     * @return string
     */
    private function getNotificationContent(): string
    {
        return '';
    }

    /**
     * Builds the notification modal by modifying $meta for the ui component.
     *
     * @param array $meta
     * @param array $page
     * @param bool  $isLastPage
     *
     * @return array
     */
    private function buildNotificationMeta(array $meta, array $page, bool $isLastPage): array
    {
        $meta['notification_modal_' . $page['name']]['arguments']['data']['config'] = [
            'isTemplate'    => false,
            'componentType' => \Magento\Ui\Component\Modal::NAME
        ];

        $meta['notification_modal_' . $page['name']]['children']['notification_fieldset']['children']
        ['notification_text']['arguments']['data']['config'] = [
            'text' => $this->renderer->getNotificationContent($page)
        ];

        if ($isLastPage) {
            $meta['notification_modal_' . $page['name']]['arguments']['data']['config']['options'] = [
                'title'   => $this->renderer->getNotificationTitle($page),
                'buttons' => [
                    [
                        'text'    => 'Done',
                        'actions' => [
                            [
                                'targetName' => '${ $.name }',
                                'actionName' => 'closeReleaseNotes'
                            ]
                        ],
                        'class'   => 'release-notification-button-next'
                    ]
                ],
            ];

            $meta['notification_modal_' . $page['name']]['children']['notification_fieldset']['children']
            ['notification_buttons']['children']['notification_button_next']['arguments']['data']['config'] = [
                'buttonClasses' => 'hide-release-notification'
            ];
        } else {
            $meta['notification_modal_' . $page['name']]['arguments']['data']['config']['options'] = [
                'title' => $this->renderer->getNotificationTitle($page)
            ];
        }

        return $meta;
    }

    /**
     * Sets the modal to not display if no content is available.
     *
     * @param  array $meta
     * @return array
     */
    private function hideNotification(array $meta): array
    {
        $meta['notification_modal_1']['arguments']['data']['config']['options'] = [
            'autoOpen' => false
        ];

        return $meta;
    }
}
