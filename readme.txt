=== Web Server Information ===
Contributors: akshayaswaroop, wpheka
Tags: server information, phpinfo, php, mysql, database
Requires at least: 5.1
Tested up to: 7.1.3
Stable tag: 1.9.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
Donate link: https://www.paypal.me/AKSHAYASWAROOP
Web Server Information plugin will give you detailed information about your hosting server's configuration and installed modules.

== Description ==
**Web Server Information** plugin allows you to check full information about the web server PHP/Mysql configurations including libraries, system type and OS version.

### 🖥️ FEATURES

* **Overview at a glance:** your PHP version, database, web server and memory limit, then the server, PHP and database details that matter most for a WordPress site.
* **Server details:** OS, software, IP address, port, hostname, document root and **server location**. The location is looked up from the server's IP address with [IP-API.com](https://ip-api.com/docs/api:json). See their [Terms and Policies](https://ip-api.com/docs/legal).
* **Every PHP setting and module**, grouped by module, with a search box.
* **Every database variable**, with a search box and a Refresh button that reads the values again.
* **Server versions in the admin footer:** WordPress, PHP, web server and MySQL. Only users who can open the page see them. You can switch it off.
* **Safe to share:** the PHP tab leaves out request headers and cookies, so a screenshot posted in a support forum carries no login cookie.

If the plugin helps you, a review on WordPress.org helps other site owners find it.

== Installation ==

1. Upload 'wpheka-web-server-information' to the '/wp-content/plugins/' directory or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WPHEKA > Server Info in wp-admin.
4. Done!

== Frequently Asked Questions ==

= Where do I find it? =
In wp-admin, under WPHEKA > Server Info. Administrators can open it.

= Why does the server location say "reserved range" or "private range"? =
The server's IP address is a local or private one (for example on a test site), so IP-API.com can't place it. On a live server it shows the city and country.

= How do I hide the server versions in the admin footer? =
Open WPHEKA > Server Info, go to Settings on the Overview tab, switch off "Server details in the admin footer" and save.

= The database values look out of date. =
They are read once a week and kept until then. Open the Database tab and click Refresh to read them again.

== Screenshots ==

1. Plugin information link.
2. The Overview tab: stats, server, PHP and database details, plus the footer setting.
3. The PHP tab: every PHP setting and module, with search.
4. The Database tab: database details and every variable, with search and Refresh.
5. Server info in WP Admin footer.

== Changelog ==

= 1.9.1 - 2026-10-29 =
* Tweak - Notices from WordPress and other plugins no longer show on the Server Info page, where they printed above the header and then jumped into place. They still show on every other screen.
* Tweak - "Server and database details were read again" now shows with an icon, like the other WPHEKA plugins' messages.
* Fix - On sites in a right-to-left language the status labels in the PHP and Database lists sat against the edge of the card. They now have the same margin as the rest of the row.

= 1.9.0 - 2026-10-27 =
* Feature - A new page design, matching the other WPHEKA plugins: Overview, PHP, Database and Help tabs. The menu item is now called "Server Info".
* Feature - The Overview tab shows your PHP version, database, web server and memory limit at a glance. Below are summaries of the PHP limits and database details that matter most.
* Feature - The PHP and Database tabs have a search box.
* Feature - A Refresh button on the Database tab reads the server and database details again. Before, the database variables were read once and never updated.
* Feature - A Help tab with links to the documentation, the support forum and the changelog.
* Security - The PHP tab no longer shows PHP's Environment and PHP Variables sections or Apache's request headers. They held the request's headers and cookies. Hosts often keep passwords in environment variables too.
* Security - The server versions in the admin footer now show only to users who can open the information page. Authors, editors and other users with wp-admin access see WordPress' own footer again.
* Security - The PHP tab no longer prints your cookies, the login cookie included. A screenshot or a copy of the page shared for support is safe to post.
* Security - On a network where the plugin is network-activated, only a network admin can change the network-wide footer setting.
* Fix - The information page and its setting now use the same capability. A site that changed who can open the page through the `wpheka_plugin_panel_menu_page_capability` filter let those users read the page but not save.
* Fix - Shows "Not available" instead of "ERROR IP096T" when the server's IP address or location can't be read.
* Fix - Shows "64-bit PHP" instead of "64Bit OS". The value describes the PHP build, not the operating system.
* Fix - A failed database query no longer causes a PHP warning on the Database tab.
* Fix - The admin footer reads the cached database version instead of querying the database on every admin page.
* Developer - Removed the `wpheka_web_server_info_tabs_array` filter and the `info_page_{tab}_tab_init` actions. A tab added through the filter caused a fatal error. No plugin used them.
* Enhancement - WordPress 7.1.3 compatibility.

= 1.8.3 - 2026-10-05 =
* Fix - Closing the review request with its X now hides it for 14 days for that user. It used to come back on the next Dashboard load.
* Enhancement - WordPress 7.1.2 compatibility.

= 1.8.2 - 2026-10-01 =
* Security - The server location lookup no longer unserializes the reply from ip-api.com, which arrives over plain HTTP and could be altered in transit. It now reads the JSON endpoint instead.

= 1.8.1 - 2026-08-23 =
* Fix - The "Plugin URI" header pointed at a page that no longer exists, so "Visit plugin site" and the plugin directory listing both led to a 404. It now points at the Web Server Information product page.

= 1.8 - 2026-08-22 =
* Feature - The server information shown in the admin footer can now be switched off, from a toggle at the bottom of the Overview tab. It stays on by default.
* Feature - Asks for a review on the Dashboard once the plugin has been used, with a link that pre-selects a five-star rating. Shown only after three visits to its own screen, dismissed per user rather than for the whole site, and never shown again once dismissed.
* Security - Escaped all admin output and guarded direct reads of server variables.
* Fix - The menu and the tab labels are translatable. Two strings also used the wrong text domain, so they never reached translation tools.
* Fix - On a network, settings are saved to the same place they are read from. Activating for all sites previously wrote them per site while the plugin read a network-wide value, so the settings appeared empty.
* Fix - Removed a way the admin menu could fail outright when an incomplete framework build was present on the site.
* Fix - The Domain Path header pointed at a languages folder that did not exist. The folder and a translation template are now included.
* Enhancement - The screen now appears under the shared WPHEKA menu.
* Enhancement - Requires PHP 8.1 and WordPress 5.1. WordPress 7.1 compatibility added.

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