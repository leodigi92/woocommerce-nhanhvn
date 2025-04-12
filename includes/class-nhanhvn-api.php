<?php
class NhanhVN_API
{
  private static $instance = null;
  private $api_url = 'https://open.nhanh.vn/api/';
  private $api_version = '2.0';

  public static function instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function __construct()
  {
  }

  public function call_api($endpoint, $data = array())
  {
    $app_id = get_option('nhanhvn_app_id');
    $secret_key = get_option('nhanhvn_secret_key');
    $business_id = get_option('nhanhvn_business_id');
    $access_token = get_option('nhanhvn_access_token', ''); // Lấy Access Token từ options

    if (!$app_id || !$secret_key || !$business_id) {
      $error_message = 'Thiếu thông tin xác thực API: ' .
        ($app_id ? 'App ID OK' : 'App ID missing') . ', ' .
        ($secret_key ? 'Secret Key OK' : 'Secret Key missing') . ', ' .
        ($business_id ? 'Business ID OK' : 'Business ID missing');
      $this->log_error($error_message);
      return new WP_Error('api_config_error', $error_message);
    }

    // Gộp dữ liệu mặc định với dữ liệu đầu vào
    $default_data = array(
      'version' => $this->api_version,
      'appId' => $app_id,
      'businessId' => $business_id,
      'accessToken' => $access_token // Sử dụng Access Token từ cài đặt
    );
    $data = array_merge($default_data, $data);

    $url = $this->api_url . $endpoint;
    $args = array(
      'method' => 'POST',
      'timeout' => 30,
      'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded'
      ),
      'body' => http_build_query($data)
    );

    error_log('Nhanh.vn API Request: ' . $url . ' - Data: ' . print_r($data, true));

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
      $this->log_error('API Error: ' . $response->get_error_message());
      return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);
    error_log('Nhanh.vn API Response: Code ' . $code . ' - Body: ' . $body);

    $result = json_decode($body, true);

    if (!$result || (isset($result['code']) && $result['code'] != 1)) {
      $error_message = isset($result['messages']) ? implode(', ', $result['messages']) : 'Unknown error (HTTP ' . $code . ')';
      $this->log_error('API Response Error: ' . $error_message . ' - Raw Response: ' . $body);
      return new WP_Error('api_response_error', $error_message);
    }

    return $result;
  }

  private function log_error($message)
  {
    global $wpdb;
    $wpdb->insert(
      $wpdb->prefix . 'nhanhvn_sync_log',
      array(
        'type' => 'api_error',
        'status' => 'error',
        'message' => $message,
        'data' => null,
        'created_at' => current_time('mysql', false)
      ),
      array('%s', '%s', '%s', '%s', '%s')
    );
  }

  public function get_products($params = array())
  {
    return $this->call_api('product/search', $params);
  }

  public function get_warehouses()
  {
    $response = $this->call_api('store/depot');

    // Ghi log toàn bộ phản hồi để debug
    error_log('Nhanh.vn Get Warehouses Response: ' . json_encode($response));

    if (is_wp_error($response)) {
      error_log('Nhanh.vn Get Warehouses Error: WP_Error - ' . $response->get_error_message());
      return false;
    }

    if (!is_array($response) || !isset($response['code'])) {
      error_log('Nhanh.vn Get Warehouses Error: Invalid response format');
      return false;
    }

    if ($response['code'] == 1) {
      if (empty($response['data'])) {
        error_log('Nhanh.vn Get Warehouses Warning: No warehouses found');
        return []; // Trả về mảng rỗng nếu không có kho
      }
      return $response['data'];
    } else {
      $error_message = $response['messages'] ?? 'Unknown error';
      error_log('Nhanh.vn Get Warehouses Error: ' . json_encode($error_message));
      return false;
    }
  }

  public function get_inventory($params = array())
  {
    return $this->call_api('inventory/search', $params);
  }

  public function create_order($order_data)
  {
    return $this->call_api('order/add', $order_data);
  }

  public function get_shipping_fee($params = array())
  {
    return $this->call_api('shipping/fee', $params);
  }

  public function update_inventory($data)
  {
    return $this->call_api('inventory/update', $data);
  }

  public function test_connection()
  {
    $response = $this->call_api('store/depot');
    if (is_wp_error($response)) {
      return $response;
    }
    return true;
  }
}
