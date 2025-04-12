<?php

class NhanhVN_Admin
{

  private static $instance = null;



  public static function instance()
  {

    if (null === self::$instance) {

      self::$instance = new self();

    }

    return self::$instance;

  }



  public function __construct()
  {

    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    add_action('wp_ajax_generate_webhook_token', array($this, 'generate_webhook_token'));
    add_action('wp_ajax_check_api_connection', array($this, 'check_api_connection'));
    add_action('wp_ajax_sync_nhanh_products', array($this, 'sync_nhanh_products'));

  }



  private function init_hooks()
  {

    add_action('admin_menu', array($this, 'add_menu_pages'));

    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

    add_action('wp_ajax_sync_nhanh_products', array($this, 'ajax_sync_products'));

    add_action('wp_ajax_check_api_connection', array($this, 'ajax_check_connection'));

  }



  public function add_menu_pages()
  {

    add_menu_page(

      'Nhanh.vn Integration',

      'Nhanh.vn',

      'manage_options',

      'nhanhvn',

      array($this, 'render_main_page'),

      'dashicons-cart',

      56

    );



    add_submenu_page(

      'nhanhvn',

      'Đồng bộ sản phẩm',

      'Đồng bộ sản phẩm',

      'manage_options',

      'nhanhvn-products',

      array($this, 'render_products_page')

    );



    add_submenu_page(

      'nhanhvn',

      'Đơn hàng',

      'Đơn hàng',

      'manage_options',

      'nhanhvn-orders',

      array($this, 'render_orders_page')

    );



    add_submenu_page(

      'nhanhvn',

      'Nhật ký',

      'Nhật ký',

      'manage_options',

      'nhanhvn-logs',

      array($this, 'render_logs_page')

    );

  }



  public function enqueue_admin_assets($hook)
  {
    if (strpos($hook, 'nhanhvn-settings') === false) {
      return;
    }

    wp_enqueue_style('nhanhvn-admin', NHANHVN_PLUGIN_URL . 'assets/css/admin.css', array(), NHANHVN_VERSION);
    wp_enqueue_script('nhanhvn-admin', NHANHVN_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), NHANHVN_VERSION, true);

    wp_localize_script('nhanhvn-admin', 'nhanhvn_admin', array(
      'ajaxurl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('nhanhvn_admin_nonce'),
      'messages' => array(
        'confirm_sync' => 'Bạn có chắc chắn muốn bắt đầu đồng bộ sản phẩm không?',
        'sync_success' => 'Đồng bộ sản phẩm hoàn tất!',
        'sync_error' => 'Lỗi khi đồng bộ sản phẩm'
      )
    ));

    wp_localize_script('nhanhvn-admin', 'nhanhvn_vars', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('nhanhvn_vars_nonce')
    ));
  }

  public function generate_webhook_token()
  {
    check_ajax_referer('nhanhvn_admin_nonce', 'nonce');
    $new_token = wp_generate_password(32, false);
    update_option('nhanhvn_webhook_token', $new_token);
    wp_send_json_success(array('token' => $new_token));
  }

  public function check_api_connection()
  {
    check_ajax_referer('nhanhvn_admin_nonce', 'nonce');
    $api = NhanhVN_API::instance();
    $result = $api->test_connection();
    if (is_wp_error($result)) {
      wp_send_json_error($result->get_error_message());
    } else {
      wp_send_json_success('Kết nối API thành công!');
    }
  }

  public function sync_nhanh_products()
  {
    check_ajax_referer('nhanhvn_admin_nonce', 'nonce');
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $sync_images = isset($_POST['sync_images']) && $_POST['sync_images'] === 'true';
    $sync_categories = isset($_POST['sync_categories']) && $_POST['sync_categories'] === 'true';
    $sync_inventory = isset($_POST['sync_inventory']) && $_POST['sync_inventory'] === 'true';

    $product_sync = NhanhVN_Product::instance();
    $result = $product_sync->sync_products(array(
      'page' => $page,
      'sync_images' => $sync_images,
      'sync_categories' => $sync_categories,
      'sync_inventory' => $sync_inventory
    ));

    if (is_wp_error($result)) {
      wp_send_json_error($result->get_error_message());
    } else {
      wp_send_json_success($result);
    }
  }

  public function render_main_page()
  {

    include NHANHVN_PLUGIN_DIR . 'admin/views/main.php';

  }



  public function render_products_page()
  {

    include NHANHVN_PLUGIN_DIR . 'admin/views/sync-products.php';

  }



  public function render_orders_page()
  {

    include NHANHVN_PLUGIN_DIR . 'admin/views/orders.php';

  }



  public function render_logs_page()
  {

    include NHANHVN_PLUGIN_DIR . 'admin/views/logs.php';

  }



  public function ajax_sync_products()
  {

    check_ajax_referer('nhanhvn-admin', 'nonce');



    $product_sync = NhanhVN_Product::instance();

    $result = $product_sync->sync_products();



    if (is_wp_error($result)) {

      wp_send_json_error($result->get_error_message());

    }



    wp_send_json_success($result);

  }



  public function ajax_check_connection()
  {

    check_ajax_referer('nhanhvn-admin', 'nonce');



    $api = NhanhVN_API::instance();

    $result = $api->test_connection();



    if (is_wp_error($result)) {

      wp_send_json_error($result->get_error_message());

    }



    wp_send_json_success('Kết nối thành công');

  }

}
