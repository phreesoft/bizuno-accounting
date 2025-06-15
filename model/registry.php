<?php
/*
 * Registry class used to manage user/business environmental variables and settings
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
 * @copyright  2008-2024, PhreeSoft Inc.
 * @license    http://opensource.org/licenses/OSL-3.0  Open Software License (OSL 3.0)
 * @version    6.x Last Update: 2023-03-01
 * @filesource /model/registry.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT."controllers/portal/guest.php", 'guest');

final class bizRegistry
{
    private $addUpgrade = 0;
    private $quickBar   = [];
    private $menuBar    = [];

    function __construct() {
        $this->dbVersion = MODULE_BIZUNO_VERSION;
        $this->guest = new guest();
    }

    /**
     * Takes basic module properties and builds inter-dependencies
     * @global array $bizunoMod
     * @global array $bizunoUser
     * @param string $usrEmail - user email
     * @param integer $bizID - business ID
     * @return  null - registry created and saved
     */
    public function initRegistry($usrEmail='', $bizID=0)
    {
        global $bizunoMod, $bizunoUser, $bizunoLang;
        msgDebug("\nEntering initRegistry with email = $usrEmail and bizID=$bizID");
        $bizunoLang= loadBaseLang($bizunoUser['profile']['language']);
        $validMods = portalModuleList();
        $bizunoMod = $this->initSettings($validMods);
        foreach ($validMods as $module => $path) { $this->initModule($module, $path); }
        if (!$this->initUser($usrEmail, $bizID)) { return; }
//        $this->initAccount();
//        $this->setUserSecurity();
//        $this->setUserMenu();
        $this->setRoleMenus();
        // Unique module initializations
        $this->initBizuno($bizunoMod);
        $this->initPhreeBooks($bizunoMod); // taxes
        $this->initPhreeForm($bizunoMod); // report structure
        dbWriteCache($usrEmail, true);
        msgDebug("\nReturning from initRegistry");
    }

    /**
     * Load original configuration, properties get reloaded but other
     * @return type
     */
    private function initSettings($allMods=[])
    {
        global $bizunoMod;
        $layout = $modSettings = [];
        $dbMods = dbGetMulti(BIZUNO_DB_PREFIX.'configuration', "config_key IN ('".implode("','", array_keys($allMods))."')");
        foreach ($dbMods as $row) { $cfgMods[$row['config_key']] = json_decode($row['config_value'], true); }
        foreach ($allMods as $modID => $path) {
            if (isset($cfgMods[$modID])) {
                msgDebug("\nConfig database data is set for module: $modID");
                $modSettings[$modID] = $cfgMods[$modID];
            } else {
                msgDebug("\nConfig database data is NOT set for module: $modID, trying to install.");
                bizAutoLoad(BIZBOOKS_ROOT ."controllers/bizuno/settings.php", 'bizunoSettings');
                setUserCache('security', 'admin', 3); // temp set security to install since it is active
                $bAdmin = new bizunoSettings();
                $bAdmin->moduleInstall($layout, $modID, $path);
                $modSettings[$modID] = $bizunoMod[$modID];
            }
            unset($modSettings[$modID]['hooks']); // will clear hooks to be rebuilt later
        }
        unset($modSettings['bizuno']['api']); // will clear list to be rebuilt later
        // get the Bizuno database version and retain for upgrade check
        $this->dbVersion = !empty($modSettings['bizuno']['properties']['version']) ? $modSettings['bizuno']['properties']['version'] : '4.1.0';
        msgDebug("\ndbVersion has been stored for bizuno module with $this->dbVersion");
        return $modSettings;
    }

    /**
     * Initializes a single module
     * @global array $bizunoMod - working module registry
     * @param string $module - module to initialize
     * @param string $path - path to module
     * @return updated $bizunoMod
     */
    public function initModule($module, $relPath)
    {
        global $bizunoMod;
        $path = bizAutoLoadMap($relPath);
        if (!file_exists("{$path}admin.php")) {
            unset($bizunoMod[$module]);
            // @todo delete the configuration db entry as the module has been removed manually and cannot be found
            return msgAdd("initModule cannot find module $module at path: $path");
        }
        msgDebug("\nBuilding registry for module $module, relPath = $relPath and path $path");
        $fqcn  = "\\bizuno\\{$module}Admin";
        bizAutoLoad("{$path}admin.php", $fqcn);
        $admin = new $fqcn();
        $bizunoMod[$module]['settings'] = isset($admin->settings) ? $admin->settings : [];
        // set some system properties
        $admin->structure['id']         = $module;
        $admin->structure['title']      = $admin->lang['title'];
        $admin->structure['description']= $admin->lang['description'];
        $admin->structure['path']       = $relPath;
        $admin->structure['url']        = str_replace(['BIZBOOKS_ROOT/', 'BIZBOOKS_EXT/', 'BIZBOOKS_LOCALE/'], ['BIZBOOKS_URL_ROOT/', 'BIZBOOKS_URL_EXT/', 'BIZBOOKS_URL_LOCALE/'], $relPath);
        if (!isset($admin->structure['status'])) { $admin->structure['status'] = 1; }
        $admin->structure['hasAdmin']   = method_exists($admin, 'adminHome') ? true : false;
        $admin->structure['devStatus']  = !empty($admin->devStatus) ? $admin->devStatus : false;
        $this->setMenus($admin->structure);
        $this->setGlobalLang($admin->structure);
        $this->setHooks($admin->structure, $module, $relPath);
        $this->setAPI($admin->structure);
        $this->initMethods($admin->structure);
        if (method_exists($admin, 'initialize')) { $admin->initialize(); }
        unset($admin->structure['lang']);
        unset($admin->structure['hooks']);
        unset($admin->structure['api']);
        $bizunoMod[$module]['properties']= $admin->structure;
        // Restore Bizuno database version for upgrade check. If the dbVersion is the same, then nothing is done
        if ($module=='bizuno') { $bizunoMod['bizuno']['properties']['version'] = $this->dbVersion; }
        msgDebug("\nFinished initModule for module: $module, updating cache.");
        $GLOBALS['updateModuleCache'][$module] = true;
    }

    /**
     * Initializes user registry
     * @global array $bizunoUser
     * @param string $usrEmail
     * @param integer $bizID
     * @return boolean
     */
    private function initUser($usrEmail, $bizID)
    {
        global $bizunoUser;
        msgDebug("\ninitUser with email = $usrEmail biz_id = $bizID");
        $lang = $bizunoUser['profile']['language']; // preserve the language selection
        if (!$row = dbGetRow(BIZUNO_DB_PREFIX.'users', "email='$usrEmail'", true, false)) { return; }
        if (isset($row['password'])) { $row['password'] = '****'; }
        msgDebug("\nRead original row from users table: ".print_r($row, true));
        $settings = json_decode($row['settings'], true);
        if (!isset($settings['profile'])) { $settings['profile'] = []; }
        $output = array_replace_recursive($bizunoUser['profile'], $settings['profile']);
        unset($row['settings']);
        $bizunoUser = ['profile' => array_replace_recursive($output, $row)];
        // set some known facts
        $bizunoUser['profile']['email']    = $usrEmail;
        $bizunoUser['profile']['biz_id']   = $bizID;
        $bizunoUser['profile']['role_id']  = get_user_meta( get_current_user_id(), 'bizbooks_role_'.$bizID, true);
        $bizunoUser['profile']['biz_multi']= sizeof($GLOBALS['bizPortal']) > 1 ? 1 : 0;
        $bizunoUser['profile']['language'] = $lang;
        $GLOBALS['updateUserCache'] = true;
        return true;
    }

    /**
     *
     * @global array $bizunoMod
     * @param type $mID
     * @param type $status
     */
    private function setModuleStatus($mID, $status=0)
    {
        global $bizunoMod;
        if ($status) {
            $props= dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='$mID'");
            $vals = json_decode($props, true);
            $vals['properties']['status'] = $status;
            $bizunoMod[$mID] = $vals;
            $GLOBALS['updateModuleCache'][$mID] = true;
        } else { setModuleCache($mID, 'properties', 'status', 0); }
    }

    /**
     * Adds the module menus to the overall menu structure
     * @param type $struc
     */
    private function setMenus(&$struc)
    {
        if (!empty($struc['menuBar'])) {
            $this->menuBar = array_replace_recursive($this->menuBar, $struc['menuBar']);
            unset($struc['menuBar']);
        }
        if (!empty($struc['quickBar'])) {
            $this->quickBar = array_replace_recursive($this->quickBar, $struc['quickBar']);
            unset($struc['quickBar']);
        }
    }

    /**
     * Load any system wide language to the registry language cache
     * @global type $structure
     */
    public function setGlobalLang($structure)
    {
        global $bizunoLang;
        if (!isset($structure['lang'])) { return; }
        foreach ($structure['lang'] as $key => $value) { $bizunoLang[$key] = $value; }
    }


    /**
     * Sets the hooks array from a given module, if present
     * @param array $structure - array of hooks for the requested module
     * @param string $hookID -
     * @return type
     */
    public function setHooks($structure, $module, $path)
    {
        global $bizunoMod;
        if (!isset($structure['hooks'])) { return; }
        foreach ($structure['hooks'] as $mod => $page) {
            foreach ($page as $pageID => $pageProps) {
                foreach ($pageProps as $method => $methodProps) {
                    $methodProps['path'] = $path;
                    if (!isset($methodProps['page']))  { $methodProps['page'] = 'admin'; }
                    if (!isset($methodProps['class'])) { $methodProps['class']= $module.ucfirst($methodProps['page']); }
                    $bizunoMod[$mod]['hooks'][$pageID][$method][$module] = $methodProps;
                }
            }
        }
    }

    /**
     *
     * @global array $bizunoMod
     * @param type $structure
     * @return type
     */
    private function setAPI($structure)
    {
        global $bizunoMod;
        if (!isset($structure['api'])) { return; }
        $bizunoMod['bizuno']['api'][$structure['id']] = $structure['api'];
    }

    /**
     *
     * @param array $bizunoMod
     */
    private function initBizuno(&$bizunoMod)
    {
        $bizunoMod['bizuno']['stores'] = dbGetStores();
    }

    /**
     *
     * @param array $bizunoMod
     */
    private function initPhreeBooks(&$bizunoMod)
    {
        $date    = biz_date('Y-m-d');
        $output  = [];
        $taxRates= dbGetMulti(BIZUNO_DB_PREFIX."tax_rates");
        foreach ($taxRates as $row) { // Needs to be auto indexed so the javascript doesn't break
            if     (!empty($row['inactive'])) { $row['status'] = 2;}
            elseif ($row['start_date']>=$date || $row['end_date']<=$date) { $row['status'] = 1; }
            else   { $row['status'] = 0; }
            $row['rate'] = $row['tax_rate'];
            $row['settings'] = json_decode($row['settings'], true);
            $output[] = $row;
        }
        $byTitle = sortOrder($output, 'text');
        $byLock  = sortOrder($byTitle,'status');
        // spilt by type
        $bizunoMod['phreebooks']['sales_tax'] = [];
        foreach ($byLock as $row) { $bizunoMod['phreebooks']['sales_tax'][$row['type']][] = $row; }
    }

    /**
     *
     * @param array $bizunoMod
     */
    private function initPhreeForm(&$bizunoMod)
    {
        $bizunoMod['phreeform']['rptGroups'] = [];
        $bizunoMod['phreeform']['frmGroups'] = [];
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'phreeform', "mime_type='dir'", "group_id, title");
        foreach ($result as $row) {
            if (strpos($row['group_id'], ':')===false) {
                $cat = lang($row['title']);
            } elseif (strpos($row['group_id'], ':rpt')) { // report folder
                $bizunoMod['phreeform']['rptGroups'][] = ["id"=>$row['group_id'], "text"=>$cat." - ".lang('reports')];
            } else { // form folder
                $bizunoMod['phreeform']['frmGroups'][] = ["id"=>$row['group_id'], "text"=>$cat." - ".lang($row['title'])];
            }
        }
        $processing = $formatting = $separators = [];
        foreach (array_keys($bizunoMod) as $module) {
            if (!class_exists("\\bizuno\\{$module}Admin")) { continue; }
            $fqcn  = "\\bizuno\\{$module}Admin";
            $admin = new $fqcn();
            if (isset($admin->phreeformProcessing)) { $processing = array_merge($processing, $admin->phreeformProcessing); }
            if (isset($admin->phreeformFormatting)) { $formatting = array_merge($formatting, $admin->phreeformFormatting); }
            if (isset($admin->phreeformSeparators)) { $separators = array_merge($separators, $admin->phreeformSeparators); }
        }
        $bizunoMod['phreeform']['processing'] = $processing;
        $bizunoMod['phreeform']['formatting'] = $formatting;
        $bizunoMod['phreeform']['separators'] = $separators;
    }

    private function setRoleMenus()
    {
        msgDebug("\nEntering setRoleMenus");
        $roles = dbGetMulti(BIZUNO_DB_PREFIX.'roles');
        $roleID= getUserCache('profile', 'role_id', false, 0);
        if (empty($roleID)) { return; } // if empty, then not logged
        foreach ($roles as $role) {
            $tmpMenu = $this->menuBar['child'];
            $tmpQuick= $this->quickBar['child'];
            $settings= json_decode($role['settings'], true);
            $this->removeOrphanMenus($tmpMenu, $settings['security']);
            $settings['menuBar'] = sortOrder($tmpMenu);
            $this->removeOrphanMenus($tmpQuick, $settings['security']);
            $settings['quickBar'] = sortOrder($tmpQuick);
            dbWrite(BIZUNO_DB_PREFIX.'roles', ['settings'=>json_encode($settings)], 'update', "id={$role['id']}");
            // set the users cache for security checks
            if ($role['id']==$roleID) { setUserCache('security', false, $settings['security']); }
        }
    }

    /**
     * Removes main menu heading if there are no sub menus underneath
     * @param array $menu - working menu
     * @return integer - maximum security value found during the removal process
     */
    private function removeOrphanMenus(&$menu, $userSecurity)
    {
        $security = 0;
        foreach ($menu as $key => $props) {
            if (isset($props['child'])) {
                $menu[$key]['security'] = $this->removeOrphanMenus($menu[$key]['child'], $userSecurity);
            } elseif (!empty($menu[$key]['required'])) {
                $menu[$key]['security'] = 4;
                setUserCache('security', $key, $menu[$key]['security']);
            } else {
                $menu[$key]['security'] = array_key_exists($key, $userSecurity) ? $userSecurity[$key] : 0;
            }
            if (!empty($menu[$key]['manager'])) { // managers can stand alone or have children, this prevents them from being removed if all children are no access
                $menu[$key]['security'] = array_key_exists($key, $userSecurity) ? $userSecurity[$key] : 0;
            }
            if (!$menu[$key]['security']) {
                unset($menu[$key]);
                continue;
            }
            $security = max($security, $menu[$key]['security']);
        }
        return $security;
    }

    /**
     *
     */
    public function initMethods($structure)
    {
        if (!isset($structure['dirMethods']))    { $structure['dirMethods'] = []; }
        if (!is_array($structure['dirMethods'])) { $structure['dirMethods'] = [$structure['dirMethods']]; }
        $structure['dirMethods'][] = 'dashboards'; // auto-add dashboards
        foreach ($structure['dirMethods'] as $folderID) {
            $methods = [];
            msgDebug("\ninitMethods is looking at module: {$structure['id']} folder: $folderID and relPath: {$structure['path']}");
            $path = bizAutoLoadMap($structure['path']);
            if (!file_exists("{$path}$folderID/")) { msgDebug("\nFolder is not there, bailing!"); continue; }
            msgDebug("\nreading methods");
            $this->methodRead($methods, "{$structure['path']}$folderID/");
            if (bizIsActivated('bizuno-pro') && $folderID <> 'dashboards') {
                $path = str_replace('BIZBOOKS_ROOT', 'BIZBOOKS_EXT', $structure['path']);
                msgDebug("\ninitMethods is looking at bizuno-pro for path: $path and module: {$structure['id']} and folder $folderID");
                $this->methodRead($methods, "{$path}$folderID/");
            }
            if (defined('BIZUNO_DATA') && $folderID <> 'dashboards') {
                msgDebug("\ninitMethods is looking at customizations for module: {$structure['id']} and folder $folderID");
                $this->methodRead($methods, "BIZUNO_DATA/myExt/controllers/{$structure['id']}/$folderID/");
            }
            $this->cleanMissingMethods($structure['id'], $folderID, $methods);
            $this->initMethodList($structure, $folderID, $methods);
        }
    }

    /**
     *
     * @global array $bizunoMod
     * @param type $structure
     * @param type $folderID
     * @param type $methods
     */
    private function initMethodList($structure, $folderID, $methods)
    {
        global $bizunoMod;
        $module = $structure['id'];
        msgDebug("\ninitMethodList is looking at methods = ".print_r($methods, true));
        foreach ($methods as $method => $path) {
            $settings = getModuleCache($module, $folderID, $method);
            if (empty($settings['settings'])) { $settings['settings'] = []; }
            if ($folderID=='dashboards') { $settings['status'] = 1; } // all dashboards are loaded into cache and user decide which to enable and where
            if (defined('BIZUNO_DATA') && strpos($path, 'BIZUNO_DATA/') === 0) {
                $bizID = getUserCache('profile', 'biz_id');
                $url   = "BIZBOOKS_URL_FS/&src=$bizID/myExt/controllers/$module/$folderID/$method/";
            } else {
                $url   = isset($structure['url']) ? "{$structure['url']}$folderID/$method/" : '';
// @TODO - BOF Delete after 2022-12-31
$url = bizAutoLoadRemap($url);
// EOF Delete after 2022-12-31
            }
            msgDebug("\nlooking for method $method at path = ".print_r($path, true));
            $fqcn = "\\bizuno\\$method";
            if (!bizAutoLoad("{$path}$method.php", $fqcn)) { continue; }
            $clsMeth = new $fqcn($settings['settings']);
            $bizunoMod[$module][$folderID][$method] = [
                'id'         => $method,
                'title'      => $clsMeth->lang['title'],
                'description'=> $clsMeth->lang['description'],
                'path'       => $path,
                'url'        => $url,
                'status'     => 0];
            if (!empty($settings['status'])) {
                $bizunoMod[$module][$folderID][$method] = array_replace_recursive($bizunoMod[$module][$folderID][$method], [
                    'status'     => 1,
                    'acronym'    => isset($clsMeth->lang['acronym']) ? $clsMeth->lang['acronym']: $clsMeth->lang['title'],
                    'default'    => isset($clsMeth->settings['default']) && $clsMeth->settings['default'] ? 1 : 0,
                    'order'      => isset($clsMeth->settings['order']) ? $clsMeth->settings['order'] : 50,
                    'settings'   => isset($clsMeth->settings) ? $clsMeth->settings : []]);
            } else { continue; }
            if (isset($clsMeth->structure)) { $this->setHooks($clsMeth->structure, $method, $path); }
            unset($bizunoMod[$module][$folderID][$method]['hooks']);
        }
        $bizunoMod[$module][$folderID] = sortOrder($bizunoMod[$module][$folderID], 'title');
    }

    public function methodRead(&$methods, $relPath)
    {
        $output = [];
        msgDebug("\nEntering methodRead with relPath = $relPath");
        $path = bizAutoLoadMap($relPath);
        if (!is_dir($path)) { msgDebug(" ... returning with folder not found"); return $output; }
        $temp = scandir($path);
        foreach ($temp as $fn) {
            if ($fn == '.' || $fn == '..') { continue; }
            if (!is_dir($path.$fn))        { continue; }
            $methods[$fn] = "{$relPath}$fn/";
        }
        return $output;
    }

    /**
    * This function cleans out stored registry values that have be orphaned in the configuration database table.
    * @param string $module - Module ID
    * @param string $folderID - Method ID
    * @param array $methods - List of all available methods in the specified folder
    * @return null
    */
    public function cleanMissingMethods($module, $folderID, $methods=[])
    {
        global $bizunoMod;
        if (!isset($bizunoMod[$module][$folderID]) || !is_array($methods)) { return; }
        $cache = array_keys($bizunoMod[$module][$folderID]);
        $allMethods = array_keys($methods);
        foreach ($cache as $method) {
            if (!in_array($method, $allMethods)) {
                msgAdd("Module: $module, folder: $folderID, Deleting missing method: $method");
                unset($bizunoMod[$module][$folderID][$method]);
            }
        }
    }

    /**
     *
     * @param type $myAcct
     * @return type
     */
    private function reSortExtensions($myAcct)
    {
        $output = [];
        if (empty($myAcct['extensions'])) { return []; }
        foreach ($myAcct['extensions'] as $cat) {
            foreach ($cat as $mID => $props) { $output[$mID] = $props; }
        }
        return $output;
    }
}