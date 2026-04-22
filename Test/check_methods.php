<?php
require '/var/www/html/vendor/autoload.php';

$checks = [
    'Magento\Quote\Model\Quote' => [
        'getGrandTotal', 'getQuoteCurrencyCode', 'getCustomerEmail', 'setCustomerEmail',
        'getId', 'getShippingAddress', 'getBillingAddress', 'getPayment',
        'getCustomer', 'reserveOrderId',
    ],
    'Magento\Quote\Model\Quote\Address' => [
        'getFirstname', 'setFirstname', 'getLastname', 'setLastname',
        'getStreet', 'setStreet', 'getTelephone', 'setTelephone',
        'getEmail', 'setEmail', 'setShouldIgnoreValidation',
    ],
];

foreach ($checks as $class => $methods) {
    echo "\n=== $class ===\n";
    $r = new ReflectionClass($class);
    $real = array_map(fn($m) => $m->getName(), $r->getMethods(ReflectionMethod::IS_PUBLIC));
    foreach ($methods as $m) {
        echo "  $m: " . (in_array($m, $real) ? 'REAL (onlyMethods)' : 'MAGIC (addMethods)') . "\n";
    }
}
