<?php
/**
 * Plugin Name: Diamond Order Tracking
 * Plugin URI: https://github.com/Likhan007/diamond-order-tracking
 * Description: Order timeline & production tracking — admin and client dashboards. Shortcodes: [login], [admin], [client], [order]
 * Version: 2.4
 * Author: Md. Iftekhar Rahman Likhan
 * Author URI: https://github.com/Likhan007
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: diamond-order-tracking
 * 
 * Copyright (C) 2025 Md. Iftekhar Rahman Likhan
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

if ( ! defined('ABSPATH') ) exit;

/* -----------------------------------------
   Default production stages
   ----------------------------------------- */
function dot_default_stages() {
    return array(
        'Fab Booking','Yarn in house','Knitting start','Knitting close',
        'Dyeing start','Dyeing close','Cutting start','Cutting close',
        'Printing start','Printing close','Sewing start','Sewing close','FRI'
    );
}

/* -----------------------------------------
   Activation: create tables (orders & stages)
   Comments stored in options to avoid schema changes
   ----------------------------------------- */
register_activation_hook(__FILE__, 'dot_install_tables');
function dot_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $orders_table = $wpdb->prefix . 'diamond_orders';
    $stages_table = $wpdb->prefix . 'diamond_stages';
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $sql1 = "CREATE TABLE IF NOT EXISTS {$orders_table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      order_code VARCHAR(120) NOT NULL,
      client_email VARCHAR(191) NOT NULL,
      style_name VARCHAR(191) DEFAULT '',
      quantity INT DEFAULT 0,
      notes LONGTEXT DEFAULT '',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY order_code_unique (order_code)
    ) {$charset_collate};";

    $sql2 = "CREATE TABLE IF NOT EXISTS {$stages_table} (
      id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      order_id BIGINT(20) UNSIGNED NOT NULL,
      stage_name VARCHAR(120) NOT NULL,
      status VARCHAR(30) NOT NULL DEFAULT 'pending',
      stage_date DATE DEFAULT NULL,
      remarks TEXT DEFAULT '',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY order_idx (order_id)
    ) {$charset_collate};";

    dbDelta($sql1);
    dbDelta($sql2);
}

/* -----------------------------------------
   Simple helpers
   ----------------------------------------- */
function dot_normalize_emails( $raw ) {
    $parts = preg_split('/,+/', strtolower( (string) $raw ) );
    $clean = array();
    foreach ( $parts as $p ) {
        $e = trim( $p );
        if ( $e === '' ) continue;
        if ( filter_var( $e, FILTER_VALIDATE_EMAIL ) ) $clean[] = $e;
    }
    $clean = array_values( array_unique( $clean ) );
    return implode( ',', $clean );
}

/* -----------------------------------------
   Admin user check (WP capabilities)
   ----------------------------------------- */
function dot_is_admin_user( $user_id = null ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            $plugin_user = dot_current_plugin_user();
            $user_id = $plugin_user ? $plugin_user->ID : 0;
        }
    }

    if ( ! $user_id ) return false;

    return user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'manage_woocommerce' );
}

/* -----------------------------------------
   Plugin cookie helpers (optional lightweight auth)
   stored to support plugin-only login without WP session
   ----------------------------------------- */
if ( ! defined('DOT_AUTH_COOKIE') ) define('DOT_AUTH_COOKIE', 'dot_auth');

function dot_set_plugin_cookie( $user_id, $remember = false ) {
    $expiry = time() + ( $remember ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS );
    $secret = defined('AUTH_KEY') ? AUTH_KEY : wp_hash('dot_secret');
    $data = $user_id . '|' . $expiry;
    $sig = hash_hmac('sha256', $data, $secret);
    $val = base64_encode( $data . '|' . $sig );
    $path = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    setcookie(DOT_AUTH_COOKIE, $val, $expiry, $path, $domain, is_ssl(), true);
    $_COOKIE[DOT_AUTH_COOKIE] = $val;
}

function dot_clear_plugin_cookie() {
    $path = defined('COOKIEPATH') ? COOKIEPATH : '/';
    $domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
    setcookie(DOT_AUTH_COOKIE, '', time() - 3600, $path, $domain, is_ssl(), true);
    unset($_COOKIE[DOT_AUTH_COOKIE]);
}

function dot_get_plugin_user_id() {
    if ( empty( $_COOKIE[DOT_AUTH_COOKIE] ) ) return 0;
    $val = $_COOKIE[DOT_AUTH_COOKIE];
    $raw = @base64_decode( $val );
    if ( ! $raw ) return 0;
    $parts = explode('|', $raw);
    if ( count($parts) !== 3 ) return 0;
    $user_id = intval( $parts[0] );
    $expiry = intval( $parts[1] );
    $sig = $parts[2];
    if ( $expiry < time() ) {
        dot_clear_plugin_cookie();
        return 0;
    }
    $secret = defined('AUTH_KEY') ? AUTH_KEY : wp_hash('dot_secret');
    $expected = hash_hmac('sha256', $user_id . '|' . $expiry, $secret);
    if ( ! hash_equals( $expected, $sig ) ) {
        dot_clear_plugin_cookie();
        return 0;
    }
    return $user_id;
}

function dot_current_plugin_user() {
    $uid = dot_get_plugin_user_id();
    if ( ! $uid ) return null;
    $u = get_userdata( $uid );
    return $u ?: null;
}

/* -----------------------------------------
   Logout handler (clears plugin cookie + WP logout)
   ----------------------------------------- */
add_action('init', function(){
    if ( isset($_GET['dot_logout']) ) {
        dot_clear_plugin_cookie();
        if ( is_user_logged_in() ) wp_logout();
        wp_safe_redirect( site_url('/login/') );
        exit;
    }
});

/* -----------------------------------------
   ENQUEUE CSS/JS (only when needed)
   ----------------------------------------- */
add_action('wp_enqueue_scripts', 'dot_enqueue_assets');
function dot_enqueue_assets() {
    if ( ! is_page() && ! isset($_GET['id']) ) return;

    $css = plugin_dir_path(__FILE__) . 'assets/style.css';
    if ( file_exists( $css ) ) {
        $css_version = @filemtime( $css ) ?: '2.4';
        wp_enqueue_style('dot_style', plugin_dir_url(__FILE__) . 'assets/style.css', array(), $css_version);
    }

    $current_portal_user = is_user_logged_in() ? wp_get_current_user() : dot_current_plugin_user();
    $client_username = '';
    if ( $current_portal_user && ! dot_is_admin_user( $current_portal_user->ID ) ) {
        $client_username = $current_portal_user->user_login ?: $current_portal_user->display_name ?: $current_portal_user->user_email;
    }

    // dot-admin (used on order page)
    $admin_js_path = plugin_dir_path(__FILE__) . 'assets/js/dot-admin.js';
    $admin_js_version = file_exists($admin_js_path) ? @filemtime($admin_js_path) : '1.0';
    wp_register_script('dot_admin_js', plugin_dir_url(__FILE__) . 'assets/js/dot-admin.js', array('jquery'), $admin_js_version, true);
    wp_localize_script('dot_admin_js', 'dot_ajax', array('url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('dot_bulk_save')));
    if ( isset($_GET['id']) ) {
        wp_enqueue_script('dot_admin_js');
    }

    // search + comments
    $search_js = plugin_dir_path(__FILE__) . 'assets/js/dot-search.js';
    if ( file_exists( $search_js ) ) {
        $search_js_version = @filemtime( $search_js ) ?: '1.0';
        wp_enqueue_script('dot_search_js', plugin_dir_url(__FILE__) . 'assets/js/dot-search.js', array('jquery'), $search_js_version, true);
        wp_localize_script('dot_search_js', 'dot_search_ajax', array(
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dot_bulk_save'),
            'is_admin' => dot_is_admin_user() ? 1 : 0,
            'client_name' => $client_username
        ));
        wp_add_inline_script('dot_search_js', 'window.addEventListener("pageshow",function(e){if(e.persisted){window.location.reload();}});');
    }
}

/* -----------------------------------------
   Prevent caching on shortcode-driven pages
   ----------------------------------------- */
add_action('template_redirect', 'dot_prevent_cache_for_pages');
function dot_prevent_cache_for_pages() {
    if ( is_admin() || wp_doing_ajax() ) return;
    if ( ! is_page() ) return;

    $post = get_post();
    if ( ! $post ) return;

    $shortcodes = array('login','admin','client','order');
    foreach ( $shortcodes as $shortcode ) {
        if ( has_shortcode( $post->post_content, $shortcode ) ) {
            if ( ! defined('DONOTCACHEPAGE') ) define('DONOTCACHEPAGE', true);
            if ( ! headers_sent() ) {
                nocache_headers();
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
            break;
        }
    }
}

/* -----------------------------------------
   AJAX: update stages in bulk (admin)
   ----------------------------------------- */
add_action('wp_ajax_dot_update_stage_bulk', 'dot_update_stage_bulk');
function dot_update_stage_bulk() {
    check_ajax_referer('dot_bulk_save', 'nonce');

    if (! dot_is_admin_user() ) {
        wp_send_json_error("No permission");
    }

    global $wpdb;
    $stages_table = $wpdb->prefix . 'diamond_stages';
    $orders_table = $wpdb->prefix . 'diamond_orders';

    $updated_order_ids = array();
    $updated_count = 0;

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'stage_status_') === 0) {
            $id = intval(str_replace('stage_status_', '', $key));
            if ($id <= 0) continue;

            $order_id = $wpdb->get_var($wpdb->prepare("SELECT order_id FROM {$stages_table} WHERE id=%d", $id));
            if ( ! $order_id ) continue;

            $status = sanitize_text_field($value);
            $date = isset($_POST['stage_date_' . $id]) ? sanitize_text_field($_POST['stage_date_' . $id]) : null;
            $remarks = isset($_POST['stage_remarks_' . $id]) ? sanitize_textarea_field($_POST['stage_remarks_' . $id]) : '';

            $res = $wpdb->update(
                $stages_table,
                array('status' => $status, 'stage_date' => $date ?: null, 'remarks' => $remarks),
                array('id' => $id),
                array('%s','%s','%s'),
                array('%d')
            );

            if ( $res !== false ) {
                $updated_count++;
                $updated_order_ids[intval($order_id)] = intval($order_id);
            }
        }
    }

    if ( ! empty( $updated_order_ids ) ) {
        foreach ( $updated_order_ids as $oid ) {
            $wpdb->query( $wpdb->prepare( "UPDATE {$orders_table} SET updated_at = NOW() WHERE id = %d", $oid ) );
        }
    }

    wp_send_json_success( array( 'updated' => $updated_count, 'orders' => array_values($updated_order_ids) ) );
}

/* -----------------------------------------
   AJAX: search orders (admin/client)
   ----------------------------------------- */
add_action('wp_ajax_dot_search_orders', 'dot_search_orders');
add_action('wp_ajax_nopriv_dot_search_orders', 'dot_search_orders');
function dot_search_orders() {
    global $wpdb;
    check_ajax_referer('dot_bulk_save', 'nonce');

    $q = trim( sanitize_text_field( $_POST['q'] ?? '' ) );
    if ( $q === '' ) wp_send_json_success( array('results'=>array()) );

    $like = '%' . $wpdb->esc_like( $q ) . '%';
    $orders_table = $wpdb->prefix . 'diamond_orders';

    if ( dot_is_admin_user() ) {
        $sql = $wpdb->prepare(
            "SELECT id, order_code, style_name, client_email, quantity, updated_at FROM {$orders_table} WHERE (order_code LIKE %s OR client_email LIKE %s) ORDER BY updated_at DESC LIMIT %d",
            $like, $like, 50
        );
        $rows = $wpdb->get_results( $sql );
    } else {
        $user = is_user_logged_in() ? wp_get_current_user() : dot_current_plugin_user();
        if ( ! $user ) wp_send_json_error('Not allowed');
        $user_email = strtolower( trim( $user->user_email ) );
        $sql = $wpdb->prepare(
            "SELECT id, order_code, style_name, client_email, quantity, updated_at FROM {$orders_table} WHERE order_code LIKE %s AND CONCAT(',', REPLACE(LOWER(client_email),' ',''), ',') LIKE %s ORDER BY updated_at DESC LIMIT %d",
            $like, '%' . $wpdb->esc_like( $user_email ) . '%', 50
        );
        $rows = $wpdb->get_results( $sql );
    }

    $is_admin = dot_is_admin_user();
    $out = array();
    foreach ( $rows as $r ) {
        $out[] = array(
            'id' => intval($r->id),
            'order_code' => $r->order_code,
            'style_name' => $r->style_name,
            'client_email' => $is_admin ? $r->client_email : '', // Only return email for admins
            'quantity' => intval($r->quantity),
            'updated_at' => $r->updated_at
        );
    }

    wp_send_json_success( array('results' => $out) );
}

/* -----------------------------------------
   COMMENTS (stored in wp_options per order)
   dot_add_comment, dot_get_comments
   ----------------------------------------- */
add_action('wp_ajax_dot_add_comment','dot_add_comment');
add_action('wp_ajax_nopriv_dot_add_comment','dot_add_comment');
function dot_add_comment() {
    check_ajax_referer('dot_bulk_save','nonce');

    $order_id = intval( $_POST['order_id'] ?? 0 );
    $comment = sanitize_textarea_field( $_POST['comment'] ?? '' );
    if ( ! $order_id || $comment === '' ) wp_send_json_error('Missing data');

    $user = is_user_logged_in() ? wp_get_current_user() : dot_current_plugin_user();
    $user_email = $user ? $user->user_email : sanitize_email( $_POST['user_email'] ?? '' );
    if ( ! $user_email || ! filter_var( $user_email, FILTER_VALIDATE_EMAIL ) ) wp_send_json_error('Invalid user');
    $user_name = '';
    if ( $user ) {
        $user_name = $user->user_login ?: $user->display_name;
    } else {
        $maybe_user = get_user_by('email', $user_email);
        if ( $maybe_user ) {
            $user_name = $maybe_user->user_login ?: $maybe_user->display_name;
        } else {
            $user_name = sanitize_text_field( $_POST['user_name'] ?? '' );
        }
    }

    $opt_key = 'dot_comments_order_' . intval($order_id);
    $comments = get_option( $opt_key, array() );
    if ( ! is_array( $comments ) ) $comments = array();

    $new = array(
        'user_email' => $user_email,
        'user_name' => $user_name,
        'comment' => $comment,
        'created_at' => current_time('mysql')
    );
    array_unshift( $comments, $new );
    if ( count($comments) > 200 ) $comments = array_slice($comments, 0, 200);
    update_option( $opt_key, $comments );

    $html = dot_render_comments_html_option( $order_id, dot_is_admin_user() );
    wp_send_json_success( array('html' => $html) );
}

add_action('wp_ajax_dot_get_comments','dot_get_comments');
add_action('wp_ajax_nopriv_dot_get_comments','dot_get_comments');
function dot_get_comments() {
    check_ajax_referer('dot_bulk_save','nonce');
    $order_id = intval( $_POST['order_id'] ?? 0 );
    if ( ! $order_id ) wp_send_json_error('Missing order');
    $html = dot_render_comments_html_option( $order_id, dot_is_admin_user() );
    wp_send_json_success( array('html' => $html) );
}

add_action('wp_ajax_dot_delete_comment','dot_delete_comment');
add_action('wp_ajax_nopriv_dot_delete_comment','dot_delete_comment');
function dot_delete_comment() {
    check_ajax_referer('dot_bulk_save','nonce');
    if ( ! dot_is_admin_user() ) wp_send_json_error('No permission');
    $order_id = intval( $_POST['order_id'] ?? 0 );
    $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
    if ( ! $order_id || $index < 0 ) wp_send_json_error('Invalid data');

    $opt_key = 'dot_comments_order_' . intval($order_id);
    $comments = get_option( $opt_key, array() );
    if ( ! is_array( $comments ) || ! isset( $comments[$index] ) ) wp_send_json_error('Comment not found');

    array_splice( $comments, $index, 1 );
    update_option( $opt_key, $comments );

    $html = dot_render_comments_html_option( $order_id, true );
    wp_send_json_success( array( 'html' => $html ) );
}

function dot_render_comments_html_option( $order_id, $show_delete = false ) {
    $opt_key = 'dot_comments_order_' . intval($order_id);
    $rows = get_option( $opt_key, array() );
    ob_start();
    ?>
    <div class="dot-comments-wrap">
      <?php if ( empty($rows) ): ?>
        <div class="dot-no-comments">No comments yet.</div>
      <?php else: foreach ( $rows as $idx => $c ): ?>
        <div class="dot-comment" data-comment-index="<?php echo intval( $idx ); ?>">
          <div class="dot-comment-meta">
            <div class="dot-comment-author">
              <strong>
                <?php
                  $label = '';
                  if ( ! empty( $c['user_name'] ) ) {
                      $label = $c['user_name'];
                  } elseif ( ! empty( $c['user_email'] ) ) {
                      $maybe_user = get_user_by('email', $c['user_email']);
                      $label = $maybe_user ? ($maybe_user->user_login ?: $maybe_user->display_name ?: $c['user_email']) : $c['user_email'];
                  }
                  echo esc_html( $label );
                ?>
              </strong>
              <span class="dot-comment-time"><?php echo esc_html( date_i18n( 'j M Y, H:i', strtotime($c['created_at']) ) ); ?></span>
            </div>
            <?php if ( $show_delete ): ?>
              <button class="dot-comment-delete" data-index="<?php echo intval( $idx ); ?>" aria-label="Delete comment">
                <i class="fa-solid fa-trash"></i>
              </button>
            <?php endif; ?>
          </div>
          <div class="dot-comment-body"><?php echo nl2br( esc_html( $c['comment'] ) ); ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/* -----------------------------------------
   [login] shortcode
   ----------------------------------------- */
add_shortcode('login', function(){
    // if logged in via WP or plugin cookie, redirect
    $plugin_user = dot_current_plugin_user();
    if ( is_user_logged_in() || $plugin_user ) {
        $u = is_user_logged_in() ? wp_get_current_user() : $plugin_user;
        if ( user_can( $u->ID, 'manage_options' ) || user_can( $u->ID, 'manage_woocommerce' ) ) {
            wp_safe_redirect( site_url('/admin/') );
            exit;
        } else {
            wp_safe_redirect( site_url('/client/') );
            exit;
        }
    }

    $msg = '';
    if ( isset($_POST['dot_login_src']) && $_POST['dot_login_src'] === 'dot' ) {
        $login_input = sanitize_text_field( $_POST['dot_user'] ?? '' );
        $password = $_POST['dot_pass'] ?? '';
        $remember = ! empty( $_POST['dot_remember'] );

        if ( empty( $login_input ) || empty( $password ) ) {
            $msg = '<div style="color:#b00;margin-bottom:10px">Provide username/email and password.</div>';
        } else {
            $user = null;
            if ( is_email( $login_input ) ) $user = get_user_by('email', $login_input);
            if ( ! $user ) $user = get_user_by('login', $login_input);

            if ( $user && wp_check_password( $password, $user->user_pass, $user->ID ) ) {
                dot_set_plugin_cookie( $user->ID, $remember );
                if ( user_can( $user->ID, 'manage_options' ) || user_can( $user->ID, 'manage_woocommerce' ) ) {
                    wp_safe_redirect( site_url('/admin/') ); exit;
                } else {
                    wp_safe_redirect( site_url('/client/') ); exit;
                }
            } else {
                $msg = '<div style="color:#b00;margin-bottom:10px">Invalid credentials.</div>';
            }
        }
    }

    ob_start();
    ?>
    <div class="dot-login-wrap" style="min-height:70vh;display:flex;align-items:center;justify-content:center;padding:48px 16px;">
      <div class="dot-card" style="max-width:420px;width:100%;text-align:center;">
        <h2 style="margin:0 0 6px;color:#222;font-size:22px">Production Login</h2>
        <p style="margin:0 0 12px;color:#666">Sign in to manage or track orders</p>
        <?php echo $msg; ?>
        <form method="post" style="display:flex;flex-direction:column;gap:10px;text-align:left;">
          <label style="font-weight:700;font-size:13px">Username or Email</label>
          <input type="text" name="dot_user" required style="padding:10px;border:1px solid #e7e7e7;border-radius:8px">
          <label style="font-weight:700;font-size:13px">Password</label>
          <input type="password" name="dot_pass" required style="padding:10px;border:1px solid #e7e7e7;border-radius:8px">
          <label style="font-size:13px"><input type="checkbox" name="dot_remember"> Remember me</label>

          <input type="hidden" name="dot_login_src" value="dot">
          <div style="display:flex;justify-content:center;margin-top:6px;">
            <button class="btn btn-primary" type="submit" style="background:#0077b6;color:#fff;border-radius:6px;padding:8px 18px;border:none;font-size:15px;line-height:1.1;">Sign in</button>
          </div>
        </form>
        <div style="margin-top:10px"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" style="color:#0077b6">Forgot password?</a></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/* -----------------------------------------
   [admin] shortcode
   ----------------------------------------- */
add_shortcode('admin', function(){
    nocache_headers();
    // Allow WP users or plugin-cookie users with admin capability
    $u = is_user_logged_in() ? wp_get_current_user() : dot_current_plugin_user();
    if ( ! $u ) return '<div style="padding:20px;text-align:center">Please <a href="'.esc_url(site_url('/login/')).'">login</a>.</div>';
    if ( ! ( user_can($u->ID,'manage_options') || user_can($u->ID,'manage_woocommerce') ) ) return '<div style="padding:20px;text-align:center;color:#b00">Access denied.</div>';

    global $wpdb;
    $orders_table = $wpdb->prefix . 'diamond_orders';
    $stages_table = $wpdb->prefix . 'diamond_stages';

    // CREATE
    if ( isset($_POST['dot_create_nonce']) && wp_verify_nonce($_POST['dot_create_nonce'], 'dot_create') ) {
        $order_code = sanitize_text_field( $_POST['order_code'] ?: 'DA-' . time() );
        $exists = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE order_code=%s", $order_code) );
        if ( $exists > 0 ) {
            echo '<div style="background:#fff3f3;color:#b00;padding:10px;border-radius:6px;margin-bottom:8px;">Order code already exists.</div>';
        } else {
            $client_email = dot_normalize_emails( $_POST['client_email'] ?? '' );
            $style_name = sanitize_text_field( $_POST['style_name'] ?? '' );
            $quantity = intval( $_POST['quantity'] ?? 0 );
            $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );
            $wpdb->insert( $orders_table, array(
                'order_code'=>$order_code,'client_email'=>$client_email,'style_name'=>$style_name,'quantity'=>$quantity,'notes'=>$notes
            ), array('%s','%s','%s','%d','%s') );
            $new_id = $wpdb->insert_id;
            foreach ( dot_default_stages() as $s ) {
                $wpdb->insert( $stages_table, array('order_id'=>$new_id,'stage_name'=>$s,'status'=>'pending','stage_date'=>null,'remarks'=>''), array('%d','%s','%s','%s','%s') );
            }
            echo '<div style="background:#e6f8e6;color:#0a0;padding:10px;border-radius:6px;margin-bottom:8px;">Order created.</div>';
        }
    }

    // DELETE
    if ( isset($_POST['dot_delete']) && wp_verify_nonce($_POST['dot_delete_nonce'],'dot_delete') ) {
        $del = intval( $_POST['dot_delete'] );
        $wpdb->delete( $stages_table, array('order_id'=>$del), array('%d') );
        $wpdb->delete( $orders_table, array('id'=>$del), array('%d') );
        echo '<div style="background:#fff5f5;color:#b00;padding:10px;border-radius:6px;margin-bottom:8px;">Order deleted.</div>';
    }

    // READ orders
    $orders = $wpdb->get_results( "SELECT * FROM {$orders_table} ORDER BY updated_at DESC, created_at DESC LIMIT 500" );

    ob_start();
    ?>
    <div class="dot-admin-wrap">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h2 style="margin:0;color:#222">Production Admin</h2>
        <a href="<?php echo esc_url( add_query_arg('dot_logout','1', site_url('/admin/') ) ); ?>" style="color:#0077b6">Logout</a>
      </div>

      <div class="dot-card dot-create-card">
        <form method="post" class="dot-create-form" novalidate>
          <!-- row 1: three equal columns -->
          <div class="dot-create-grid">
            <div class="dot-field">
              <label class="dot-label">Order Code</label>
              <input name="order_code" class="dot-input dot-input--full" placeholder="e.g. POU22559">
            </div>

            <div class="dot-field">
              <label class="dot-label">Client Emails</label>
              <input name="client_email" class="dot-input dot-input--full" placeholder="a@x.com, b@y.com">
            </div>

            <div class="dot-field">
              <label class="dot-label">Style</label>
              <input name="style_name" class="dot-input dot-input--full" placeholder="Denim Jacket">
            </div>
          </div>

          <!-- row 2: qty | notes | action -->
          <div class="dot-create-grid-row2">
            <div class="dot-field dot-qty-field">
              <label class="dot-label">Qty</label>
              <input name="quantity" type="number" class="dot-input dot-input--qty" placeholder="0" min="0" step="1">
            </div>

            <div class="dot-field dot-notes-field">
              <label class="dot-label">Notes</label>
              <input name="notes" class="dot-input dot-input--full" placeholder="Internal notes">
            </div>

            <div class="dot-field dot-action-field">
              <input type="hidden" name="dot_create_nonce" value="<?php echo esc_attr( wp_create_nonce('dot_create') ); ?>">
              <div class="dot-action-wrapper">
                <button class="btn btn-primary dot-create-btn" type="submit" aria-label="Create Order">Create Order</button>
              </div>
            </div>
          </div>
        </form>
      </div>


        <div class="dot-search-wrap" style="margin-bottom:12px;">
          <input id="dot-search-input" class="dot-input" placeholder="Search order code or client email..." autocomplete="off" />
          <i class="fa-solid fa-magnifying-glass dot-search-icon" aria-hidden="true"></i>
          <div id="dot-search-results" class="dot-search-results"></div>
        </div>


      <div class="dot-table-card" style="margin-top:14px;">
        <table class="dot-orders-table">
          <thead>
            <tr>
              <th style="width:160px">Order</th>
              <th>Client(s)</th>
              <th style="width:200px">Style/Qty</th>
              <th style="width:160px">Updated</th>
              <th style="width:160px">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ( empty($orders) ): ?>
              <tr><td colspan="5" style="text-align:center;padding:18px;color:#666">No orders yet.</td></tr>
            <?php else: foreach ( $orders as $r ): ?>
              <tr>
                <td>
                  <div class="dot-order-code"><b>Code: </b><?php echo esc_html($r->order_code); ?></div>
                </td>

                <td class="dot-client"><?php echo esc_html( implode(', ', array_map('trim', explode(',', $r->client_email))) ); ?></td>

                <td>
                  <span class="dot-style"><?php echo esc_html($r->style_name); ?></span>
                  <span class="dot-qty"><?php echo intval($r->quantity); ?> pcs</span>
                </td>

                <td class="dot-updated"><?php echo esc_html($r->updated_at); ?></td>

                <td class="dot-actions-cell">
                  <div class="dot-actions-row">
                    <a class="dot-btn dot-btn-edit" href="<?php echo esc_url(site_url('/order/?id=' . intval($r->id))); ?>">Edit</a>

                    <form method="post" class="dot-delete-form" onsubmit="return confirm('Delete this order?');" style="display:inline-block;margin:0;padding:0;">
                      <input type="hidden" name="dot_delete" value="<?php echo intval($r->id); ?>">
                      <input type="hidden" name="dot_delete_nonce" value="<?php echo esc_attr( wp_create_nonce('dot_delete') ); ?>">
                      <button type="submit" class="dot-btn dot-btn-delete">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php
    return ob_get_clean();
});

/* -----------------------------------------
   [client] shortcode
   ----------------------------------------- */
add_shortcode('client', function(){
    $u = is_user_logged_in() ? wp_get_current_user() : dot_current_plugin_user();
    if ( ! $u ) return '<div style="padding:20px;text-align:center">Please <a href="'.esc_url(site_url('/login/')).'">login</a>.</div>';
    global $wpdb;
    $like = '%,' . $wpdb->esc_like( strtolower( $u->user_email ) ) . ',%';
    $orders = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}diamond_orders WHERE CONCAT(',', REPLACE(LOWER(client_email),' ',''), ',') LIKE %s ORDER BY created_at DESC", $like) );

    ob_start(); ?>
    <div style="max-width:900px;margin:40px auto;font-family:inherit;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h2 style="margin:0;color:#222">My Orders</h2>
        <a href="?dot_logout=1" style="color:#0077b6;text-decoration:none;font-weight:600;">Logout</a>
      </div>
      <div style="color:#555;margin-bottom:16px;font-size:13px;">Signed in as <strong><?php echo esc_html( $u->user_login ?: $u->display_name ?: $u->user_email ); ?></strong></div>

        <div class="dot-search-wrap" style="margin-bottom:12px;">
          <input id="dot-search-input" class="dot-input" placeholder="Search order code or client email..." autocomplete="off" />
          <i class="fa-solid fa-magnifying-glass dot-search-icon" aria-hidden="true"></i>
          <div id="dot-search-results" class="dot-search-results"></div>
        </div>


      <div style="background:#fff;padding:16px;border-radius:10px;box-shadow:0 8px 22px rgba(0,0,0,0.04);">
        <?php if ( empty($orders) ): ?>
          <p style="text-align:center;color:#666">No orders assigned to you.</p>
        <?php else: foreach ( $orders as $r ): ?>
          <div style="padding:14px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div>
                Order Code# <b> <?php echo esc_html( $r->order_code ?: ('ID-' . intval($r->id)) ); ?></b> --- <?php echo esc_html($r->style_name); ?>
              </div>
              <div style="color:#777;font-size:13px"><?php echo intval($r->quantity); ?> pcs</div>
            </div>
            <a href="<?php echo esc_url(site_url('/order/?id=' . intval($r->id))); ?>" style="color:#0077b6;font-weight:700">View Timeline</a>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php return ob_get_clean();
});

/* -----------------------------------------
   [order] shortcode (detail + stages + comments)
   ----------------------------------------- */
add_shortcode('order', function(){
    global $wpdb;
    $id = intval( $_GET['id'] ?? 0 );
    if ( ! $id ) return '<div style="text-align:center;color:#b00;padding:20px">Invalid order id.</div>';

    $orders_table = $wpdb->prefix . 'diamond_orders';
    $stages_table = $wpdb->prefix . 'diamond_stages';

    $order = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$orders_table} WHERE id=%d", $id) );
    if ( ! $order ) return '<div style="text-align:center;color:#b00;padding:20px">Order not found.</div>';

    $user = is_user_logged_in() ? wp_get_current_user() : dot_current_plugin_user();
    if ( ! $user ) return '<div style="text-align:center;color:#b00;padding:20px">Access denied.</div>';

    $is_admin = user_can($user->ID,'manage_options') || user_can($user->ID,'manage_woocommerce');
    $client_username = $user ? ($user->user_login ?: $user->display_name ?: $user->user_email) : '';

    $is_client = false;
    if ( ! empty( $order->client_email ) ) {
        $norm = ',' . str_replace(' ', '', strtolower( $order->client_email ) ) . ',';
        $is_client = ( strpos( $norm, ',' . strtolower( $user->user_email ) . ',' ) !== false );
    }

    if ( ! $is_admin && ! $is_client ) return '<div style="text-align:center;color:#b00;padding:20px">Access denied.</div>';

    $stages = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$stages_table} WHERE order_id=%d ORDER BY id ASC", $id) );

    // Save edits (admins only)
    if ( $is_admin && isset($_POST['dot_save_nonce']) && wp_verify_nonce($_POST['dot_save_nonce'], 'dot_save') ) {
        $order_code = sanitize_text_field( $_POST['order_code'] ?? '' );
        $dup = $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM {$orders_table} WHERE order_code=%s AND id!=%d", $order_code, $id) );
        if ( $dup > 0 ) {
            echo '<div style="background:#fff3f3;color:#b00;padding:10px;border-radius:6px;margin-bottom:10px;">Order code already exists.</div>';
        } else {
            $wpdb->update(
                $orders_table,
                array(
                    'order_code' => $order_code,
                    'client_email' => dot_normalize_emails( $_POST['client_email'] ?? '' ),
                    'style_name' => sanitize_text_field( $_POST['style_name'] ?? '' ),
                    'quantity' => intval( $_POST['quantity'] ?? 0 ),
                    'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' )
                ),
                array('id' => $id),
                array('%s','%s','%s','%d','%s'),
                array('%d')
            );

            foreach ( $stages as $s ) {
                $status = sanitize_text_field( $_POST['stage_status_' . $s->id] ?? 'pending' );
                $date_raw = sanitize_text_field( $_POST['stage_date_' . $s->id] ?? '' );
                $remarks = sanitize_textarea_field( $_POST['stage_remarks_' . $s->id] ?? '' );
                $date_val = $date_raw ? $date_raw : null;

                $wpdb->update(
                    $stages_table,
                    array(
                        'status' => $status,
                        'stage_date' => $date_val,
                        'remarks' => $remarks
                    ),
                    array('id' => $s->id),
                    array('%s','%s','%s'),
                    array('%d')
                );
            }

            $redirect_url = add_query_arg(
                array(
                    'id' => $id,
                    'dot_saved' => 1
                ),
                site_url('/order/')
            );
            ?>
            <script>
              window.location.replace('<?php echo esc_js( $redirect_url ); ?>');
            </script>
            <noscript>
              <meta http-equiv="refresh" content="0;url=<?php echo esc_url( $redirect_url ); ?>">
            </noscript>
            <?php
            exit;
        }
    }

    // progress
    $done = 0;
    foreach ( $stages as $s ) if ( $s->status === 'done' ) $done++;
    $total = count( $stages );
    $percent = $total ? round( ($done / $total) * 100 ) : 0;
    $bar_color = $percent < 30 ? '#9aa3ad' : ($percent < 70 ? '#0077b6' : '#2a9d8f');

    ob_start();
    ?>
    <div style="max-width:980px;margin:28px auto;font-family:inherit;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <div>
          <h2 style="margin:0">Order Code# <?php echo esc_html($order->order_code); ?></h2>
          <div style="color:#666"><?php echo esc_html($order->style_name); ?></div>
          <div style="color:#666">
            <?php echo $is_admin ? esc_html($order->client_email) : esc_html($client_username); ?>
          </div>
        </div>

        <div>
          <?php
            $back_url = $is_admin ? site_url('/admin/') : site_url('/client/');
            $back_label = $is_admin ? 'Back to Admin' : 'Back to My Orders';
          ?>
          <a href="<?php echo esc_url( $back_url ); ?>" class="dot-btn dot-btn-edit" style="margin-right:10px;">← <?php echo esc_html( $back_label ); ?></a>
          <?php if ( ! $is_admin ): ?>
            <a href="?dot_logout=1" style="color:#0077b6;">Logout</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ( $is_admin && isset($_GET['dot_saved']) ): ?>
        <div style="background:#e6f8e6;color:#0a0;padding:10px;border-radius:6px;margin-bottom:12px;">Saved successfully.</div>
      <?php endif; ?>

      <div style="margin-bottom:18px;">
        <div style="display:flex;justify-content:space-between;font-weight:700;margin-bottom:6px;">
          <div>Production Progress</div><div><?php echo intval($percent); ?>%</div>
        </div>
        <div style="background:#eef2f5;border-radius:10px;height:14px;overflow:hidden;">
          <div style="width:<?php echo intval($percent); ?>%;height:14px;background:<?php echo esc_attr($bar_color); ?>;transition:width .4s;"></div>
        </div>
      </div>

      <?php if ($is_admin) echo '<form method="post">'; ?>

      <div style="display:grid;grid-template-columns:1fr 330px;gap:20px;">
        <div>
          <?php foreach ($stages as $s): 
              $color = $s->status === 'done' ? '#2a9d8f' : ($s->status === 'in_progress' ? '#ff7a00' : '#ccc');
              $bg = $s->status === 'in_progress' ? '#fff8f0' : ( $s->status === 'done' ? '#f2f9ff' : '#fafafa' );
          ?>
            <div style="border-left:6px solid <?php echo esc_attr($color); ?>;background:<?php echo esc_attr($bg); ?>;padding:12px;border-radius:8px;margin-bottom:10px;">
              <div style="display:flex;justify-content:space-between;gap:12px;">
                <div>
                  <strong><?php echo esc_html($s->stage_name); ?></strong><br>
                  <small style="color:#666;"><?php echo $s->stage_date ? esc_html(date_i18n('j M Y', strtotime($s->stage_date))) : 'No date'; ?></small>
                  <?php if ( $s->remarks ): ?><div style="color:#444;margin-top:6px;"><?php echo nl2br( esc_html($s->remarks) ); ?></div><?php endif; ?>
                </div>

                <?php if ( $is_admin ): ?>
                  <div style="width:200px;text-align:right;">
                    <select name="stage_status_<?php echo intval($s->id); ?>" style="padding:8px;border:1px solid #ddd;border-radius:6px;width:100%;">
                      <option value="pending" <?php selected( $s->status, 'pending' ); ?>>Pending</option>
                      <option value="in_progress" <?php selected( $s->status, 'in_progress' ); ?>>In progress</option>
                      <option value="done" <?php selected( $s->status, 'done' ); ?>>Completed</option>
                    </select>
                    <input type="date" name="stage_date_<?php echo intval($s->id); ?>" value="<?php echo esc_attr($s->stage_date); ?>" style="width:100%;margin-top:8px;padding:8px;border:1px solid #ddd;border-radius:6px;">
                    <textarea name="stage_remarks_<?php echo intval($s->id); ?>" rows="2" placeholder="Remarks" style="width:100%;margin-top:8px;padding:8px;border:1px solid #ddd;border-radius:6px;"><?php echo esc_textarea($s->remarks); ?></textarea>
                  </div>
                <?php else: ?>
                  <div style="min-width:90px;text-align:right;color:#666;"><?php echo $s->status === 'done' ? 'Completed' : ( $s->status === 'in_progress' ? 'In progress' : 'Pending' ); ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div style="background:#fff;padding:12px;border-radius:8px;box-shadow:0 6px 16px rgba(0,0,0,0.04);">
          <h3 style="margin-top:0">Order details</h3>
          <?php if ( $is_admin ): ?>
            <label style="font-weight:700;display:block;margin-top:6px">Order Code</label>
            <input name="order_code" value="<?php echo esc_attr($order->order_code); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
            <label style="font-weight:700;display:block;margin-top:6px">Client Emails</label>
            <input name="client_email" value="<?php echo esc_attr($order->client_email); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
            <label style="font-weight:700;display:block;margin-top:6px">Style</label>
            <input name="style_name" value="<?php echo esc_attr($order->style_name); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
            <label style="font-weight:700;display:block;margin-top:6px">Quantity</label>
            <input name="quantity" type="number" value="<?php echo intval($order->quantity); ?>" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;">
            <label style="font-weight:700;display:block;margin-top:6px">Notes</label>
            <textarea name="notes" rows="4" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px;"><?php echo esc_textarea($order->notes); ?></textarea>

            <input type="hidden" name="dot_save_nonce" value="<?php echo esc_attr( wp_create_nonce('dot_save') ); ?>">
            <div style="text-align:right;margin-top:12px;">
              <button style="background:#0077b6;color:#fff;padding:10px 16px;border:none;border-radius:6px;">Save changes</button>
            </div>
          <?php else: ?>
            <p><strong>Style:</strong> <?php echo esc_html($order->style_name); ?></p>
            <p><strong>Quantity:</strong> <?php echo intval($order->quantity); ?></p>
            <p><strong>Notes:</strong><br><?php echo nl2br( esc_html($order->notes) ); ?></p>
          <?php endif; ?>

          <hr style="margin:12px 0;">
          <h4 style="margin-top:6px">Comments</h4>
          <div id="dot-comments-area">
            <?php echo dot_render_comments_html_option( $order->id, $is_admin ); ?>
          </div>

          <?php if ( ! $is_admin ): ?>
            <div style="margin-top:8px;">
              <textarea id="dot-comment-text" rows="3" class="dot-input" placeholder="Write a comment..."></textarea>
              <div style="text-align:right;margin-top:8px;">
                <button id="dot-comment-submit" class="btn btn-primary">Post comment</button>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <?php if ($is_admin) echo '</form>'; ?>
    </div>
    <?php
    return ob_get_clean();
});

/* End of plugin */
