<?php
/*
 * Bizuno dashboard - My Links
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
 * @filesource /controllers/bizuno/dashboards/my_links/my_links.php
 *
 */

namespace bizuno;

class my_links
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'my_links';
    public $category = 'general';

    function __construct($settings)
    {
        $this->security= getUserCache('security', 'profile', 0);
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

    public function render(&$layout=[])
    {
        $index = 1;
        if (empty($this->settings['data'])) { $rows[] = '<li><span>'.lang('no_results')."</span></li>"; }
        else { foreach ($this->settings['data'] as $title => $hyperlink) {
            $trash  = html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->moduleID:$this->code', $index); }"]]);
            $rows[] = viewDashList(viewFavicon($hyperlink, $title, true)." $title", $trash);
            $index++;
        } }
        $layout = array_merge_recursive($layout, [
            'divs'  => [
                'admin'=>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'_0', $this->code.'_1', $this->code.'_btn']]]],
                'body' =>['order'=>50,'type'=>'list','key'=>$this->code]],
            'fields'=> [
                $this->code.'_0'  => ['order'=>10,'break'=>true,'label'=>lang('title'),'attr'=>['required'=>'true']],
                $this->code.'_1'  => ['order'=>20,'break'=>true,'label'=>lang('url')." including http:// or https://",'attr'=>['required'=>'true']],
                $this->code.'_btn'=> ['order'=>90,'attr'=>['type'=>'button','value'=>lang('add')],'events'=>['onClick'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]],
            'lists' => [$this->code=>$rows]]);
    }

    public function save()
    {
        $menu_id = clean('menuID', 'cmd', 'get');
        $rmID    = clean('rID', 'integer', 'get');
        $my_title= clean($this->code.'_0', 'text', 'post');
        $my_url  = clean($this->code.'_1', 'text', 'post');
        if (!$rmID && ($my_title == '' || $my_url == '')) { return; }
        // fetch the current settings
        $result = dbGetRow(BIZUNO_DB_PREFIX."users_profiles", "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND menu_id='$menu_id' AND dashboard_id='$this->code'");
        $settings = json_decode($result['settings'], true);
        if (!isset($settings['data'])) { unset($settings['users']); unset($settings['roles']); $settings=['data'=>$settings]; } // OLD WAY
        if ($rmID) { array_splice($settings['data'], $rmID - 1, 1); }
        else       { $settings['data'][$my_title] = $my_url; }
        ksort($settings['data'], SORT_LOCALE_STRING | SORT_FLAG_CASE);
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
        return $result['id'];
    }
}
