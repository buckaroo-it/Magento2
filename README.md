<p align="center">
  <img src="https://www.buckaroo.nl/media/3473/magento2_icon.png" width="200px" position="center">
</p>

# Buckaroo Magento 2 Payments Plugin
[![Latest release](https://badgen.net/github/release/buckaroo-it/Magento2)](https://github.com/buckaroo-it/Magento2/releases)

## Index
- [About](#about)
- [Requirements](#requirements)
- [Installation](#installation)
- [Upgrade](#upgrade)
- [Configuration](#configuration)
- [Additional Plugins](#additional-plugins)
- [Contribute](#contribute)
- [Versioning](#versioning)
- [Additional information](#additional-information)
---
<br>

## About

Magento is an e-commerce platform owned by Adobe. There are two versions: Magento Open Source, the free, open source version written in PHP, and Magento Commerce, the paid cloud version.
More than 250,000 merchants around the world use the Magento platform.

The Buckaroo Payments Plugin ([Dutch](https://docs.buckaroo.io/docs/nl/magento-2) or [English](https://docs.buckaroo.io/docs/magento-2)) for Magento 2 enables a ready-to-sell payment gateway. You can choose from popular online payment methods in The Netherlands, Belgium, France, Germany and globally.
Start accepting payments within a few minutes.
<br>

## Requirements

To use the Buckaroo plugin, please be aware of the following minimum requirements:
- A Buckaroo account ([Dutch](https://www.buckaroo.nl/start) or [English](https://www.buckaroo.eu/solutions/request-form))
- Magento version Magento 2.4.5 up to 2.4.7
- PHP 8.1, 8.2, 8.3
<br>

## Installation

We recommend you to install the Buckaroo Magento 2 Payments plugin with composer. It is easy to install, update and maintain.

**Run the following commands in the Magento 2 root folder:**
```
composer require buckaroo/magento2
php bin/magento module:enable Buckaroo_Magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```
<br>

## Upgrade

**You can also update the Buckaroo plugin with composer.
To do this, please run the following commands in your Magento 2 root folder:**

```
composer update buckaroo/magento2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

### Run compile if your store in Production mode:
````
php bin/magento setup:di:compile
````
<br>

## Configuration

For the configuration of the plugin, please refer to our [Dutch](https://docs.buckaroo.io/docs/nl/magento-2) or [English](https://docs.buckaroo.io/v1/docs/magento-2) support website.
You will find all the necessary information there.<br>
But if you still have some unanswered questions, then please contact our [technical support team](mailto:support@buckaroo.nl).
<br>

## Additional plugins

<b>The Buckaroo Magento 2 plugin can be extended with the following modules:</b>

| [Second chance<br>module](https://github.com/buckaroo-it/Magento2_SecondChance)   | [Google Analytics<br>module](https://github.com/buckaroo-it/Magento2_Analytics)   | [Hyvä React Checkout<br>module](https://github.com/buckaroo-it/Magento2_Hyva) | [Hyvä Checkout<br>module](https://github.com/buckaroo-it/Magento2_Hyva_Checkout)  | [GraphQL<br>module](https://github.com/buckaroo-it/Magento2_GraphQL)   |
:-------------------------:|:-------------------------:|:-------------------------:|:-------------------------:|:-------------------------:|
[<img src="https://github.com/buckaroo-it/Magento2/assets/105488705/68ba0c08-1162-44c6-a18a-8734692b8b02" alt="Second-chance" width="200"/>](https://github.com/buckaroo-it/Magento2_SecondChance)|  [<img src="https://github.com/buckaroo-it/Magento2/assets/105488705/1c6e9345-a0ff-46cf-be31-d1c17e69fd90" alt="Google-analytics" width="200"/>](https://github.com/buckaroo-it/Magento2_Analytics)| [<img src="https://github.com/buckaroo-it/Magento2/assets/105488705/11953f16-3f5d-4c10-bb6b-f9a949a97a7a" alt="Hyva-react-checkout" width="200"/>](https://github.com/buckaroo-it/Magento2_Hyva) | [<img src="https://github.com/buckaroo-it/Magento2/assets/105488705/b00d2fcd-2458-4a8b-ab1f-e85d678a0008" alt="Hyva-checkout" width="200"/>](https://github.com/buckaroo-it/Magento2_Hyva_Checkout) | [<img src="https://github.com/buckaroo-it/Magento2/assets/105488705/8611dfeb-bb84-4ba6-ab72-7b6459143dff" alt="GraphQL" width="200"/>](https://github.com/buckaroo-it/Magento2_GraphQL) |
<br>

## Contribute

We really appreciate it when developers contribute to improve the Buckaroo plugins.<br>
If you want to contribute as well, then please follow our [Contribution Guidelines](CONTRIBUTING.md).
<br>

## Versioning 
<p align="left">
  <img src="https://www.buckaroo.nl/media/3480/magento_versioning.png" width="500px" position="center">
</p>

- **MAJOR:** Breaking changes that require additional testing/caution.
- **MINOR:** Changes that should not have a big impact.
- **PATCHES:** Bug and hotfixes only.
<br>

## Additional information
- **Knowledge base & FAQ:** Available in [Dutch](https://docs.buckaroo.io/docs/nl/magento-2) or [English](https://docs.buckaroo.io/docs/magento-2).
- **Support:** https://docs.buckaroo.io
- **Contact:** [support@buckaroo.nl](mailto:support@buckaroo.nl) or [+31 (0)30 711 50 50](tel:+310307115050)

<b>Please note:</b><br>
This file has been prepared with the greatest possible care and is subject to language and/or spelling errors.
