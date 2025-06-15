<?php
/*
 * PhreeBooks Totals - Subtotal by checkbox
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
 * @version    6.x Last Update: 2022-12-28
 * @filesource /controllers/phreebooks/totals/subtotalChk/subtotalChk.php
 */

namespace bizuno;

class subtotalChk {
    public $code      = 'subtotalChk';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;

    public function __construct()
    {
        $this->settings= ['gl_type'=>'sub','journals'=>'[17,18,20,22]','order'=>0];
        $this->lang    = getMethLang   ($this->moduleID, $this->methodDir, $this->code);
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type' => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'order'   => ['label'=>lang('order'),'options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    public function render()
    {
        $this->fields = [
            'totals_subtotal'    => ['label'=>$this->lang['subtotal'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']],
            'totals_subtotal_opt'=> ['icon'=>'blank','size'=>'small']];
        $html = '<div style="text-align:right">'
            .html5('totals_subtotal', $this->fields['totals_subtotal'])
            .html5('',                $this->fields['totals_subtotal_opt'])."</div>\n";
        htmlQueue("function totals_subtotalChk(begBalance) {
    var newBalance = 0;
    var rowData = jqBiz('#dgJournalItem').datagrid('getData');
    for (var i=0; i<rowData.rows.length; i++) {
        if (rowData.rows[i].is_ach!=1 && rowData.rows[i]['checked']) {
            var total   = parseFloat(rowData.rows[i].total);
            if (isNaN(total)) { total = 0; }
            var discount= parseFloat(rowData.rows[i].discount);
            if (isNaN(discount)) { discount = 0; }
            newBalance += total + discount;
        }
    }
    bizNumSet('totals_subtotal', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
