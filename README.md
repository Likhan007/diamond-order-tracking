# Diamond Order Tracking

A custom WordPress plugin for B2B garment production tracking with admin and client dashboards, separate from WooCommerce.

## Features

- **Admin Dashboard** - Create, edit, delete, and manage production orders
- **Client Dashboard** - View assigned orders with production progress
- **Order Timeline** - Visual progress tracking with 13 default production stages
- **Comment System** - Client-administrator communication
- **AJAX Search** - Real-time order search functionality
- **Custom Authentication** - Plugin-specific login system
- **Production Stages** - Track orders through: Fab Booking → Yarn in house → Knitting → Dyeing → Cutting → Printing → Sewing → FRI

## Installation

1. Download or clone this repository
2. Upload the `diamond-order-tracking` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress 'Plugins' menu
4. Create pages with the required shortcodes (see below)

## Shortcodes

- `[login]` - Custom login page (redirects admin to `/admin/`, client to `/client/`)
- `[admin]` - Admin production dashboard (create orders, search, manage)
- `[client]` - Client dashboard (view assigned orders only)
- `[order]` - Order detail timeline page (use `?id=X` in URL)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Database Structure

The plugin creates two custom tables on activation:

- `wp_diamond_orders` - Stores order information
- `wp_diamond_stages` - Stores production stage data per order
- `wp_options` - Stores comments (as `dot_comments_order_{ID}`)

## Default Production Stages

1. Fab Booking
2. Yarn in house
3. Knitting start
4. Knitting close
5. Dyeing start
6. Dyeing close
7. Cutting start
8. Cutting close
9. Printing start
10. Printing close
11. Sewing start
12. Sewing close
13. FRI

## User Roles

- **Admin**: Users with `manage_options` or `manage_woocommerce` capability
- **Client**: WordPress users whose email matches an order's `client_email` field

## Security Features

- Nonce verification for all AJAX requests
- Input sanitization and validation
- Capability-based access control
- Custom cookie authentication system

## File Structure

```
diamond-order-tracking/
├── diamond-order-tracking.php  (Main plugin file)
├── assets/
│   ├── style.css               (All plugin styling)
│   └── js/
│       ├── dot-admin.js        (Admin stage saving + toasts)
│       └── dot-search.js       (Search + comments functionality)
└── README.md                   (This file)
```

## Changelog

### Version 2.4
- Fixed comment system with instant updates
- Improved UI/UX with better card styling
- Fixed caching issues for admin page
- Added username display for clients (instead of emails)
- Enhanced search functionality
- Added comment deletion for admins
- Improved browser history handling
- Better error handling and debugging

## Support

For issues, questions, or contributions, please open an issue on GitHub.

## License

This plugin is licensed under the GPL v2 or later.

```
Copyright (C) 2025 Md. Iftekhar Rahman Likhan

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Author

**Md. Iftekhar Rahman Likhan**

- Email: iftekharlikhan@gmail.com
- GitHub: [@Likhan007](https://github.com/Likhan007)
- Website: [likhan.me](https://likhan.me)

## Copyright

© 2025 Md. Iftekhar Rahman Likhan. All rights reserved.

---

**Diamond Order Tracking** - Production tracking made simple.

