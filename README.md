# Buckaroo Magento 2 Extension

### Installation & Configuration 

Below you will find a link to the installation and configuration manual of the Buckaroo Magento 2 extension. This will provide you a step-by-step description of how you can install the extension on your environment.

https://support.buckaroo.nl/categorie%C3%ABn/plugins/magento-2

#### Install via composer
We recommend you to install the Buckaroo Magento 2 module via composer. It is easy to install, update and maintain.

Run the following commands in the Magento 2 root folder.

##### Install
```
composer require buckaroo/magento2
php bin/magento module:enable Buckaroo_Magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

##### Upgrade
```
composer update buckaroo/magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

##### Run compile if your store in Production mode:
```
php bin/magento setup:di:compile
```

### Contribute

See [Contribution Guidelines](CONTRIBUTING.md)

### Additional information

Release notes:

https://support.buckaroo.nl/categorie%C3%ABn/plugins/magento-2/release-notes

Knowledge base & FAQ:

https://support.buckaroo.nl/categorie%C3%ABn/plugins/magento-2

Support:

https://support.buckaroo.nl/contact
