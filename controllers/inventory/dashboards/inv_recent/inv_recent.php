<?php
/*
 * PhreeBooks Dashboard - Today's Vendor Purchases
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
 * @version    6.x Last Update: 2023-06-01
 * @filesource /controllers/inventory/dashboards/inv_recent/inv_recent.php
 */

namespace bizuno;

class inv_recent
{
    public $moduleID = 'inventory';
    public $methodDir= 'dashboards';
    public $code     = 'inv_recent';
    public $category = 'inventory';

    function __construct($settings)
    {
        $this->security= getUserCache('security', 'j6_mgr', false, 0);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $defaults      = ['jID'=>6,'max_rows'=>20,'users'=>-1,'roles'=>-1,'store_id'=>-1,'num_rows'=>5,'order'=>'desc'];
        $this->settings= array_replace_recursive($defaults, $settings);
        $this->trim    = 20; // length to trim primary_name to fit in frame
    }

    /**
     *
     * @return type
     */
    public function settingsStructure()
    {
        $order  = ['asc'=>lang('increasing'),'desc'=>lang('decreasing')];
        $stores = getModuleCache('bizuno', 'stores');
        array_unshift($stores, ['id'=>-1, 'text'=>lang('all')]);
        return [
            'jID'     => ['attr'=>['type'=>'hidden','value'=>$this->settings['jID']]],
            'max_rows'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['max_rows']]],
            'users'   => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10,'multiple'=>'multiple']],
            'roles'   => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10,'multiple'=>'multiple']],
            'store_id'=> ['order'=>10,'break'=>true,'position'=>'after','label'=>lang('store_id'),'values'=>$stores,'attr'=>['type'=>'select','value'=>$this->settings['store_id']]],
            'num_rows'=> ['order'=>20,'break'=>true,'position'=>'after','label'=>lang('limit_results'),'options'=>['min'=>0,'max'=>50,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['num_rows']]],
            'order'   => ['order'=>30,'break'=>true,'position'=>'after','label'=>lang('sort_order'),   'values'=>viewKeyDropdown($order),'attr'=>['type'=>'select','value'=>$this->settings['order']]]];
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function render(&$layout=[])
    {
        $rows  = [];
        $struc = $this->settingsStructure();
        $today = biz_date('Y-m-d');
        $lstWk = localeCalculateDate($today, -7);
        $filter= "journal_id=6 AND post_date>'$lstWk' AND post_date<='$today'";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($this->settings['store_id'] > -1) {
            $filter .= " AND store_id='{$this->settings['store_id']}'";
        }
        $order = $this->settings['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','post_date','purch_order_id','store_id'], $this->settings['num_rows']);
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $row) { // row has store id if that matters
                $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$row['id']} AND gl_type='itm' AND sku<>''");
                foreach ($items as $item) {
                    $type   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "sku='".addslashes($item['sku'])."'");
                    if (in_array($type, ['sv','lb'])) { continue; }
                    $elDOM  = ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID=6&rID={$row['id']}');"],'attr'=>['type'=>'button','value'=>"#{$row['purch_order_id']}"]];
                    $store  = sizeof(getModuleCache('bizuno', 'stores')) > 1 ? viewFormat($this->settings['num_rows'], 'storeID').' ' : '';
                    $left   = biz_date('m/d', strtotime($row['post_date']))." $store - ({$item['qty']}) ".viewText($item['sku'], $this->trim);
                    $right  = ''; // viewText($item['qty']);
                    $action = html5('', $elDOM);
                    $rows[] = viewDashLink($left, $right, $action);
                }
            }
        }
        $filter = ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($this->settings['order']).(!empty($this->settings['num_rows']) ? " ({$this->settings['num_rows']});" : '');
        $layout = array_merge_recursive($layout, [
            'divs'   => [
                'admin'=>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'num_rows', $this->code.'store_id', $this->code.'order']]]],
                'head' =>['order'=>40,'type'=>'html','html'=>$filter,'hidden'=>getModuleCache('bizuno','settings','general','hide_filters',0)],
                'body' =>['order'=>50,'type'=>'list','key' =>$this->code]],
            'fields' => [
                $this->code.'store_id'=> array_merge_recursive($struc['store_id'],['events'=>['onChange'=>"jqBiz('#{$this->code}num_rows').keyup();"]]),
                $this->code.'num_rows'=> array_merge_recursive($struc['num_rows'],['events'=>['onChange'=>"jqBiz('#{$this->code}num_rows').keyup();"]]),
                $this->code.'order'   => array_merge_recursive($struc['order'],   ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]])],
            'lists'  => [$this->code=>$rows],
            'jsReady'=>['init'=>"dashDelay('$this->moduleID:$this->code', 0, '{$this->code}num_rows');"]]);
    }

    /**
     *
     */
    public function save()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $settings = [
            'store_id'=> clean($this->code.'store_id',['format'=>'integer','default'=>0],'post'), // default needs to be zero or clean will not allow zero setting, returns default
            'num_rows'=> clean($this->code.'num_rows', 'integer','post'),
            'order'   => clean($this->code.'order', 'cmd', 'post')];
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
    }
}
