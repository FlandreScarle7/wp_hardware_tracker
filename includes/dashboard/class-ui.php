<?php
if (!defined('ABSPATH')) exit;

class Hardware_Tracker_UI {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu_page() {
        add_menu_page(
            '访客追踪',
            '访客追踪',
            'manage_options',
            'hardware-tracker',
            [$this, 'render_dashboard_page'],
            'dashicons-visibility',
            25
        );
    }

    public function enqueue_assets($hook) {
        if (isset($_GET['page']) && $_GET['page'] === 'hardware-tracker') {
            wp_enqueue_script('hardware-tracker-script',
                plugin_dir_url(__FILE__) . 'assets/script.js',
                ['jquery'], null, true
            );
            wp_enqueue_style('hardware-tracker-style',
                plugin_dir_url(__FILE__) . 'assets/style.css'
            );
        }
    }

    public function render_dashboard_page() {
        global $wpdb;
        $table = $wpdb->prefix . 'hardware_visitors';

        // 分页参数
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // 搜索逻辑
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $where_clause = '1=1';
        $query_params = [];

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clause .= $wpdb->prepare(" AND (ip LIKE %s OR os_name LIKE %s OR browser_name LIKE %s)", $like, $like, $like);
        }

        // 获取数据
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where_clause");
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
             WHERE $where_clause 
             ORDER BY created_at DESC 
             LIMIT %d OFFSET %d",
            $per_page, $offset
        ));

        // 界面输出
        echo '<div class="wrap"><h1>访客追踪</h1>';

        // 搜索框
        echo '<form method="get" class="search-form">
                <input type="hidden" name="page" value="hardware-tracker">
                <div class="search-box">
                    <input type="search" name="s" value="' . esc_attr($search) . '" placeholder="搜索 IP/浏览器/系统">
                    <button type="submit" class="button">搜索</button>
                </div>
              </form>';

        // 数据表格
        echo '<table class="widefat striped hardware-tracker-table">
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>操作系统</th>
                        <th>浏览器</th>
                        <th>IP地址</th>
                        <th>地理位置</th>
                        <th>硬件配置</th>
                        <th>用户代理</th>
                    </tr>
                </thead>
                <tbody>';

        if ($results) {
            foreach ($results as $row) {
                // 服务器时间（上海时区）
                $server_time = date('Y-m-d H:i:s', strtotime($row->created_at));

                // ============== 用户时间（浏览器时区） ==============
                $user_time = '未知';
                $user_timezone = null;
                if (!empty($row->timezone)) {
                    try {
                        $user_timezone = new DateTimeZone($row->timezone);
                        $user_time = (new DateTime($row->created_at, new DateTimeZone('Asia/Shanghai')))
                            ->setTimezone($user_timezone)
                            ->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $user_time = '时区无效';
                    }
                }

                // ============== IP地区时间（GeoIP时区） ==============
                $ip_time = '未知';
                $geo_timezone = $row->geo_timezone ?? 'unknown';
                if (!empty($geo_timezone) && $geo_timezone !== 'unknown') {
                    try {
                        $ip_timezone = new DateTimeZone($geo_timezone);
                        $ip_time = (new DateTime($row->created_at, new DateTimeZone('Asia/Shanghai')))
                            ->setTimezone($ip_timezone)
                            ->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $ip_time = '时区无效: ' . esc_html($geo_timezone);
                    }
                }

                // ============== 时区不一致警告 ==============
                $timezone_warning = '';
                if ($user_timezone && $geo_timezone !== 'unknown') {
                    $time_diff = $this->calculate_timezone_diff($user_timezone, new DateTimeZone($geo_timezone));
                    if ($time_diff !== 0) {
                        $timezone_warning = '<div class="timezone-warning" style="color:#d63638;">
                            ⚠️ 时区偏移: ' . $time_diff . ' 小时
                        </div>';
                    }
                }

                // 输出表格行
                echo '<tr>';
                echo '<td>
                        <div class="time-group">
                            <div class="time-server">🕒 服务器: ' . esc_html($server_time) . '</div>
                            <div class="time-user">👤 用户: ' . esc_html($user_time) . '</div>
                            <div class="time-ip">🌍 IP地区: ' . esc_html($ip_time) . '</div>
                            ' . $timezone_warning . '
                        </div>
                      </td>';
                echo '<td>' . esc_html("{$row->os_name} {$row->os_version}") . '</td>';
                echo '<td>' . esc_html("{$row->browser_name} {$row->browser_version}") . '</td>';
                echo '<td>' . esc_html($row->ip) . '</td>';
                echo '<td>
                        <div class="geo-info">
                            <div class="geo-country">🏳️ ' . esc_html($row->country) . '</div>
                            <div class="geo-region">📍 ' . esc_html("{$row->region} · {$row->city}") . '</div>
                            <div class="geo-timezone">⏰ ' . esc_html($geo_timezone) . '</div>
                            <button class="button view-location" 
                                    data-lat="' . esc_attr($row->latitude) . '" 
                                    data-lng="' . esc_attr($row->longitude) . '">
                                查看经纬度
                            </button>
                        </div>
                      </td>';
                echo '<td>
                        <div class="hardware-info">
                            <div class="cpu-info">💻 ' . esc_html("{$row->cpu_arch} · {$row->cpu_cores}核") . '</div>
                            <button class="button view-gpu" 
                                    data-gpu="' . esc_attr("{$row->gpu_vendor} - {$row->gpu_model}") . '">
                                GPU详情
                            </button>
                        </div>
                      </td>';
                echo '<td>
                        <div class="ua-info">
                            <button class="button view-ua" 
                                    data-ua="' . esc_attr($row->user_agent) . '">
                                UA
                            </button>
                            <button class="button view-uach" 
                                    data-uach="' . esc_attr($row->ua_ch) . '">
                                UA-CH
                            </button>
                        </div>
                      </td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7" class="no-data">😢 暂无追踪数据</td></tr>';
        }

        echo '</tbody></table>';

        // 分页导航
        if ($total_items > $per_page) {
            echo '<div class="tablenav bottom">
                    <div class="tablenav-pages">
                        ' . paginate_links([
                            'base'    => add_query_arg('paged', '%#%'),
                            'format'  => '',
                            'current' => $current_page,
                            'total'   => ceil($total_items / $per_page)
                        ]) . '
                    </div>
                  </div>';
        }

        echo '</div>'; // 结束 .wrap
    }

    /**
     * 计算两个时区的小时差
     */
    private function calculate_timezone_diff(DateTimeZone $tz1, DateTimeZone $tz2): int {
        $date = new DateTime('now', $tz1);
        $offset1 = $tz1->getOffset($date);
        $offset2 = $tz2->getOffset($date);
        return (int) round(($offset2 - $offset1) / 3600);
    }
}
