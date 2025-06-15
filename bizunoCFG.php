<?php
/*
 * WordPress Plugin - Configuration file to work in parallel with WordPress
 *
 * NOTICE OF LICENSE
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.TXT.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/OSL-3.0
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please refer to http://www.phreesoft.com for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2024, PhreeSoft, Inc.
 * @license    http://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * @version    3.x Last Update: 2023-08-27
 * @filesource /bizunoCFG.php
 */

if (!defined( 'ABSPATH' )) { die( 'No script kiddies please!' ); }

if (!defined('SCRIPT_START_TIME')) { define('SCRIPT_START_TIME', microtime(true)); }

global $wpdb;

// URL paths
$page  = get_page_by_path('bizuno');
$perma = !empty($page) ? get_permalink( $page->ID ) : '';
$pluginURL = str_replace('bizuno-accounting/', '', plugin_dir_url( __FILE__ ));
define('BIZUNO_HOST',        'wordpress');
define('BIZUNO_HOME',        strpos($perma, '?')===false ? $perma.'?' : $perma); // Full URL path to Bizuno Home
define('BIZUNO_AJAX',        admin_url().'admin-ajax.php?action=bizuno_ajax'); // for non-html requests
//define('BIZOFFICE_HOME',   'index.php?page=bizoffice');
//define('BIZOFFICE_AJAX',   admin_url().'admin-ajax.php?action=bizoffice_ajax');
define('BIZUNO_SRVR',        'http'.(!empty($_SERVER['HTTPS'])?"s":"")."://".$_SERVER['SERVER_NAME'].'/'); // WordPress root folder
define('BIZBOOKS_URL_ROOT',  $pluginURL.'bizuno-accounting/'); // full url to Bizuno root folder
define('BIZBOOKS_URL_EXT',   $pluginURL.'bizuno-pro/'); // full url to Bizuno Pro plugin folder
define('BIZBOOKS_URL_LOCALE',$pluginURL.'bizuno-locale/'); // full url to Bizuno Locale plugin folder
define('BIZBOOKS_URL_FS',    admin_url().'admin-ajax.php?action=bizuno_ajax_fs'); // full url to Bizuno data root folder
define('BIZUNO_LOGO',        $pluginURL.'bizuno-accounting/view/images/bizuno.png'); // URL to default logo

// File system paths
$pluginRoot = str_replace('bizuno-accounting/', '', plugin_dir_path( __FILE__ ));
define('BIZBOOKS_ROOT',      $pluginRoot.'bizuno-accounting/'); // file system path to bizuno root index file
define('BIZBOOKS_API',       $pluginRoot.'bizuno-api/'); // file system path to Bizuno API
define('BIZBOOKS_EXT',       $pluginRoot.'bizuno-pro/'); // file system path to Bizuno pro
define('BIZBOOKS_LOCALE',    $pluginRoot.'bizuno-locale/language/'); // file system path to non-US locale folder

// Database
$upload_dir = wp_upload_dir();
define('PORTAL_DB_PREFIX',   $wpdb->prefix); // WordPress table prefix
$GLOBALS['dbPortal']  = ['type'=>'mysql','host'=>DB_HOST,'name'=>DB_NAME,'user'=>DB_USER,'pass'=>DB_PASSWORD,'prefix'=>PORTAL_DB_PREFIX];
$GLOBALS['bizPortal'] = [['id'=>1,'title'=>'My Business','data'=>$upload_dir['basedir'].'/bizuno/','type'=>'mysql','host'=>DB_HOST,'name'=>DB_NAME,'user'=>DB_USER,'pass'=>DB_PASSWORD,'prefix'=>$wpdb->prefix.'bizuno_']];
if     (file_exists($upload_dir['basedir']."/bizCustom.php"))        { require_once($upload_dir['basedir']."/bizCustom.php"); }
elseif (file_exists($upload_dir['basedir']."/bizuno/bizCustom.php")) { require_once($upload_dir['basedir']."/bizuno/bizCustom.php"); }

// Third Party Apps
define('BIZUNO_3P_PDF', BIZBOOKS_ROOT.'assets/TCPDF/');

// Other
define('BIZUNO_HOST_UPGRADE',  true); // upgrades are handled through the host
define('BIZUNO_STRIP_SLASHES', true); // WordPress adds slashes to all input data

// Legacy
define('BIZUNO_LIB', BIZBOOKS_ROOT);
//define('BIZUNO_EXT', BIZBOOKS_EXT);
