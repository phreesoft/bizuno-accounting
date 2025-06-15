<?php
/*
 * Bizuno dashboard - Company Links
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
 * @version    6.x Last Update: 2020-07-22
 * @filesource /controllers/bizuno/dashboards/company_links/company_links.php
 */

namespace bizuno;

class company_links
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'company_links';
    public $category = 'general';

    function __construct($settings)
    {
        $this->security= 1;
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $defaults      = ['users'=>'-1','roles'=>'-1'];
        $this->settings= array_replace_recursive($defaults, $settings);
    }

    public function settingsStructure()
    {
        return [
            'users' => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10,'multiple'=>'multiple']],
            'roles' => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10,'multiple'=>'multiple']]];
    }

    public function install($moduleID = '', $menu_id = '')
    {
        $settings = dbGetValue(BIZUNO_DB_PREFIX."users_profiles", 'settings', "dashboard_id='$this->code'");
        $sql_data = [
            'user_id'     => getUserCache('profile', 'admin_id', false, 0),
            'menu_id'     => $menu_id,
            'module_id'   => $moduleID,
            'dashboard_id'=> $this->code,
            'column_id'   => 0,
            'row_id'      => 0,
            'settings'    => $settings];
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", $sql_data);
    }

    public function render(&$layout=[])
    {
        $security= getUserCache('security', 'admin', false, 0);
        $index   = 1;
        if (empty($this->settings['data'])) { $rows[] = '<li><span>'.lang('no_results')."</span></li>"; }
        else { foreach ($this->settings['data'] as $title => $hyperlink) {
            $trash  = html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->moduleID:$this->code', $index); }"]]);
            $rows[] = viewDashList(viewFavicon($hyperlink, $title, true)." $title", $security>2 ? $trash : '');
            $index++;
        } }
        $layout  = array_merge_recursive($layout, [
            'divs'  => [
                'admin'=>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'_0', $this->code.'_1', $this->code.'_btn']]]],
                'body' =>['order'=>50,'type'=>'list','key'=>$this->code]],
            'fields'=> [
                $this->code.'_0'  => ['order'=>10,'break'=>true,'hidden'=>$security>1?false:true,'label'=>lang('title'),'attr'=>['required'=>'true']],
                $this->code.'_1'  => ['order'=>20,'break'=>true,'hidden'=>$security>1?false:true,'label'=>lang('url')." including http:// or https://",'attr'=>['required'=>'true']],
                $this->code.'_btn'=> ['order'=>90,'hidden'=>$security>1?false:true,'attr'=>['type'=>'button','value'=>lang('add')],'styles'=>['text-align'=>'right'],'events'=>['onClick'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]],
            'lists' => [$this->code => $rows]]);
    }

    public function save()
    {
        if (getUserCache('security', 'admin', false, 0) < 2) { return msgAdd('Illegal Access!'); }
        $menu_id = clean('menuID', 'cmd', 'get');
        $rmID    = clean('rID', 'integer', 'get');
        $my_title= clean($this->code.'_0', 'text', 'post');
        $my_url  = clean($this->code.'_1', 'text', 'post');
        if (!$rmID && ($my_title == '' || $my_url == '')) { return; }
        // fetch the current settings
        $result = dbGetRow(BIZUNO_DB_PREFIX."users_profiles", "menu_id='$menu_id' AND dashboard_id='$this->code'");
        $settings = json_decode($result['settings'], true);
        if (!isset($settings['data'])) { unset($settings['users']); unset($settings['roles']); $settings=['data'=>$settings]; } // OLD WAY
        if ($rmID) { array_splice($settings['data'], $rmID-1, 1); }
        else       { $settings['data'][$my_title] = $my_url; }
        ksort($settings['data'], SORT_LOCALE_STRING | SORT_FLAG_CASE);
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "dashboard_id='$this->code'");
        return $result['id'];
    }
}
