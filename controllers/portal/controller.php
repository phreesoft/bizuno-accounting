<?php
/*
 * Wordpress Plugin - main entry controller
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
 * @version    3.x Last Update: 2023-05-03
 * @filesource /controllers/portal/controller.php
 */

namespace bizuno;

if (!defined('BIZBOOKS_ROOT')) { require_once ( __DIR__ . "/../../bizunoCFG.php" ); }

// set some sitewide constants
define('COG_ITEM_TYPES', 'ma,mi,ms,sa,si,sr');

require_once (BIZBOOKS_ROOT.'model/functions.php');
bizAutoLoad(BIZBOOKS_ROOT.'locale/cleaner.php',   'cleaner');
bizAutoLoad(BIZBOOKS_ROOT.'locale/currency.php',  'currency');
bizAutoLoad(BIZBOOKS_ROOT.'model/db.php',         'db');
bizAutoLoad(BIZBOOKS_ROOT.'model/encrypter.php',  'encryption');
bizAutoLoad(BIZBOOKS_ROOT.'model/io.php',         'io');
bizAutoLoad(BIZBOOKS_ROOT.'model/msg.php',        'messageStack');
bizAutoLoad(BIZBOOKS_ROOT.'model/portal.php',     'portal');
bizAutoLoad(BIZBOOKS_ROOT.'view/main.php',        'view');
bizAutoLoad(BIZBOOKS_ROOT.'view/easyUI/html5.php','html5');

class portalCtl
{
    public  $layout = [];
    private $userValidated = false;
    private $bizValidated  = false;

    function __construct()
    {
        global $msgStack, $cleaner, $html5, $mixer, $io, $portal, $portalDB;
        $msgStack= new messageStack();
        $cleaner = new cleaner();
        $html5   = new html5();
        $mixer   = new encryption();
        $portal  = new portal();
        $GLOBALS['myDevice'] = detectDevice(); // 'desktop' or 'mobile';
//      $GLOBALS['myDevice'] = 'mobile'; // for testing mobile behavior on desktop devices
        $email = $this->validateUser();
        setUserCache('profile', 'biz_id', $this->validateBusiness());
        $this->initBusiness();
        $io      = new io(); // needs to be AFTER BIZUNO_DATA is defined
        $portalDB= new db($GLOBALS['dbPortal']);
        $this->initUserCache($email);
        $this->initModuleCache();
    }

    public function compose()
    {
        clean('bizRt', 'command', 'get');
        compose($GLOBALS['bizunoModule'], $GLOBALS['bizunoPage'], $GLOBALS['bizunoMethod'], $this->layout);
        return $this->layout;
    }

    public function getMessages()
    {
        global $msgStack;
        $msgStack->debugWrite();
        return $msgStack->error;
    }

    private function validateUser()
    {
        global $bizunoUser, $bizunoLang;
        $bizunoUser = $this->setGuestCache();
        $bizunoLang = loadBaseLang($bizunoUser['profile']['language']);
        $email  = '';
        if (is_user_logged_in() ) {
            $wpUser = wp_get_current_user();
            $email =  $wpUser->user_email;
            $this->userValidated = true;
            setUserCache('profile', 'email', $email);
        }
        msgDebug("\nLeaving validateUser with email = $email and user validated: ".($this->userValidated ? 'true' : 'false'));
        return $email;
    }

    private function validateBusiness()
    {
        msgDebug("\nEntering validateBusiness");
        if (!$this->userValidated) { return; } // not logged in
        $access = false;
        $userID = get_current_user_id();
        foreach ($GLOBALS['bizPortal'] as $biz) {
            if ( !empty(get_user_meta( $userID, 'bizbooks_enable_'.$biz['id'], true ) ) ) { $access = true; break; }
        }
        if (!$access ) { msgDebug("\nNo access to any businesses, bailing..."); return; }
        $bizID  = get_user_meta( $userID, 'bizbooks_bizID', true ); // check for last biz accessed
        msgDebug("\nWP user meta bizbooks_bizID = $bizID");
        if ($bizID) { return $bizID; } // logged in and business selected
        $bID    = clean('bizID', 'integer', 'get'); // see if a biz id was passed
        $bizList= portalGetBizIDs();
        if (sizeof($bizList) == 1) {
            $biz   = array_shift($bizList);
            $bizID = intval($biz['id']);
            update_user_meta( $userID, 'bizbooks_bizID', $bizID );
        } else {
            foreach ($bizList as $biz) {
                if ($biz['id'] == $bID) {
                    $bizID = intval($biz['id']);
                    update_user_meta( $userID, 'bizbooks_bizID', $bizID );
                    break;
                }
            }
        }
        msgDebug("\nLeaving validateBusiness with bizID = $bizID");
        return $bizID;
    }

    private function initBusiness()
    {
        global $db;
        $bizID = getUserCache('profile', 'biz_id');
        msgDebug("\nEntering initBusiness with biz_id = $bizID");
        $myBiz = false;
        foreach ($GLOBALS['bizPortal'] as $row) { // if logged in
            if ($bizID==$row['id']) { $myBiz = $row; break; }
        }
        if (empty($myBiz)) { // no business selected, find default if multi-business
            foreach ($GLOBALS['bizPortal'] as $row) {
                if (!empty($row['default'])) { $myBiz = $row; break; }
            }
        }
        if (empty($myBiz)) { $myBiz = $GLOBALS['bizPortal'][0]; } // single business or no default defined, assume default is first business
//        $bizID = $myBiz['id'];
        if (!defined('BIZUNO_DB_PREFIX')) { define('BIZUNO_DB_PREFIX',$myBiz['prefix']); }
        if (!defined('BIZUNO_DATA'))      { define('BIZUNO_DATA',     $myBiz['data']); }
        $GLOBALS['dbBizuno'] = $myBiz;
        $db = new db($GLOBALS['dbBizuno']);
        $this->validateInstall($bizID);
        msgDebug("\nReturning from initBusiness with biz_id = $bizID and BIZUNO_DATA = ".BIZUNO_DATA);
    }

    private function initUserCache($email='')
    {
        global $bizunoUser, $bizunoLang;
        $bizID = getUserCache('profile', 'biz_id');
        msgDebug("\nEntering initUserCache with email = $email and bizID = $bizID");
        if ($this->userValidated && !empty($bizID) && empty($GLOBALS['bizuno_not_installed'])) {
            $usrData = dbGetRow(BIZUNO_DB_PREFIX.'users', "email='$email'", true, false);
            if       (!empty($usrData) && (empty($usrData['cache_date']) || $usrData['cache_date']=='0000-00-00 00:00:00')) { // logged in, need to reload cache
                $this->reloadCache($email, $bizID);
            } elseif (!empty($usrData)) { // logged in, normal just get settings
                $bizunoUser = json_decode($usrData['settings'], true);
            } else { msgDebug("\nCreating Bizuno user record"); //create record and load cache
                $this->setNewUser($email, $bizID);
                $this->reloadCache($email, $bizID);
            }
            $bizunoUser['security'] = dbGetSecurity();
        }
        $bizunoLang = loadBaseLang($bizunoUser['profile']['language']);
        setlocale(LC_COLLATE,getUserCache('profile', 'langauge'));
        setlocale(LC_CTYPE,  getUserCache('profile', 'langauge'));
        msgDebug("\nLeaving initUserCache with cached email = {$bizunoUser['profile']['email']}");
    }

    private function initModuleCache()
    {
        global $bizunoMod, $currencies;
        $bizID = getUserCache('profile', 'biz_id');
        msgDebug("\nEntering initModuleCache");
        $bizunoMod = [];
        if (!empty($GLOBALS['dbBizuno']) && !empty($bizID) && empty($GLOBALS['bizuno_not_installed'])) { // logged in, fetch the cache from db
            $rows = dbGetMulti(BIZUNO_DB_PREFIX.'configuration');
            foreach ($rows as $row) { $bizunoMod[$row['config_key']] = json_decode($row['config_value'], true); }
            if (biz_date('Y-m-d') > getModuleCache('phreebooks', 'fy', 'period_end')) { periodAutoUpdate(false); }
            $this->validateVersion($bizunoMod['bizuno']['properties']['version']);
        } else { // set basic registry, only needed for install
            msgDebug("\nSetting guest module cache");
            require_once(BIZBOOKS_ROOT."model/registry.php");
            $registry = new bizRegistry();
            $registry->initModule('bizuno', BIZBOOKS_ROOT."controllers/bizuno/"); // load the bizuno structure
            if (file_exists(BIZUNO_DATA."myExt/controllers/myPortal/admin.php")) { $registry->initModule('myPortal', BIZUNO_DATA."myExt/controllers/myPortal/"); }
        }
        date_default_timezone_set($bizunoMod['bizuno']['settings']['locale']['timezone']);
        $currencies = new currency(); // Needs PhreeBooks cache loaded to properly initialize otherwise defaults to USD
    }

    /**
     * Validates that Bizuno is installed
     */
    private function validateInstall($bizID)
    {
        msgDebug("\nValidating Bizuno is installed for bizID = $bizID ... ");
        if (empty($bizID)) { return; }
        if (!dbTableExists(BIZUNO_DB_PREFIX.'configuration')) { // logged in but bizuno not installed
//            $GLOBALS['bizuno_install_admin_id']= 1; // set flags used when requesting to install
//            $GLOBALS['bizuno_install_biz_id']  = $bizID;
            setUserCache('dashboards', 'login', ['column_id'=>0,'row_id'=>0,'module_id'=>'bizuno','dashboard_id'=>'install']);
            setUserCache('profile', 'admin_id',1);
            setUserCache('profile', 'role_id', 1);
            setUserCache('profile', 'biz_id',  $bizID);
            $GLOBALS['bizuno_not_installed'] = true;
            msgDebug("\nBIZUNO IS NOT INSTALLED!");
            return;
        }
        msgDebug("\nWe're good, Bizuno is installed.");
    }

    private function validateVersion($dbVersion=1.0)
    {
        msgDebug("\nValidating installed Bizuno version ".MODULE_BIZUNO_VERSION." to db version: $dbVersion");
        if (empty(getUserCache('profile', 'biz_id'))) { return; }
        if (version_compare(MODULE_BIZUNO_VERSION, $dbVersion) > 0) {
            if (file_exists( BIZBOOKS_ROOT."controllers/portal/upgrade.php")) {
                require_once(BIZBOOKS_ROOT."controllers/portal/upgrade.php");
                bizunoUpgrade($dbVersion);
                setModuleCache('bizuno', 'properties', 'version', MODULE_BIZUNO_VERSION);
                // write cache and reload with new changes
                dbWriteCache();
                $this->reloadCache(getUserCache('profile', 'email'), getUserCache('profile', 'biz_id'));
            }
        }
    }

    private function reloadCache($usrEmail, $bizID)
    {
        msgDebug("\nEntering reloadCache with email = $usrEmail and bizID = $bizID");
        bizAutoLoad(BIZBOOKS_ROOT."model/registry.php", 'bizRegistry');
        $registry = new bizRegistry();
        $registry->initRegistry($usrEmail, $bizID);
    }

    public function setGuestCache($usrEmail='')
    {
        msgDebug("\nGet Locale returned: ".print_r(\get_locale(), true));
        $settings = [
            'profile'   => ['email'=>$usrEmail, 'admin_id'=>0, 'biz_id'=>0],
            'dashboards'=> [
                'login' => ['column_id'=>0,'row_id'=>0,'module_id'=>'bizuno','dashboard_id'=>'login'],
//              'tip'   => ['column_id'=>1,'row_id'=>1,'module_id'=>'bizuno','dashboard_id'=>'daily_tip'],
//              'news'  => ['column_id'=>2,'row_id'=>2,'module_id'=>'bizuno','dashboard_id'=>'ps_news'],
                ]];
        $lang = clean('bizunoLang', 'cmd', 'cookie');
        msgDebug("\nGuest cache with language cookie = $lang");
        $settings['profile']['language'] = !empty($lang) ? $lang : \get_locale();
        msgDebug("\nReturn from guest cache with language = ".$settings['profile']['language']);
        return $settings;
    }

    /**
     * Creates new record in Bizuno users table to sync with WordPress
     * @param type $email
     */
    private function setNewUser($email='', $bizID=0)
    {
        msgDebug("\nEntering setNewUser with email: $email and bizID = $bizID");
        $wpUser  = get_user_by( 'email', $email );
        $bizRole = get_user_meta( $wpUser->ID, "bizbooks_role_$bizID", true );
        if (!empty($email) && !empty($bizRole)) {
            dbWrite(BIZUNO_DB_PREFIX.'users', ['email'=>$email, 'title'=>$wpUser->display_name, 'role_id'=>$bizRole ]);
        }
    }
}
