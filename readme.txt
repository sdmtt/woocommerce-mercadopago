=== WooCommerce MercadoPago ===
Contributors: claudiosanches
Donate link: http://claudiosmweb.com/doacoes/
Tags: woocommerce, mercadopago, payment
Requires at least: 4.0
Tested up to: 4.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds MercadoPago gateway to your WooCommerce store

== Description ==

[MercadoPago](https://www.mercadopago.com/) is a payment gateway developed by MercadoLibre.

The WooCommerce MercadoPago plugin was developed without any incentive from MercadoPago or MercadoLibre. We developed this plugin based on the [MercadoPago official documentation](http://developers.mercadopago.com/).

= Compatibility =

- [WooCommerce](https://wordpress.org/plugins/woocommerce/) 2.2 or later (yes, this includes support for 2.5).
- [WooCommerce Subscriptions](https://www.woothemes.com/products/woocommerce-subscriptions/) 2.0 or later.

= Install Process: =

Check our [installation guide](http://wordpress.org/extend/plugins/woocommerce-mercadopago/installation/).

= Questions? =

- First of all, make sure if your question has already been answered in our [FAQ](http://wordpress.org/extend/plugins/woocommerce-mercadopago/faq/).
- Still have question? Create a topic in your [support forum](http://wordpress.org/support/plugin/woocommerce-mercadopago).
- Or found a bug? Report in our [GitHub page](https://github.com/claudiosmweb/woocommerce-mercadopago/issues).

Usually I don't have time to reply support topics, so be patient.

= Contribute =

You can contribute to the source code in our [GitHub](https://github.com/claudiosmweb/woocommerce-mercadopago) page.

== Installation ==

- Upload plugin files to your plugins folder or install using WordPress built-in "Add New Plugin" installer.
- Activate the plugin.

= Requirements: =

- A [MercadoPago](https://www.mercadopago.com/) account.
- Installed the latest version of [WooCommerce](http://wordpress.org/extend/plugins/woocommerce/).

= MercadoPago Settings: =

Get your `Client_id` and `Client_secret` from MercadoPago:

* [Argentina](https://www.mercadopago.com/mla/herramientas/aplicaciones)
* [Brazil](https://www.mercadopago.com/mlb/ferramentas/aplicacoes)
* [Colombia](https://www.mercadopago.com/mco/herramientas/aplicaciones)
* [Mexico](https://www.mercadopago.com/mlm/herramientas/aplicaciones)
* [Venezuela](https://www.mercadopago.com/mlv/herramientas/aplicaciones)

Set up your notification page accessing:

* [Argentina](https://www.mercadopago.com/mla/herramientas/notificaciones)
* [Brazil](https://www.mercadopago.com/mlb/ferramentas/notificacoes)
* [Colombia](https://www.mercadopago.com/mco/herramientas/notificaciones)
* [Mexico](https://www.mercadopago.com/mlm/herramientas/notificaciones)
* [Venezuela](https://www.mercadopago.com/mlv/herramientas/notificaciones)

Your notification page must be like this example:

	http://example.com/?wc-api=WC_MercadoPago_Gateway

Kind of obvious... But you need to change `example.com` for your domain!

= Plugin Settings: =

Once the plugin is installed you need to go to "WooCommerce" > "Settings" > "Checkout" > "MercadoPago" and turn on the MercadoPago, fill your email address, `Client_id` and `Client_secret` fields.

Now your store is ready to receive payments from MercadoPago.

== Frequently Asked Questions ==

= What is the plugin license? =

* This plugin is released under a GPL license.

= What is needed to use this plugin? =

* WooCommerce version 2.2 or latter installed and active.
* An account on [MercadoPago](https://www.mercadopago.com/ "MercadoPago").
* Get your `Client_id` and `Client_secret` from MercadoPago.
* Set a notification page in MercadoPago.

See more details in the [installation guide](http://wordpress.org/extend/plugins/woocommerce-mercadopago/installation/).

= Currencies accepted =

Supports all currencies of [this list](https://api.mercadopago.com/currencies).

= Why my order remain pending? =

This happens when your site not receive the payment notifications from MercadoPago.

Reasons for this problem happen:

- Security plugins blacklisting MercadoPago user-agent (eg: **iThemes Security**). You can find [here](http://tureseller.com.ar/solucion-al-problema-de-recibir-notificaciones-de-compra-desde-mercadopago-en-woocommerce-para-wordpress/) instructions on how to solve this problem.
- `mod_security` blocking external requests or user-agents. You need to allow requests from MercadoPago.
- CloudFlare blocking external requests or user-agents. You need to allow requests from MercadoPago.

= The order was paid and got the status of "processing" and not as "complete"... There's something wrong? =

Nop! In fact, this means that the plugin is working like expected.

All payment gateways in Woocommerce should change order status to "processing" when an order is paid and never change to "complete", because you should use the "complete" status just only after shipped your order.

If you are working with downloadable products you should turn on the "Grant access to downloadable products after payment" option in "WooCommerce" > "Settings" > "Products" > "Downloadable Products" page.

= Still having problems? =

Turn on the "Debug Log" option, try make a payment again, then get your log file and paste the content in [pastebin.com](http://pastebin.com/) and start a [support forum topic](http://wordpress.org/support/plugin/woocommerce-mercadopago) with your pastebin link.

== Screenshots ==

1. Settings page.
2. Checkout page.

== Changelog ==

= 3.0.0 - 2015/12/22 =

* Feature - WooCommerce Subscriptions integration.
* Feature - Added support for several currencies.

== Upgrade Notice ==

= 3.0.0 =

* Feature - WooCommerce Subscriptions integration.
* Feature - Added support for several currencies.
