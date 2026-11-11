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

use Buckaroo\Magento2\Service\Store\PushUrlBuilder;
use Buckaroo\Magento2\Service\Store\StoreUrlBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class PushUrlBuilderTest extends TestCase
{
    private const BASE = 'https://example.com/';

    /**
     * @var UrlInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $urlBuilder;

    /**
     * @var StoreManagerInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $storeManager;

    /**
     * @var PushUrlBuilder
     */
    private $pushUrlBuilder;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createMock(UrlInterface::class);
        $this->urlBuilder->method('getDirectUrl')->willReturnCallback(fn($p) => self::BASE . $p);

        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        // A real StoreUrlBuilder over the mocked collaborators: the point of these tests is the
        // composed URL, so stubbing the composition away would leave them asserting nothing.
        $this->pushUrlBuilder = new PushUrlBuilder(
            new StoreUrlBuilder($this->urlBuilder, $this->storeManager)
        );
    }

    private function store(string $code, string $baseUrl = self::BASE): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn($code);
        $store->method('getBaseUrl')->willReturn($baseUrl);

        return $store;
    }

    /**
     * Magento\Webapi\Controller\PathProcessor only calls setCurrentStore() for
     * rest/<storeCode>/V1/... - the bare rest/V1/... form falls through unhandled, which is how
     * every push ended up in the default store view.
     */
    public function testPutsTheStoreCodeInThePath(): void
    {
        $this->storeManager->method('getStore')->with(2)->willReturn($this->store('second_store'));

        $this->assertSame(
            self::BASE . 'rest/second_store/V1/buckaroo/push',
            $this->pushUrlBuilder->getPushUrl(2)
        );
    }

    public function testUsesTheAmbientStoreWhenNoneIsGiven(): void
    {
        $this->storeManager->method('getStore')->with()->willReturn($this->store('default'));

        $this->assertSame(
            self::BASE . 'rest/default/V1/buckaroo/push',
            $this->pushUrlBuilder->getPushUrl()
        );
    }

    /**
     * rest/all/V1/... maps to the admin store, where configuration resolves against scope 0 rather
     * than the shopper's store. Emitting the storeless URL instead keeps the push in the frontend
     * scope, where Model\Push's emulation can put it in the order's store.
     */
    public function testFallsBackToTheStorelessUrlForTheAdminStore(): void
    {
        $this->storeManager->method('getStore')->with(0)->willReturn($this->store('admin'));

        $this->assertSame(
            self::BASE . 'rest/V1/buckaroo/push',
            $this->pushUrlBuilder->getPushUrl(0)
        );
    }

    /**
     * Where each website has its own domain, the push is delivered to that website's host and the
     * signature covers the URL the gateway called. Rebuilding it against the ambient store's host
     * would not match, so the push would be rejected.
     *
     * UrlInterface::getDirectUrl() cannot do this - ['_scope' => $storeId] does not change the host
     * it resolves - which is why the base URL comes from the store itself.
     */
    public function testUsesTheHostOfTheOrdersOwnStore(): void
    {
        $this->storeManager->method('getStore')->with(2)
            ->willReturn($this->store('second_store', 'https://store2.example.com/'));

        $this->assertSame(
            'https://store2.example.com/rest/second_store/V1/buckaroo/push',
            $this->pushUrlBuilder->getPushUrl(2)
        );
    }

    public function testBothCandidatesUseTheOrdersOwnHost(): void
    {
        $this->storeManager->method('getStore')->with(2)
            ->willReturn($this->store('second_store', 'https://store2.example.com/'));

        $this->assertSame(
            [
                'https://store2.example.com/rest/second_store/V1/buckaroo/push',
                'https://store2.example.com/rest/V1/buckaroo/push',
            ],
            $this->pushUrlBuilder->getCandidateUris(2)
        );
    }

    public function testFallsBackToTheStorelessUrlForAnUnknownStore(): void
    {
        $this->storeManager->method('getStore')->willThrowException(new NoSuchEntityException());

        $this->assertSame(
            self::BASE . 'rest/V1/buckaroo/push',
            $this->pushUrlBuilder->getPushUrl(99999)
        );
    }

    public function testFallsBackToTheStorelessUrlWhenTheStoreHasNoCode(): void
    {
        $this->storeManager->method('getStore')->willReturn($this->store(''));

        $this->assertSame(
            self::BASE . 'rest/V1/buckaroo/push',
            $this->pushUrlBuilder->getPushUrl(2)
        );
    }

    /**
     * Orders already at the gateway carry the old storeless pushURL, and the signature covers the
     * URL that was called - so both forms have to stay acceptable, newest first.
     */
    public function testCandidateUrisKeepTheLegacyStorelessForm(): void
    {
        $this->storeManager->method('getStore')->with(2)->willReturn($this->store('second_store'));

        $this->assertSame(
            [
                self::BASE . 'rest/second_store/V1/buckaroo/push',
                self::BASE . 'rest/V1/buckaroo/push',
            ],
            $this->pushUrlBuilder->getCandidateUris(2)
        );
    }

    public function testCandidateUrisAreDeduplicatedWhenBothFormsMatch(): void
    {
        $this->storeManager->method('getStore')->with(0)->willReturn($this->store('admin'));

        $this->assertSame(
            [self::BASE . 'rest/V1/buckaroo/push'],
            $this->pushUrlBuilder->getCandidateUris(0)
        );
    }
}
