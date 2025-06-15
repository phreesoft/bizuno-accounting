<?php
/*
 * PhreeBooks Totals - Balance
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
 * @version    6.x Last Update: 2020-07-29
 * @filesource /controllers/phreebooks/totals/balance/balance.php
 */

namespace bizuno;

class balance
{
    public $code      = 'balance';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;

    public function __construct()
    {
        $this->settings= ['gl_type'=>'','journals'=>'[2]','order'=>99];
        $this->lang    = getMethLang   ($this->moduleID, $this->methodDir, $this->code);
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type' => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'order'   => ['label'=>lang('order'),'position'=>'after','options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    public function render()
    {
        $html  = '<div style="text-align:right">'."\n";
        $html .= html5('total_balance',['label'=>$this->lang['title'],'attr'=>['type'=>'currency','size'=>15,'value'=>0]]);
        $html .= html5('total_amount', ['attr'=>['type'=>'hidden','value'=>0]]);
        $html .= "</div>\n";
        htmlQueue("function totals_balance(begBalance) {
    var newBalance = begBalance;
    if (newBalance == 0) jqBiz('#total_balance').css({color:'#000000'}); else jqBiz('#total_balance').css({color:'#FF0000'});
    bizNumSet('total_balance', newBalance);
    var totalDebit = cleanCurrency(bizNumGet('totals_debit'));
    jqBiz('#total_amount').val(totalDebit);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
