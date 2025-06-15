<?php
/*
 * Functions to support user operations
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
 * @version    6.x Last Update: 2024-02-13
 * @filesource /controllers/bizuno/users.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/portal/guest.php', 'guest');

class bizunoUsers
{
    public $moduleID = 'bizuno';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Main entry point structure for Bizuno users
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function manager(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'users', 1)) { return; }
        $title = lang('bizuno_users');
        $layout = array_replace_recursive($layout, viewMain(), [
            'title'=> $title,
            'divs' => [
                'heading'=> ['order'=>30, 'type'=>'html',     'html'=>"<h1>$title</h1>\n"],
                'users'  => ['order'=>60, 'type'=>'accordion','key' =>'accUsers']],
            'accordion'=> ['accUsers'=>  ['divs'=>  [
                'divUsersManager'=> ['order'=>30,'label'=>lang('manager'),'type'=>'datagrid','key'=>'dgUsers'],
                'divUsersDetail' => ['order'=>70,'label'=>lang('details'),'type'=>'html','html'=>'&nbsp;']]]],
            'datagrid'=> ['dgUsers' => $this->dgUsers('dgUsers')]]);
    }

    /**
     * Lists the users with applied filters from user
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerRows(&$layout)
    {
        if (!$security = validateSecurity('bizuno', 'users', 1)) { return; }
        $rows=[];
        foreach (listRoles(false, false, false) as $role) { $roles['r'.$role['id']] = $role['text']; }
//      $users = portalGetUsers(); // Users will soon be pulled through the portal
        $users = dbGetMulti(BIZUNO_DB_PREFIX.'users');
        foreach ($users as $user) {
            $dtls = bizGetUser($user['email']);
            if (empty($dtls)) { // delete the user from the db if not found in the portal users list
                dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users where email='{$user['email']}'");
                continue;
            }
            $rows[] = [
                'admin_id' => $user['admin_id'],
                'inactive' => $user['inactive'],
                'email'    => $user['email'],
                'title'    => $dtls['title'],
                'role'     => $roles['r'.$dtls['role']],
            ];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($rows), 'rows'=>$rows])]);
    }

    /**
     * saves user selections in cache for page re-entry
     */
    private function managerSettings()
    {
        $data = ['path'=>'bizunoUsers','values' => [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>1],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX."users.title"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'f0',    'clean'=>'char',   'default'=>'y'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     * structure to edit a user
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'users', 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (empty($rID)) { return msgAdd("The users database record cannot be found, this record needs to be created by the portal first!"); }
        $struc  = dbLoadStructure(BIZUNO_DB_PREFIX.'users');
        $dbData = dbGetRow(BIZUNO_DB_PREFIX.'users', "admin_id=$rID");
        dbStructureFill($struc, $dbData);
        $fldAcct= ['admin_id','email','inactive','title','contact_id','role_id']; //  // role is set in WP Admin
        $eKeys  = ['smtp_enable', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'];
        $fields = $this->getViewUsers($struc);
        $title  = lang('bizuno_users').' - '.$fields['email']['attr']['value'];
        $cID    = !empty($fields['contact_id']['attr']['value']) ? $fields['contact_id']['attr']['value'] : 0;
        $name   = !empty($cID) ? dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'primary_name', "ref_id=$cID AND type='m'") : '';
        $data   = ['type'=>'divHTML',
            'divs'    => ['detail'=>['order'=>50,'type'=>'divs','divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbUsers'],
                'head'   => ['order'=>15,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'frmBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmUsers'],
                'body'   => ['order'=>50,'type'=>'tabs',   'key' =>'tabUsers'],
                'frmEOF' => ['order'=>90,'type'=>'html',   'html'=>"</form>"]]]],
            'toolbars'=> ['tbUsers'=>['icons'=>[
                'save'=> ['order'=>20,'hidden'=>$security>1?'0':'1','events'=>['onClick'=>"jqBiz('#frmUsers').submit();"]]]]],
            'tabs'=> ['tabUsers'=>['divs'=>[
                'general'=> ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genAcct' => ['order'=>10,'type'=>'panel','key'=>'genAcct','classes'=>['block33']],
                    'genAtch' => ['order'=>80,'type'=>'panel','key'=>'genAtch','classes'=>['block66']]]],
                'email'  => ['order'=>40,'label'=>lang('email'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'pnlEmail'=> ['order'=>40,'type'=>'panel','key'=>'pnlEmail','classes'=>['block50']]]]]]],
            'panels' => [
                'genAcct' => ['label'=>lang('account'),   'type'=>'fields','keys'=>$fldAcct],
                'genAtch' => ['type'=>'attach','defaults'=>['path'=>getModuleCache($this->moduleID,'properties','usersAttachPath'),'prefix'=>"rID_{$rID}_"]],
                'pnlEmail'=> ['label'=>lang('settings'),  'type'=>'fields','keys'=>$eKeys]],
            'forms'   => ['frmUsers'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=bizuno/users/save"]]],
            'fields'  => $fields,
            'text'    => ['pw_title' => $rID?lang('password_lost'):lang('password')],
            'jsHead'  => ['init'=>"var usersContact = ".json_encode([['id'=>$cID, 'primary_name'=>$name]]).";"],
            'jsReady' => ['init'=>"ajaxForm('frmUsers');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Generates the field list for the user edit view
     * @param array $fields
     * @param type $settings
     * @return array
     */
    private function getViewUsers($fields)
    {
        $user  = bizGetUser($fields['email']['attr']['value']);
        msgDebug("\nReturned from fetching WP user with result: ".print_r($user, true));
        $mySettings= json_decode($fields['settings']['attr']['value'], true);
        $settings  = isset($mySettings['profile']) ? $mySettings['profile'] : [];
        msgDebug("\nSettings decoded is: ".print_r($settings, true));
        $stores= getModuleCache('bizuno', 'stores');
        array_unshift($stores, ['id'=>-1, 'text'=>lang('all')]);
        $defs  = ['type'=>'e', 'data'=>'usersContact', 'callback'=>''];
        $output= [
            'admin_id'      => $fields['admin_id'],
            'email'         => array_replace_recursive($fields['email'],   ['order'=>15,'break'=>true,'attr'=>['value'=>$user['email'],   'readonly'=>'readonly']]),
            'inactive'      => array_replace_recursive($fields['inactive'],['order'=>20,'break'=>true,'attr'=>['value'=>$user['inactive'],'readonly'=>'readonly']]),
            'title'         => array_replace_recursive($fields['title'],   ['order'=>25,'break'=>true,'attr'=>['value'=>$user['title'],   'readonly'=>'readonly']]),
            'role_id'       => array_replace_recursive($fields['role_id'], ['order'=>30,'break'=>true,'tip'=>lang('tip_wp_edit_user'),'values'=>listRoles(true, false, false),'attr'=>['type'=>'select','value'=>$user['role'],'readonly'=>'readonly']]),
            'contact_id'    => ['order'=>35,'break'=>true,'label'=>lang('contacts_rep_id_i'),'defaults'=>$defs,'attr'=>['type'=>'contact','value'=>$fields['contact_id']['attr']['value']]],
            'smtp_enable'   => ['order'=>20,'break'=>true,'label'=>$this->lang['smtp_enable_lbl'],'tip'=>$this->lang['smtp_enable_tip'],'attr'=>['type'=>'selNoYes','value'=>isset($settings['smtp_enable'])?$settings['smtp_enable']:0]],
            'smtp_host'     => ['order'=>30,'break'=>true,'label'=>$this->lang['smtp_host_lbl'],  'tip'=>$this->lang['smtp_host_tip'],  'attr'=>['value'=>isset($settings['smtp_host'])?$settings['smtp_host']:'smtp.gmail.com']],
            'smtp_port'     => ['order'=>40,'break'=>true,'label'=>$this->lang['smtp_port_lbl'],  'tip'=>$this->lang['smtp_port_tip'],  'attr'=>['type'=>'integer' ,'value'=>isset($settings['smtp_port'])?$settings['smtp_port']:587]],
            'smtp_user'     => ['order'=>50,'break'=>true,'label'=>$this->lang['smtp_user_lbl'],'attr'=>['value'=>isset($settings['smtp_user'])?$settings['smtp_user']:'']],
            'smtp_pass'     => ['order'=>60,'break'=>true,'label'=>$this->lang['smtp_pass_lbl'],'attr'=>['type'=>'password','value'=>'']]];
        return $output;
    }

    /**
     * This method saves the users data and updates the portal if required.
     * @return Post save action, refresh grid, clear form
     */
    public function save(&$layout=[])
    {
        global $io;
        $rID     = clean('admin_id',  'integer', 'post');
        if (!$security = validateSecurity('bizuno', 'users', $rID?3:2)) { return; }
        if (empty($rID)) { return msgAdd("The users database record cannot be found, this record needs to be created by the portal first!"); }
        $cID     = clean('contact_id','integer', 'post');
        $settings= $rID ? json_decode(dbGetValue(BIZUNO_DB_PREFIX.'users', 'settings', "admin_id=$rID"), true) : [];
        $settings['profile']['smtp_enable']= clean('smtp_enable','boolean','post');
        $settings['profile']['smtp_host']  = clean('smtp_host',  'url',    'post');
        $settings['profile']['smtp_port']  = clean('smtp_port',  'integer','post');
        $settings['profile']['smtp_user']  = clean('smtp_user',  'email',  'post');
        if (empty($settings['profile']['smtp_pass'])) { $settings['profile']['smtp_pass'] = ''; }
        $password= clean('smtp_pass', 'text', 'post');
        $settings['profile']['smtp_pass']  = !empty($password) ? $password : $settings['profile']['smtp_pass'];
        // clean up some legacy junk
        // COMMENTED OUT - the admin_id is used all over the place. Never shoud have been removed.
//        unset($settings['profile']['admin_id'], $settings['profile']['title'], $settings['profile']['contact_id'], $settings['profile']['biz_multi']);
        // Only change the values that are not controled by the portal
        $dbData =['contact_id'=>$cID, 'settings'=>json_encode($settings)];
        dbWrite(BIZUNO_DB_PREFIX.'users', $dbData, $rID?'update':'insert', "admin_id=$rID");
        msgDebug("\nUser admin_id = $rID and current user admin_id = ".getUserCache('profile', 'admin_id', false, 0));
        if ($io->uploadSave('file_attach', getModuleCache('bizuno', 'properties', 'usersAttachPath')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'users', ['attach'=>1], 'update', "admin_id=$rID");
        }
        $email = dbGetValue(BIZUNO_DB_PREFIX.'users', 'email', "admin_id=$rID");
        msgLog(lang('table')." users - ".lang('save')." $email ($rID)");
        msgAdd(lang('msg_database_write'), 'success');
        $data  = ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accUsers').accordion('select',0); bizGridReload('dgUsers');"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Copies a user to a new username. Not used in WordPress hosted sites
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function copy(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        if (!$security = validateSecurity('bizuno', 'users', $rID?3:2)) { return; }
        $this->security = getUserCache('security');
        $email= clean('data', 'email', 'get');
        if (!$rID || !$email) { return msgAdd(lang('err_copy_name_prompt')); }
        $user = dbGetRow(BIZUNO_DB_PREFIX."users", "admin_id='$rID'");
        // copy user at the portal
        $pData= portalRead('users', "biz_user='{$user['email']}'");
        unset($pData['id']);
        unset($pData['date_updated']);
        $pData['date_created']= biz_date('Y-m-d H:i:s');
        $pData['biz_user']    = $email;
        portalWrite('users', $pData);
        $settings = json_decode($user['settings'], true);
        $settings['profile']['email'] = $email;
        unset($user['admin_id'], $user['cache_date'], $user['attach']);
        $user['title']   = $email;
        $user['email']   = $email;
        $user['settings']= json_encode(['profile'=>$settings['profile']]);
        $nID   = $_GET['rID'] = dbWrite(BIZUNO_DB_PREFIX.'users', $user);
        if ($nID) { msgLog(lang('table')." users-".lang('copy').": $email ($rID => $nID)"); }
        $data  = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgUsers'); accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".jsLang('details')."', 'bizuno/users/edit', $nID);"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Deletes a user and removes them from the portal
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'users', 4)) { return; }
        $rID     = clean('rID', 'integer', 'get');
        $this->security = getUserCache('security');
        if (!$rID) { return msgAdd(lang('err_copy_name_prompt')); }
        if (getUserCache('profile', 'admin_id', false, 0) == $rID) { return msgAdd($this->lang['err_delete_user']); }
        $settings= json_decode(dbGetValue(BIZUNO_DB_PREFIX."users", 'settings', "admin_id='$rID'"), true);
        $data    = ['content'=> ['action'=>'eval', 'actionData'   =>"bizGridReload('dgUsers');"],
            'dbAction'    => [BIZUNO_DB_PREFIX."users"         => "DELETE FROM ".BIZUNO_DB_PREFIX."users WHERE admin_id='$rID'",
                              BIZUNO_DB_PREFIX."users_profiles"=> "DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE user_id='$rID'"]];
        portalDelete($settings['profile']['email'], $settings['profile']['biz_id']);
        $io = new \bizuno\io();
        $io->fileDelete(getModuleCache('bizuno', 'properties', 'usersAttachPath')."rID_{$rID}_*");
        msgLog(lang('table')." users-".lang('delete')." {$settings['profile']['email']} ($rID)");
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Datagrid structure for Bizuno users
     * @param string $name - DOM field name
     * @param integer $security - users defined security level
     * @return array - datagrid structure
     */
    private function dgUsers($name)
    {
        $this->managerSettings();
        return ['id'=>$name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'idField'=>'admin_id', 'url'=>BIZUNO_AJAX."&bizRt=bizuno/users/managerRows"],
            'events' => [
                'rowStyler'    => "function(index, row) { if (row.inactive == '1') { return {class:'row-inactive'}; }}",
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".lang('details')."', 'bizuno/users/edit', rowData.admin_id); }"],
            'columns' => [
                'admin_id'=> ['order'=>0, 'attr'=>['hidden'=>true]],
                'inactive'=> ['order'=>0, 'attr'=>['hidden'=>true]],
                'action'  => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>$name.'Formatter'],
                    'actions'=> [
                        'edit'=>['order'=>20,'icon'=>'edit', 'events'=>['onClick'=>"accordionEdit('accUsers', 'dgUsers', 'divUsersDetail', '".lang('details')."', 'bizuno/users/edit', idTBD);"]]]],
                'email'   => ['order'=>10,'label'=>lang('email'),'attr'=>['sortable'=>true, 'resizable'=>true]],
                'title'   => ['order'=>20,'label'=>lang('title'),'attr'=>['sortable'=>true, 'resizable'=>true]],
                'role'    => ['order'=>30,'label'=>lang('role'), 'attr'=>['sortable'=>true, 'resizable'=>true]]]];
    }
}
