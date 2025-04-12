<?php
/*
Plugin Name: WP Hardware Tracker
Description: 访客UA收集，硬件级追踪，获取高熵数据，智能解析GeoIP属地。
Version: 2.0
Author: Hansjakob Florian
*/

if (!defined('ABSPATH')) exit;


// 加载依赖库
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/geoip/class-geoip-resolver.php';
// 加载访客信息展示 UI
require_once plugin_dir_path(__FILE__) . 'includes/dashboard/class-ui.php';
new Hardware_Tracker_UI();
// 待开发。。。

// ==================== 数据库设置 ====================
register_activation_hook(__FILE__, 'hardware_tracker_create_table');
function hardware_tracker_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hardware_visitors';
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
        browser_name VARCHAR(50) NOT NULL DEFAULT 'unknown',
        browser_version VARCHAR(50) NOT NULL DEFAULT 'unknown',
        ua_ch TEXT NOT NULL,
        country VARCHAR(50) NOT NULL DEFAULT 'unknown',
        region VARCHAR(50) NOT NULL DEFAULT 'unknown',
        city VARCHAR(50) NOT NULL DEFAULT 'unknown',
        district VARCHAR(50) NOT NULL DEFAULT 'unknown',
        geo_timezone VARCHAR(50) NOT NULL DEFAULT 'unknown',
        latitude DECIMAL(10,6) NOT NULL DEFAULT 0.0,
        longitude DECIMAL(10,6) NOT NULL DEFAULT 0.0,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id)
    ) $charset;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // 创建数据目录
    if (!file_exists(plugin_dir_path(__FILE__) . 'data')) {
        mkdir(plugin_dir_path(__FILE__) . 'data', 0755, true);
    }
}



// ==================== 数据收集处理 ====================
function hardware_tracker_get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];

    $proxy_headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED'
    ];

    foreach ($proxy_headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip_list = explode(',', $_SERVER[$header]);
            foreach ($ip_list as $ip_candidate) {
                $ip_candidate = trim($ip_candidate);
                if (filter_var($ip_candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                    return $ip_candidate;
                }
            }
        }
    }

    return $ip;
}


// 数据入库函数
function hardware_tracker_insert_data($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'hardware_visitors';

    // GeoIP解析
    $geo_data = [
        'country'   => 'unknown',
        'region'    => 'unknown',
        'city'      => 'unknown',
        'district'  => 'unknown',
        'latitude'  => 0.0,
        'longitude' => 0.0,
        'geo_timezone'  => 'unknown'
    ];

    if (get_option('hardware_tracker_geoip_enabled', 0)) {
        $resolver = new Hardware_Tracker_GeoIP_Resolver();
        $location = $resolver->resolve_ip($data['ip']);
        if ($location && is_array($location)) {
            $geo_data = array_merge($geo_data, $location); // 安全合并
        }
    }

        if (!get_option('hardware_tracker_ua_ch_enabled', 1)) {
        $data['ua_ch'] = '{}'; // 关闭时存储空JSON
    }
    
    // 合并所有数据
    $insert_data = array_merge($data, $geo_data);
    
    $format = [
        '%s', '%s', '%s', '%d',   // os_name, os_version, cpu_arch, cpu_cores
        '%s', '%s',               // gpu_vendor, gpu_model
        '%s', '%s',               // ip, timezone
        '%s',                     // user_agent
        '%s', '%s',               // browser_name, browser_version
        '%s',                     // ua_ch
        '%s', '%s', '%s', '%s',  // country, region, city, district
        '%f', '%f', '%s',               // latitude, longitude, geo_timezone
        '%s'                      // created_at
    ];

    // 执行数据库插入
    return $wpdb->insert($table, $insert_data, $format);
}


// ==================== AJAX处理 ====================
add_action('wp_ajax_hardware_tracker', 'hardware_tracker_handle');
add_action('wp_ajax_nopriv_hardware_tracker', 'hardware_tracker_handle');
function hardware_tracker_handle() {
    check_ajax_referer('hardware_tracker_nonce', 'security');

    $data = [
        'os_name'        => sanitize_text_field($_POST['os_name'] ?? 'unknown'),
        'os_version'     => sanitize_text_field($_POST['os_version'] ?? 'unknown'),
        'cpu_arch'       => sanitize_text_field($_POST['cpu_arch'] ?? 'unknown'),
        'cpu_cores'      => absint($_POST['cpu_cores'] ?? 0),
        'gpu_vendor'     => sanitize_text_field($_POST['gpu_vendor'] ?? 'unknown'),
        'gpu_model'      => sanitize_text_field($_POST['gpu_model'] ?? 'unknown'),
        'ip'             => hardware_tracker_get_client_ip(),
        'timezone'       => sanitize_text_field($_POST['timezone'] ?? 'unknown'),
        'user_agent'     => sanitize_textarea_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'browser_name'   => sanitize_text_field($_POST['browser_name'] ?? 'unknown'),
        'browser_version'=> sanitize_text_field($_POST['browser_version'] ?? 'unknown'),
        'ua_ch'          => sanitize_textarea_field($_POST['ua_ch'] ?? '{}'),
        'created_at'     => current_time('mysql')
    ];

    hardware_tracker_insert_data($data);
    wp_send_json_success(['message' => '数据记录成功']);
}

// ==================== 后台设置 ====================
add_action('admin_menu', 'hardware_tracker_add_settings_menu');
function hardware_tracker_add_settings_menu() {
    add_options_page(
        '硬件级访客追踪设置',
        '硬件级访客追踪',
        'manage_options',
        'hardware-tracker-settings',
        'hardware_tracker_settings_page'
    );
}

function hardware_tracker_settings_page() {
    ?>
    <div class="wrap">
        <h1>硬件级访客追踪设置</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('hardware_tracker_options');
            do_settings_sections('hardware-tracker-settings');
            submit_button('保存配置');
            ?>
        </form>
        
        <div class="card">
            <h3>功能状态</h3>
            <table class="status-table">
                <tr>
                    <td>GeoIP数据库：</td>
                    <td>
                        <?php if (file_exists(plugin_dir_path(__FILE__) . 'data/GeoLite2-City.mmdb')): ?>
                            <span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> 已安装
                        <?php else: ?>
                            <span class="dashicons dashicons-warning" style="color:#dc3232;"></span> 未安装
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>UA客户端提示：</td>
                    <td>
                        <?php if (get_option('hardware_tracker_ua_ch_enabled', 1)): ?>
                            <span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> 已启用
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color:#dc3232;"></span> 已禁用
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            
            <h4>数据库路径</h4>
            <code><?= plugin_dir_path(__FILE__) ?>data/GeoLite2-City.mmdb</code>
        </div>
    </div>
    
    <style>
        .status-table {
            border-spacing: 0;
            width: 100%;
        }
        .status-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .status-table td:first-child {
            width: 150px;
            font-weight: 500;
        }
    </style>
    <?php
}


add_action('admin_init', 'hardware_tracker_register_settings');
function hardware_tracker_register_settings() {
    // GeoIP设置
    register_setting(
        'hardware_tracker_options',
        'hardware_tracker_geoip_enabled',
        ['sanitize_callback' => 'absint']
    );
    
    // UA客户端提示设置
    register_setting(
        'hardware_tracker_options',
        'hardware_tracker_ua_ch_enabled',
        ['sanitize_callback' => 'absint']
    );

    add_settings_section(
        'geoip_settings',
        '数据收集设置',
        function() {
            echo '<p>控制不同类型数据的收集功能</p>';
        },
        'hardware-tracker-settings'
    );

    // GeoIP字段
    add_settings_field(
        'geoip_enabled',
        '地理位置解析',
        function() {
            $enabled = get_option('hardware_tracker_geoip_enabled', 0);
            echo '<label><input type="checkbox" name="hardware_tracker_geoip_enabled" value="1" '
                . checked(1, $enabled, false) . '> 启用IP地理位置解析</label>';
        },
        'hardware-tracker-settings',
        'geoip_settings'
    );

    // UA客户端提示字段
    add_settings_field(
        'ua_ch_enabled',
        '高级浏览器特征',
        function() {
            $enabled = get_option('hardware_tracker_ua_ch_enabled', 1);
            echo '<label><input type="checkbox" name="hardware_tracker_ua_ch_enabled" value="1" '
                . checked(1, $enabled, false) . '> 收集UA客户端提示数据</label>';
        },
        'hardware-tracker-settings',
        'geoip_settings'
    );
}


// ==================== 前端脚本 ====================
add_action('wp_enqueue_scripts', 'hardware_tracker_scripts');
function hardware_tracker_scripts() {
    wp_enqueue_script(
        'hardware-tracker',
        plugins_url('/assets/tracker.js', __FILE__),
        [],
        '2.0',
        ['in_footer' => true]
    );

    wp_localize_script('hardware-tracker', 'hardwareTracker', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('hardware_tracker_nonce'),
            'ua_ch_enabled' => (int)get_option('hardware_tracker_ua_ch_enabled', 1)
    ]);
}

// ==================== 卸载处理 ====================
register_uninstall_hook(__FILE__, 'hardware_tracker_uninstall');
function hardware_tracker_uninstall() {
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}hardware_visitors");
    delete_option('hardware_tracker_geoip_enabled');
    delete_option('hardware_tracker_ua_ch_enabled');
}
