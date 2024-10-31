=== NAPPS - Mobile app builder ===
Contributors: nappssolutions
Tags: mobile, android, ios, woocoomerce app builder, native app
Requires at least: 4.7
Tested up to: 6.6
Stable tag: 1.0.27
Requires PHP: 7.4
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
 
Create your app with NAPPS. We are a mobile app builder for e-commerce, download our plugin and start your free trial.

== Description ==

NAPPS main focus is to simplify the creation of mobile apps. Our platform allows Brands to build a fully customizable mobile app.

https://www.youtube.com/watch?v=NSnt5kBW4CE


Create your own app with NAPPS by simply following the steps below:

* Create your account on napps.io
* Connect your website and app with the Napps plugin for wordpress
* Personalize your app appearance
* Download ready-to-publish app builds and submit them on Google Play and Apple App Store

## WHAT DOES THE NAPPS PLUGIN DO?

This plugin is a complement to the napps e-commerce solution.  Essentially it connects your WordPress website with your mobile app.

**Features**

* **iOS & Android native apps**
Build your native app for your brand without writing a single line of code

* **Push Notifications**
Our dashboard gives you the opportunity to send the notifications you want without any additional cost. 
Engage with your user with the most efficient tool.

* **Real Time Sync & Instantly Updates**
You can sync with your store in minutes. All of your products, collections, and much more will be ready to use in your app as soon as the sync finish.
Even better, is when you are already using our system, all the changes you made to your store, will be updated to the app instantly.

* **One Page Checkout & Wishlist**
These are two pages that will have a major impact on your app. 
The one-page checkout will make your customers buy impulsively, it is just too easy to finish purchases. 
The Wishlist will be the desired list of products that your customers want, will give them reminders to buy those. 

* **Multi-Search Options**
The search action is too easy with our multi-search option. Searching by categories, tags, products or colors will allow your customers to find easily want they are looking for.

**All of these combined with all other features**

* A lot of blocks to design your app
* Your branding all over, you can even adjust to special seasons or campaigns
* Automations that will give you time to plan better your next steps

**THE BEST PART IS OUR FREE TRIAL WITHOUT CREDIT CARD NEEDED**

**For more details, visit us *[napps.io](https://www.napps.io)***

== Installation ==

= Minimum Requirements =

* PHP 7.4 or greater
* Wordpress 4.7.0 or greater
* WooCommerce 3.5.0 or greater

= Automatic installation =

= Manual installation =

= Updating =

== Changelog ==

= 1.0.27 =

* Fix - Tags

= 1.0.26 =

* New - Support new wc version

= 1.0.25 =

* New - Account data delete request page.

= 1.0.24 =

* Fix - Webhooks collections sometimes not working

= 1.0.23 =

* Fix - Fix shipping rate cart subtotal
* New - New Smartbanner route

= 1.0.22 =

* New - Products force deleted were not triggering webhooks

= 1.0.21 =

* New - Shipping rate cart, shipping day

= 1.0.20 =

* Fix - Linked variations payload

= 1.0.19 =

* New - Integration with brand plugin
* New - Integration with linked variations
* Tweak - Improve collections webhook

= 1.0.18 =

* Fix - Webhook payload

= 1.0.17 =

* Fix - Attribute stock module not dispatching webhook for a product update

= 1.0.16 =

* Fix - Smartbanner position z-index

= 1.0.15 =

* Fix - Smartbanner rockloader (cloudflare) support

= 1.0.14 =

* Fix - Disable/Enable webhooks only on napps plugin

= 1.0.13 =

* Fix - Fix checkout cart rounding
* New - Integration with attribute stock

= 1.0.12 =

* Fix - Fix webcheckout not comparing total price with tax

= 1.0.11 =

* Fix - Fix regression on exclusive coupons

= 1.0.10 =

* Fix - Typo in plugin name

= 1.0.9 =

* Fix - Improve frontend when multiple shipping options are available (flat rate cart)
* New - Ability to create a cart and open that session on the checkout page
* Tweak - Use woocommerce authentication for smartbanner route

= 1.0.8 =

* Fix - Shipping rate based on cart items using wrong wp_notice type
* New - Translations in english and portuguese
* Fix - Qtranslate order webhook data with untraslated data

= 1.0.7 =

* Tweak - Improve qtranslate integration 
* Woocommerce - New shipping rate based on cart items

= 1.0.6 =

* Fix - Wrong Smartbanner position on some themes
* Fix - Ignore multi translations on webhooks

= 1.0.5 =

* Fix - Order payment auth cookie

= 1.0.4 =

* New - Order detail redirect page

= 1.0.3 =

* Fix - Order redirect page

= 1.0.2 =

* New - Exclusive coupons for mobile app
* New - Show if order was created from a mobile app on the order admin panel
* Fix - Issue with smartbanner printing a console error when shop had no mobile application info (published applications)
* New - Initial mobile integration with some plugins
* Fix - Not sending correct payload when action was run by wp-cron
* New - Toogle webhooks when plugin is desactivated / activated

= 1.0.1 =

* Fix - Minimum supported version for woocommerce
* Tweak - Added php minimum version supported checks on plugin activation hook
* Fix - Fix issue on options webhook payload using a function only available on php 8.0
