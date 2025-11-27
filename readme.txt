=== Diamond Order Tracking ===
Contributors: Likhan007
Tags: order tracking, production, b2b, timeline, manufacturing, garment
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Custom B2B garment production tracking system with admin and client dashboards, separate from WooCommerce.

== Description ==

Diamond Order Tracking is a comprehensive WordPress plugin designed for B2B garment production tracking. It provides a complete order management system with visual timelines, production stage tracking, and client communication features.

**Key Features:**

* Admin dashboard to create, edit, and delete production orders
* Client dashboard showing only assigned orders
* Visual production timeline with progress bar
* 13 default production stages (customizable)
* Real-time AJAX search functionality
* Comment system for client-administrator communication
* Custom authentication system
* Secure role-based access control

**Perfect for:**
* Garment manufacturers
* Production companies
* B2B businesses needing order tracking
* Companies managing multiple production stages

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/diamond-order-tracking/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create pages with the following shortcodes:
   * Create a page with `[login]` shortcode (e.g., `/login/`)
   * Create a page with `[admin]` shortcode (e.g., `/admin/`)
   * Create a page with `[client]` shortcode (e.g., `/client/`)
   * Create a page with `[order]` shortcode (e.g., `/order/`)
4. Set up user accounts:
   * Admins need `manage_options` or `manage_woocommerce` capability
   * Clients are regular WordPress users whose email matches order assignments

== Frequently Asked Questions ==

= What are the default production stages? =

The plugin includes 13 default stages: Fab Booking, Yarn in house, Knitting start, Knitting close, Dyeing start, Dyeing close, Cutting start, Cutting close, Printing start, Printing close, Sewing start, Sewing close, and FRI.

= Can I customize the production stages? =

Yes, you can modify the `dot_default_stages()` function in the main plugin file to customize stages for your workflow.

= Where are comments stored? =

Comments are stored in the WordPress `wp_options` table as serialized arrays, avoiding the need for additional database tables.

= Does this work with WooCommerce? =

No, this is a completely separate system from WooCommerce, designed specifically for B2B production tracking.

= Can clients see other clients' orders? =

No, clients can only see orders where their email address is listed in the `client_email` field.

== Screenshots ==

1. Admin dashboard with order creation form
2. Order timeline with production stages
3. Client dashboard showing assigned orders
4. Comment system for client communication

== Changelog ==

= 2.4 =
* Fixed comment system with instant updates
* Improved UI/UX with better card styling and visibility
* Fixed caching issues for admin page refresh
* Added username display for clients (instead of emails)
* Enhanced search functionality (clients can only search by order code)
* Added comment deletion capability for admins
* Improved browser history handling
* Better error handling and debugging
* Fixed form resubmission warnings

= 2.3 =
* Initial stable release

== Upgrade Notice ==

= 2.4 =
This version includes important bug fixes and UI improvements. Update recommended.

== Author ==

**Md. Iftekhar Rahman Likhan**

* Email: iftekharlikhan@gmail.com
* GitHub: https://github.com/Likhan007
* Website: https://likhan.me

== Copyright ==

Copyright (C) 2025 Md. Iftekhar Rahman Likhan

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

