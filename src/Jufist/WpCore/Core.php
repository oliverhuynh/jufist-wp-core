<?php 

namespace Jufist\WpCore;


/**
 * Description of Core
 *
 * @author Oliver Huynh
 */
class Core { 
    public $file;
    public static $instance = [];

    static function GetInstance()
    {
	    $id = get_called_class();
	    if (!isset(self::$instance[$id])) {
	      self::$instance[$id] = new static();    
	    }
	    
	    return self::$instance[$id];
    }

        public function basic()
    {
        add_action('init', function () {
            if (!session_id()) {
	        ini_set('session.save_path',realpath(ROOTDIR . '/tmp'));
                session_start();
            }
        });

        add_action('wp_enqueue_scripts', [$this, 'InitAssets'], 999);
        add_action('admin_enqueue_scripts', [$this, 'InitAssets'], 999);

        // Cron
        if (method_exists($this, 'jufistcron')) {
            $this->InitCronAll();
        }
    }
    function InitCronAll()
    {
        add_action('jufistcron', [$this, 'jufistcron']);
        add_filter('cron_schedules', function ($schedules) {
            $schedules['everyminute'] = [
                'interval' => 60,
                'display' => __('Once Every Minute'),
            ];
            return $schedules;
        });
        add_action('init', [$this, 'InitCron']);
        register_deactivation_hook($this->file, [$this, 'InitCronDeactivate']);
    }

    function InitCronDeactivate()
    {
        $timestamp = wp_next_scheduled('jufistcron');
        wp_unschedule_event($timestamp, 'jufistcron');
    }

    public $cronwithdb = TRUE;

    function InitCron()
    {
       
        if ($this->cronwithdb && !wp_next_scheduled('jufistcron')) {
            wp_schedule_event(time(), 'everyminute', 'jufistcron');
        }

        $this->InitMyPoorCron();
    }

    function InitMyPoorCron()
    {
        if (isset($_GET['jufistcron'])) {
            print "Executing poor cron. ";
            if (isset($_GET['debug'])) {
                $this->InitDebug();
            }
           // $upload_dir = wp_upload_dir();
           //  $basedir = $upload_dir['basedir'];
            $lock = get_temp_dir() . "jufistcron";
            print_r(['lock exists?', file_exists($lock)]);
            if (isset($_GET['force'])) {
                unlink($lock);
            }
            // $optimizerChain = OptimizerChainFactory::create();
            // $optimizerChain->optimize($basedir . "/order/1295/order_1295_1.png", $basedir . "/order/1295/order_1295_1.test.png");
            // Image is not accessible debug
            // /var/www/vhosts/thenotesoflove.com/httpdocs/wp-content/uploads/order/1304/order_1304_70.png
            /*if (isset($_GET['f'])) {
                readfile($_GET['f']);
                exit ;
            }*/
            do_action('jufistcron');
            print "Executed poor cron";
            die();
        }
    }


    public $version=1.28;
    function InitAssets($hook)
    {
        static $added;
        if ($added) {
            return;
        }

        $added = true;
	$d = dirname($this->file);

	if (!file_exists($d . '/dist/index.js')) {
		return ;
	}

        wp_enqueue_script(
	    'no_script_' . base64_encode($d),
            plugins_url('/dist/index.js', $this->file),
            [],// No need depends on jQuery
            $this->version,
            true
        );
    }
    function addInlineScript($script, $directly = false)
    {
	    if ($directly) {
		    // Print it directly
		    print "<script type='text/javascript'>$script</script>";
		    return;
	    }
        static $id = 0;
        $id++;
        wp_register_script('dummy-handle-header' . $id, '');
        wp_enqueue_script('dummy-handle-header' . $id);
        wp_add_inline_script('dummy-handle-header' . $id, $script);
    }

    public $pluginns = "jufist";

    function addVar($varname, $varvalue, $directly = false) {
		    $pluginns = $this->pluginns;
		    $script = "var $pluginns = $pluginns || {}; $pluginns.$varname = " .  json_encode($varvalue) .  ';';
		$this->addInlineScript($script, $directly);
    }


    function insertVar($varname, $varvalue, $directly = false) {
	    $pluginns = $this->pluginns;
	    $script = "var $pluginns = $pluginns || {}; $pluginns.$varname = $pluginns.$varname || []; $pluginns.$varname.push(" .  json_encode($varvalue) . ');';
        $this->addInlineScript($script, $directly);
    }

    public function InitHardDebug() {
	    /* FOR DEBUGGING PURPOSE
 */

/*
declare(ticks=1);

function tick_handler() {
    global $backtrace;
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
}
register_tick_function('tick_handler');

register_shutdown_function(function() use (&$shouldExit) {
    global $backtrace;
    if (true || $_SERVER['REQUEST_URI'] == '/wp-login.php') {
        error_log ('XXX:Something went wrong.' . $_SERVER['REQUEST_URI']);

        error_log(print_r($backtrace, 1));//debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 1));
        return ;
    }
    //
    if (! $shouldExit) {
        return;
    }
});
*/
    }

    public function InitDebug()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        ini_set('display_startup_errors', true);
    }

    function __construct() {
        $reflector = new \ReflectionClass(get_class($this));
        $this->file = $reflector->getFileName();
    }

    public function InitPlugin()
    {
        $this->basic();

    }
}
