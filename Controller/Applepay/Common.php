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
namespace Buckaroo\Magento2\Controller\Applepay;

use Buckaroo\Magento2\Logging\Log;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Common extends Action
{
    /** @var  PageFactory */
    protected $resultPageFactory;

    /**
     * @var \Magento\Framework\Translate\Inline\ParserInterface
     */
    protected $inlineParser;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    protected $logger;

    /**
     * @param Context     $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Framework\Translate\Inline\ParserInterface $inlineParser,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        Log $logger
    ) {
        parent::__construct($context);

        $this->resultPageFactory = $resultPageFactory;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->inlineParser = $inlineParser;
        $this->logger             = $logger;
    }

    public function execute()
    {

    }

    /**
     * @param $address
     * @param $quoteTotals
     *
     * @return array
     */
    public function gatherTotals($address, $quoteTotals)
    {
        $totals = array(
            'subtotal'   => $quoteTotals['subtotal']->getValue(),
            'discount'   => isset($quoteTotals['discount']) ? $quoteTotals['discount']->getValue() : null,
            'shipping'   => $address->getData('shipping_incl_tax'),
            'grand_total' => $quoteTotals['grand_total']->getValue()
        );

        return $totals;
    }

    /**
     * @param $wallet
     * @param string $type
     *
     * @return array
     */
    public function processAddressFromWallet($wallet, $type = 'shipping')
    {
        $address = array(
            'prefix'     => '',
            'firstname'  => isset($wallet['givenName']) ? $wallet['givenName'] : '',
            'middlename' => '',
            'lastname'   => isset($wallet['familyName']) ? $wallet['familyName'] : '',
            'street'     => array(
                '0' => isset($wallet['addressLines'][0]) ? $wallet['addressLines'][0] : '',
                '1' => isset($wallet['addressLines'][1]) ? $wallet['addressLines'][1] : null
            ),
            'city'       => isset($wallet['locality']) ? $wallet['locality'] : '',
            'country_id' => isset($wallet['countryCode']) ? $wallet['countryCode'] : '',
            'region'     => isset($wallet['administrativeArea']) ? $wallet['administrativeArea'] : '',
            'region_id'  => '',
            'postcode'   => isset($wallet['postalCode']) ? $wallet['postalCode'] : '',
            'telephone'  => isset($wallet['phoneNumber']) ? $wallet['phoneNumber'] : 'N/A',
            'fax'        => '',
            'vat_id'     => ''
        );

        if ($type == 'shipping') {
            $address['email'] = isset($wallet['emailAddress']) ? $wallet['emailAddress'] : '';
        }

        return $address;
    }

}
