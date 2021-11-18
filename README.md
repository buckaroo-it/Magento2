<p align="center">
  <img src="https://www.buckaroo.nl/media/2975/m2_icon.jpg" width="150px" position="center">
</p>

# Buckaroo Magento 2 Extension

### Installation & Configuration 

Below you will find a link to the installation and configuration manual of the Buckaroo Magento 2 extension. This will provide you a step-by-step description of how you can install the extension on your environment.

https://support.buckaroo.nl/categorie%C3%ABn/plugins/magento-2

### Install via composer
We recommend you to install the Buckaroo Magento 2 module via composer. It is easy to install, update and maintain.
Run the following commands in the Magento 2 root folder:

### Install
```
composer require buckaroo/magento2
php bin/magento module:enable Buckaroo_Magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```
### Upgrade
```
composer update buckaroo/magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```
### Run compile if your store in Production mode:
```
php bin/magento setup:di:compile
```

### Additional modules

The Buckaroo Magento 2 plugin can be extended with the following modules: 

* [Buckaroo Second Chance extension](https://github.com/buckaroo-it/Magento2_SecondChance) (Makes it possible to follow up unpaid orders)
* [Buckaroo Google Analytics extension](https://github.com/buckaroo-it/Magento2_Analytics) (GA Tracking for cross-browser or cross-device)

### Contribute

See [Contribution Guidelines](CONTRIBUTING.md)

### Additional information
Knowledge base & FAQ:

https://support.buckaroo.nl/categorie%C3%ABn/plugins/magento-2

Support:

https://support.buckaroo.nl/contact
