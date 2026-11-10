=== Hibira Live Visitors ===
Contributors: hibira
Tags: woocommerce, live visitors, social proof, sales popup, realtime
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Real "3 people are viewing this" counts, "Someone just bought" popups and live stock on product pages. No fake numbers.

== Description ==

Every number this extension shows is a real open browser tab, counted by Hibira's realtime service.

* Product viewers: a badge under the price shows how many shoppers have the product open, above a threshold you choose.
* Store-wide count: `[hibira_viewers scope="store"]` shows how many shoppers are on the shop right now.
* Purchase popups: open pages show "Someone just bought" with the product when an order is paid. Later visitors see one recent order. No customer names or places are sent.
* Live stock: "Only 2 left in stock" and "Out of stock" update on open product pages, and the add to cart button is disabled when a product sells out.

Works with classic and block themes, HPOS and the cart and checkout blocks.

== External services ==

This extension connects to Hibira (https://hibira.io), a realtime WebSocket service, to count open product pages and deliver purchase and stock updates.

* Shoppers' browsers connect to your Hibira app's WebSocket host on WooCommerce pages. Hibira receives the channel names (a hash of the store URL plus product IDs) and the visitor's IP address as part of the connection.
* Your server sends paid orders (product ID, name, link, image and time) and stock status to Hibira's HTTP API. No customer data is sent.

Terms: https://hibira.io/legal/terms — Privacy policy: https://hibira.io/legal/privacy

== Installation ==

1. Install and activate the extension.
2. Create an app at https://hibira.io and turn on Subscription count in its settings. The Free plan (100 connections) works.
3. Go to WooCommerce > Settings > Live visitors, enter the App ID, key and secret, save and press Send test event.

You can also define `HIBIRA_APP_ID`, `HIBIRA_APP_KEY` and `HIBIRA_APP_SECRET` in wp-config.php.

== Frequently Asked Questions ==

= Are the numbers real? =

Yes. The badge is the number of open tabs subscribed to the product's channel, reported by Hibira. It never adds or rounds up.

= Does it slow down checkout? =

No. Events are sent after WordPress has finished the response.

== Screenshots ==

1. A product page with four shoppers on it: the live viewer count, live stock and a "Someone just bought" popup from a paid order.
2. The store-wide count from `[hibira_viewers scope="store"]`, with the recent order popup a later visitor sees.
3. When the last item sells, open product pages switch to "Out of stock" and disable the add to cart button.
4. The viewer count on a phone.
5. WooCommerce > Settings > Live visitors.

== Changelog ==

= 0.1.2 =
* Plugin name is now "Hibira Live Visitors", so the WordPress.org slug matches the text domain `hibira-live-visitors`.
* Tested up to WordPress 7.1.

= 0.1.1 =
* Prepared for the WordPress.org plugin directory: translations now come from translate.wordpress.org.

= 0.1.0 =
* First release: product viewers, store-wide count, purchase popups and live stock.
