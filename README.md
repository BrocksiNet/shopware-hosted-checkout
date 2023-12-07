# Hosted Checkout Subscriber
Adds an Event Subscriber to pass carts from headless frontends to the default Shopware 6 checkout.

## Why?
For the end user it is not good to switch between two different frontends (UX). But I can also understand that it is maybe
hard to implement the complete checkout process in a headless frontend. So this plugin is a compromise to use the
twig storefront (and with that all plugins and apps from marketplace) only for the checkout process. It allows to use
the same cart in headless (create before via store API) and twig storefront. But for security reasons this is a one-way process. Once the token is used
the headless frontend does not know anything about the cart anymore (token replaced).

## Installation

Require it via composer
```bash
composer require brocksinet/hosted-checkout
```

Install and activate the plugin via the console
```bash
bin/console plugin:install HostedCheckout --activate
```

## Overview checkout process
![Overview about Shopware hosted Checkout process](/assets/images/shopware-hosted-checkout-overview.png)

## Project specific ToDo's after installation
- You maybe need to adjust the templates for the cart/checkout so that your end user is jumping back to the headless frontend instead of the default twig storefront.
- You need to provide the link with the cart token for the user in your headless frontend to jumping to the twig storefront checkout.

## Issues or problems?
Please create an issue in this repository.

---

Created with 💙 by [BrocksiNet](https://brocksinet.de)