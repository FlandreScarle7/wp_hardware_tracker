<?php
if (!defined('ABSPATH')) exit;

class Hardware_Tracker_GeoIP_Resolver {
    private $reader;
    private $db_path;

    public function __construct() {
        $this->db_path = plugin_dir_path(dirname(__FILE__, 2)) . 'data/GeoLite2-City.mmdb';
        $this->initialize_reader();
    }

    private function initialize_reader() {
        try {
            if (file_exists($this->db_path)) {
                $this->reader = new MaxMind\Db\Reader($this->db_path);
                error_log('[硬件追踪] GeoIP数据库加载成功');
            } else {
                error_log('[硬件追踪] 数据库文件未找到：' . $this->db_path);
            }
        } catch (Exception $e) {
            error_log('[硬件追踪] GeoIP初始化失败：' . $e->getMessage());
        }
    }

    public function resolve_ip($ip) {
        if (!$this->reader || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        try {
            $record = $this->reader->get($ip);
            return $this->parse_record($record);
        } catch (Exception $e) {
            error_log('[硬件追踪] IP解析失败：' . $e->getMessage());
            return false;
        }
    }

    private function parse_record($record) {
        return [
            'country'   => $record['country']['names']['en'] ?? 'unknown',
            'region'    => $record['subdivisions'][0]['names']['en'] ?? 'unknown',
            'city'      => $record['city']['names']['en'] ?? 'unknown',
            'district'  => $record['subdivisions'][1]['names']['en'] ?? 'unknown',
            'latitude'  => $record['location']['latitude'] ?? 0,
            'longitude' => $record['location']['longitude'] ?? 0,
            'geo_timezone' => $record['location']['time_zone'] ?? 'unknown'
        ];
    }
}
