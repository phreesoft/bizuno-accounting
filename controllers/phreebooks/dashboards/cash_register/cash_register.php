<?php
/*
 * Contacts dashboard - New Customers
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
 * @version    6.x Last Update: 2023-08-31
 * @filesource /controllers/phreebooks/dashboards/cash_register/cash_register.php
 */

namespace bizuno;

class cash_register
{
    public $moduleID  = 'phreebooks';
    public $methodDir = 'dashboards';
    public $code      = 'cash_register';
    public $category  = 'banking';
    public $noSettings= true;

    function __construct($settings)
    {
        $this->security= getUserCache('security', 'mgr_c', false, 0);
        $defaults      = ['users'=>-1,'roles'=>-1];
        $this->settings= array_replace_recursive($defaults, $settings);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
    }

    public function settingsStructure()
    {
        return [
            'users' => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10,'multiple'=>'multiple']],
            'roles' => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10,'multiple'=>'multiple']]];
    }

    public function render(&$layout=[])
    {
        $total  = 0;
        $cashGL = [];
        $glAccts= getModuleCache('phreebooks', 'chart', 'accounts');
        foreach ($glAccts as $glAcct => $props) {
            if (!empty($props['type']) || !empty($props['inactive'])) { continue; } // cash accounts are of type 0, or inactive
            $balance = dbGetGLBalance($glAcct, biz_date());
            $cashGL[]= [$props['id'], $props['title'], ['v'=>$balance,'f'=>viewFormat($balance, 'currency')]];
            $total += $balance;
        }
        $cashGL[]= [lang('total'), '', ['v'=>$total,'f'=>viewFormat($total, 'currency')]];
        $js = "function chart{$this->code}() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', '".jslang('GL Acct')."');
    data.addColumn('string', '".jslang('Bank')   ."');
    data.addColumn('number', '".jslang('balance')."');
    data.addRows(".json_encode($cashGL).");
    data.setColumnProperties(0, {style:'font-style:bold;text-align:center'});
    var table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, {showRowNumber:false, width:'100%', height:'100%'});
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n";
        $layout = array_merge_recursive($layout, [
            'divs'  => ['body'=>['order'=>50,'type'=>'html','html'=>'<span style="width:100%" id="'.$this->code.'_chart"></span>']],
            'jsHead'=> ['init'=>$js]]);
    }
}
