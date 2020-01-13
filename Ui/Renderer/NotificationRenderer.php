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
namespace Buckaroo\Magento2\Ui\Renderer;

use Magento\Framework\Escaper;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * @see \Magento\ReleaseNotification\Ui\Renderer\NotificationRenderer
 */
class NotificationRenderer
{
    /** @var Escaper $escaper */
    private $escaper;

    /** @var AssetRepository $assetRepository */
    private $assetRepository;

    /**
     * @param Escaper $escaper
     * @param AssetRepository $assetRepository
     */
    public function __construct(Escaper $escaper,
                                AssetRepository $assetRepository)
    {
        $this->escaper = $escaper;
        $this->assetRepository = $assetRepository;
    }

    /**
     * Returns the HTML for notification's title to the ui component
     *
     * @param array $page
     * @return string
     */
    public function getNotificationTitle(array $page)
    {
        $title = $this->escaper->escapeHtml($page['mainContent']['title']);
        $content = "";

        if (!empty($page['mainContent']['imageUrl'])) {
            $content .= "<div>";
            $content .= $title;
            $content .= "</div>";
        } else {
            $content = $title;
        }

        return $content;
    }

    /**
     * Returns the HTML for the content in the notification ui component
     *
     * @param array $page
     * @return string
     */
    public function getNotificationContent(array $page)
    {
        $content = $this->buildMainContent($page['mainContent']);
        $content .= $this->buildSubHeadings($page['subHeading']);
        $content .= $this->buildFooter($page['footer']);

        return $content;
    }

    /**
     * Builds the HTML for the main content in the notification ui component
     *
     * @param array $mainContent
     * @return string
     */
    private function buildMainContent(array $mainContent)
    {
        $content = $this->buildContentTextAreas($mainContent['content']);
        $content .= $this->buildLists($mainContent['lists']);

        return $this->formatContentWithLinks($content);
    }

    /**
     * Builds the HTML for the main text areas in the notification ui component
     *
     * @param array $contentAreas
     * @return string
     */
    private function buildContentTextAreas(array $contentAreas)
    {
        $content = "";
        $lastContentArea = end($contentAreas);

        foreach ($contentAreas as $contentArea) {
            $content .= "<p>";
            $content .= $this->escaper->escapeHtml($contentArea['text']);
            $content .= "</p>";
            if ($contentArea != $lastContentArea) {
                $content .= "<br />";
            }
        }

        return $content;
    }

    /**
     * Builds the HTML for the bullet list content in the notification ui component
     *
     * @param array $lists
     * @return string
     */
    private function buildLists(array $lists)
    {
        $content = "<ul>";

        foreach ($lists as $listItem) {
            $content .= "<li><span>";
            $content .= $this->escaper->escapeHtml($listItem['text']);
            $content .= "</span></li>";
        }

        $content .= "</ul>";

        return $content;
    }

    /**
     * Builds the HTML for the highlighted sub heads for the overview page in the notification ui component
     *
     * @param array $subHeadings
     * @return string
     */
    private function buildSubHeadings(array $subHeadings)
    {
        $content = "";

        foreach ($subHeadings as $subHeading) {
            if (! empty($subHeading['imageUrl'])) {
                $imageUrl = $this->assetRepository->getUrl($subHeading['imageUrl']);
                $content .= "<div class='buckaroo-highlight-item'>";
                $content .= '<img src="'.$this->escaper->escapeUrl($imageUrl).'" />';
            } else {
                $content .= "<div class='highlight-item-no-image'>";
            }

            $content .= "<h3>";
            $content .= $this->escaper->escapeHtml($subHeading['title']);
            $content .= "</h3>";
            $content .= "<p>";
            $content .= $this->formatContentWithLinks($subHeading['content']);
            $content .= "</p>";
            $content .= "</div>";
        }

        return $content;
    }

    /**
     * Builds the HTML for the footer content in the notification ui component
     *
     * @param array $footer
     * @return string
     */
    private function buildFooter(array $footer)
    {
        $content = "<p>";
        $content .= $this->escaper->escapeHtml($footer['content']);
        $content .= "</p>";

        return $this->formatContentWithLinks($content);
    }

    /**
     * Searches a given string for a URL, formats it to an HTML anchor tag, and returns the original string in the
     * correct HTML format.
     *
     * @param string $content
     * @return string
     */
    private function formatContentWithLinks($content)
    {
        $urlRegex = '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#';
        $urlTextRegex = '/\[(.*?)\]/';

        preg_match_all($urlRegex, $content, $urlMatches);
        preg_match_all($urlTextRegex, $content, $urlTextMatches);

        foreach ($urlMatches[0] as $key => $urlMatch) {
            if (!empty($urlTextMatches[0])) {
                $linkMatch = $urlMatch . " " . $urlTextMatches[0][$key];
                $content = str_replace(
                    $linkMatch,
                    "<a target='_blank' href='{$this->escaper->escapeUrl($urlMatch)}'>
                        {$this->escaper->escapeHtml($urlTextMatches[1][$key])}</a>",
                    $content
                );
            } else {
                $content = str_replace(
                    $urlMatch,
                    "<a target='_blank' href='{$this->escaper->escapeUrl($urlMatch)}'>
                        {$this->escaper->escapeUrl($urlMatch)}</a>",
                    $content
                );
            }
        }

        return $content;
    }
}
