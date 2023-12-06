<?php

/**
 * Plugin Name: Skallar Cloudflare
 * Description: Plugin criado para forçar a limpeza do cache de borda quando ocorrer atualizações
 * Author: Claudio Cirino jr
 */

defined('ABSPATH') || exit;

class SkallarCF
{

  protected static $instance = null;



  public function __construct()
  {

    //hook que dispara quando é editado/salvo
    add_action('save_post', [$this, 'cloudflare_purge_all']);
    add_action('added_option', [$this, 'cloudflare_purge_all'], 10, 2);
    add_action('update_option', [$this, 'cloudflare_purge_all'], 10, 2);
    //adcionando página de configuração em ferramentas
    add_action('admin_menu', [$this, 'skallar_cf_submenu']);

    add_action('admin_init', [$this, 'sk_cf_settings_init']);
    add_action('admin_head', [$this, 'admin_script']);
  }


  public function admin_script()
  {

    echo '<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
<meta http-equiv="Pragma" content="no-cache" />
<meta http-equiv="Expires" content="0" />';
  }




  public function skallar_cf_submenu()
  {

    add_submenu_page(
      'tools.php',
      'Cloudflare Skallar',
      'Cloudflare Skallar',
      'manage_options',
      'skallar_cf',
      [$this, 'skallar_cf_page_callback'],
      4
    );
  }


  public function sk_cf_settings_init()
  {
    register_setting('sk_cf_settings_group', 'sk_cf_email');
    register_setting('sk_cf_settings_group', 'sk_cf_apikey');
    register_setting('sk_cf_settings_group', 'sk_cf_zone');

    add_settings_section(
      'sk_cf_settings_section',
      'Cloudflare Skallar',
      [$this, 'sk_cf_settings_section_callback'],
      'sk-cf-settings'
    );

    add_settings_field(
      'sk_cf_email',
      'Email',
      [$this, 'sk_cf_email_callback'],
      'sk-cf-settings',
      'sk_cf_settings_section'
    );

    add_settings_field(
      'sk_cf_apikey',
      'API Key',
      [$this, 'sk_cf_apikey_callback'],
      'sk-cf-settings',
      'sk_cf_settings_section'
    );

    add_settings_field(
      'sk_cf_zone',
      'Zone',
      [$this, 'sk_cf_zone_callback'],
      'sk-cf-settings',
      'sk_cf_settings_section'
    );
  }


  public function sk_cf_settings_section_callback()
  {
    echo '<p>Configure os seguites dados integrar ao Cloudflare:</p>';
  }

  public function sk_cf_email_callback()
  {
    $value = get_option('sk_cf_email') ? get_option('sk_cf_email') : 'ti@skallardigital.com.br';
    echo '<input type="text" name="sk_cf_email" value="' . esc_attr($value) . '" />';
  }

  public function sk_cf_apikey_callback()
  {
    $value = get_option('sk_cf_apikey') ? get_option('sk_cf_apikey') : '7f3b47f48ee1c27480797e0416dad4f52807a';
    echo '<input type="text" name="sk_cf_apikey" value="' . esc_attr($value) . '" />';
  }

  public function sk_cf_zone_callback()
  {
    $value = get_option('sk_cf_zone');
    echo '<input type="text" name="sk_cf_zone" required value="' . esc_attr($value) . '" />';
  }


  // método que limpa o cache de borda
  public function skallar_cf_page_callback()
  {
?>
    <div class="wrap">
      <h1>Skallar CloudFlare Plugin</h1>

      <form method="post" action="options.php">
        <?php
        settings_fields('sk_cf_settings_group');
        do_settings_sections('sk-cf-settings');
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



    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.cloudflare.com/client/v4/zones/' . get_option('sk_cf_zone') . '/purge_cache',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => '{"purge_everything":true}',
      CURLOPT_HTTPHEADER => array(
        'X-Auth-Email:' . get_option('sk_cf_email'),
        'X-Auth-Key:' . get_option('sk_cf_apikey'),
        'Content-Type: application/json',
        'Origin: https://www.cloudflare.com'
      ),
    ));

    curl_exec($curl);

    curl_close($curl);
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


$skallar_cloudflare = SkallarCF::get_instance();
