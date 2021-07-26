<?php
declare(strict_types=1);

namespace Buckaroo\Magento2\Plugin;

class AbstractTransactionBuilderAfterGetUrl
{

    public function __construct(
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
    ) {
        $this->cookieManager = $cookieManager;
    }
    public function afterGetReturnUrl(
        $subject,
        $result
    ) {
        
        try {
            $ga_cookie = $this->cookieManager->getCookie(
                '_ga'
            );
            $parts = explode(".",$ga_cookie);
            if($parts) array_shift($parts);
            if($parts) array_shift($parts);
            $clientId = implode(".",$parts);        
            $result .= "&clientId=".$clientId;
        } catch(\Exception $e) {
            //@todo log
        }    
        return $result;
    }
}
