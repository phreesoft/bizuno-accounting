<?php
/*
 * Bizuno Settings methods
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
 * @version    6.x Last Update: 2024-01-12
 * @filesource /controllers/bizuno/settings.php
 */

namespace bizuno;

class bizunoSettings
{
    public  $moduleID= 'bizuno';
    public  $notes   = [];

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Main Settings page, builds a list of all available modules and puts into groups
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'admin', 2)) { return; }
        $data = ['title'=>lang('settings'),
            'divs' => [
                'heading' => ['order'=>10,'type'=>'html','html'=>"<h1>".lang('settings')."</h1>"],
                'manager' => ['order'=>50,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView']]]];
        $tmp0 = portalModuleList();
        msgDebug("\nModule list = ".print_r($tmp0, true));
        foreach (array_keys($tmp0) as $idx) { $tmp1[$idx] = getModuleCache($idx, 'properties'); }
        $mods = sortOrder($tmp1, 'title');
        $order= 30;
        foreach ($mods as $mID => $props) {
            msgDebug("\nSettings for module $mID = ".print_r($props, true));
            $data['divs']['manager']['divs'][$mID] = ['order'=>$order,'type'=>'panel','classes'=>['block25'],'key'=>$mID];
            $data['panels'][$mID] = ['label'=>$props['title'],'type'=>'html','html'=>$this->buildModProps($mID, $props)];
            $order++;
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Builds the panel for installed modules, either active or inactive
     * @param type $mID - module ID
     * @param type $settings
     * @return string
     */
    private function buildModProps($mID, $settings)
    {
        $html  = '<div style="float:left">';
        if (!empty($settings['logo'])){ $html .= html5('', ['styles'=>['cursor'=>'pointer','max-height'=>'50px'],'attr'=>['type'=>'img','src'=>$settings['logo']]]); }
        else                          { $html .= html5('', ['styles'=>['cursor'=>'pointer','max-height'=>'50px'],'attr'=>['type'=>'img','src'=>BIZBOOKS_URL_ROOT.'view/images/phreesoft.png']]); }
        $html .= '</div>'."\n";
        if (!empty(getModuleCache($mID)) || !empty(getModuleCache($mID, 'dashboards')) || !empty(getModuleCache($mID, 'properties', 'dirMethods'))) {
            $html .= '<div style="float:right">';
            $html .= html5("prop_$mID", ['icon'=>'settings','events'=>['onClick'=>"location.href='".BIZUNO_HOME."&bizRt=$mID/admin/adminHome'"]]);
            $html .= '</div>'."\n";
        }
        $html .= "<div><p>{$settings['description']}</p>";
        $html .= '</div>';
        return $html;
    }

    /**
     * Handles the installation of a module
     * @global array $msgStack - working messages to be returned to user
     * @param array $layout - structure coming in
     * @param string $module - name of module to install
     * @param string $relPath - relative path to module
     * @return modified $layout
     */
    public function moduleInstall(&$layout=[], $module=false, $relPath='')
    {
        global $msgStack, $bizunoMod;
        if (!$security = validateSecurity('bizuno', 'admin', 3)) { return; }
        if (!$module) {
            $module = clean('rID', 'cmd', 'get');
            $relPath= clean('data','filename', 'get');
        }
        $path = bizAutoLoadMap($relPath);
        if (!$module || !$path) { return msgAdd("Error installing module: unknown. No name/path passed!"); }
        $installed = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value',  "config_key='$module'");
        if ($installed) {
            $settings = json_decode($installed, true);
            if (!$settings['properties']['status']) {
                $settings['properties']['status'] = 1;
                $bizunoMod[$module] = $settings;
                dbWrite(BIZUNO_DB_PREFIX.'configuration', ['config_value'=>json_encode($settings)], 'update', "config_key='$module'");
            } else { return msgAdd(sprintf($this->lang['err_install_module_exists'], $module), 'caution'); }
        } else {
            $path = rtrim($path, '/') . '/';
            msgDebug("\nInstalling module: $module at path: $path");
            if (!file_exists("{$path}admin.php")) { return msgAdd(sprintf("There was an error finding file %s", "{$path}admin.php")); }
            $fqcn = "\\bizuno\\{$module}Admin";
            bizAutoLoad("{$path}admin.php", $fqcn);
            $adm = new $fqcn();
            $bizunoMod[$module]['settings']                 = isset($adm->settings) ? $adm->settings : [];
            $bizunoMod[$module]['properties']               = $adm->structure;
            $bizunoMod[$module]['properties']['id']         = $module;
            $bizunoMod[$module]['properties']['title']      = $adm->lang['title'];
            $bizunoMod[$module]['properties']['description']= $adm->lang['description'];
            $bizunoMod[$module]['properties']['status']     = 1;
            $bizunoMod[$module]['properties']['path']       = $relPath;
            $this->adminInstDirs($adm);
            if (isset($adm->tables)) { $this->adminInstTables($adm->tables); }
            $this->adminAddRptDirs($adm);
            $this->adminAddRpts($module=='bizuno' ? BIZBOOKS_ROOT : $path);
            if (method_exists($adm, 'install')) { $adm->install(); }
            if (isset($adm->notes)) { $this->notes = array_merge($this->notes, $adm->notes); }
            // create the initial configuration table record
            $exists = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_key', "config_key='$module'");
            $dbData = ['config_key'=>$module, 'config_value'=>json_encode($bizunoMod[$module])];
            dbWrite(BIZUNO_DB_PREFIX.'configuration', $dbData, $exists?'update':'insert', "config_key='$module'");
            if (!empty($adm->structure['menuBar']['child'])) { $this->setSecurity($adm->structure['menuBar']['child']); }
            if (!empty($adm->structure['quickBar']['child'])){ $this->setSecurity($adm->structure['quickBar']['child']); }
            msgLog  ("Installed module: $module");
            msgDebug("\nInstalled module: $module");
            if (isset($msgStack->error['error']) && sizeof($msgStack->error['error']) > 0) { return; }
        }
        dbClearCache('all');
        $cat    = getModuleCache($module, 'properties', 'category', false, 'bizuno');
        $layout = array_replace_recursive($layout, ['content'=>['rID'=>$module,'action'=>'href','link'=>BIZUNO_HOME."&bizRt=bizuno/settings/manager&cat=$cat"]]);
    }

    /**
     * Removes a module from Bizuno
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function moduleDelete(&$layout=[])
    {
        global $io;
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        $module = clean('rID', 'text', 'get');
        msgDebug("\n removing module: $module with properties = ".print_r(getModuleCache($module, 'properties'), true));
        if (empty($module)) { return; }
        $path = getModuleCache($module, 'properties', 'path');
        if (file_exists("$path/admin.php")) {
            $fqcn = "\\bizuno\\{$module}Admin";
            bizAutoLoad("$path/admin.php", $fqcn);
            $mod_admin = new $fqcn();
            $this->adminDelDirs($mod_admin);
            if (isset($mod_admin->tables)) { $this->adminDelTables($mod_admin->tables); }
            if (method_exists($mod_admin, 'remove')) { if (!$mod_admin->remove()) {
                return msgAdd("There was an error removing module: $module");
            } }
        }
        if (is_dir("$path/$module/dashboards/")) {
            $dBoards = scandir("$path/$module/dashboards/");
            foreach ($dBoards as $dBoard) { if (!in_array($dBoard, ['.', '..'])) {
                dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE dashboard_id='$dBoard'");
            } }
        }
        if (!empty($path) && !in_array(BIZUNO_HOST, ['phreesoft'])) {
            $modPath = str_replace(BIZUNO_DATA, '', $path);
            msgDebug("\nDeleting folder BIZUNO_DATA/$modPath");
            $io->folderDelete($modPath);
        }
        msgLog("Removed module: $module");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."configuration WHERE config_key='$module'");
        dbClearCache('all'); // force reload of all users cache with next page access, menus and permissions, etc.
        $layout= array_replace_recursive($layout, ['content'=>['rID'=>$module, 'action'=>'href', 'link'=>BIZUNO_HOME."&bizRt=bizuno/settings/manager"]]);
    }

    /**
     * Installs a method associated with a module
     * @param array $layout - structure coming in
     * @param array $attrs - details of the module to add method
     * @param boolean $verbose - [default true] true to send user message, false to just install method
     * @return type
     */
    public function methodInstall(&$layout=[], $attrs=[], $verbose=true)
    {
        if (!$security=validateSecurity('bizuno', 'admin', 3)) { return; }
        $module = isset($attrs['module']) ? $attrs['module'] : clean('module','text', 'get');
        $subDir = isset($attrs['path'])   ? $attrs['path']   : clean('path',  'text', 'get');
        $method = isset($attrs['method']) ? $attrs['method'] : clean('method','text', 'get');
        if (!$module || !$subDir || !$method) { return msgAdd("Bad data installing method!"); }
        msgDebug("\nInstalling method $method with methodDir = $subDir");
        $relPath = getModuleCache($module, 'properties', 'path')."$subDir/$method/";
        if (file_exists(BIZBOOKS_EXT."controllers/$module/$subDir/$method/$method.php"))      { $relPath = "BIZBOOKS_EXT/controllers/$module/$subDir/$method/"; }
        if (file_exists(BIZUNO_DATA ."myExt/controllers/$module/$subDir/$method/$method.php")){ $relPath = "BIZUNO_DATA/myExt/controllers/$module/$subDir/$method/"; }
        $fqcn = "\\bizuno\\$method";
        msgDebug("\nretrieving class $fqcn with relPath = $relPath");
        bizAutoLoad("{$relPath}$method.php", $fqcn);
        $methSet = getModuleCache($module, $subDir, $method, 'settings');
        $clsMeth = new $fqcn($methSet);
        if (method_exists($clsMeth, 'install')) { $clsMeth->install($layout); }
        $url = str_replace(['BIZBOOKS_ROOT/', 'BIZBOOKS_EXT/', 'BIZBOOKS_LOCALE/'], ['BIZBOOKS_URL_ROOT/', 'BIZBOOKS_URL_EXT/', 'BIZBOOKS_URL_LOCALE/'], $relPath);
        if (defined('BIZUNO_DATA') && strpos($relPath, 'BIZUNO_DATA/') === 0) {
            $bizID = getUserCache('profile', 'biz_id');
            $url   = "BIZBOOKS_URL_FS/&src=$bizID/myExt/controllers/$module/$subDir/$method/";
        }
        $properties = [
            'id'         => $method,
            'title'      => $clsMeth->lang['title'],
            'description'=> $clsMeth->lang['description'],
            'path'       => $relPath,
            'url'        => $url,
            'status'     => 1,
            'settings'   => $clsMeth->settings];
        setModuleCache($module, $subDir, $method, $properties);
        dbClearCache();
        $data = $verbose ? ['content'=>['action'=>'eval','actionData'=>"location.reload();"]] : [];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Saves user settings for a specific method
     * @param $layout - structure coming in
     * @return modified structure
     */
    public function methodSettingsSave(&$layout=[])
    {
        if (!$security=validateSecurity('bizuno', 'admin', 3)) { return; }
        $module = clean('module','text', 'get');
        $subDir = clean('type',  'text', 'get');
        $method = clean('method','text', 'get');
        if (!$module || !$subDir || !$method) { return msgAdd("Not all the information was provided!"); }
        $properties = getModuleCache($module, $subDir, $method);
        $fqcn = "\\bizuno\\$method";
        bizAutoLoad("{$properties['path']}$method.php", $fqcn);
        $methSet = getModuleCache($module,$subDir,$method,'settings');
        $objMethod = new $fqcn($methSet);
        msgDebug('received raw data = '.print_r(file_get_contents("php://input"), true));
        $structure = method_exists($objMethod, 'settingsStructure') ? $objMethod->settingsStructure() : [];
        $settings = [];
        settingsSaveMethod($settings, $structure, $method.'_');
        $properties['settings'] = $settings;
        setModuleCache($module, $subDir, $method, $properties);
        dbClearCache();
        if (method_exists($objMethod, 'settingSave')) { $objMethod->settingSave(); }
        msgAdd(lang('msg_settings_saved'), 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"jqBiz('#divMethod_$method').hide('slow');"]]);
    }

    /**
     * Cleans out roles that are no longer in the db
     * @param type $values
     */
    private function validateRoles(&$values=[])
    {
        foreach ($values as $idx => $value) {
            if (empty($value)) { continue; } // none selected
            $roleID = dbGetValue(BIZUNO_DB_PREFIX.'roles', 'id', "id=$value");
            if (empty($roleID)) { unset($values[$idx]); }
        }
    }

    /**
     * Cleans out roles that are no longer in the db
     * @param type $values
     */
    private function validateUsers(&$values=[])
    {
        foreach ($values as $idx => $value) {
            if (empty($value)) { continue; } // none selected
            $userID = dbGetValue(BIZUNO_DB_PREFIX.'users', 'id', "id=$value");
            if (empty($userID)) { unset($values[$idx]); }
        }
    }

    /**
     * Removes a method from the db and session cache
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function methodRemove(&$layout=[], $attrs=[]) {
        if (!$security=validateSecurity('bizuno', 'admin', 4)) { return; }
        $module = isset($attrs['module']) ? $attrs['module'] : clean('module','text', 'get');
        $subDir = isset($attrs['path'])   ? $attrs['path']   : clean('type',  'text', 'get');
        $method = isset($attrs['method']) ? $attrs['method'] : clean('method','text', 'get');
        if (!$module || !$subDir) { return msgAdd("Bad method data provided!"); }
        $properties = getModuleCache($module, $subDir, $method);
        if ($properties) {
            $fqcn = "\\bizuno\\$method";
            bizAutoLoad("{$properties['path']}$method.php", $fqcn);
            $methSet = getModuleCache($module,$subDir,$method,'settings');
            $clsMeth = new $fqcn($methSet);
            if (method_exists($clsMeth, 'remove')) { $clsMeth->remove(); }
            $properties['status'] = 0;
            $properties['settings'] = [];
            setModuleCache($module, $subDir, $method, $properties);
            dbClearCache();
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"location.reload();"]]);
    }

    /**
     * Installs the file structure for a module, if any
     * @param array $dirlist - list for folders to create
     * @param string $path - folder path to start
     * @return boolean, false on error, true on success
     */
    function adminInstDirs($adm)
    {
        global $io;
        if (!isset($adm->dirlist)) { return; }
        if (is_array($adm->dirlist)) { foreach ($adm->dirlist as $dir) { $io->validatePath($dir); } }
    }

    /**
     * Removes folders when a module is removed
     * @param array $dirlist - folder list to remove
     * @param string $path - root path where folders can be found
     * @return boolean true
     */
    function adminDelDirs($mod_admin)
    {
        if (!isset($mod_admin->dirlist)) { return; }
        if (is_array($mod_admin->dirlist)) {
            $temp = array_reverse($mod_admin->dirlist);
            foreach($temp as $dir) {
                if (!@rmdir(BIZUNO_DATA . $dir)) { msgAdd(sprintf(lang('err_io_dir_remove'), $dir)); }
            }
        }
        return true;
    }

    /**
     * Installs db tables when a module is installed
     * @param array $tables - list of tables to create
     * @return boolean true on success, false on error
     */
    public function adminInstTables($tables=[])
    {
        foreach ($tables as $table => $props) {
            $fields = [];
            foreach ($props['fields'] as $field => $values) {
                $temp = "`$field` ".$values['format']." ".$values['attr'];
                if (isset($values['comment'])) { $temp .= " COMMENT '".$values['comment']."'"; }
                $fields[] = $temp;
            }
            msgDebug("\n    Creating table: $table");
            $sql = "CREATE TABLE IF NOT EXISTS `".BIZUNO_DB_PREFIX."$table` (".implode(', ', $fields).", ".$props['keys']." ) ".$props['attr'];
            dbGetResult($sql);
        }
    }

    /**
     * Removes tables from the db
     * @param array $tables - list of tables to drop
     */
    function adminDelTables($tables=[])
    {
        foreach ($tables as $table =>$values) {
            dbGetResult("DROP TABLE IF EXISTS `".BIZUNO_DB_PREFIX."$table`");
        }
    }

    /**
     * Adds new folders to the PhreeForm tree, used when installing a new module
     * @param array $adm -
     * @return boolean true on success, false on error
     */
    private function adminAddRptDirs($adm)
    {
        global $bizunoMod;
        $date = biz_date('Y-m-d');
        if (isset($adm->reportStructure)) { foreach ($adm->reportStructure as $heading => $settings) {
            $parent_id = dbGetValue(BIZUNO_DB_PREFIX."phreeform", 'id', "title='".$settings['title']."' and mime_type='dir'");
            if (!$parent_id) { // make the heading
                $parent_id = dbWrite(BIZUNO_DB_PREFIX."phreeform", ['group_id'=>$heading, 'mime_type'=>'dir', 'title'=>$settings['title'], 'create_date'=>$date, 'last_update'=>$date]);
            }
            if (is_array($settings['folders'])) { foreach ($settings['folders'] as $gID => $values) {
                if (!$result = dbGetValue(BIZUNO_DB_PREFIX."phreeform", 'id', "group_id='$gID' and mime_type='{$values['type']}'")) {
                    dbWrite(BIZUNO_DB_PREFIX."phreeform", ['parent_id'=>$parent_id, 'group_id'=>$gID, 'mime_type'=>$values['type'], 'title'=>$values['title'], 'create_date'=>$date, 'last_update'=>$date]);
                }
            } }
        } }
        if (isset($adm->phreeformProcessing)) {
            if (!isset($bizunoMod['phreeform']['processing'])) { $bizunoMod['phreeform']['processing'] = []; }
            $temp = array_merge_recursive($bizunoMod['phreeform']['processing'], $adm->phreeformProcessing);
            $bizunoMod['phreeform']['processing'] = sortOrder($temp, 'group'); // sort phreeform processing
        }
        if (isset($adm->phreeformFormatting)) {
            if (!isset($bizunoMod['phreeform']['formatting'])) { $bizunoMod['phreeform']['formatting'] = []; }
            $temp = array_merge_recursive($bizunoMod['phreeform']['formatting'], $adm->phreeformFormatting);
            $bizunoMod['phreeform']['formatting'] = sortOrder($temp, 'group'); // sort phreeform formatting
        }
        if (isset($adm->phreeformSeparators)) {
            if (!isset($bizunoMod['phreeform']['separators'])) { $bizunoMod['phreeform']['separators'] = []; }
            $temp = array_merge_recursive($bizunoMod['phreeform']['separators'], $adm->phreeformSeparators);
            $bizunoMod['phreeform']['separators'] = sortOrder($temp, 'group'); // sort phreeform separators
        }
    }

    /**
     * Adds reports to PhreeForm, typically during a module install
     * @param string $module - module name to look for reports
     * @param boolean $core - true if a core Bizuno module, false otherwise
     * @return boolean
     */
    public function adminAddRpts($path='')
    {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreeform/functions.php", 'phreeformImport', 'function');
        $error = false;
        msgDebug("\nAdding reports to path = $path");
        if ($path <> BIZBOOKS_ROOT) { $path = "$path/"; }
        if (file_exists($path."locale/".getUserCache('profile', 'language', false, 'en_US')."/reports/")) {
            $read_path = $path."locale/".getUserCache('profile', 'language', false, 'en_US')."/reports/";
        } elseif (file_exists($path."locale/en_US/reports/")) {
            $read_path = $path."locale/en_US/reports/";
        } else { msgDebug(" ... returning with no reports found!"); return true; } // nothing to import
        $files = scandir($read_path);
        foreach ($files as $file) {
            if (strtolower(substr($file, -4)) == '.xml') {
                msgDebug("\nImporting report name = $file at path $read_path");
                if (!phreeformImport('', $file, $read_path, false)) { $error = true; }
            }
        }
        return $error ? false : true;
    }

    /**
     * Fill security values in the menu structure
     * @param integer $role_id - role id of the user
     * @param integer $level - level to set security value
     * @return boolean true
     */
    public function adminFillSecurity($role_id=0, $level=0)
    {
        global $bizunoMod;
        $security = [];
        foreach ($bizunoMod as $settings) {
            if (!isset($settings['properties']['menuBar']['child'])) { continue; }
            foreach ($settings['properties']['menuBar']['child'] as $key1 => $menu1) {
                $security[$key1] = $level;
                if (!isset($menu1['child'])) { continue; }
                foreach ($menu1['child'] as $key2 => $menu2) {
                    $security[$key2] = $level;
                    if (!isset($menu2['child'])) { continue; }
                    foreach ($menu2['child'] as $key3 => $menu3) { $security[$key3] = $level; }
                }
            }
        }
        foreach ($bizunoMod as $settings) {
            if (!isset($settings['properties']['quickBar']['child'])) { continue; }
            foreach ($settings['properties']['quickBar']['child'] as $key => $menu) {
                $security[$key] = $level;
                if (!isset($menu['child'])) { continue; }
                foreach ($menu['child'] as $skey => $smenu) { $security[$skey] = $level; }
            }
        }
        $result = dbGetRow(BIZUNO_DB_PREFIX."roles", "id='$role_id'");
        if ($result) {
            $settings = json_decode($result['settings'], true);
            $settings['security'] = $security;
            setUserCache('security', false, $security);
            dbWrite(BIZUNO_DB_PREFIX."roles", ['settings'=>json_encode($settings)], 'update', "id='$role_id'");
        }
        return true;
    }

    /**
     * Sets security for the menu items into the database
     * @param array $menu - menu structure
     */
    private function setSecurity($menu)
    {
        $roleID  = getUserCache('profile', 'role_id', false, 1);
        $dbData  = dbGetRow(BIZUNO_DB_PREFIX.'roles', "id=$roleID");
        $settings= !empty($dbData['settings']) ? json_decode($dbData['settings'], true) : [];
        foreach ($menu as $idx => $catChild) {
            if ($idx<>'child') { continue; }
            $subMenus = array_keys($catChild['child']);
            foreach ($subMenus as $item) {
                $settings['security'][$item] = 4;
                if (!empty($catChild['child'])) { $this->setSecurity($catChild); }
            }
        }
        dbWrite(BIZUNO_DB_PREFIX.'roles', ['settings'=>json_encode($settings)], 'update', "id=$roleID");
    }
}
