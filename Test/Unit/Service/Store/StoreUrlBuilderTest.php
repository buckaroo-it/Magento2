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

namespace Buckaroo\Magento2\Test\Unit\Service\Store;

use Buckaroo\Magento2\Service\Store\StoreUrlBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * The whole point of this class is that a URL for another store must not be built from the ambient
 * base URL. On a per-domain setup that is the difference between the shopper coming back to their
 * own store and being dropped on the default store's domain, where their session does not exist.
 */
class StoreUrlBuilderTest extends TestCase
{
    private const AMBIENT_BASE = 'https://ambient.example.com/';
    private const STORE_2_BASE = 'https://store2.example.com/';

    /**
     * @var UrlInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $urlBuilder;

    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManager;

    /**
     * @var StoreUrlBuilder
     */
    private $storeUrlBuilder;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        // Stands in for getDirectUrl()'s behaviour: it always resolves the AMBIENT host, which is
        // exactly why ['_scope' => $id] was not enough.
        $this->urlBuilder->method('getDirectUrl')->willReturnCallback(
            fn($path) => self::AMBIENT_BASE . $path
        );

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->storeUrlBuilder = new StoreUrlBuilder($this->urlBuilder, $this->storeManager);
    }

    private function store(string $baseUrl): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn($baseUrl);

        return $store;
    }

    public function testBuildsAgainstTheGivenStoresOwnHostNotTheAmbientOne(): void
    {
        $this->storeManager->method('getStore')->with(2)->willReturn($this->store(self::STORE_2_BASE));

        $this->assertSame(
            'https://store2.example.com/buckaroo/redirect/process',
            $this->storeUrlBuilder->getUrl(2, 'buckaroo/redirect/process')
        );
    }

    /**
     * getStoreId() hands back a string off the sales_order row, so the normaliser has to absorb it.
     */
    public function testAcceptsAStringStoreId(): void
    {
        $this->storeManager->method('getStore')->with(2)->willReturn($this->store(self::STORE_2_BASE));

        $this->assertSame(
            'https://store2.example.com/buckaroo/redirect/process',
            $this->storeUrlBuilder->getUrl('2', 'buckaroo/redirect/process')
        );
    }

    public function testUsesTheAmbientStoreWhenNoStoreIsGiven(): void
    {
        $this->storeManager->expects($this->once())->method('getStore')->with()
            ->willReturn($this->store(self::AMBIENT_BASE));

        $this->assertSame(
            'https://ambient.example.com/buckaroo/redirect/process',
            $this->storeUrlBuilder->getUrl(null, 'buckaroo/redirect/process')
        );
    }

    /**
     * A failure here has to degrade to what the module emitted before store scoping existed,
     * rather than to a broken URL.
     */
    public function testFallsBackToTheAmbientUrlWhenTheStoreCannotBeResolved(): void
    {
        $this->storeManager->method('getStore')->willThrowException(new NoSuchEntityException());

        $this->assertSame(
            'https://ambient.example.com/buckaroo/redirect/process',
            $this->storeUrlBuilder->getUrl(99999, 'buckaroo/redirect/process')
        );
    }

    public function testDoesNotDoubleTheSlashWhenTheBaseUrlHasATrailingOne(): void
    {
        $this->storeManager->method('getStore')->willReturn($this->store('https://store2.example.com/'));

        $this->assertSame(
            'https://store2.example.com/rest/second_store/V1/buckaroo/push',
            $this->storeUrlBuilder->getUrl(2, 'rest/second_store/V1/buckaroo/push')
        );
    }

    /**
     * Guards the documented reason the concrete Store model is required: getBaseUrl() is not on
     * StoreInterface, so anything else has to degrade to the ambient URL instead of fataling.
     */
    public function testFallsBackToTheAmbientUrlForAStoreWithoutGetBaseUrl(): void
    {
        $this->assertSame(
            'https://ambient.example.com/some/path',
            $this->storeUrlBuilder->buildForStore(null, 'some/path')
        );
    }
}
