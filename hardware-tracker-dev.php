<?php
/*
Plugin Name: 硬件级访客追踪 (开发版)
Description: 开发环境专用版本
Version: 1.0-dev
Author: Hansjakob Florian
*/

if (!defined('ABSPATH')) exit;

// 开发版数据表
register_activation_hook(__FILE__, 'hardware_tracker_dev_create_table');
function hardware_tracker_dev_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hardware_visitors_dev';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        os_name VARCHAR(50) NOT NULL DEFAULT 'unknown',
        os_version VARCHAR(50) NOT NULL DEFAULT 'unknown',
        cpu_arch VARCHAR(20) NOT NULL DEFAULT 'unknown',
        cpu_cores SMALLINT NOT NULL DEFAULT 0,
        gpu_vendor VARCHAR(50) NOT NULL DEFAULT 'unknown',
        gpu_model VARCHAR(100) NOT NULL DEFAULT 'unknown',
        ip VARCHAR(45) NOT NULL DEFAULT '0.0.0.0',
        timezone VARCHAR(50) NOT NULL DEFAULT 'unknown',
        user_agent TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY os_name (os_name),
        KEY cpu_arch (cpu_arch)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// 数据库操作
function hardware_tracker_dev_insert_data($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'hardware_visitors_dev';

    $wpdb->insert($table, 
        [
            'os_name'    => sanitize_text_field($data['os_name']),
            'os_version' => sanitize_text_field($data['os_version']),
            'cpu_arch'   => sanitize_text_field($data['cpu_arch']),
            'cpu_cores'  => intval($data['cpu_cores']),
            'gpu_vendor' => sanitize_text_field($data['gpu_vendor']),
            'gpu_model'  => sanitize_text_field($data['gpu_model']),
            'ip'         => sanitize_text_field($data['ip']),
            'timezone'   => sanitize_text_field($data['timezone']),
            'user_agent' => sanitize_textarea_field($data['user_agent']),
            'created_at' => current_time('mysql')
        ],
        [
            '%s', '%s', '%s', '%d', 
            '%s', '%s', '%s', '%s', 
            '%s', '%s'
        ]
    );
}

// AJAX处理
add_action('wp_ajax_hardware_tracker_dev', 'hardware_tracker_dev_handle');
add_action('wp_ajax_nopriv_hardware_tracker_dev', 'hardware_tracker_dev_handle');
function hardware_tracker_dev_handle() {
    check_ajax_referer('hardware_tracker_dev_nonce', 'security');

    $data = [
        'os_name'    => $_POST['os_name'] ?? 'unknown',
        'os_version' => $_POST['os_version'] ?? 'unknown',
        'cpu_arch'   => $_POST['cpu_arch'] ?? 'unknown',
        'cpu_cores'  => isset($_POST['cpu_cores']) ? intval($_POST['cpu_cores']) : 0,
        'gpu_vendor' => $_POST['gpu_vendor'] ?? 'unknown',
        'gpu_model'  => $_POST['gpu_model'] ?? 'unknown',
        'ip'         => $_SERVER['REMOTE_ADDR'],
        'timezone'   => $_POST['timezone'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    hardware_tracker_dev_insert_data($data);
    wp_send_json_success();
}

// 前端资源
add_action('wp_enqueue_scripts', 'hardware_tracker_dev_scripts');
function hardware_tracker_dev_scripts() {
    wp_enqueue_script(
        'hardware-tracker-dev',
        plugins_url('/assets/tracker-dev.js', __FILE__),
        [],
        '1.0-dev',
        true
    );

    wp_localize_script('hardware-tracker-dev', 'hardwareTrackerDev', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('hardware_tracker_dev_nonce')
    ]);
}

// 卸载处理
register_uninstall_hook(__FILE__, 'hardware_tracker_dev_uninstall');
function hardware_tracker_dev_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hardware_visitors_dev");
}
