=== Web Server Information ===
Contributors: akshayaswaroop, wpheka
Tags: server information, phpinfo, php, mysql, database
Requires at least: 4.8
Tested up to: 7.1
Stable tag: 1.7
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://www.paypal.me/AKSHAYASWAROOP
Web Server Information plugin will give you detailed information about your hosting server's configuration and installed modules.

== Description ==
**Web Server Information** plugin allows you to check full information about the web server PHP/Mysql configurations including libraries, system type and OS version.

= Features List: =

* Display **server OS**
* Display **server software**
* Display **server IP address**
* Display **server port**
* Display **server location** detected by ip address using [IP-API.com](https://ip-api.com/docs/api:serialized_php) .See [Terms and Policies](https://ip-api.com/docs/legal).
* Display **server hostname**
* Display **server document root**
* Detailed information about the **PHP version** you are using and **installed modules**.
* Detailed information about your **Database**.
* Display **PHP, Mysql, Web server, WordPress version** info in admin footer.

If you enjoyed this plugin then please put a review, that will encourage me to bring some more …

== Installation ==

1. Upload 'wpheka-web-server-information' to the '/wp-content/plugins/' directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Admin area -> WPHEKA -> Web Server Information
4. Done!

== Frequently Asked Questions ==

= How It Works? =
*check installation*

== Screenshots ==

1. Plugin information link.
2. Plugin Overview page.
3. Plugin PHP Info page.
4. Plugin Database Info page.
5. Server info in WP Admin footer.

== Changelog ==

= 1.7 - 2026-02-12 =
* Fix - Fixed PHP 8.0+ undefined array key warning when accessing invalid tab.
* Security - Added proper output escaping for tab URLs and labels.
* Security - Added escaping for SERVER_SOFTWARE variable in admin footer.
* Enhancement - WordPress version 6.9.1 compatibility added.

= 1.6 - 2025-05-13 =
* Enhancement - WordPress version 6.8.1 compatibility added.

= 1.5 - 2024-11-30 =
* Enhancement - WordPress version 6.7.1 compatibility added.

= 1.4 - 2023-08-24 =
* Enhancement - WordPress version 6.3 compatibility added.

= 1.3 - 2022-05-25 =
* Enhancement - WordPress version 6.0 compatibility added.

= 1.2 - 2022-05-24 =
* Fix - PHP 8.0 Warning: __wakeup() must have public visibility.

= 1.1 - 2021-08-13 =
* Enhancement - WordPress version 5.8 compatibility added.

= 1.0 - 2020-07-29 =
* Initial release