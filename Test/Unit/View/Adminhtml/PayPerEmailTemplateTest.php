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

namespace Buckaroo\Magento2\Test\Unit\View\Adminhtml;

use Buckaroo\Magento2\ViewModel\PpeCustomerDetails;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Payment\Block\Form;
use PHPUnit\Framework\TestCase;

/**
 * The admin PayPerEmail form prefills the customer from the view model on the parent billing form block.
 */
class PayPerEmailTemplateTest extends TestCase
{
    private const TEMPLATE = __DIR__ . '/../../../../view/adminhtml/templates/sales/order/create/billingmethod/payperemail.phtml';

    private function render(?AbstractBlock $parent): string
    {
        $block = $this->getMockBuilder(Form::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMethodCode', 'getParentBlock', 'escapeHtml', 'escapeHtmlAttr'])
            ->getMock();
        $block->method('getMethodCode')->willReturn('buckaroo_magento2_payperemail');
        $block->method('getParentBlock')->willReturn($parent);
        $block->method('escapeHtml')->willReturnCallback(function ($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES);
        });
        $block->method('escapeHtmlAttr')->willReturnCallback(function ($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES);
        });

        $render = function (string $template) use ($block): string {
            ob_start();
            include $template;
            return (string)ob_get_clean();
        };
        return $render(self::TEMPLATE);
    }

    private function parentWith($viewModel): AbstractBlock
    {
        $parent = $this->getMockBuilder(AbstractBlock::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->getMockForAbstractClass();
        $parent->method('getData')->willReturnCallback(function ($key = '') use ($viewModel) {
            return $key === 'ppe_customer_details' ? $viewModel : null;
        });
        return $parent;
    }

    private function fieldValue(string $html, string $id): ?string
    {
        if (!preg_match('/id="' . preg_quote($id, '/') . '"[^>]*?value="([^"]*)"/s', $html, $match)) {
            return null;
        }
        return html_entity_decode($match[1], ENT_QUOTES);
    }

    public function testCustomerDetailsArePrefilledFromTheParentViewModel(): void
    {
        $viewModel = $this->createMock(PpeCustomerDetails::class);
        $viewModel->method('getPPeCustomerDetails')->willReturn([
            'firstName' => 'Jan',
            'middleName' => 'van',
            'lastName' => "D'Arc",
            'email' => 'jan@example.com',
        ]);

        $html = $this->render($this->parentWith($viewModel));

        $this->assertSame('Jan', $this->fieldValue($html, 'buckaroo_magento2_payperemail_BillingFirstName'));
        $this->assertSame('van', $this->fieldValue($html, 'buckaroo_magento2_payperemail_BillingMiddleName'));
        $this->assertSame("D'Arc", $this->fieldValue($html, 'buckaroo_magento2_payperemail_BillingLastName'));
        $this->assertSame('jan@example.com', $this->fieldValue($html, 'buckaroo_magento2_payperemail_Email'));
    }

    public function testFieldsAreEmptyWithoutParentBlock(): void
    {
        $html = $this->render(null);

        $this->assertSame('', $this->fieldValue($html, 'buckaroo_magento2_payperemail_BillingFirstName'));
        $this->assertSame('', $this->fieldValue($html, 'buckaroo_magento2_payperemail_Email'));
    }

    public function testFieldsAreEmptyWhenParentHasNoViewModel(): void
    {
        $html = $this->render($this->parentWith(null));

        $this->assertSame('', $this->fieldValue($html, 'buckaroo_magento2_payperemail_BillingFirstName'));
    }

    public function testFieldsAreEmptyWhenViewModelFindsNoCustomer(): void
    {
        $viewModel = $this->createMock(PpeCustomerDetails::class);
        $viewModel->method('getPPeCustomerDetails')->willReturn(null);

        $html = $this->render($this->parentWith($viewModel));

        $this->assertSame('', $this->fieldValue($html, 'buckaroo_magento2_payperemail_Email'));
    }
}
