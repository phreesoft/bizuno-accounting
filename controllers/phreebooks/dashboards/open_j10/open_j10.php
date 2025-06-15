<?php
/*
 * PhreeBooks dashboard - Open Customer Sales Orders
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
 * @filesource /controllers/phreebooks/dashboards/open_j10/open_j10.php
 */

namespace bizuno;

class open_j10
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'dashboards';
    public $code     = 'open_j10';
    public $category = 'customers';

    function __construct($settings)
    {
        $this->security= getUserCache('security', 'j10_mgr', false, 0);
        $defaults      = ['jID'=>10,'max_rows'=>20,'users'=>'-1','roles'=>'-1','reps'=>'0','selRep'=>0,'num_rows'=>5,'limit'=>1,'order'=>'desc'];
        $this->settings= array_replace_recursive($defaults, $settings);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $this->trim    = 20; // length to trim primary_name to fit in frame
        $this->noYes   = ['0'  =>lang('no'),        '1'   =>lang('yes')];
        $this->order   = ['asc'=>lang('increasing'),'desc'=>lang('decreasing')];
    }

    public function settingsStructure()
    {
        $roles = viewRoleDropdown();
        array_unshift($roles, ['id'=>'-1', 'text'=>lang('all')]);
        return [
            'jID'     => ['attr'=>['type'=>'hidden','value'=>$this->settings['jID']]],
            'max_rows'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['max_rows']]],
            'users'   => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10, 'multiple'=>'multiple']],
            'roles'   => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10, 'multiple'=>'multiple']],
            'reps'    => ['label'=>lang('just_reps'),'values'=>viewKeyDropdown($this->noYes),'position'=>'after','attr'=>['type'=>'select','value'=>$this->settings['reps']]],
            'selRep'  => ['order'=>10,'break'=>true,'position'=>'after','label'=>lang('contacts_rep_id_c'),'position'=>'after','values'=>$roles,'attr'=>['type'=>'select','value'=>$this->settings['selRep']]],
            'num_rows'=> ['order'=>20,'break'=>true,'position'=>'after','label'=>lang('limit_results'),'options'=>['min'=>0,'max'=>50,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['num_rows']]],
            'limit'   => ['order'=>30,'break'=>true,'position'=>'after','label'=>lang('hide_future'),  'values'=>viewKeyDropdown($this->noYes),'attr'=>['type'=>'select','value'=>$this->settings['limit']]],
            'order'   => ['order'=>40,'break'=>true,'position'=>'after','label'=>lang('sort_order'),   'values'=>viewKeyDropdown($this->order),'attr'=>['type'=>'select','value'=>$this->settings['order']]]];
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    function render(&$layout=[])
    {
        global $currencies;
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'getInvoiceInfo', 'function');
        $selRep = !empty($this->settings['reps']) && getUserCache('security', 'admin', false, 0)<3 ? 0 : $this->settings['selRep'];
        $struc  = $this->settingsStructure();
        $filter = "journal_id={$this->settings['jID']} AND closed='0'";
        if ($selRep==0 && $this->settings['reps'] && getUserCache('profile', 'contact_id', false, '0')) { // None by the select, so limit to current rep ID
            $filter.= " AND rep_id='".getUserCache('profile', 'contact_id', false, '0')."'";
        } elseif ($selRep>0) { // Admin requesting Specific Rep
            $filter.= " AND rep_id='$selRep'";
        } // else all sales
        if (getUserCache('profile', 'restrict_store') && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter.= " AND store_id=".getUserCache('profile', 'store_id');
        }
        if (isset($this->settings['limit']) && $this->settings['limit']=='1') { $filter.= " AND post_date<='".biz_date('Y-m-d')."'"; }
        if (!empty(getUserCache('profile', 'restrict_store'))) { $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        $order  = $this->settings['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result = dbGetMulti(BIZUNO_DB_PREFIX."journal_main", $filter, $order, ['id','journal_id','total_amount','currency','currency_rate','post_date','invoice_num', 'primary_name_b'], $this->settings['num_rows']);
        $total = 0;
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $entry) {
                $currencies->iso  = $entry['currency'];
                $currencies->rate = $entry['currency_rate'];
                $entry['total_amount'] += getInvoiceInfo($entry['id'], $entry['journal_id']);
                $total += $entry['total_amount'];
                $left   = viewDate($entry['post_date'])." - ".viewText($entry['primary_name_b'], $this->trim);
                $right  = viewFormat($entry['total_amount'], 'currency');
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID={$this->settings['jID']}&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $rows[] = '<div style="float:right"><b>'.viewFormat($total, 'currency').'</b></div><div style="float:left"><b>'.lang('total')."</b></div>";
        }
        $filter = ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($this->settings['order']).(!empty($this->settings['num_rows']) ? " ({$this->settings['num_rows']});" : '');
        $layout = array_merge_recursive($layout, [
            'divs'  => [
                'admin'=>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'selRep', $this->code.'num_rows', $this->code.'order', $this->code.'limit']]]],
                'head' =>['order'=>40,'type'=>'html','html'=>$filter,'hidden'=>getModuleCache('bizuno','settings','general','hide_filters',0)],
                'body' =>['order'=>50,'type'=>'list','key' =>$this->code]],
            'fields'=> [
                $this->code.'selRep'  => array_merge_recursive($struc['selRep'],  ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]),
                $this->code.'num_rows'=> array_merge_recursive($struc['num_rows'],['events'=>['onChange'=>"jqBiz('#{$this->code}num_rows').keyup();"]]),
                $this->code.'limit'   => array_merge_recursive($struc['limit'],   ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]),
                $this->code.'order'   => array_merge_recursive($struc['order'],   ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]])],
            'lists' => [$this->code=>$rows],
            'jsReady'=>['init'=>"dashDelay('$this->moduleID:$this->code', 0, '{$this->code}num_rows');"]]);
      }

    function save()
    {
        $menu_id  = clean('menuID', 'text', 'get');
        $settings['selRep']  = clean($this->code.'selRep',  'integer','post');
        $settings['num_rows']= clean($this->code.'num_rows','integer','post');
        $settings['order']   = clean($this->code.'order',   'cmd',    'post');
        $settings['limit']   = clean($this->code.'limit',   'integer','post');
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
    }
}
