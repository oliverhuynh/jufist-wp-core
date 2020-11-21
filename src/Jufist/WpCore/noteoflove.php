<?php
/*
Plugin Name: Note Of Love 
Plugin URI: https://jufist.com
Description: Core features for NoteOfLove
Version: 5.0.0
Author: Oliver Huynh 
Author URI: https://jufist.com
Text Domain: noteoflove-jufist
Domain Path: /langs
Copyright: © 2020 Pluggabl LLC.
WC tested up to: 4.1
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
if (!defined('ABSPATH')) {
    exit(); // Exit if accessed directly
}

define(NOLUPLOADDIR, ABSPATH . 'wp-content/uploads/pdfs');
require "vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;
// use Spatie\ImageOptimizer\OptimizerChainFactory;
use BenMajor\ImageResize\Image;
use iio\libmergepdf\Merger;
use iio\libmergepdf\Pages;

if (!defined('NOLC_DEBUG')) {
    define('NOLC_DEBUG', false);
}

if (!defined('NOLC_DEBUGPDF')) {
    define('NOLC_DEBUGPDF', false);
}

define('NOLPLUGINDIR', untrailingslashit(plugin_dir_path(__FILE__)));

class NoteOfLoveCore
{
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
        wp_register_script('dummy-handle-header', '');
        wp_enqueue_script('dummy-handle-header');
        wp_add_inline_script('dummy-handle-header', $script);
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

    function add_order_item_meta()
    {
        if (!session_id()) {
            session_start();
        }

        //$order_number = $_SESSION['order_number'];

        $key = $_POST['key']; // Define your key here

        $value = $_POST['image']; // Get your value here

        $order_number = $_POST['order_number'];

        $image_no = "order_" . $order_number . "_" . $key; //or Anything You Need

        $image = str_replace('data:image/png;base64,', '', $_POST['image']);

        $upload_dir = wp_upload_dir();

        $basedir = $upload_dir['basedir'];

        if (!file_exists($basedir . '/order/' . $order_number)) {
            mkdir($basedir . '/order/' . $order_number, 0777, true);
        }

        $path = $basedir . '/order/' . $order_number . "/" . $image_no . ".png";

        $img_url =
            site_url() .
            '/wp-content/uploads/order/' .
            $order_number .
            "/" .
            $image_no .
            ".png";

        $status = file_put_contents($path, base64_decode($image));

        if ($status) {
            echo "1";
            // $this->InitDebug();
            $this->order_image_updateorinsert($order_number, $key, $img_url);
        } else {
            echo "0";
        }

        exit();
    }

    public function InitPdf()
    {
        // Define the Buy Membership actions
        add_action('wp_ajax_nolpdfs', [$this, 'pdfs']);
        // Allow anonymous to refresh for faster PDF creation
        add_action('wp_ajax_nopriv_nolpdfs', [$this, 'pdfs']);
        // $this->InitDebug();

        add_action('wp_ajax_add_order_item_meta', [
            $this,
            'add_order_item_meta',
        ]);
        add_action('wp_ajax_nopriv_add_order_item_meta', [
            $this,
            'add_order_item_meta',
        ]);

        $this->addInlineScript(
            'var jufist = jufist || {}; jufist.settings = jufist.settings || {}; jufist.settings.papers = ' .
                json_encode($this->papers) .
                ';'
        );
    }

    public static $lock;
    public function jufistcron()
    {
        set_time_limit(0);
        ini_set('memory_limit', '2640M');

        // Don't turn on until I say
        NoteOfLoveCore::$lock = get_temp_dir() . "jufistcron";
        if (file_exists(NoteOfLoveCore::$lock)) {
            $mtime = filemtime(NoteOfLoveCore::$lock);
            print_r("is being locked");
            if (time() - $mtime >= 10 * 60) {
                // print_r("forcing unlock");
                // @unlink(NoteOfLoveCore::$lock);
            }
            return;
        }

        touch(NoteOfLoveCore::$lock);

        // Finish then unlock
        register_shutdown_function(function () {
            print_r("is being unlocked");
            print_r(NoteOfLoveCore::$lock);
            unlink(NoteOfLoveCore::$lock);
        });

        try {
            $htmlFiles = glob(NOLUPLOADDIR . "/\.*.pdf", GLOB_BRACE);
            foreach ($htmlFiles as $filestring) {
                preg_match('/cards-(\d+).([^\.]*).pdf/', $filestring, $matches);
                if (count($matches) != 3) {
                    continue;
                }
                $order = $matches[1];
                $size = $matches[2];
                $detail = $this->papers[$size];

                // Render to small pages and merge later
                // 1. Count pages and do looping
                $htmlfilestring = $filestring . '.html';
                if (!file_exists($htmlfilestring)) {
                    $htmlPrintingFiles = glob(
                        "$htmlfilestring.*.html",
                        GLOB_BRACE
                    );
                    $this->printingpage = count($htmlPrintingFiles);
                    $htmloutput = $this->html($size, $order);
                    file_put_contents(
                        $htmlfilestring . "." . $this->printingpage . ".html",
                        $htmloutput
                    );
                    file_put_contents(
                        $htmlfilestring . ".json",
                        '{"total": "' .
                            $this->pages .
                            '", "current": "' .
                            ($this->printingpage + 1) .
                            '"}'
                    );

                    // Convert the small PDF
                    $this->makePDF(
                        $htmloutput,
                        $size,
                        $htmlfilestring .
                            '.' .
                            $this->printingpage .
                            ".pdfpartly"
                    );
                    if ($this->pages == $this->printingpage + 1) {
                        touch($htmlfilestring);
                        exit(0);
                    }
                    exit();
                }

                // 2. Do merge after 1st task
                $pdfPrintingFiles = glob(
                    "$htmlfilestring.*.pdfpartly",
                    GLOB_BRACE
                );
                $merger = new Merger();
                foreach ($pdfPrintingFiles as $pfilestring) {
                    $merger->addFile(
                        $pfilestring,
                        new Pages($detail['mergepos'])
                    );
                }
                file_put_contents(
                    NOLUPLOADDIR . "/cards-$order.$size.pdf",
                    $merger->merge()
                );
                unlink($filestring);
                array_map( 'unlink', array_filter((array) glob("${filestring}*") ) );

                // Run only one file pertime
                exit();
                break;
            }
        } catch (Exception $e) {
            print_r($e);
        }
    }

    public function makePDF($htmloutput, $size, $filepath)
    {
        $paper = $this->papers[$size];
        // set_time_limit(0);
        // ini_set('memory_limit', '-1');
        define("DOMPDF_ENABLE_REMOTE", true);

        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $contxt = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);
        $dompdf->setHttpContext($contxt);
        $dompdf->loadHtml($htmloutput);
        $portrait = $paper['portrait'];
        $dompdf->setPaper($paper['size'], $paper['portrait']);
        $dompdf->render();

        // $dompdf->stream("cards-$order.$size.pdf", array("Attachment"=>1));
        $output = $dompdf->output();
        file_put_contents($filepath, $output);
    }

    // https://github.com/dompdf/dompdf/blob/42e16f120c40abdc825a171140f438a1373bc3e5/src/Adapter/CPDF.php
    public $papers = [
        'Sample' => [
            'size' => [
                0,
                0,
                595.28 * (8.79 / 8.27),
                595.28 * (8.79 / 8.27) * (4.66 / 8.79),
            ],
            'portrait' => 'portrait',
            'splitcount' => 0,
            'itemsperpage' => 1,
            'mergepos' => 1,
            'css' => '
.Sample .card {
position: absolute;
top: 0;
left: 0;
}
                
                ',
        ],
        /*'A4' => [
                'size' => [0, 0, 595.28, 841.89],
                'portrait' => 'portrait',
                'splitcount' => 0,
                'itemsperpage' => 2,
                'mergepos' => 1,
                'css' => '
                .A4 .row > div:nth-child(2n+3) {
                page-break-before: always;
                }
                '
            ]*/
        /**/
        /*,
            'A3' => [
                'disabled' => true,
                'splitcount' => 2,
                'itemsperpage' => 4,
                'mergepos' => 1,
                'css' => '
                .A3 .row > div {
                float: left;
                }

                .A3 .wrap > div:nth-child(2n+3) {
                page-break-before: always;
                }
                '
            ]*/
    ];

    public function pdfs($print = false)
    {
        // $this->InitDebug();
        if (!is_dir(NOLUPLOADDIR)) {
            mkdir(NOLUPLOADDIR);
        }
        set_time_limit(0);
        // ini_set('memory_limit', '-1');
        ini_set('memory_limit', '640M');

        $order = $_POST['order'];
        if ($order == 'session') {
            $order = $_SESSION['order_number'];
            if (!$order) {
                wp_send_json_success(['status' => 'error']);
                return;
            }
        }
        $size = $_POST['size'];
        if ($size == 'all') {
            $ret = [];

            ob_start();
            foreach ($this->papers as $paper => $settings) {
                $_POST['size'] = $paper;
                $this->pdfs(true);
                $ret[] = ob_get_contents();
            }
            ob_end_clean();
            wp_send_json_success(['ret' => $ret]);
            return;
        }

        $force = isset($_POST['force']) ? $_POST['force'] != 'false' : false;

        // Check for result already built
        if (!$force && file_exists(NOLUPLOADDIR . "/cards-$order.$size.pdf")) {
            !$print &&
                wp_send_json_success([
                    'url' => "/wp-content/uploads/pdfs/cards-$order.$size.pdf",
                ]);
            $print &&
                print_r([
                    'url' => "/wp-content/uploads/pdfs/cards-$order.$size.pdf",
                ]);
            return;
        }

        // Check for status
        $mtime = time();
        if (!$force && file_exists(NOLUPLOADDIR . "/.cards-$order.$size.pdf")) {
            $mtime = filemtime(NOLUPLOADDIR . "/.cards-$order.$size.pdf");
            if (
                file_exists(
                    NOLUPLOADDIR . "/.cards-$order.$size.pdf.html.0.html"
                )
            ) {
                $mtime = filemtime(
                    NOLUPLOADDIR . "/.cards-$order.$size.pdf.html.0.html"
                );
            }
            // Should be same with bottom
            $json = [];
            try {
                $json = file_get_contents(
                    NOLUPLOADDIR . "/.cards-$order.$size.pdf.html.json"
                );
                $json = json_decode($json, true);
            } catch (Exception $e) {
                $json = [];
            }
            $ret = [
                'status' => "inprogress",
                "elapsed" => time() - $mtime,
                "start" => $mtime,
                'detail' => $json,
            ];
            !$print && wp_send_json_success($ret);
            $print && print_r($ret);
            return;
        }

        file_put_contents(
            NOLUPLOADDIR . "/.cards-$order.$size.pdf",
            'inprogress'
        );
        unlink(NOLUPLOADDIR . "/cards-$order.$size.pdf");
        // Force by removing html
        $filestring = NOLUPLOADDIR . "/.cards-$order.$size.pdf.html";
        @unlink($filestring);
        array_map('unlink', array_filter((array) glob("${filestring}*")));
        // Should be same with bottom
        !$print &&
            wp_send_json_success([
                'status' => "inprogress",
                "elapsed" => time() - $mtime,
                "start" => $mtime,
            ]);
        $print &&
            print_r([
                'status' => "inprogress",
                "elapsed" => time() - $mtime,
                "start" => $mtime,
            ]);

        return;

        // DB can be closed from now. NO. all sizes case.
        // global $wpdb;
        // $wpdb->close();

        // Debug mode
        if (NOLC_DEBUGPDF) {
            print $this->browser($htmloutput);
            return;
        }
    }

    public function browser($s)
    {
        // base64 already
        // $s = str_replace("/app/", "/", $s);
        return $s;
    }

    function image($img, $width, $height, $padding = 33)
    {
        $padding = $padding * $this->reallife;
        $pw = $width + $padding * 2;
        $ph = $height + $padding * 2;
        $lht = $padding; // Lineheight
        $ab = (int) ($padding / 7); // A bit space of line
        $alht = $lht - $ab; // Lineheight without space
        $nlh = $ph - $lht; // Full height no line height
        $nlw = $pw - $lht; // Full width no line height
        $nalh = $nlh + $ab; // Height - lineheight - space
        $nalw = $nlw + $ab; // Width - lineheight - space
        $svg = <<<EOX
<svg width="$pw" height="$ph" xmlns="http://www.w3.org/2000/svg">
 <g>
  <title>Cutting Lines</title>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_1" y2="0" x2="$lht" y1="$alht" x1="$lht" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_2" y2="$lht" x2="0" y1="$lht" x1="$alht" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_3" y2="$nalh" x2="$lht" y1="$ph" x1="$lht" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_4" y2="$nlh" x2="$alht" y1="$nlh" x1="0" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_5" y2="$lht" x2="$nalw" y1="$lht" x1="$pw" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_6" y2="$alht" x2="$nlw" y1="0" x1="$nlw" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_7" y2="$nlh" x2="$nalw" y1="$nlh" x1="$pw" stroke-width="0.5" stroke="#000" fill="none"/>
  <line stroke-linecap="undefined" stroke-linejoin="undefined" id="svg_8" y2="$nalh" x2="$nlw" y1="$ph" x1="$nlw" stroke-width="0.5" stroke="#000" fill="none"/>
 </g>
</svg>
EOX;

        $svg = base64_encode($svg);
        $type = pathinfo($img, PATHINFO_EXTENSION);

        // Resize image, postponed. quality issue;
        // $this->InitDebug();
        // $imageObj = new Image($img);
        // $imageObj->resizeWidth( $width );
        // $base64 = $imageObj->outputHTML(false);

        $data = file_get_contents($img);
        $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);

        return <<<EOT
        <div style="width: ${pw}px; height: ${ph}px; position: relative;" class="card">
        <div style="width: ${pw}px; height: ${ph}px;top: ${padding}px;left: ${padding}px; position: absolute;">
        <img src="$base64" width="$width" height="$height"/>
        </div>
        <img src="data:image/svg+xml;base64,${svg}" width="${pw}px" height="${ph}px" style="position:absolute;top:0;left:0;" />
        </div>
EOT;
    }

    // Tip: Resize document viewer to 106.4 and compare with sample
    public $reallife = 1.333;
    // public $reallife = 341717;//1.3283;
    public function convert($size)
    {
        $ratio = 28.3465 * $this->reallife;
        return [intval($size[0] * $ratio), intval($size[1] * $ratio)];
    }

    public function order_image_update($order_id, $key, $img_url)
    {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {
            $item_data = $item->get_data();
            $item_meta_data = $item->get_meta_data();
            if (!is_array($item_meta_data)) {
                continue;
            }
            foreach ($item_meta_data as $record) {
            }
        }
    }

    public function order_image_updateorinsert($order_id, $key, $img_url)
    {
        $found = false;
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        $base = get_site_url();

        $boxid = 'xxx';
        $rowcount = 0;
        foreach ($order->get_items() as $item) {
            $item_data = $item->get_data();
            $item_meta_data = $item->get_meta_data();
            if (!is_array($item_meta_data)) {
                continue;
            }

            $found = false;
            foreach ($item_meta_data as $record) {
                if (strpos($record->key, 'Note') !== 0 || !$record->value) {
                    if (!$rowcount && $record->key == 'Box id') {
                        $boxid = $record->value;
                        global $wpdb;
                        $sql = "select count(*) from notetext where boxid='{$boxid}'";
                        $rowcount = $wpdb->get_var($sql);
                    }
                    continue;
                }

                // Cleanup
                if ($rowcount) {
                    $number = substr($record->key, 4);
                    if ($number > $rowcount) {
                        wc_delete_order_item_meta(
                            $item->get_id(),
                            $record->key
                        );
                    }
                }

                if ($record->key == 'Note' . $key) {
                    // More cleanup on update
                    wc_delete_order_item_meta(
                        $item->get_id(),
                        $record->key,
                        '',
                        true
                    );
                    // wc_update_order_item_meta($item->get_id(), $record->key, $img_url);

                    wc_add_order_item_meta(
                        $item->get_id(),
                        $record->key,
                        $img_url
                    );
                    $found = true;
                }
                // print_r($record);
                // die(0);
            }

            // Insert
            if (!$found) {
                wc_add_order_item_meta(
                    $item->get_id(),
                    'Note' . $key,
                    $img_url
                );
            }
        }

        return !$found;
    }

    public function order_images($order_id)
    {
        $images = [];
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();
        $base = get_site_url();
        $boxid = 'xxx';
        foreach ($order->get_items() as $item) {
            $item_data = $item->get_data();
            $item_meta_data = $item->get_meta_data();
            if (!is_array($item_meta_data)) {
                continue;
            }
            foreach ($item_meta_data as $record) {
                if (strpos($record->key, 'Note') !== 0 || !$record->value) {
                    if ($record->key == 'Box id') {
                        $boxid = $record->value;
                    }
                    continue;
                }
                $v = $record->value;
                $v = str_replace($base . '/', ABSPATH, $v);
                $images[] = $v;
            }
        }
        $images = array_unique($images);

        // Respect the boxid
        global $wpdb;
        $sql = "select count(*) from notetext where boxid='{$boxid}'";
        $rowcount = $wpdb->get_var($sql);
        sort($images);

        $limit = $rowcount;
        if (NOLC_DEBUG) {
            $limit = 1;
        }
        return array_slice($images, 0, $limit);
    }

    public function acard($img)
    {
        $size = ['20', '9.5'];
        $pxsize = $this->convert($size);
        return $this->image($img, $pxsize[0], $pxsize[1]);
    }

    private $pages = 0;
    private $printingpage = false;

    public function html($papersize, $order_id)
    {
        // 20cm x 9.5cm a card
        $size = ['20', '9.5'];
        // Cur Image is 2360x1120
        $items = $this->order_images($order_id);
        // $items = array_slice($items, 0, 8);
        // $items = ['/app/wp-content/uploads/order/1306/order_1306_68.png', '/app/wp-content/uploads/order/1306/order_1306_68.png', '/app/wp-content/uploads/order/1306/order_1306_68.png', '/app/wp-content/uploads/order/1306/order_1306_68.png', '/app/wp-content/uploads/order/1306/order_1306_68.png', '/app/wp-content/uploads/order/1306/order_1306_68.png', '/app/wp-content/uploads/order/1306/order_1306_68.png'];
        $images = '';

        // Make sure CSS page-break-before  reflects this also
        extract($this->papers[$papersize]);

        // Allow partial printing
        $this->pages = ceil(count($items) / $itemsperpage);
        if ($this->printingpage !== false) {
            $items = array_slice(
                $items,
                $itemsperpage * $this->printingpage,
                $itemsperpage
            );
        }

        $pos = 0;
        foreach ($items as $item) {
            $pos++;
            $images .= $this->acard($item);
            if ($splitcount && $pos % $splitcount === 0) {
                $images .= '</div><div class="row">';
            }
        }

        return $this->rawhtml($papersize, $images);
    }

    public function rawhtml($papersize, $images)
    {
        extract($this->papers[$papersize]);
        return <<<EOT
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <style>
@page { margin: 0px; }
body { margin: 0px; }
div, span, img {
  box-sizing: border-box;
}
.row {
 clear: both;
}
.row > div {
  margin: 0px;
}

.card {
  page-break-inside: avoid;
}

${css}

.clearfix {
  clear: both;
  float: none !important;
}

</style>
</head>
<body class="$papersize">
<div class="wrap">
<div class="row">
$images
</div>
</div>
</body>
</html>
EOT;
    }
}

$noteoflove = NoteOfLoveCore::GetInstance();
$noteoflove->InitPlugin();
