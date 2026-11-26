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

namespace Buckaroo\Magento2\Test\Unit\Model\Service;

use Buckaroo\Magento2\Logging\BuckarooLoggerInterface;
use Buckaroo\Magento2\Model\Service\GiftCardRefundService;
use Magento\Framework\DataObject;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * The Adobe Commerce gift card account module is not installed here, so its repository and
 * history factory are replaced by small in-test doubles injected into the service.
 *
 * @SuppressWarnings(PHPMD.UnusedLocalVariable)
 */
class GiftCardRefundServiceTest extends TestCase
{
    /**
     * @var GiftCardRefundService
     */
    private $service;

    /**
     * @var Order|MockObject
     */
    private $order;

    /**
     * @var array accounts by id, as DataObject with a "balance"
     */
    private $accounts = [];

    /**
     * @var array ids saved through the repository, in order
     */
    private $saved = [];

    /**
     * @var string|null id whose load fails
     */
    private $failingGet = null;

    /**
     * @var string|null id whose save fails
     */
    private $failingSave = null;

    protected function setUp(): void
    {
        $this->service = new GiftCardRefundService($this->createMock(BuckarooLoggerInterface::class));

        $this->order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addCommentToStatusHistory', 'getIncrementId'])
            ->getMock();
        $this->order->method('getIncrementId')->willReturn('000000042');

        $this->accounts = [];
        $this->saved = [];
        $this->failingGet = null;
        $this->failingSave = null;
    }

    public function testOrderWithoutGiftCardsIsLeftUntouched(): void
    {
        $this->order->expects($this->never())->method('addCommentToStatusHistory');

        $this->service->refund($this->order);
    }

    /**
     * Without the Adobe Commerce gift card module the order gets a note to refund the cards by hand.
     */
    public function testWithoutAdobeCommerceTheOrderGetsAManualRefundNote(): void
    {
        $this->order->setData('gift_cards', json_encode([['i' => '5', 'a' => 12.5]]));

        $this->order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with(
                'Order contains gift cards. Automatic gift card refund is only available in Adobe Commerce. '
                . 'Please process gift card refunds manually if needed.'
            );

        $this->service->refund($this->order);
    }

    public function testInvalidGiftCardDataRefundsNothing(): void
    {
        $this->enableAdobeCommerce();
        $this->order->setData('gift_cards', 'not-json');

        $this->order->expects($this->never())->method('addCommentToStatusHistory');

        $this->service->refund($this->order);

        $this->assertSame([], $this->saved);
    }

    /**
     * The amount goes back onto the card balance; a failing history record does not stop that.
     */
    public function testGiftCardAmountIsAddedBackToTheCardBalance(): void
    {
        $this->enableAdobeCommerce();
        $this->accounts['5'] = new DataObject(['balance' => 7.25]);
        $this->order->setData('gift_cards', json_encode([['i' => '5', 'a' => 12.5]]));

        $this->order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with('Refunded 12.50 to gift card #5. New balance: 19.75');

        $this->service->refund($this->order);

        $this->assertSame(['5'], $this->saved);
        $this->assertSame(19.75, $this->accounts['5']->getData('balance'));
    }

    /**
     * Cards without an id or with no positive amount are skipped, the valid ones still refunded.
     */
    public function testInvalidCardsAreSkippedAndOthersStillRefunded(): void
    {
        $this->enableAdobeCommerce();
        $this->accounts['8'] = new DataObject(['balance' => 0.0]);
        $this->accounts['9'] = new DataObject(['balance' => 3.0]);
        $this->order->setData('gift_cards', json_encode([
            ['a' => 10.0],
            ['i' => '8', 'a' => 0],
            ['i' => '8'],
            ['i' => '8', 'a' => -4.0],
            ['i' => '9', 'a' => 0.01],
        ]));

        $this->order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with('Refunded 0.01 to gift card #9. New balance: 3.01');

        $this->service->refund($this->order);

        $this->assertSame(['9'], $this->saved);
        $this->assertSame(0.0, $this->accounts['8']->getData('balance'));
    }

    /**
     * A card whose new balance would be negative is not saved.
     */
    public function testNegativeResultingBalanceIsNotSaved(): void
    {
        $this->enableAdobeCommerce();
        $this->accounts['5'] = new DataObject(['balance' => -20.0]);
        $this->order->setData('gift_cards', json_encode([['i' => '5', 'a' => 12.5]]));

        $this->order->expects($this->never())->method('addCommentToStatusHistory');

        $this->service->refund($this->order);

        $this->assertSame([], $this->saved);
        $this->assertSame(-20.0, $this->accounts['5']->getData('balance'));
    }

    /**
     * A card that ends at exactly zero is still saved.
     */
    public function testBalanceEndingAtZeroIsSaved(): void
    {
        $this->enableAdobeCommerce();
        $this->accounts['5'] = new DataObject(['balance' => -2.5]);
        $this->order->setData('gift_cards', json_encode([['i' => '5', 'a' => 2.5]]));

        $this->service->refund($this->order);

        $this->assertSame(['5'], $this->saved);
        $this->assertSame(0.0, $this->accounts['5']->getData('balance'));
    }

    public function testCardThatCannotBeLoadedIsSkippedAndNextCardRefunded(): void
    {
        $this->enableAdobeCommerce();
        $this->failingGet = '4';
        $this->accounts['6'] = new DataObject(['balance' => 1.0]);
        $this->order->setData('gift_cards', json_encode([['i' => '4', 'a' => 5.0], ['i' => '6', 'a' => 2.0]]));

        $this->order->expects($this->once())
            ->method('addCommentToStatusHistory')
            ->with('Refunded 2.00 to gift card #6. New balance: 3.00');

        $this->service->refund($this->order);

        $this->assertSame(['6'], $this->saved);
    }

    public function testCardWhoseSaveFailsGetsNoRefundNote(): void
    {
        $this->enableAdobeCommerce();
        $this->failingSave = '5';
        $this->accounts['5'] = new DataObject(['balance' => 1.0]);
        $this->order->setData('gift_cards', json_encode([['i' => '5', 'a' => 2.0]]));

        $this->order->expects($this->never())->method('addCommentToStatusHistory');

        $this->service->refund($this->order);

        $this->assertSame([], $this->saved);
    }

    /**
     * Replace the Adobe Commerce collaborators with in-test doubles
     */
    private function enableAdobeCommerce(): void
    {
        $test = $this;
        $repository = new class ($test) {
            /** @var GiftCardRefundServiceTest */
            private $test;

            public function __construct(GiftCardRefundServiceTest $test)
            {
                $this->test = $test;
            }

            public function get($cardId)
            {
                return $this->test->loadAccount((string)$cardId);
            }

            public function save($account)
            {
                $this->test->saveAccount($account);
                return $account;
            }
        };
        $historyFactory = new class {
            public function create()
            {
                throw new \RuntimeException('History unavailable');
            }
        };

        $this->setPrivate('isAdobeCommerceAvailable', true);
        $this->setPrivate('giftCardRepo', $repository);
        $this->setPrivate('historyFactory', $historyFactory);
    }

    /**
     * @param string $cardId
     *
     * @return DataObject
     */
    public function loadAccount(string $cardId): DataObject
    {
        if ($cardId === $this->failingGet || !isset($this->accounts[$cardId])) {
            throw new \RuntimeException('No such card');
        }
        $account = $this->accounts[$cardId];
        $account->setData('card_id', $cardId);

        return $account;
    }

    /**
     * @param DataObject $account
     */
    public function saveAccount(DataObject $account): void
    {
        if ($account->getData('card_id') === $this->failingSave) {
            throw new \RuntimeException('Cannot save');
        }
        $this->saved[] = $account->getData('card_id');
    }

    /**
     * @param string $property
     * @param mixed  $value
     */
    private function setPrivate(string $property, $value): void
    {
        $reflection = new \ReflectionProperty(GiftCardRefundService::class, $property);
        $reflection->setValue($this->service, $value);
    }
}
