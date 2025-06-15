<?php
/*
 * Functions related to logging in from portal
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
 * @version    6.x Last Update: 2023-08-29
 * @filesource /controllers/bizuno/portal.php
 */

namespace bizuno;

/**
 * This is the main entry point to gain access to Bizuno but will mostly depend on the
 * host system. The guest.php script will need to handle most of the functionality of
 * this class.
 */
bizAutoLoad(BIZBOOKS_ROOT.'controllers/portal/guest.php', 'guest');

class bizunoPortal extends guest
{
    public $moduleID = 'bizuno';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        parent::__construct();
    }

    /**
     * Main login landing page
     * @param type $layout
     */
    public function login(&$layout=[])
    {
        msgLog(lang('user_login').": ".getUserCache('profile', 'email', false, 0));
        $browserData = [];
        compose('bizuno', 'admin', 'loadBrowserSession', $browserData); // get the browser data
        $sessionData = $browserData['content'];
        $action = "var sData=".json_encode($sessionData)."; sessionStorage.setItem('bizuno', JSON.stringify(sData)); window.location=bizunoHome;";
        compose('bizuno', 'main', 'bizunoHome', $layout);
        $layout['jsHead']['initCache'] = $action;
    }

    /**
     * Logs a user off of Bizuno and destroys session, returns to index.php to log in
     */
    public function logout(&$layout=[]) {
        bizClrEncrypt();
        dbClearCache();
        dbWriteCache();
        bizClrCookie('bizunoSession');
        $userID = get_current_user_id();
        update_user_meta( $userID, 'bizbooks_bizID', 0 );
        wp_logout();
        $redirect_url = get_home_url();
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
     * this method provides a hook point for Bizuno Pro and other customizations.
     * @param type $layout
     */
    public function authCallback(&$layout=[])
    {
        // hook into another
        if (bizIsActivated('bizuno-pro')) {
            bizAutoLoad(BIZBOOKS_EXT.'controllers/proLgstc/admin.php', 'proLgstcAdmin');
            $pro = new proLgstcAdmin();
            $pro->authCallback($layout);
        }
    }

    /**
     * Installs extension tables and initialization for a given
     * @param type $layout
     * @return modified $layout
     */
    public function installPlugin(&$layout=[])
    {
        $plugin = clean('plugin', 'cmd', 'get');
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/bizuno/settings.php', 'bizunoSettings');
        $bAdmin = new bizunoSettings();
        foreach (portalModuleList() as $module => $path) {
            if (substr($module, 0, 3) == $plugin) { $bAdmin->moduleInstall($layout, $module, $path); }
        }
    }

    /**
     * Installs extension tables and initialization for a given
     * @param type $layout
     * @return modified $layout
     */
    public function deletePlugin(&$layout=[])
    {
        $plugin = clean('plugin', 'cmd', 'get');
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/bizuno/settings.php', 'bizunoSettings');
        $bAdmin = new bizunoSettings();
        foreach (array_keys(portalModuleList(false)) as $module) {
            $_GET['rID'] = $module;
            if (substr($module, 0, 3) == $plugin) { $bAdmin->moduleDelete($layout); }
        }
    }

    /**
     *
     * @param type $layout
     */
    public function viewCSS()
    {
        $icnSet = clean('icons', ['format'=>'cmd','default'=>'default'], 'get');
        $path   = BIZBOOKS_ROOT.'view/icons/';
        $pathURL= portalIconURL ($icnSet);
        if (!file_exists("{$path}$icnSet.php")) { // icons cannot be found, use default
            $icnSet = 'default';
            $path   = BIZBOOKS_ROOT    .'view/icons/';
            $pathURL= BIZBOOKS_URL_ROOT.'view/icons/';
        }
        $icons = [];
        $output="/* $icnSet */\n";
        require("{$path}$icnSet.php");
        foreach ($icons as $idx => $icon) {
            $output .= ".icon-$idx  { background:url('{$pathURL}$icnSet/16x16/{$icon['path']}') no-repeat; }\n";
            $output .= ".iconM-$idx { background:url('{$pathURL}$icnSet/24x24/{$icon['path']}') no-repeat; }\n";
            $output .= ".iconL-$idx { background:url('{$pathURL}$icnSet/32x32/{$icon['path']}') no-repeat; }\n";
        }
        if (defined('BIZBOOKS_EXT')) {
            $this->addCSS($output, 'pro', '16');
            $this->addCSS($output, 'pro', '32');
        }
        if (defined('BIZBOOKS_LOCALE')) {
            $this->addCSS($output, 'locale', '16');
            $this->addCSS($output, 'locale', '32');
        }
        if (defined('BIZUNO_DATA')) {
            $this->addCSS($output, 'custom', '16');
            $this->addCSS($output, 'custom', '32');
        }
        header("Content-type: text/css; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    private function addCSS(&$output, $type='pro', $size=32)
    {
        switch ($type) {
            default:
            case 'pro':
                $dirPath= BIZBOOKS_EXT    ."view/icons/{$size}x{$size}/";
                $dirURL = BIZBOOKS_URL_EXT."view/icons/{$size}x{$size}/";
                break;
            case 'locale':
                $dirPath= BIZBOOKS_LOCALE    ."../view/icons/{$size}x{$size}/";
                $dirURL = BIZBOOKS_URL_LOCALE."view/icons/{$size}x{$size}/";
                break;
            case 'custom':
                $dirPath= BIZUNO_DATA    ."myExt/view/icons/{$size}x{$size}/";
                $dirURL = BIZBOOKS_URL_FS."&src=".getUserCache('profile','biz_id')."/myExt/view/icons/{$size}x{$size}/";
                break;
        }
        $suffix = $size == 32 ? 'L' : '';
        $output .= "/* $dirURL */\n";
        if (is_dir($dirPath)) {
            $icons = scandir($dirPath);
            foreach ($icons as $icon) {
                if ($icon=='.' || $icon=='..') { continue; }
                $path_parts = pathinfo($icon);
                $output .= ".icon{$suffix}-{$path_parts['filename']} { background:url('{$dirURL}$icon') no-repeat; }\n";
            }
        }
    }
}
