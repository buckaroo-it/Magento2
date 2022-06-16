<?php

/**
 * SOAP has a different method signatures between php 7.x and 8.x
 * so we replace the current 8.x class with a compatible class to php 7.x
 */
if (version_compare(PHP_VERSION, '8', '<')) {
    copy(
        __DIR__."\.soap-for-php7", 
        "app\code\Buckaroo\Magento2\Soap\Client\SoapClientWSSEC.php"
    );
}