<p align="center">
  <img src="https://github.com/user-attachments/assets/d9ed3b9e-551c-4426-b995-4481ba21816d" width="200px" position="center">
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

The [Buckaroo Payments Plugin for Magento 2](https://docs.buckaroo.io/docs/magento-2-old) enables a ready-to-sell payment gateway. You can choose from popular online payment methods in The Netherlands, Belgium, France, Germany and globally.
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

For the configuration of the plugin, please refer to our [configuration page](https://docs.buckaroo.io/docs/magento-2-old-configuration)
You will find all the necessary information there.<br>
But if you still have some unanswered questions, then please contact our [technical support team](mailto:support@buckaroo.nl).
<br>

## Additional plugins

<b>The Buckaroo Magento 2 plugin can be extended with the following modules:</b>

| [Second chance<br>module](https://github.com/buckaroo-it/Magento2_SecondChance)   | [Google Analytics<br>module](https://github.com/buckaroo-it/Magento2_Analytics)   | [Hyvä React Checkout<br>module](https://github.com/buckaroo-it/Magento2_Hyva) | [Hyvä Checkout<br>module](https://github.com/buckaroo-it/Magento2_Hyva_Checkout)  | [GraphQL<br>module](https://github.com/buckaroo-it/Magento2_GraphQL)   |
:-------------------------:|:-------------------------:|:-------------------------:|:-------------------------:|:-------------------------:|
[<img src="https://www.buckaroo.nl/media/n2qpue0z/magento2_secondchance_icon.png" alt="Second-chance" width="200"/>](https://github.com/buckaroo-it/Magento2_SecondChance)|  [<img src="https://www.buckaroo.nl/media/osphsp1u/magento2_googleanalytics_icon.png" alt="Google-analytics" width="200"/>](https://github.com/buckaroo-it/Magento2_Analytics)| [<img src="https://www.buckaroo.nl/media/iyvnqp2k/magento2_hyvareactcheckout_icon.png" alt="Hyva-react-checkout" width="200"/>](https://github.com/buckaroo-it/Magento2_Hyva) | [<img src="https://www.buckaroo.nl/media/33gf24ru/magento2_hyvacheckout_icon.png" alt="Hyva-checkout" width="200"/>](https://github.com/buckaroo-it/Magento2_Hyva_Checkout) | [<img src="https://www.buckaroo.nl/media/w0sdhkjd/magento2_graphql_icon.png" alt="GraphQL" width="200"/>](https://github.com/buckaroo-it/Magento2_GraphQL) |
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
- **Knowledge base & FAQ:** [docs.buckaroo.io](https://docs.buckaroo.io)
- **Contact:** [support@buckaroo.nl](mailto:support@buckaroo.nl) or [+31 (0)30 711 50 50](tel:+310307115050)

<b>Please note:</b><br>
This file has been prepared with the greatest possible care and is subject to language and/or spelling errors.

