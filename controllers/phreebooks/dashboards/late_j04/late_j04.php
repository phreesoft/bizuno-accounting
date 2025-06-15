<?php
/*
 * Phreebooks dashboard - Late Items from Vendor Purchase Orders
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
 * @version    6.x Last Update: 2024-01-25
 * @filesource /controllers/phreebooks/dashboards/late_j04/late_j04.php
 */

namespace bizuno;

class late_j04
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'dashboards';
    public $code     = 'late_j04';
    public $category = 'vendors';

    function __construct($settings)
    {
        $this->security= getUserCache('security', 'j4_mgr', false, 0);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $defaults      = ['jID'=>4,'max_rows'=>20,'users'=>-1,'roles'=>-1,'store_id'=>-1,'reps'=>0,'num_rows'=>5,'order'=>'desc'];
        $this->settings= array_replace_recursive($defaults, $settings);
        $this->trim    = 20; // length to trim primary_name to fit in frame
        $this->order   = ['asc'=>lang('increasing'),'desc'=>lang('decreasing')];
    }

    public function settingsStructure()
    {
        return [
            'jID'     => ['attr'=>['type'=>'hidden','value'=>$this->settings['jID']]],
            'max_rows'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['max_rows']]],
            'users'   => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10, 'multiple'=>'multiple']],
            'roles'   => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10, 'multiple'=>'multiple']],
            'reps'    => ['label'=>lang('just_reps'),    'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['reps']]],
            'num_rows'=> ['order'=>10,'break'=>true,'position'=>'after','label'=>lang('limit_results'),'options'=>['min'=>0,'max'=>50,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['num_rows']]],
            'order'   => ['order'=>20,'break'=>true,'position'=>'after','label'=>lang('sort_order'),   'values'=>viewKeyDropdown($this->order),'attr'=>['type'=>'select','value'=>$this->settings['order']]],
            'store_id'=> ['order'=>30,'break'=>true,'position'=>'after','label'=>lang('store_id'),'values'=>dbGetStores(true),'attr'=>['type'=>'select','value'=>$this->settings['store_id']]]];
    }

    function render(&$layout=[])
    {
        global $currencies;
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/contacts/functions.php', 'getContactID', 'function');
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'getInvoiceInfo', 'function');
        $struc = $this->settingsStructure();
        $today = biz_date('Y-m-d');
        $filter= "journal_id={$this->settings['jID']} AND closed='0'";
        if ($this->settings['reps'] && getUserCache('profile', 'contact_id', false, '0')) {
            if (getUserCache('security', 'admin', false, 0)<3) { $filter.= " AND rep_id='".getUserCache('profile', 'contact_id', false, '0')."'"; }
        }
        if (!empty(getUserCache('profile', 'restrict_store'))) { $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        elseif ($this->settings['store_id']>-1)                { $filter.= " AND store_id=".$this->settings['store_id']; }
        $order = $this->settings['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','journal_id','total_amount','currency','currency_rate','post_date','invoice_num', 'primary_name_b']);
        $total = $counter = 0;
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $entry) { // build the list
                $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$entry['id']} AND gl_type='itm' AND date_1<'$today'", '', ['id', 'sku', 'qty', 'description', 'credit_amount', 'debit_amount','date_1']);
                foreach ($items as $item) {
                    $item['total_amount'] = $item['debit_amount'] - $item['credit_amount'];
                    $filled= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty) AS qty", "item_ref_id={$item['id']} AND gl_type='itm'", false);
                    if ($item['qty'] <= $filled || empty($item['total_amount'])) { continue; }
                    $currencies->iso  = $entry['currency'];
                    $currencies->rate = $entry['currency_rate'];
                    $total += $item['total_amount'];
                    $left   = viewDate($item['date_1'])." - ".viewText($item['description'], $this->trim);
                    $right  = viewFormat($item['total_amount'], 'currency');
                    $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID={$this->settings['jID']}&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                    $rows[] = viewDashLink($left, $right, $action);
                    $counter++;
                    if (!empty($this->settings['num_rows']) && $counter >= $this->settings['num_rows']) { break; }
                }
                if (!empty($this->settings['num_rows']) && $counter >= $this->settings['num_rows']) { break; }
            }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $rows[] = '<div style="float:right"><b>'.viewFormat($total, 'currency').'</b></div><div style="float:left"><b>'.lang('total')."</b></div>";
        }
        $filter = lang('filter').": ".getContactID($this->settings['store_id']).", ".lang('sort')." ".strtoupper($this->settings['order']).(!empty($this->settings['num_rows']) ? " ({$this->settings['num_rows']});" : '');
        $layout = array_merge_recursive($layout, [
            'divs'   => [
                'admin'=>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'store_id', $this->code.'num_rows', $this->code.'order']]]],
                'head' =>['order'=>40,'type'=>'html','html'=>$filter,'hidden'=>getModuleCache('bizuno','settings','general','hide_filters',0)],
                'body' =>['order'=>50,'type'=>'list','key'=>$this->code]],
            'fields' => [
                $this->code.'store_id'=> array_merge_recursive($struc['store_id'],['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]),
                $this->code.'num_rows'=> array_merge_recursive($struc['num_rows'],['events'=>['onChange'=>"jqBiz('#{$this->code}num_rows').keyup();"]]),
                $this->code.'order'   => array_merge_recursive($struc['order'],   ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]])],
            'lists'  => [$this->code=>$rows],
            'jsReady'=>['init'=>"dashDelay('$this->moduleID:$this->code', 0, '{$this->code}num_rows');"]]);
      }

    function save()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $settings['store_id']= clean($this->code.'store_id','integer','post');
        $settings['num_rows']= clean($this->code.'num_rows','integer','post');
        $settings['order']   = clean($this->code.'order',   'cmd',    'post');
        dbWrite(BIZUNO_DB_PREFIX.'users_profiles', ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
    }
}
