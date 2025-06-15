<?php
/*
 * All things dashboard related
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
 * @version    6.x Last Update: 2023-04-18
 * @filesource /controllers/bizuno/dashboard.php
 */

namespace bizuno;

class bizunoDashboard
{
    public $moduleID = 'bizuno';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Sets the structure for the dashboard manager page to select preferred dashboards
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function manager(&$layout=[])
    {
        $menu_id= clean('menuID', ['format'=>'text','default'=>'home'], 'get');
        if    ($menu_id=='home')     { $label = lang('home'); }
        elseif($menu_id=='settings') { $label = lang('bizuno_company'); }
        else                         {
            $menus = dbGetRoleMenu();
            $label = $menus['menuBar']['child'][$menu_id]['label'];
        }
        $title  = sprintf($this->lang['edit_dashboard'], $label );
        $data   = [
            'title'  => lang('dashboards'),
            'menu_id'=> $menu_id,
            'divs'   => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbDashBoard'],
                'frmDash' => ['order'=>15,'type'=>'html',   'html'=>html5('frmDashboard', ['attr'=>  ['type'=>'form', 'action'=>BIZUNO_AJAX."&bizRt=bizuno/dashboard/save&menuID=$menu_id"]])],
                'heading' => ['order'=>30,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'adminSet'=> ['order'=>50,'type'=>'tabs',   'key' =>'tabSettings'],
                'footer'  => ['order'=>99,'type'=>'html',   'html'=>"</form>"]],
            'jsReady' => ['jsForm'=>"ajaxForm('frmDashboard');"],
            'tabs'    => ['tabSettings'=> ['attr'=>['tabPosition'=>'left']]],
            'toolbars'=> ['tbDashBoard'=> ['icons' => [
                'cancel'=> ['order'=> 10, 'events'=>['onClick'=>"location.href='".BIZUNO_HOME."&bizRt=bizuno/main/bizunoHome&menuID=$menu_id'"]],
                'save'  => ['order'=> 20, 'events'=>['onClick'=>"jqBiz('#frmDashboard').submit();"]]]]]];
        $order  = 1;
        $header = '<table style="border-collapse:collapse;width:100%">'."\n".' <thead class="panel-header">'."\n";
        $header.= "  <tr><th>".lang('active')."</th><th>".lang('title')."</th><th>".lang('description')."</th></tr>\n</thead>\n <tbody>\n";
        $footer = " </tbody>\n</table>\n";
        $dashboards = $this->loadDashboards($menu_id);
        msgDebug("\nFound dashboards = ".print_r($dashboards, true));
        foreach ($dashboards as $cat => $module) {
            $temp = [];
            foreach ($module as $key => $value) { $temp[$key] = $value['title']; }
            array_multisort($temp, SORT_ASC, $module);
            $html = $header;
            foreach ($module as $piece) {
                $checkbox = ['attr'=>['type'=>'checkbox','value'=>$piece['module'].':'.$piece['id'], 'checked'=>$piece['active']?'checked':false]];
                $html .= "  <tr><td>".html5("dashID[]", $checkbox)."</td><td>".$piece['title']."</td><td>".$piece['description']."</td></tr>\n";
                $html .= '  <tr><td colspan="4"><hr /></td></tr>'."\n";
            }
            $html .= $footer;
            $data['tabs']['tabSettings']['divs'][$cat] = ['order'=>$order,'label'=>lang($cat),'type'=>'html','html'=>$html];
            $order++;
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /*
     * Retrieves the dashboards with settings for a given menu, from there each is loaded separately
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function render(&$layout=[])
    {
        $menu_id = clean('menuID', 'text', 'get');
        $layout = array_replace_recursive($layout, ['content'=>$this->listDashboards($menu_id)]);
    }

    /**
     * Saves state after user moves dashboards on home screen. Stores the dashboard placement on a given menu in the users profile
     */
    public function organize()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $state   = clean('state',  'text', 'get');
        $columns = explode(':', $state);
        msgDebug("\nNum columns = ".getUserCache('profile', 'cols', false, 3));
        for ($col = 0; $col < getUserCache('profile', 'cols', false, 3); $col++) {
            if (strlen($columns[$col]) == 0) { continue; }
            $rows = explode(',', $columns[$col]);
            for ($row = 0; $row < count($rows); $row++) { // write the row, column
                $sql_data = ['column_id' => $col, 'row_id' => $row];
                dbWrite(BIZUNO_DB_PREFIX."users_profiles", $sql_data, 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND menu_id='$menu_id' AND dashboard_id='{$rows[$row]}'");
            }
        }
    }

    /**
     * Save selected dashboards into the users profile
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function save(&$layout=[])
    {
        $menu_id = clean('menuID', 'text', 'get');
        $enabled = clean('dashID','array', 'post');
        $temp    = $this->listDashboards($menu_id); // fetch the current state
        $current = [];
        if (is_array($temp['Dashboard'])) { foreach ($temp['Dashboard'] as $value) { $current[] = $value['module_id'].':'.$value['id']; } }
        msgDebug("\ncurrent = ".print_r($current, true).' and enabled = '.print_r($enabled, true));
        $adds = array_diff($enabled, $current);
        $dels = array_diff($current, $enabled);
        msgDebug("\nadds = ".print_r($adds, true).' and dels = '.print_r($dels, true));
        if (sizeof($adds) > 0) { foreach ($adds as $dashboard) {
            $path   = explode(':', $dashboard);
            if (in_array($dashboard, $current)) { continue; } // already active, skip
            $newAdd = getDashboard($path[0], $path[1]);
            if ($newAdd) {
                if (method_exists($newAdd, 'install')) {
                    $newAdd->install($path[0], $menu_id);
                } else {
                    if (empty($newAdd->settings)) { $newAdd->settings = []; }
                    $sql_data = ['user_id'=>getUserCache('profile', 'admin_id', false, 0),'menu_id'=>$menu_id,'module_id'=>$path[0],
                        'dashboard_id'=>$path[1],'column_id'=>0,'row_id'=>0,'settings'=>json_encode($newAdd->settings)];
                    dbWrite(BIZUNO_DB_PREFIX."users_profiles", $sql_data);
                }
            }
        } }
        if (sizeof($dels) > 0) { foreach ($dels as $dashboard) {
            $path = explode(':', $dashboard);
            $newDel = getDashboard($path[0], $path[1]);
            if ($newDel) {
                if (method_exists($newDel, 'remove')) {
                    $newDel->remove($menu_id);
                } else {
                    dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE user_id=".getUserCache('profile', 'admin_id', false, 0)." AND menu_id='$menu_id' AND dashboard_id='{$path[1]}'");
                }
            }
        } }
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'href', 'link'=>BIZUNO_HOME."&bizRt=bizuno/main/bizunoHome&menuID=$menu_id"]]);
    }

    /**
     * Renders the dashboard contents, called when loading menu home pages
     * @param modified array - $layout modified with the dashboard settings
     */
    public function settings(&$layout=[])
    {
        $mID = clean('mID', 'cmd', 'get');
        $dID = clean('dID', 'cmd', 'get');
        $menu= clean('menu','cmd', 'get');
        msgDebug("\nModule id = $mID and dash id = $dID and menu = $menu");
        $settings = dbGetDashSettings($menu, $dID);
        $dashboard= getDashboard($mID, $dID, $settings);
        if (!$dashboard) { return msgAdd("ERROR: Dashboard $dID NOT FOUND!"); }
        $html     = $dashboard->render($layout, $menu);
        $jsReady  = "jqBiz('#{$dID}Form').keypress(function(event) { var keycode=event.keyCode?event.keyCode:event.which; if (keycode=='13') { dashSubmit('$mID:$dID', 0); } });";
        if (is_string($html) && strlen($html)) { // plain old HTML, old way but still used frequently
            $data = ['type'=>'divHTML',
                'divs'   =>[$dID=>['order'=>50,'type'=>'html','html'=>$html]],
                'jsReady'=>['dbInit'=>strpos($html, "{$dID}Form") ? $jsReady : '']];
        } else { // it's a structure, process it
            $data   = ['type'=>'divHTML',
                'divs'   => [
                    'divBOF'=>['order'=> 1,'type'=>'html','html'=>"<div>"],
//                  'body'  =>['order'=>50,'type'=>'list','key'=>'notes'], // set in individual dashboard
                    'admin' =>['order'=>10,'styles'=>['display'=>'none'],'attr'=>['id'=>"{$dID}_attr"],'type'=>'divs','divs'=>[
                        'frmBOF' => ['order'=>20,'type'=>'form',  'key' =>"{$dID}Form"],
//                      'body'   => ['order'=>50,'type'=>'fields','keys'=>array_keys($layout['fields'])],  // set in individual dashboard
                        'frmEOF' => ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                    'divBOF'=>['order'=> 1,'type'=>'html','html'=>"</div>"]],
                'forms'  => ["{$dID}Form"=>['attr'=>['type'=>'form','action'=>'']]],
                'jsReady'=>['dbInit'=>$jsReady]];
        }
        $layout = array_replace_recursive($data, $layout);
        msgDebug("\nlayout after processing = ".print_r($layout, true));
    }

    /**
     * Deletes a dashboard from the users profile
     * @return null, removes the table row from the users profile
     */
    public function delete()
    {
        $menu_id     = clean('menuID',     'text','get');
        $module_id   = clean('moduleID',   'text','get');
        $dashboard_id= clean('dashboardID','text','get');
        $dashboard   = getDashboard($module_id, $dashboard_id);
        if (!$dashboard) { return msgAdd('ERROR: Dashboard delete failed!'); }
        if (method_exists($dashboard, 'remove')) {
            $dashboard->remove($menu_id);
        } else {
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE user_id=".getUserCache('profile', 'admin_id', false, 0)." AND menu_id='$menu_id' AND dashboard_id='$dashboard_id'");
        }
    }

    /**
     * Updates a dashboard settings from the menu settings submit
     * @return null, just saves the new settings so next time the dashboard is loaded, the new settings will be there
     */
    public function attr()
    {
        $menu_id     = clean('menuID',     'text','get');
        $module_id   = clean('moduleID',   'text','get');
        $dashboard_id= clean('dashboardID','text','get');
        $dashboard   = getDashboard($module_id, $dashboard_id);
        if (!is_object($dashboard)) { return msgAdd('ERROR: Dashboard update failed!'); }
        $dashboard->save($menu_id);
    }

    /**
     * Builds the dashboard list for a given menu
     * @param string $menu_id
     * @return array - structure of dashboards to render menu page
     */
    private function listDashboards($menu_id='home')
    {
        $cols = getColumns();
        $dashboard = $temp = $state = [];
        if (getUserCache('profile', 'admin_id') && empty($GLOBALS['bizuno_not_installed'])) {
            $result = dbGetMulti(BIZUNO_DB_PREFIX.'users_profiles', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND menu_id='$menu_id'", "column_id, row_id");
        } else {
            $result = getUserCache('dashboards');
            $menu_id='portal';
        }
        msgDebug("\ncols = $cols and menu_id = $menu_id and result = ".print_r($result, true));
        foreach ($result as $values) {
            $colID = min($cols-1, $values['column_id']);
            $temp[$colID][] = $values['dashboard_id'];
            $myDash = getDashboard($values['module_id'], $values['dashboard_id']);
            if (!is_object($myDash)) { continue; }
            if (!$myDash && getUserCache('profile', 'admin_id')) {
// @TODO Uncomment after 10/1/2021, since modules were moved to controllers, prevent accidental deletion of users preferences.
//              dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE dashboard_id='{$values['dashboard_id']}' AND user_id=".getUserCache('profile', 'admin_id', false, 0));
                continue;
            }
            $icnTools = [['iconCls' => 'icon-refresh','handler'=>"(function () { jqBiz('#{$values['dashboard_id']}').panel('refresh'); })"]];
            if (empty($myDash->noSettings)) {
                $icnTools[] = ['iconCls'=>'icon-edit','handler'=>"(function () { jqBiz('#{$values['dashboard_id']}_attr').toggle('slow'); })"];
            }
            $dashboard[] = [
                'id'         => $values['dashboard_id'],
                'module_id'  => $values['module_id'],
                'title'      => $myDash->lang['title'],
                'description'=> $myDash->lang['description'],
                'security'   => $myDash->security,
                'collapsible'=> empty($myDash->noCollapse)? true : false,
                'closable'   => empty($myDash->noClose)   ? true : false,
                'tools'      => $icnTools,
                'href'       => BIZUNO_AJAX.'&bizRt=bizuno/dashboard/settings&dID='.$values['dashboard_id'].'&mID='.$values['module_id'].'&menu='.$menu_id];
        }
        msgDebug("\nList dashboards for menu ID = $menu_id is: ".print_r($dashboard, true));
        for ($i = 0; $i < $cols; $i++) { $state[] = isset($temp[$i]) && is_array($temp[$i]) ? implode(',', $temp[$i]) : ''; }
        return ['Dashboard'=>$dashboard, 'State'=>implode(':', $state)];
    }

    /**
    * This function loads the details of all dashboards for active modules ONLY
    * @param string $menu_id - (default: home) Lists the menu index to find loaded dashboards
    * @return array $result
    */
    private function loadDashboards($menu_id='home')
    {
        global $bizunoMod;
        $output = $loaded = [];
        $loaded_dashboards = $this->listDashboards($menu_id);
        if (is_array($loaded_dashboards['Dashboard'])) { foreach ($loaded_dashboards['Dashboard'] as $dashboard) { $loaded[] = $dashboard['id']; } }
        foreach ($bizunoMod as $module => $settings) {
            $path    = bizAutoLoadMap($settings['properties']['path']);
            if (empty($path) || !file_exists("{$path}dashboards") || !is_dir("{$path}dashboards")) { continue; }
            msgDebug("\nFound path {$path}dashboards");
            if (!getModuleCache($module, 'properties', 'status')) { continue; } // skip if module not loaded
            $thelist = scandir("{$path}dashboards");
            msgDebug("\ntheList read from cache = ".print_r($thelist, true));
            foreach ($thelist as $dashboard) {
                if ($dashboard == '.' || $dashboard == '..' || !is_dir("{$path}/dashboards/$dashboard")) { continue; }
                $myDash = getDashboard($module, $dashboard);
                if (isset($myDash->hidden) && $myDash->hidden) { continue; }
                if (isset($myDash->settings)) { msgDebug("\nmyDash defaults = ".print_r($myDash->settings, true)); }
                if ($myDash) {
                    $category = isset($myDash->category) ? lang($myDash->category) : lang('misc');
                    if (validateDashboardSecurity($myDash)) { // security check dashboard
                        msgDebug("\nPassed Security");
                        $output[$category][] = [
                            'id'         => $dashboard,
                            'title'      => $myDash->lang['title'],
                            'description'=> $myDash->lang['description'],
                            'module'     => $module,
                            'security'   => $myDash->security,
                            'active'     => in_array($dashboard, $loaded) ? true : false];
                    }
                }
            }
        }
        ksort($output); // start sorting everything
        return $output;
    }
}
