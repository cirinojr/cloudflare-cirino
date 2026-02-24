<?php

/**
 * Plugin Name: Cirino Cloudflare
 * Description: Plugin created to force edge cache purge when updates occur
 * Author: Claudio Cirino jr
 */

defined('ABSPATH') || exit;

class CirinoCF
{

  protected static $instance = null;



  public function __construct()
  {

    // Hook triggered when a post is edited/saved
    add_action('save_post', [$this, 'maybe_purge_on_save_post'], 10, 3);
    // Adding settings page in Tools menu
    add_action('admin_menu', [$this, 'cirino_cf_submenu']);

    add_action('admin_init', [$this, 'cc_cf_settings_init']);
    add_action('admin_head', [$this, 'admin_script'], 10, 2);
  }


  public function admin_script()
  {

    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0"); // Proxies.

  }




  public function cirino_cf_submenu()
  {

    add_submenu_page(
      'tools.php',
      'Cloudflare Cirino',
      'Cloudflare Cirino',
      'manage_options',
      'cirino_cf',
      [$this, 'cirino_cf_page_callback'],
      4
    );
  }


  public function cc_cf_settings_init()
  {
    register_setting('cc_cf_settings_group', 'cc_cf_email', [
      'type' => 'string',
      'sanitize_callback' => [$this, 'sanitize_email_setting'],
      'default' => '',
    ]);
    register_setting('cc_cf_settings_group', 'cc_cf_apikey', [
      'type' => 'string',
      'sanitize_callback' => [$this, 'sanitize_apikey_setting'],
      'default' => '',
    ]);
    register_setting('cc_cf_settings_group', 'cc_cf_zone', [
      'type' => 'string',
      'sanitize_callback' => [$this, 'sanitize_zone_setting'],
      'default' => '',
    ]);
    register_setting('cc_cf_settings_group', 'cc_cf_purge_mode', [
      'type' => 'string',
      'sanitize_callback' => [$this, 'sanitize_purge_mode_setting'],
      'default' => 'everything',
    ]);

    add_settings_section(
      'cc_cf_settings_section',
      'Cloudflare Cirino',
      [$this, 'cc_cf_settings_section_callback'],
      'cc-cf-settings'
    );

    add_settings_field(
      'cc_cf_email',
      'Email',
      [$this, 'cc_cf_email_callback'],
      'cc-cf-settings',
      'cc_cf_settings_section'
    );

    add_settings_field(
      'cc_cf_apikey',
      'API Key',
      [$this, 'cc_cf_apikey_callback'],
      'cc-cf-settings',
      'cc_cf_settings_section'
    );

    add_settings_field(
      'cc_cf_zone',
      'Zone',
      [$this, 'cc_cf_zone_callback'],
      'cc-cf-settings',
      'cc_cf_settings_section'
    );

    add_settings_field(
      'cc_cf_purge_mode',
      'Purge Mode',
      [$this, 'cc_cf_purge_mode_callback'],
      'cc-cf-settings',
      'cc_cf_settings_section'
    );
  }

  public function sanitize_email_setting($value)
  {
    return sanitize_email($value);
  }

  public function sanitize_apikey_setting($value)
  {
    $value = sanitize_text_field($value);
    return preg_replace('/[^a-zA-Z0-9]/', '', $value);
  }

  public function sanitize_zone_setting($value)
  {
    $value = sanitize_text_field($value);
    return preg_replace('/[^a-zA-Z0-9]/', '', $value);
  }

  public function sanitize_purge_mode_setting($value)
  {
    $value = sanitize_text_field($value);
    if (!in_array($value, ['everything', 'url'], true)) {
      return 'everything';
    }
    return $value;
  }


  public function cc_cf_settings_section_callback()
  {
    echo '<p>Configure os seguites dados integrar ao Cloudflare:</p>';
  }

  public function cc_cf_email_callback()
  {
    $value = get_option('cc_cf_email') ? get_option('cc_cf_email') : '';
    echo '<input type="text" name="cc_cf_email" value="' . esc_attr($value) . '" />';
  }

  public function cc_cf_apikey_callback()
  {
    $value = get_option('cc_cf_apikey') ? get_option('cc_cf_apikey') : '';
    echo '<input type="password" name="cc_cf_apikey" value="' . esc_attr($value) . '" autocomplete="off" />';
  }

  public function cc_cf_zone_callback()
  {
    $value = get_option('cc_cf_zone');
    echo '<input type="text" name="cc_cf_zone" required value="' . esc_attr($value) . '" />';
  }

  public function cc_cf_purge_mode_callback()
  {
    $value = get_option('cc_cf_purge_mode', 'everything');
    echo '<label><input type="radio" name="cc_cf_purge_mode" value="everything" ' . checked($value, 'everything', false) . ' /> Purge everything</label><br />';
    echo '<label><input type="radio" name="cc_cf_purge_mode" value="url" ' . checked($value, 'url', false) . ' /> Purge only affected post/page URL</label>';
  }


  // Settings page callback
  public function cirino_cf_page_callback()
  {
    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to access this page.', 'cirino-cloudflare'));
    }
?>
    <div class="wrap">
      <h1>Cirino CloudFlare Plugin</h1>

      <form method="post" action="options.php">
        <?php
        settings_fields('cc_cf_settings_group');
        do_settings_sections('cc-cf-settings');
        submit_button();
        ?>
      </form>
    </div>
<?php
  }


  public static function get_instance()
  {

    if (null === self::$instance) {
      self::$instance = new self;
    }

    return self::$instance;
  }



  public static function cloudflare_purge_all()
  {
    $zone_id = preg_replace('/[^a-zA-Z0-9]/', '', (string) get_option('cc_cf_zone'));
    $email = sanitize_email((string) get_option('cc_cf_email'));
    $apikey = preg_replace('/[^a-zA-Z0-9]/', '', (string) get_option('cc_cf_apikey'));

    if (empty($zone_id) || empty($email) || empty($apikey)) {
      return false;
    }

    $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/purge_cache';

    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{"purge_everything":true}',
      CURLOPT_HTTPHEADER => array(
        'X-Auth-Email:' . $email,
        'X-Auth-Key:' . $apikey,
        'Content-Type: application/json',
      ),
    ));

    curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (curl_errno($curl)) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Cirino Cloudflare purge error: ' . curl_error($curl));
      }
      curl_close($curl);
      return false;
    }

    curl_close($curl);
    return $http_code >= 200 && $http_code < 300;
  }

  public static function cloudflare_purge_url($page_url)
  {
    $zone_id = preg_replace('/[^a-zA-Z0-9]/', '', (string) get_option('cc_cf_zone'));
    $email = sanitize_email((string) get_option('cc_cf_email'));
    $apikey = preg_replace('/[^a-zA-Z0-9]/', '', (string) get_option('cc_cf_apikey'));
    $page_url = esc_url_raw((string) $page_url);

    if (empty($zone_id) || empty($email) || empty($apikey) || empty($page_url)) {
      return false;
    }

    $url = 'https://api.cloudflare.com/client/v4/zones/' . $zone_id . '/purge_cache';

    $args = array(
      'headers' => array(
        'X-Auth-Email' => $email,
        'X-Auth-Key' => $apikey,
        'Content-Type' => 'application/json',
      ),
      'body' => wp_json_encode(array('files' => array($page_url))),
      'method' => 'POST',
      'data_format' => 'body',
      'timeout' => 15,
    );

    $response = wp_remote_post($url, $args);

    if (is_wp_error($response)) {
      if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Cirino Cloudflare purge URL error: ' . $response->get_error_message());
      }
      return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    return $status_code >= 200 && $status_code < 300;
  }

  public function maybe_purge_on_save_post($post_id, $post, $update)
  {
    if (!$update || empty($post_id)) {
      return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
      return;
    }

    if ('auto-draft' === get_post_status($post_id)) {
      return;
    }

    $purge_mode = get_option('cc_cf_purge_mode', 'everything');

    if ('url' === $purge_mode) {
      $post_url = get_permalink($post_id);
      if (!empty($post_url)) {
        $purged = self::cloudflare_purge_url($post_url);
        if ($purged) {
          return;
        }
      }
    }

    self::cloudflare_purge_all();
  }




  public function activate()
  {
    global $wp_rewrite;
    $this->flush_rewrite_rules();
  }

  public function flush_rewrite_rules()
  {
    global $wp_rewrite;
    $wp_rewrite->flush_rules();
  }
}


$cirino_cloudflare = CirinoCF::get_instance();
