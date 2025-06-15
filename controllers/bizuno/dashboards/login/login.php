<?php
/*
 * Bizuno dashboard - Login
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
 * @version    6.x Last Update: 2021-05-12
 * @filesource /controllers/bizuno/dashboards/login/login.php
 */

namespace bizuno;

class login
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'login';
    public $noSettings= true;
    public $noCollapse= true;
    public $noClose   = true;

    function __construct()
    {
        $this->security= getUserCache('profile', 'biz_id', false, 0) ? 0 : 1; // only for the portal to log in
        $this->hidden  = true;
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
    }

    public function render(&$layout=[])
    {
        if (getUserCache('profile', 'email')) {
            $rows    = [];
            $bizList = portalGetBizIDs();
            $theList = sortOrder($bizList, 'title');
            foreach ($theList as $biz) {
                $html  = html5('', ['label'=>$biz['title'],'styles'=>['max-height'=>'50px','height'=>'50px','max-width'=>'150px','width'=>'auto'],'attr'=>['type'=>'img', 'src'=>$biz['src']],
                    'events'=>['onClick'=>"jqBiz('body').addClass('loading'); hrefClick('{$biz['action']}');"]]);
                $html .= html5('biz'.$biz['id'], ['styles'=>['vertical-align'=>'middle','font-size'=>'18px'],'attr'=>['type'=>'span','value'=>$biz['title']],
                    'events'=>['onClick'=>"jqBiz('body').addClass('loading'); hrefClick('{$biz['action']}');"]]);
                $rows[]= $html;
            }
            $layout = array_merge_recursive($layout, [
                'divs' => ['selBiz'=>['order'=>50,'type'=>'list','key'=>'bizIDs']],
                'lists'=> ['bizIDs'=>$rows]]);
        }
    }
}
