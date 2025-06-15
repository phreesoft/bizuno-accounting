<?php
/*
 * Inventory dashboard - Stock levels by month as seen in the journal_history
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
 * @filesource /controllers/inventory/dashboards/inv_stock/inv_stock.php
 */

namespace bizuno;

class inv_stock
{
    public  $moduleID = 'inventory';
    public  $methodDir= 'dashboards';
    public  $code     = 'inv_stock';
    public  $category = 'inventory';

    function __construct($settings=[])
    {
        $this->security= getUserCache('security', 'inv_mgr', false, 0);
        $glDefaults    = getModuleCache('phreebooks', 'chart', 'defaults', getDefaultCurrency());
        $defaults      = ['users'=>-1,'roles'=>-1,'glAcct'=>isset($glDefaults[4])?$glDefaults[4]:''];
        $this->settings= array_replace_recursive($defaults, $settings);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $this->dates   = localeDates(true, true, true, false, true);
    }

    public function settingsStructure()
    {
        return [
            'users' => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10,'multiple'=>'multiple']],
            'roles' => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10,'multiple'=>'multiple']],
            'glAcct'=> ['order'=>10,'break'=>true,'position'=>'after','types'=>[lang('gl_acct_type_4')],'label'=>lang('gl_account'),'attr'=>['type'=>'ledger','value'=>$this->settings['glAcct']]]];
    }

    /**
     *
     * @return type
     */
    public function render(&$layout=[])
    {
        if (empty($this->settings['glAcct'])) { return msgAdd('Please select a valid GL account'); }
        $struc  = $this->settingsStructure();
        $iconExp= ['attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#inv_data').submit();"]];
        $action = BIZUNO_AJAX."&bizRt=inventory/tools/invDataGo";
        $output = ['divID'=>$this->code."_chart",'type'=>'line','attr'=>['chartArea'=>['left'=>'15%'],'title'=>'GL Acct: '.$this->settings['glAcct']],'data'=>$this->getData()];
        $js     = "ajaxDownload('inv_data');
var data_{$this->code} = ".json_encode($output).";
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(chart{$this->code});
function chart{$this->code}() { drawBizunoChart(data_{$this->code}); };";
        $filter = ucfirst(lang('filter')).": GL Acct: {$this->settings['glAcct']}";
        $layout = array_merge_recursive($layout, [
            'divs'   => [
                'admin' =>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'glAcct']]]],
                'head'  =>['order'=>40,'type'=>'html','html'=>$filter,'hidden'=>getModuleCache('bizuno','settings','general','hide_filters',0)],
                'body'  =>['order'=>50,'type'=>'html','html'=>'<div style="width:100%" id="'.$this->code.'_chart"></div>'],
                'export'=>['order'=>95,'type'=>'html','html'=>'<form id="inv_data" action="'.$action.'">'.html5('', $iconExp).'</form>']],
            'fields' => [$this->code.'glAcct'=>array_merge_recursive($struc['glAcct'], ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]])],
            'jsHead' => ['init'=>$js]]);
    }

    public function getData()
    {
        $period = calculatePeriod(biz_date('Y-m-d'), true);
        $begPer = $period - 12;
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "gl_account='{$this->settings['glAcct']}' AND period >= $begPer AND period <= $period", "period");
        $data[] = [lang('period'), lang('value')]; // headings
        foreach ($rows as $row) {
            $bal = $row['beginning_balance'] + $row['debit_amount'] - $row['credit_amount'];
            $data[] = [$row['period'], round($bal)];
        }
        return $data;
    }

    /**
     *
     */
    public function save()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $this->settings['glAcct']= clean($this->code.'glAcct','cmd','post');
        dbWrite(BIZUNO_DB_PREFIX.'users_profiles', ['settings'=>json_encode($this->settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
    }
}
