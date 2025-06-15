<?php
/*
 * Bizuno dashboard - Daily tip
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
 * @version    6.x Last Update: 2023-05-03
 * @filesource /controllers/bizuno/dashboards/daily_tip/daily_tip.php
 */

namespace bizuno;

class daily_tip
{
    public $moduleID  = 'bizuno';
    public $methodDir = 'dashboards';
    public $code      = 'daily_tip';
    public $category  = 'bizuno';
    public $noSettings= true;
    public $noCollapse= true;

    function __construct()
    {
        $this->security= 1;
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
    }

    public function render(&$layout=[])
    {
        global $portal;
        $tip = $portal->cURL('https://www.phreesoft.com/wp-admin/admin-ajax.php', 'action=bizuno_ajax&bizRt=myPortal/admin/getTip');
        msgDebug("\nReceived back from cURL: ".print_r($tip, true));
        $layout = array_merge_recursive($layout, [
            'divs' => [
                'icon' =>['order'=>10,'type'=>'html','html'=>'<div style="float:left">'.html5('', ['icon'=>'tip']).'</div>'],
                'tip' => ['order'=>50,'type'=>'html','html'=>!empty($tip) ? $tip : lang('no_results')]]]);
    }
}
