<?php

declare(strict_types=1);

namespace Buckaroo\Magento2\Gateway\Request;

use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Buckaroo\Magento2\Gateway\Request\AbstractDataBuilder;
use Buckaroo\Magento2\Model\ConfigProvider\Method\Transfer;

class TransferOrderDataBuilder extends AbstractDataBuilder
{

    protected ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function build(array $buildSubject): array
    {
        parent::initialize($buildSubject);
        return [
            'dateDue' => $this->getDueDate(),
            'sendMail' => (bool)$this->getSendEmail(),
        ];
    }

    /**
     * Get send email flag
     *
     * @return string
     */
    protected function getSendEmail()
    {
        return $this->scopeConfig->getValue(
            Transfer::XPATH_TRANSFER_SEND_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $this->getOrder()->getStore()
        );
    }
    /**
     * Get transfer due date
     * @return string 
     */
    protected function getDueDate(): string
    {
        $dueDays = abs(
            $this->scopeConfig->getValue(
                Transfer::XPATH_TRANSFER_DUE_DATE,
                ScopeInterface::SCOPE_STORE,
                $this->getOrder()->getStore()
            )
        );
        return (new \DateTime())
            ->modify("+{$dueDays} day")
            ->format('Y-m-d');
    }
}
