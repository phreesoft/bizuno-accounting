<?php
/*
 * PhreeBooks totals - Beginning balance
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
 * @version    6.x Last Update: 2023-03-04
 * @filesource /controllers/phreebooks/totals/balanceBeg/balanceBeg.php
 *
 */

namespace bizuno;

class balanceBeg
{
    public $code      = 'balanceBeg';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;

    public function __construct()
    {
        $this->settings= ['gl_type'=>'','journals'=>'[20,22]','order'=>0];
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
        // ajax request with GL acct/post_date to get starting balance
        // need to modify post_date and gl_account field to call javascript call
        $this->fields = ['totals_balanceBeg'=>['label'=>$this->lang['title'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']]];
        $html = '<div style="text-align:right">'."\n"
                .html5('totals_balanceBeg',$this->fields['totals_balanceBeg']).html5('', ['icon'=>'blank', 'size'=>'small'])."</div>\n";
        htmlQueue("function totals_balanceBeg(begBalance) { return cleanCurrency(bizTextGet('totals_balanceBeg')); }
function totalsGetBegBalance(postDate) {
    var rID      = jqBiz('#id').val();
    var glAccount= bizSelGet('gl_acct_id');
    jqBiz.ajax({
        url: '".BIZUNO_AJAX."&bizRt=phreebooks/main/journalBalance&rID='+rID+'&postDate='+postDate+'&glAccount='+glAccount,
        success: function (json) {
            processJson(json);
            if (json.balance) { bizNumSet('totals_balanceBeg', json.balance); }
            else              { alert('Balance could not be found!'); }
            totalUpdate('balanceBeg');
       }
    });
}", 'jsHead');
        htmlQueue("totalsGetBegBalance(bizDateGet('post_date'));", 'jsReady');
        return $html;
    }
}
