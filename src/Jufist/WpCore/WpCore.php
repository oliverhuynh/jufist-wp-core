<?php 

namespace Jufist\WpCore;

/**
 * Description of WpCore
 *
 * @author Oliver Huynh
 */
class WpCore { 
    private static $instance;
    static function GetInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new static();
        }
        return self::$instance;
    }

        public function basic()
    {
        add_action('init', function () {
            if (!session_id()) {
                session_start();
            }
        });

        add_action('wp_enqueue_scripts', [$this, 'InitAssets']);
        add_action('admin_enqueue_scripts', [$this, 'InitAssets']);

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
        register_deactivation_hook(__FILE__, [$this, 'InitCronDeactivate']);
    }

    function InitCronDeactivate()
    {
        $timestamp = wp_next_scheduled('jufistcron');
        wp_unschedule_event($timestamp, 'jufistcron');
    }

    function InitCron()
    {
        if (!wp_next_scheduled('jufistcron')) {
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
            $upload_dir = wp_upload_dir();
            $basedir = $upload_dir['basedir'];
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

    function InitAssets($hook)
    {
        static $added;
        if ($added) {
            return;
        }
        $added = true;

        wp_enqueue_script(
            'no_script',
            plugins_url('/dist/index.js', __FILE__),
            ['jquery'],
            1.28,
            true
        );
    }
    function addInlineScript($script)
    {
        static $id = 0;
        $id++;
        wp_register_script('dummy-handle-header' + $id, '');
        wp_enqueue_script('dummy-handle-header' + $id);
        wp_add_inline_script('dummy-handle-header' + $id, $script);
    }
    public function InitDebug()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        ini_set('display_startup_errors', true);
    }

    public function InitPlugin()
    {
        $this->basic();
        // $this->InitDebug();
        $this->InitPDF();
    }
}
