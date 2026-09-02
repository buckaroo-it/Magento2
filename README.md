<p align="center">
  <a href="https://www.buckaroo.nl">
    <img src="https://raw.githubusercontent.com/buckaroo-it/Media/main/Buckaroo/README.md%20Headers/buckaroo-magento2-header-rounded.png" alt="Buckaroo — Payments for Magento 2" width="100%">
  </a>
</p>

<h1 align="center">Buckaroo for Magento 2</h1>

<p align="center">
  A ready-to-sell payment gateway for Magento 2 (Adobe Commerce and Magento Open Source).<br>
  Accept the payment methods your customers expect in the Netherlands, Belgium, Germany, France and beyond.
</p>

<p align="center">
  <a href="https://packagist.org/packages/buckaroo/magento2"><img src="https://img.shields.io/packagist/v/buckaroo/magento2.svg?label=release" alt="Latest release"></a>
  <a href="https://packagist.org/packages/buckaroo/magento2"><img src="https://img.shields.io/packagist/l/buckaroo/magento2.svg?label=license" alt="License"></a>
  <a href="https://docs.buckaroo.io/docs/magento-2"><img src="https://img.shields.io/badge/docs-docs.buckaroo.io-1a1a4b.svg" alt="Documentation"></a>
</p>

<p align="center">
  <a href="#about">About</a> ·
  <a href="#requirements">Requirements</a> ·
  <a href="#installation">Installation</a> ·
  <a href="#upgrade">Upgrade</a> ·
  <a href="#configuration">Configuration</a> ·
  <a href="#payment-methods">Payment methods</a> ·
  <a href="#additional-modules">Additional modules</a> ·
  <a href="#support">Support</a> ·
  <a href="#contribute">Contribute</a>
</p>

---

## About

Magento is an e-commerce platform owned by Adobe, available as Magento Open Source (free, self-hosted, written in PHP) and Adobe Commerce (the paid, cloud-hosted edition). Around 150,000 merchants worldwide run on the platform.

The Buckaroo plugin for Magento 2 connects your store to the Buckaroo payment gateway, so you can start accepting payments within minutes. Buckaroo is a Dutch Payment Service Provider and an **Adobe Silver Technology Partner**.

Beyond payments, the plugin ships with extras such as Google Analytics tracking and Second Chance, which sends reminder e-mails to customers who did not complete their payment.

[Full plugin documentation on docs.buckaroo.io](https://docs.buckaroo.io/docs/magento-2)

---

## Requirements

| Requirement | Supported versions |
|---|---|
| Magento | 2.4.7 up to 2.4.9 |
| PHP | 8.1 up to 8.5 |
| Composer | 2.x |

You also need a Buckaroo account. Don't have one yet? [Request an account](https://www.buckaroo.nl/start).

> [!NOTE]
> Older Magento and PHP versions may still work, but are no longer tested or supported. We recommend running a supported combination as listed in the [Adobe system requirements](https://experienceleague.adobe.com/docs/commerce-operations/installation-guide/system-requirements.html).

---

## Installation

We recommend installing the plugin with Composer — it is the easiest way to install, update and maintain.

Run the following commands from your Magento 2 root folder:

```bash
composer require buckaroo/magento2
php bin/magento module:enable Buckaroo_Magento2
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

<details>
<summary>Installing a specific version</summary>

```bash
composer require buckaroo/magento2:^2.0
```

All released versions are listed on the [releases page](https://github.com/buckaroo-it/Magento2/releases).
</details>

---

## Upgrade

```bash
composer update buckaroo/magento2
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

If your store runs in **production mode**, make sure `setup:di:compile` and `setup:static-content:deploy` are executed after the upgrade.

> [!TIP]
> Always test an upgrade on a staging environment first and check the [release notes](https://github.com/buckaroo-it/Magento2/releases) for breaking changes.

---

## Configuration

After installation, configure the plugin in the Magento admin under
**Stores → Configuration → Sales → Buckaroo**.

You will need your **Store key** and **Secret key**, which you can find under [API credentials in Buckaroo Plaza](https://plaza.buckaroo.nl/Configuration/Merchant/ApiKeys).

Step-by-step instructions: [Configuring the Magento 2 plugin](https://docs.buckaroo.io/docs/magento-2-configuration)

---

## Payment methods

The plugin supports the following payment methods. Each one can be enabled or disabled individually and switched between live and test mode.

| | | |
|---|---|---|
| [Alipay](https://docs.buckaroo.io/docs/alipay) | [Apple Pay](https://docs.buckaroo.io/docs/apple-pay) | [Bancontact](https://docs.buckaroo.io/docs/bancontact) |
| [Bank Transfer](https://docs.buckaroo.io/docs/transfer) | [Belfius](https://docs.buckaroo.io/docs/belfius) | [Billink](https://docs.buckaroo.io/docs/billink) |
| [Bizum](https://docs.buckaroo.io/docs/bizum) | [Blik](https://docs.buckaroo.io/docs/blik) | [Buckaroo Voucher](https://docs.buckaroo.io/docs/buckaroo-voucher) |
| [Credit and debit cards](https://docs.buckaroo.io/docs/creditcards) | [EPS](https://docs.buckaroo.io/docs/eps) | [Giftcards](https://docs.buckaroo.io/docs/giftcards) |
| [Google Pay](https://docs.buckaroo.io/docs/google-pay) | [iDEAL / Wero](https://docs.buckaroo.io/docs/ideal) | [In3](https://docs.buckaroo.io/docs/in3) |
| [KBC](https://docs.buckaroo.io/docs/kbc) | [Klarna](https://docs.buckaroo.io/docs/klarna-kp) | [MB Way](https://docs.buckaroo.io/docs/mb-way) |
| [Multibanco](https://docs.buckaroo.io/docs/multibanco) | [Pay by Bank](https://docs.buckaroo.io/docs/pay-by-bank) | [PayLink](https://docs.buckaroo.io/docs/payperemail) |
| [PayPal](https://docs.buckaroo.io/docs/paypal) | [PayPerEmail](https://docs.buckaroo.io/docs/payperemail) | [Przelewy24](https://docs.buckaroo.io/docs/przelewy24) |
| [Riverty](https://docs.buckaroo.io/docs/riverty) | [SEPA Direct Debit](https://docs.buckaroo.io/docs/sepa-direct-debit) | [Swish](https://docs.buckaroo.io/docs/swish) |
| [Trustly](https://docs.buckaroo.io/docs/trustly) | [Twint](https://docs.buckaroo.io/docs/twint) | [WeChatPay](https://docs.buckaroo.io/docs/wechatpay) |
| [Wero](https://docs.buckaroo.io/docs/wero) |  |  |

> [!IMPORTANT]
> All supported methods appear in the Magento admin, but you need an active Buckaroo subscription for a method before you can offer it in your checkout.

---

## Additional modules

The Buckaroo Magento 2 plugin can be extended with the following modules:

- **[Hyvä Checkout](https://github.com/buckaroo-it/Magento2_Hyva_Checkout)** — payment support for the Hyvä Checkout.
- **[Hyvä React Checkout](https://github.com/buckaroo-it/Magento2_Hyva)** — payment support for the Hyvä React Checkout.
- **[GraphQL](https://github.com/buckaroo-it/Magento2_GraphQL)** — payment support for headless setups via GraphQL.

More details: [additional modules documentation](https://docs.buckaroo.io/docs/magento-2-additional-modules).

---

## Support

Having trouble? Work through this list before reaching out:

1. Check the [knowledge base and FAQ](https://docs.buckaroo.io/docs/magento-2).
2. Confirm you are on the [latest release](https://github.com/buckaroo-it/Magento2/releases).
3. Enable debug logging in the plugin configuration and reproduce the issue.
4. Verify that your push URL is reachable from outside your network.

Still stuck? Contact us and include your Magento version, plugin version, PHP version, the relevant log lines and the transaction key.

- **Bug reports and feature requests:** [open an issue](https://github.com/buckaroo-it/Magento2/issues)
- **Technical support:** [support@buckaroo.nl](mailto:support@buckaroo.nl)
- **Phone:** +31 (0)30 711 50 50
- **Gateway status:** [status.buckaroo.io](https://status.buckaroo.io/)

---

## Contribute

We really appreciate it when developers help improve the Buckaroo plugins. Please read our [Contribution Guidelines](https://github.com/buckaroo-it/Magento2/blob/develop/CONTRIBUTING.md) before opening a pull request, and target the `develop` branch.

Found a security issue? Please report it privately to [support@buckaroo.nl](mailto:support@buckaroo.nl) instead of opening a public issue.

---

## Versioning

We follow semantic versioning (`MAJOR.MINOR.PATCH`):

- **MAJOR** — breaking changes that require additional testing and caution.
- **MINOR** — new functionality with limited impact.
- **PATCH** — bug fixes and hotfixes only.

All changes are documented on the [releases page](https://github.com/buckaroo-it/Magento2/releases).

---

## License

This module is released under the [MIT License](LICENSE).

The Buckaroo name, logo and other Buckaroo brand assets are trademarks of
Buckaroo B.V. and are not covered by the MIT License. The licence grants rights
to the source code only; it does not grant permission to use the Buckaroo brand
to endorse or promote derived works.

---

<p align="center">
  <sub>Made with care by <a href="https://www.buckaroo.nl">Buckaroo</a>.<br>
  This document is subject to change; typos and language errors are possible.</sub>
</p>
