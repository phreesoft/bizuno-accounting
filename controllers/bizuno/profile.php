<?php
/*
 * This method handles user profiles
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
 * @filesource /controllers/bizuno/profile.php
 */

namespace bizuno;

class bizunoProfile
{
    public $moduleID = 'bizuno';

    public function __construct()
    {
        $this->lang  = getLang($this->moduleID);
        $this->freqs = ['d'=>lang('daily'),'w'=>lang('weekly'),'b'=>lang('bi-weekly'),'h'=>lang('semi-monthly'),'m'=>lang('monthly'),
            'q'=>lang('quarterly'),'y'=>lang('yearly')];
        $this->zones = [
            ['id'=>'America/Los Angeles','text'=>'America/Los Angeles'],
            ['id'=>'America/Denver',     'text'=>'America/Denver'],
            ['id'=>'America/Chicago',    'text'=>'America/Chicago'],
            ['id'=>'America/New York',   'text'=>'America/New York']];
    }

    /**
     * Adds/edits user profiles
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function edit(&$layout=[])
    {
        msgDebug("\nRead stores array = ",print_r(getModuleCache('bizuno', 'stores'), true));
        if (!$security = validateSecurity('bizuno', 'profile', 1)) { return; }
        $rID     = getUserCache('profile', 'admin_id', false, 0);
        $struc   = dbLoadStructure(BIZUNO_DB_PREFIX.'users');
        $dbData  = dbGetRow(BIZUNO_DB_PREFIX.'users', "admin_id=$rID");
        $settings= json_decode($dbData['settings'], true)['profile'];
        unset($dbData['settings']);
        dbStructureFill($struc, $dbData);
        $fldGen = ['title','email','gmail','langForce']; // ,'password','password_new','password_confirm'
        $fldProp= ['icons','theme','menu','cols','def_periods','grid_rows'];
        $data = ['title'=>lang('bizuno_profile'),
            'divs'    => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbProfile'],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmProfile'],
                'heading'=> ['order'=>20,'type'=>'html',   'html'=>"<h1>".lang('bizuno_profile')."</h1>"],
                'body'   => ['order'=>50,'type'=>'tabs',   'key' =>'tabProfile'],
                'formEOF'=> ['order'=>90,'type'=>'html',   'html'=>"</form>"]],
            'toolbars'=> ['tbProfile' =>['icons'=>[
                'save'=>['order'=>40,'hidden'=>$security<2?true:false,'events'=>['onClick'=>"jqBiz('#frmProfile').submit();"]]]]],
            'tabs'    => ['tabProfile'=>['divs'=>[
                'general' => ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genAcct' => ['order'=>10,'type'=>'panel','key'=>'genAcct','classes'=>['block33']],
                    'genProp' => ['order'=>40,'type'=>'panel','key'=>'genProp','classes'=>['block33']]]],
                'reminders'=> ['order'=>50,'label'=>$this->lang['reminders'],'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=bizuno/profile/reminderManager&uID=".getUserCache('profile', 'admin_id', false, 0)."'"]]]]],
            'panels' => [
                'genAcct' => ['label'=>lang('account'),   'type'=>'fields','keys'=>$fldGen],
                'genProp' => ['label'=>lang('properties'),'type'=>'fields','keys'=>$fldProp]],
            'forms'   => ['frmProfile'=>['attr' =>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=bizuno/profile/save"]]],
            'fields'  => $this->editFields($struc, $settings),
            'jsReady' => ['jsProfile'=>"ajaxForm('frmProfile');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Modifies the fields needed to edit a profile
     * @param array $fields - raw database fields
     * @param type $settings
     * @return type
     */
    private function editFields($fields, $settings)
    {
        msgDebug("\nsetting field struc with settings = ".print_r($settings, true));
        $periods = viewKeyDropdown(localeDates(true, false, true, true, false));
        $values  = [10,20,30,40,50]; // This must match the values set in EasyUI, [10,20,30,40,50] is the default
        foreach ($values as $value) {$rows[] = ['id'=>$value, 'text'=>$value]; }
        return [
            'title'      => array_merge($fields['title'], ['order'=>10,'break'=>true]),
            'email'      => array_merge($fields['email'], ['order'=>15,'break'=>true]),
            'gmail'      => ['order'=>20,'break'=>true,'label'=>$this->lang['gmail_address'],'tip'=>$this->lang['gmail_address_tip'],'attr'=>['type'=>'email','size'=>50,'value'=>isset($settings['gmail']) ? $settings['gmail'] : '']],
            'langForce'  => ['order'=>25,'break'=>true,'label'=>lang('language'),'options'=>['width'=>300],'values'=>viewLanguages(),'attr'=>['type'=>'select','value'=>isset($settings['langForce'] )?$settings['langForce'] : '']],
            'def_periods'=> ['order'=>30,'break'=>true,'label'=>$this->lang['date_range'],'values'=>$periods,     'attr'=>['type'=>'select','value'=>isset($settings['def_periods'] )?$settings['def_periods']:'l']],
            'grid_rows'  => ['order'=>35,'break'=>true,'label'=>$this->lang['grid_rows'], 'values'=>$rows,        'attr'=>['type'=>'select','value'=>isset($settings['grid_rows'] )?$settings['grid_rows']:20]],
            'icons'      => ['order'=>40,'break'=>true,'label'=>$this->lang['icon_set'],  'values'=>portalIcons(),'attr'=>['type'=>'select','value'=>isset($settings['icons'] )?$settings['icons']:'default']],
            'theme'      => ['order'=>45,'break'=>true,'label'=>lang('theme'),            'values'=>portalSkins(),'attr'=>['type'=>'select','value'=>isset($settings['theme']) ?$settings['theme']:'bizuno']],
            'cols'       => ['order'=>50,'break'=>true,'label'=>$this->lang['dashboard_columns'],               'attr'=>['value'=>isset($settings['cols'])  ?$settings['cols'] : 3,'size'=>2]]];
    }

    /**
     * Saves users profile
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function save(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'profile', 3)) { return; }
        setUserCache('profile', 'title',      clean('title', 'text',   'post'));
        setUserCache('profile', 'def_periods',clean('def_periods','alpha_num','post'));
        setUserCache('profile', 'grid_rows',  clean('grid_rows',  'integer',  'post'));
        setUserCache('profile', 'icons',      clean('icons',      'filename', 'post'));
        setUserCache('profile', 'theme',      clean('theme',      'filename', 'post'));
        setUserCache('profile', 'cols',       clean('cols',       'integer',  'post'));
        setUserCache('profile', 'gmail',      clean('gmail',      'email',    'post'));
        $langForce = clean('langForce', 'cmd', 'post');
        if (empty($langForce)) { $langForce = bizuno_get_locale(); }
        setUserCache('profile', 'langForce', $langForce);
        setUserCache('profile', 'language', $langForce); // update the language also
        dbClearCache();
        bizSetCookie('bizunoLang', getUserCache('profile', 'language'), time()+(60*60*24*7));
        msgLog(lang('bizuno_profile')." - ".lang('update')." ".getWordPressEmail());
        $data = ['content'=>['action'=>'href','link'=>BIZUNO_HOME."&bizRt=bizuno/profile/edit"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * manager to enter, delete and support the reminder dashboard
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function reminderManager(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'profile', 1)) { return; }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs' => ['divReminder' => ['order'=>50, 'type'=>'accordion','key' =>"accReminder"]],
            'accordion'=> ['accReminder'=>  ['divs'=>  [
                'divReminderMgr' => ['order'=>30,'label'=>$this->lang['reminders'],'type'=>'datagrid','key'=>'dgReminder'],
                'divReminderDtl' => ['order'=>70,'label'=>lang('details'),'type'=>'html', 'html'=>'&nbsp;']]]],
            'datagrid' => ['dgReminder'=>$this->dgReminder('dgReminder', $security)]]);
    }

    /**
     * Lists the reminders for the user to support the manager
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function reminderManagerRows(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'profile', 1)) { return; }
        $result  = dbGetRow(BIZUNO_DB_PREFIX."users_profiles", "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='reminder'");
        $settings= clean($result['settings'], 'json');
        if (!isset($settings['source'])) { $settings['source'] = []; }
        $output  = [];
        foreach ((array)$settings['source'] as $idx => $values) {
            $output[] = ['id'=>($idx+1),'title'=>$values['title'],'recur'=>$this->freqs[$values['recur']],
                'dateStart'=>viewFormat($values['dateStart'], 'date'),'dateNext'=>viewFormat($values['dateNext'], 'date')];
        }
        $total = sizeof($output);
        $page = clean('page', ['format'=>'integer','default'=>1], 'post');
        $rows = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post');
        $sort = clean('sort', ['format'=>'text',   'default'=>'label'], 'post');
        $order= clean('order',['format'=>'text',   'default'=>'asc'], 'post');
        $temp = [];
        foreach ($output as $key => $value) { $temp[$key] = $value[$sort]; }
        array_multisort($temp, $order=='desc'?SORT_DESC:SORT_ASC, $output);
        $parts = array_slice($output, ($page-1)*$rows, $rows);
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>$total, 'rows'=>$parts])]);
    }

    /**
     * Editor for reminders
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function reminderEdit(&$layout=[])
    {
        if (!validateSecurity('bizuno', 'profile', 2)) { return; }
        $fields = [
            'title'    => ['order'=>10,'break'=>true,'label'=>lang('title'),'attr'=>['value'=>'']],
            'dateStart'=> ['order'=>20,'break'=>true,'label'=>$this->lang['start_date'],'attr'=>['type'=>'date','value'=>biz_date('Y-m-d')]],
            'recur'    => ['order'=>30,'break'=>true,'label'=>$this->lang['frequency'], 'values'=>viewKeyDropdown($this->freqs),'attr'=>['type'=>'select','value'=>'m']]];
        $data = ['type'=>'divHTML',
            'divs'    => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbReminder'],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmReminder'],
                'body'   => ['order'=>50,'type'=>'fields', 'keys'=>array_keys($fields)],
                'formEOF'=> ['order'=>90,'type'=>'html',   'html'=>"</form>"]],
            'toolbars'=> ['tbReminder'=>['icons'=>['save'=>['order'=>10,'icon'=>'save','label'=>lang('save'),'events'=>['onClick'=>"jqBiz('#frmReminder').submit();"]]]]],
            'forms'   => ['frmReminder'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=bizuno/profile/reminderSave"]]],
            'fields'  => $fields,
            'jsReady' => ['init'=>"ajaxForm('frmReminder');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Adds a new reminder to the list, not possible to edit, need to delete and re-save a new reminder
     * @param array $layout - structure coming in typically []
     * @return array - $layout modified
     */
    public function reminderSave(&$layout=[])
    {
        if (!validateSecurity('bizuno', 'profile', 2)) { return; }
        if (!$title= clean('title',    'text', 'post')){ return msgAdd("Title cannot be blank"); }
        $dateStart = clean('dateStart',['format'=>'date','default'=>biz_date('Y-m-d')], 'post');
        $recur     = clean('recur',    'char', 'post');
        $dateNext  = $dateStart;
        $result    = dbGetRow(BIZUNO_DB_PREFIX."users_profiles", "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='reminder'");
        $settings  = clean($result['settings'], 'json');
        if (!$result) {
            bizAutoLoad(BIZBOOKS_ROOT."controllers/bizuno/dashboards/reminder/reminder.php", 'reminder');
            $dashB    = new reminder();
            $dashB->install('bizuno', 'home');
            $settings = [];
        }
        if ($dateStart <= biz_date('Y-m-d')) { // see if any are due, add to current array if so
            $settings['current'][] = ['title'=>$title, 'date'=>$dateStart];
            $dateNext = LocaleSetDateNext($dateStart, $recur);
        }
        $settings['source'][] = ['title'=>$title, 'recur'=>$recur, 'dateStart'=>$dateStart, 'dateNext'=>$dateNext];
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='reminder'");
        msgAdd(lang('msg_record_saved'), 'success');
        msgLog("{$this->lang['reminders']} - ".lang('save')." - $title");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accReminder').accordion('select', 0); bizGridReload('dgReminder'); jqBiz('#divReminderDtl').html('&nbsp;');"]]);
    }

    /**
     * Deletes a reminder
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function reminderDelete(&$layout=[])
    {
        if (!validateSecurity('bizuno', 'profile', 4)) { return; }
        if (!$rID = clean('rID', 'integer', 'get')) { return msgAdd('The proper id was not passed!'); }
        $result   = dbGetRow(BIZUNO_DB_PREFIX."users_profiles", "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='reminder'");
        $settings = clean($result['settings'], 'json');
        $title    = $settings['source'][($rID-1)]['title'];
        unset($settings['source'][($rID-1)]);
        $settings['source'] = array_values($settings['source']);
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='reminder'");
        msgLog("{$this->lang['reminders']} - ".lang('delete')." - $title");
        $jsData = "jqBiz('#accReminder').accordion('select', 0); bizGridReload('dgReminder'); jqBiz('#divReminderDtl').html('&nbsp;');";
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>$jsData]]);
    }

    /**
     * Grid structure for reminders
     * @param string $name - DOM element id
     * @param integer $security - users security level
     * @return array - Grid structure
     */
    public function dgReminder($name, $security=0)
    {
        $output = ['id'=>$name, 'rows'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'page'=>'1',
            'attr'=> ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'url'=>BIZUNO_AJAX."&bizRt=bizuno/profile/reminderManagerRows&uID=".getUserCache('profile', 'admin_id', false, 0).""],
            'source'   => ['actions'=>['reminderNew'=>['order'=>10,'icon'=>'new','events'=>['onClick'=>"accordionEdit('accReminder','dgReminder','divReminderDtl','".jsLang('details')."','bizuno/profile/reminderEdit', 0);"]]]],
            'columns'  => ['id'=>['order'=>0,'attr'=>['hidden'=>true]],
                'action' => ['order'=> 1, 'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'delete'=> ['icon'=>'trash','size'=>'small', 'order'=>90, 'hidden'=>$security>3?false:true,
                            'events'=> ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/profile/reminderDelete', idTBD);"]]]],
                'title'    => ['order'=>10,'label'=>lang('title'),            'attr'=>['width'=>300,'resizable'=>true]],
                'recur'    => ['order'=>20,'label'=>$this->lang['frequency'], 'attr'=>['width'=>100,'resizable'=>true]],
                'dateStart'=> ['order'=>30,'label'=>$this->lang['start_date'],'attr'=>['width'=>100,'resizable'=>true]],
                'dateNext' => ['order'=>50,'label'=>$this->lang['next_date'], 'attr'=>['width'=>100,'resizable'=>true]]]];
        if ($GLOBALS['myDevice'] == 'mobile') {
            $output['columns']['recur']['attr']['hidden'] = true;
            $output['columns']['dateStart']['attr']['hidden'] = true;
        }
        return $output;
    }
}
