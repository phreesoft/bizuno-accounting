<?php
/*
 * PhreeBooks dashboard - Sales Summary - chart form
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
 * @version    6.x Last Update: 2022-05-18
 * @filesource /controllers/phreebooks/dashboards/chart_j12/chart_j12.php
 *
 */

namespace bizuno;

class chart_j12
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'dashboards';
    public $code     = 'chart_j12';
    public $category = 'customers';

    function __construct($settings=[])
    {
        $this->security= getUserCache('security', 'j12_mgr', false, 0);
        $defaults      = ['jID'=>12,'rows'=>10,'users'=>-1,'roles'=>-1,'reps'=>0,'selRep'=>-1,'range'=>'l'];
        $this->settings= array_replace_recursive($defaults, $settings);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $this->dates   = localeDates(true, true, true, false, true);
        msgDebug("\nUpdated settings = ".print_r($settings, true));
    }

    public function settingsStructure()
    {
        $roles = viewRoleDropdown();
        array_unshift($roles, ['id'=>'-1', 'text'=>lang('all')]);
        $output = ['jID'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['jID']]],
            'rows'   => ['attr'=>['type'=>'hidden','value'=>$this->settings['rows']]],
            'users'  => ['label'=>lang('users'),            'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10,'multiple'=>'multiple']],
            'roles'  => ['label'=>lang('groups'),           'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10,'multiple'=>'multiple']],
            'reps'   => ['label'=>lang('just_reps'),        'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['reps']]],
            'selRep' => ['label'=>lang('contacts_rep_id_c'),'position'=>'after','values'=>$roles,'attr'=>['type'=>'hidden']],
            'range'  => ['order'=>10,'break'=>true, 'position'=>'after','label'=>lang('range'),'values'=>viewKeyDropdown($this->dates),'attr'=>['type'=>'select','value'=>$this->settings['range']]]];
        if (getUserCache('security', 'admin', false, 0) > 2) {
            $output['selRep']['attr']['type'] = 'select';
            $output['selRep']['attr']['value'] = $this->settings['selRep'];
        }
        return $output;
    }

    public function save()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $settings['range'] = clean($this->code.'range', 'cmd', 'post');
        if (getUserCache('security', 'admin', false, 0) > 2) { $settings['selRep'] = clean($this->code.'selRep', 'integer', 'post'); }
        dbWrite(BIZUNO_DB_PREFIX."users_profiles", ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
    }

    public function render(&$layout=[])
    {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/functions.php", 'phreebooksProcess', 'function');
        $struc  = $this->settingsStructure();
        $iconExp= ['attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#form{$this->code}').submit();"]];
        $selRep = !empty($this->settings['reps']) && getUserCache('security', 'admin', false, 0)<3 ? 0 : $this->settings['selRep'];
        $action = BIZUNO_AJAX."&bizRt=$this->moduleID/tools/exportSales&range={$this->settings['range']}&selRep=$selRep";
        $cData  = chartSales($this->settings['jID'], $this->settings['range'], $this->settings['rows'], $selRep);
        $title  = sprintf($this->lang['chart_title'], $this->dates[$this->settings['range']], $cData['count'], viewFormat($cData['total'], 'currency'));
        $output = ['divID'=>$this->code."_chart",'type'=>'pie','attr'=>['chartArea'=>['left'=>'15%'],'title'=>$title],'data'=>$cData['chart']];
        $js     = "ajaxDownload('form{$this->code}');
var data_{$this->code} = ".json_encode($output).";
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(chart{$this->code});
function chart{$this->code}() { drawBizunoChart(data_{$this->code}); };";
        $filter = ucfirst(lang('filter')).": {$this->dates[$this->settings['range']]}";
        $layout = array_merge_recursive($layout, [
            'divs'  => [
                'admin' =>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'range',$this->code.'selRep']]]],
                'head'  =>['order'=>40,'type'=>'html','html'=>$filter,'hidden'=>getModuleCache('bizuno','settings','general','hide_filters',0)],
                'body'  =>['order'=>50,'type'=>'html','html'=>'<div style="width:100%" id="'.$this->code.'_chart"></div>'],
                'export'=>['order'=>80,'type'=>'html','html'=>'<form id="form'.$this->code.'" action="'.$action.'">'.html5('', $iconExp).'</form>']],
            'fields'=> [
                $this->code.'range' => array_merge_recursive($struc['range'], ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]),
                $this->code.'selRep'=> array_merge_recursive($struc['selRep'],['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]])],
            'jsHead'=> ['init'=>$js]]);
    }
}
