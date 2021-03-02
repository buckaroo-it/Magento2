<?php
return  [
  'backend' =>
   [
    'frontName' => 'admin',
  ],
  'install' =>
   [
    'date' => 'Mon, 01 Feb 2016 15:21:04 +0000',
  ],
  'crypt' =>
   [
    'key' => 'ef0e78a6c44764690780138ce89b7791',
  ],
  'session' =>
   [
    'save' => 'db',
  ],
  'db' =>
   [
    'table_prefix' => '',
    'connection' =>
     [
      'default' =>
       [
        'host' => 'MAGENTO_DB_HOST',
        'dbname' => 'MAGENTO_DB_NAME',
        'username' => 'MAGENTO_DB_USER',
        'password' => 'MAGENTO_DB_PASS',
        'active' => '1',
      ],
    ],
  ],
  'resource' =>
   [
    'default_setup' =>
     [
      'connection' => 'default',
    ],
  ],
  'x-frame-options' => 'SAMEORIGIN',
  'MAGE_MODE' => 'default',
];
