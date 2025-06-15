<?php
/*
 * Handles user roles
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
 * @version    6.x Last Update: 2023-07-30
 * @filesource /controllers/bizuno/roles.php
 */

namespace bizuno;

class bizunoRoles
{
    public  $moduleID = 'bizuno';
    private $quickBar = [];
    private $menuBar  = [];

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->securityChoices = [
            ['id'=>'-1','text'=>lang('select')],
            ['id'=>'0', 'text'=>lang('none')],
            ['id'=>'1', 'text'=>lang('readonly')],
            ['id'=>'2', 'text'=>lang('add')],
            ['id'=>'3', 'text'=>lang('edit')],
            ['id'=>'4', 'text'=>lang('full')],
            ['id'=>'5', 'text'=>lang('admin')]];
    }

    /**
     * Roles manager main entry point
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function manager(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'roles', 1)) { return; }
        $title = lang('roles');
        $layout = array_replace_recursive($layout, viewMain(), [
            'title' => $title,
            'divs' => [
                'heading'=> ['order'=>30, 'type'=>'html',     'html'=>"<h1>$title</h1>\n"],
                'roles'  => ['order'=>60, 'type'=>'accordion','key'=>'accRoles']],
            'accordion' => ['accRoles'=>['divs'=>[
                'divRolesManager'=>['order'=>30,'label'=>lang('manager'),'type'=>'datagrid','key'=>'dgRoles'],
                'divRolesDetail' =>['order'=>70,'label'=>lang('details'),'type'=>'html','html'=>'&nbsp;']]]],
            'datagrid' => ['dgRoles'=>$this->dgRoles('dgRoles', $security)]]);
    }

    /**
     * List roles available filtered per user request
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'roles', 1)) { return; }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'dgRoles','datagrid'=>['dgRoles'=>$this->dgRoles('dgRoles', $security)]]);
    }

    /**
     * Saves the user preferences for the roles grid in a session array
     */
    private function managerSettings()
    {
        $data = ['path'=>'bizunoRoles', 'values' => [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX."roles.title"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     * Structure to handle editing roles
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'roles', 1)) { return; }
        $rID      = clean('rID', 'integer', 'get');
        $fields   = ['id','title','inactive','selFill','restrict'];
        $dbData   = $rID ? dbGetRow(BIZUNO_DB_PREFIX.'roles', "id=$rID") : ['id'=>0,'settings'=>[]];
        $structure= dbLoadStructure(BIZUNO_DB_PREFIX.'roles');
        $structure['selFill'] = ['order'=>60,'label'=>$this->lang['desc_security_fill'],'break'=>true,'values'=>$this->securityChoices,'events'=>['onChange'=>"autoFill();"],'attr'=>['type'=>'select']];
        $structure['restrict']= ['order'=>70,'label'=>$this->lang['restrict_access'],'tip'=>$this->lang['roles_restrict'],'break'=>true,'attr'=>['type'=>'checkbox','checked'=>!empty($dbData['settings']['restrict']) ? 1 : 0]];
        dbStructureFill($structure, $dbData);
        $data  = ['type'=>'divHTML','title'=>lang('roles').' - '.($rID ? $dbData['title'] : lang('new')),
            'divs'    => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbRoles'],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmRoles'],
                'body'   => ['order'=>50,'type'=>'divs',   'classes'=>['areaView'],'divs'=>[
                    'flds' => ['order'=>40,'type'=>'panel','classes'=>['block50'],'key'=>'pnlGen'],
                    'tabs' => ['order'=>60,'type'=>'panel','classes'=>['block50'],'key'=>'pnlMod']]],
                'formEOF'=> ['order'=>85,'type'=>'html',   'html'=>"</form>"]],
            'toolbars'=> ['tbRoles'=>['icons'=>[
                'save' => ['order'=>20,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"jqBiz('#frmRoles').submit();"]],
                'new'  => ['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"accordionEdit('accRoles','dgRoles','divRolesDetail','".jsLang('details')."','bizuno/roles/edit', 0);"]]]]],
            'panels' => [
                'pnlGen' => ['label'=>lang('general'),'type'=>'fields','keys'=>$fields],
                'pnlMod' => ['type'=>'tabs','key'=>'tabRoles']],
            'tabs'    => ['tabRoles'=>['attr'=>['tabPosition'=>'left', 'headerWidth'=>200]]],
            'forms'   => ['frmRoles'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=bizuno/roles/save"]]],
            'fields'  => $structure,
            'jsHead'  => ['init'=>"function autoFill() {
    var setting = bizSelGet('selFill');
    jqBiz('#frmRoles select').each(function() {
        if (typeof jqBiz(this).attr('id') !== 'undefined' && jqBiz(this).attr('id').substr(0, 4) == 'sID_') { bizSelSet(jqBiz(this).attr('id'), setting); }
    });
}"],
            'jsReady' => ['init'=>"ajaxForm('frmRoles');"]];
        $this->roleTabs($data, $dbData['settings']);
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Structure for saving roles after edit
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function save(&$layout=[])
    {
        $request = $_POST;
        $rID     = clean('id', 'integer', 'post');
        $restrict= clean('restrict', 'boolean', 'post');
        if (!$security = validateSecurity('bizuno', 'roles', $rID?3:2)) { return; }
        $values  = requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'roles'));
        $dup     = dbGetValue(BIZUNO_DB_PREFIX.'roles', 'id', "title='".addslashes($values['title'])."' AND id<>$rID");
        if ($dup) { return msgAdd(lang('error_duplicate_id')); }
        $settings= [];
        $settings['restrict'] = $restrict;
        foreach ($request as $key => $value) { //extract the security
            if (substr($key, 0, 4) == 'sID_') { // it's a valid security ID
                $code = substr($key, 4);
                $settings['security'][$code] = $value;
            }
        }
        $values['settings'] = json_encode($settings);
        $result  = dbWrite(BIZUNO_DB_PREFIX.'roles', $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $_GET['rID'] = $_POST['rID'] = $result; }
        dbClearCache('all'); // force reload of all users cache with next page access
        msgLog(lang('table').' '.BIZUNO_DB_PREFIX."roles - ".lang('save')." {$values['title']} ($rID)");
        msgAdd(lang('msg_record_saved'), 'success');
        $title   = lang('manager');
        $data    = ['content'=>['rID'=>$rID, 'action'=>'eval','actionData'=>"jqBiz('#accRoles').accordion('select','$title'); bizGridReload('dgRoles');"]];
        $layout  = array_replace_recursive($layout, $data);
    }

    /**
     * Structure for copying roles as a quick add/edit
     * @param array $layout -  structure coming in
     * @return modified $layout
     */
    public function copy(&$layout=[])
    {
        $rID  = clean('rID', 'integer','get');
        if (!$security = validateSecurity('bizuno', 'roles', $rID?3:2)) { return; }
        $title= clean('data','text',   'get');
        if (!$rID || !$title) { return msgAdd(lang('err_copy_name_prompt')); }
        $role = dbGetRow(BIZUNO_DB_PREFIX."roles", "id=$rID");
        unset($role['id']);
        $role['title'] = $title;
        $nID  = $_GET['rID'] = dbWrite(BIZUNO_DB_PREFIX."roles", $role);
        if ($nID) { msgLog(lang('roles')."-".lang('copy')." $title ($rID => $nID)"); }
        $data = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgRoles'); accordionEdit('accRoles','dgRoles','divRolesDetail','".jsLang('details')."', 'bizuno/roles/edit',$nID);"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Structure to delete a role
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'roles', 4)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd(lang('err_delete_name_prompt')); }
        $block = dbGetMulti(BIZUNO_DB_PREFIX.'users', "role_id=$rID", "title");
        if (sizeof($block) > 0) {
            $users = [];
            foreach ($block as $row) { $users[] = $row['title']; }
            return msgAdd(sprintf($this->lang['err_delete_role'], implode(', ', $users)));
        }
        $title = dbGetValue(BIZUNO_DB_PREFIX."roles", 'title', "id=$rID");
        msgLog(lang('table').' '.BIZUNO_DB_PREFIX."roles".'-'.lang('delete')." $title ($rID)");
        $data = [
            'content' => ['action'=>'eval','actionData'=>"bizGridReload('dgRoles');"],
            'dbAction'=> [BIZUNO_DB_PREFIX."roles"=>"DELETE FROM ".BIZUNO_DB_PREFIX."roles WHERE id=$rID"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Loads additional tabs to the roles edit page for modules other than Bizuno
     * @param integer $fldSettings - database field settings encoded in JSON
     * @return string - HTML view
     */
    private function roleTabs(&$data, $fldSettings=[])
    {
        $settings= is_array($fldSettings) ? $fldSettings : json_decode($fldSettings, true);
        $security= isset($settings['security']) ? $settings['security'] : [];
        $order   = 50;
        $theList = portalModuleList();
        foreach ($theList as $mID => $path) {
            $fqcn = "\\bizuno\\{$mID}Admin";
            bizAutoLoad("{$path}admin.php", $fqcn);
            $tmp = new $fqcn();
            $this->setMenus($tmp->structure);
        }
        $html    = '';
        $menuBar = sortOrder($this->menuBar['child']);
        $html   .= $this->roleTabsMain($data, $order, $menuBar, $security);
        $quickBar= sortOrder($this->quickBar['child']);
        // Add some special cases - Store Manager
        $quickBar['settings']['child']['mgr_b'] = ['order'=>90, 'label'=>lang('stores')];
        $html   .= $this->roleTabsMain($data, $order, $quickBar, $security);
    }

    private function roleTabsMain(&$data, &$order, $menu, $security)
    {
        foreach ($menu as $mID => $props) {
            $html = '';
            msgDebug("\nprocessing menu ID = $mID");
            if (!empty($props['child'])) { $html .= $this->roleTabsChildren($props['child'], $props['label'], $security); }
            $order++;
            $data['tabs']['tabRoles']['divs'][$mID] = ['order'=>$order,'label'=>$props['label'],'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'security' => ['order'=>80,'type'=>'panel','classes'=>['block50'],'key'=>"{$mID}Security"]]];
            $data['panels']["{$mID}Security"] = ['label'=>lang('security'),'type'=>'html','html'=>$html];
        }
        return $html;
    }

    /**
     * Sets the possible role security levels for menu children
     * @param array $children - list of menu children
     * @param string $title - Category title
     * @param array $security - Security setting of parent
     * @return string - HTML view
     */
    private function roleTabsChildren($children=[], $title='', $security=0)
    {
        $tab = '';
        foreach ($children as $id => $props) {
            if (isset($props['child'])) {
                $value = array_key_exists($id, $security) ? $security[$id] : 0;
                $tab .= html5("sID_$id", ['label'=>$props['label'],'values'=>$this->securityChoices,'attr'=>['type'=>'select','value'=>$value]])."<br />\n";
                $tab .= $this->roleTabsChildren($props['child'], $title, $security);
            } elseif (empty($props['required'])) {
                $value = array_key_exists($id, $security) ? $security[$id] : 0;
                if (empty($props['label'])) { msgAdd("label not set: ".print_r($props, true)); }
                $label = $props['label']=='reports' ? lang($title).' - '.lang($props['label']) : lang($props['label']);
                $tab  .= html5("sID_$id", ['label'=>$label,'values'=>$this->securityChoices,'attr'=>['type'=>'select','value'=>$value]])."<br />\n";
            }
        }
        return $tab;
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
     * Grid structure for roles manager
     * @param string $name - DOM id of the grid
     * @param integer $security - security setting for the user
     * @return array - grid structure
     */
    private function dgRoles($name, $security=0)
    {
        $this->managerSettings();
        return ['id'=>$name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'url'=>BIZUNO_AJAX."&bizRt=bizuno/roles/managerRows"],
            'events' => [
                'rowStyler'    => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; }}",
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('accRoles', 'dgRoles', 'divRolesDetail', '".lang('details')."', 'bizuno/roles/edit', rowData.id); }"],
            'source' => [
                'tables'  => ['roles'=>['table'=>BIZUNO_DB_PREFIX."roles"]],
                'actions' => [
                    'newRole'  => ['order'=>10,'icon'=>'new',  'events'=>['onClick'=>"accordionEdit('accRoles', 'dgRoles', 'divRolesDetail', '".lang('details')."', 'bizuno/roles/edit', 0);"]],
                    'clrSearch'=> ['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search', ''); ".$name."Reload();"]]],
                'search'  => [BIZUNO_DB_PREFIX."roles".'.title'],
                'sort'    => ['s0'=>  ['order'=>10, 'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]],
                'filters' => ['search' => ['order'=>'90','attr'=>['value'=>$this->defaults['search']]]]],
            'columns' => [
                'id'      => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."roles.id",      'attr'=>['hidden'=>true]],
                'inactive'=> ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."roles.inactive",'attr'=>['hidden'=>true]],
                'action'  => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>$name.'Formatter'],
                    'actions'=> [
                        'edit'  => ['order'=>20,'icon'=>'edit',
                            'events'=> ['onClick'=>"accordionEdit('accRoles', 'dgRoles', 'divRolesDetail', '".lang('details')."', 'bizuno/roles/edit', idTBD);"]],
                        'copy'  => ['order'=>40,'icon'=>'copy', 'hidden'=>$security>1?false:true,
                            'events'=> ['onClick'=>"var title=prompt('".lang('msg_copy_name_prompt')."'); jsonAction('bizuno/roles/copy', idTBD, title);"]],
                        'delete'=> ['order'=>90,'icon'=>'trash','hidden'=>$security>3?false:true,
                            'events'=> ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/roles/delete', idTBD);"]]]],
                'title'   => ['order'=>10, 'field'=>BIZUNO_DB_PREFIX."roles.title", 'label'=>pullTableLabel(BIZUNO_DB_PREFIX."roles", 'title'),
                    'attr' => ['sortable'=>true,'resizable'=>true]]]];
    }
}
