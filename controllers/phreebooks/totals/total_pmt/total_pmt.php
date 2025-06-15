<?php
/*
 * PhreeBooks total method for total payments applied
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
 * @filesource /controllers/phreebooks/totals/total_pmt/total_pmt.php
 */

namespace bizuno;

class total_pmt
{
    public $code     = 'total_pmt';
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $required = true;

    public function __construct()
    {
        $this->settings= ['journals'=>'[19]','order'=>98];
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
//      $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
//      settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
     }

    /**
     * Sets the structure
     * @return array - method settings
     */
    public function settingsStructure()
    {
        return [
            'journals'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'order'   => ['label'=>lang('order'),'options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    /**
     * Renders the HTML for this method
     * @param array $output - running output buffer
     * @return modified $output
     */
    public function render()
    {
        $html = '<div style="text-align:right">'."\n";
        $html.= html5("totals_{$this->code}", ['label'=>$this->lang['total_pmt'],'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]);
        $html.= "</div>\n";
        htmlQueue("function totals_total_pmt(begBalance) {
    var totalPayment= bizNumGet('totals_{$this->code}');
    var newBalance = begBalance;
    newBalance -= totalPayment;
    bizNumSet('total_pmt', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
