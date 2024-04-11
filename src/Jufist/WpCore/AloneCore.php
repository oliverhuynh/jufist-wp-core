<?php 

namespace Jufist\WpCore;


// Load Wordpress library
$reflection = new \ReflectionClass(\Jufist\WpCore\Core::class);
$vendorDir = dirname($reflection->getFileName(), 6);
define( 'ABSPATH', $vendorDir . '/johnpbloch/wordpress-core/');
// Require wp-settings but not DB checking, remove vars, etc..
require_once __DIR__ . '/wordpress.light.inc';

/**
 * Description of WpCore
 *
 * @author Oliver Huynh
 */
class AloneCore extends Core {
    public function loadComponents() {
	global $extensions;
        // Manage components via admin backend
        defined('ALONEDIR') || define('ALONEDIR', dirname($this->file));
        if (file_exists(ALONEDIR . "/components.json")) {
            $string = file_get_contents(ALONEDIR . "/components.json");
            $components = json_decode($string, true);
            foreach ($components as $comp => $compv) {
		if (isset($extensions) && (is_array($extensions) && !in_array($comp, $extensions))) {
			continue ;
	        }
                require_once(ALONEDIR . '/components/' . $comp . '/' . $comp . '.php');
            }
        }
    }

    public function InitPlugin()
    {
        global $wp_filter;
        $wp_filter = [];
        // Readd the actions
        add_action( 'wp_head',             'wp_enqueue_scripts',              1     );
        add_action( 'wp_head',             'wp_print_styles',                  8    );
        add_action( 'wp_head',             'wp_print_head_scripts',            9    );
        add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
        add_action( 'wp_print_footer_scripts', '_wp_footer_scripts' );
        add_action( 'admin_print_footer_scripts', '_wp_footer_scripts' );
        add_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
        define('WPMU_PLUGIN_DIR', dirname($this->file));
        define('WP_PLUGIN_DIR', WPMU_PLUGIN_DIR); 
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        defined('WP_CONTENT_URL') || define( 'WP_CONTENT_URL', $protocol . $_SERVER['HTTP_HOST']);
        define( 'WPMU_PLUGIN_URL', WP_CONTENT_URL . '/extensions' );
        define('WP_PLUGIN_URL', WPMU_PLUGIN_URL); 
        wp_cache_set( 'alloptions', [
		"blog_charset" => 'utf8',
		"cron" => ['version' => 2],
            "can_compress_scripts" => 0], 'options');
        parent::InitPlugin();
        $this->loadComponents();

        do_action( 'after_setup_theme' );
        do_action( 'init' );
    }
}
