<?php
/*
 * WordPress Plugin - View methods tailored for portal
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
 * @version    3.x Last Update: 2023-08-27
 * @filesource controllers/portal/view.php
 */

namespace bizuno;

/**
 * Handles page rendering specific to the distribution
 * This class varies depending on framework.
 */
class portalView
{
    function __construct()
    {
        $this->myDevice = !empty($GLOBALS['myDevice']) ? $GLOBALS['myDevice'] : 'desktop';
    }

    /**
     * DEPRECATED - For use when operating within admin screen
     */
    public function BuildHead() { }

    /**
     * manually generates head used for full screen user access
     * @param array $data -
     * @return modified $data
     */
    private function setEnvHTML(&$data=[])
    {
        $icons   = getUserCache('profile', 'icons', false, 'default');
        $theme   = getUserCache('profile', 'theme', false, 'bizuno');
//      $lang    = substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2);
        $logoPath= getModuleCache('bizuno', 'settings', 'company', 'logo');
        $favicon = $logoPath ? BIZBOOKS_URL_FS."&src=".getUserCache('profile', 'biz_id')."/images/$logoPath" : BIZUNO_LOGO;
        $js  = "var jqBiz = $.noConflict();\n";
        $js .= "var bizID = '".getUserCache('profile','biz_id', false, 0)."';\n";
        $js .= "var bizunoHome = '".BIZUNO_HOME."';\n";
        $js .= "var bizunoAjax = '".BIZUNO_AJAX."';\n";
        $js .= "var bizunoAjaxFS = '".BIZBOOKS_URL_FS."';\n";
        $js .= "var myDevice = '{$GLOBALS['myDevice']}';\n";
        // Create page Head HTML
        $data['head']['metaTitle']   = ['order'=>20,'type'=>'html','html'=>'<title>'.(!empty($data['title']) ? $data['title'] : getModuleCache('bizuno', 'properties', 'title')).'</title>'];
        $data['head']['metaPath']    = ['order'=>22,'type'=>'html','html'=>'<!-- route:'.clean('bizRt',['format'=>'path_rel','default'=>''],'get').' -->'];
        $data['head']['metaContent'] = ['order'=>24,'type'=>'html','html'=>'<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'];
        $data['head']['metaViewport']= ['order'=>26,'type'=>'html','html'=>'<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=0.9, maximum-scale=0.9" />'];
        $data['head']['metaMobile']  = ['order'=>30,'type'=>'html','html'=>'<meta name="mobile-web-app-capable" content="yes" />'];
        $data['head']['metaIcon']    = ['order'=>28,'type'=>'html','html'=>'<link rel="icon" type="image/png" href="'.$favicon.'" />'];
        $data['head']['cssTheme']    = ['order'=>40,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/themes/'.$theme.'/easyui.css" />'];
        $data['head']['cssIcon']     = ['order'=>42,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/themes/icon.css" />'];
        $data['head']['cssStyle']    = ['order'=>44,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZBOOKS_URL_ROOT.'view/easyUI/stylesheet.css" />'];
        $data['head']['cssBizuno']   = ['order'=>46,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_AJAX.'&bizRt=bizuno/portal/viewCSS&icons='.$icons.'" />'];
        $data['head']['cssMobile']   = ['order'=>50,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/themes/mobile.css" />'];
        $data['head']['jsjQuery']    = ['order'=>60,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_ROOT.'assets/jQuery-3.7.1.js"></script>'];
//      $data['head']['jstinyMCE']   = ['order'=>61,'type'=>'html','html'=>'<script type="text/javascript" src="https://www.phreesoft.com/biz-apps/tinymce/tinymce.min.js"></script>'];
        $data['head']['jsBizuno']    = ['order'=>62,'type'=>'html','html'=>'<script type="text/javascript">'.$js."</script>"];
        $data['head']['jsEasyUI']    = ['order'=>64,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/jquery.easyui.min.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];
        $data['head']['jsMobile']    = ['order'=>66,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/jquery.easyui.mobile.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];
//      $data['head']['jsLang']      = ['order'=>72,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/locale/easyui-lang-'.$lang.'.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];
        $data['head']['jsCommon']    = ['order'=>78,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_ROOT.'view/easyUI/common.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];
        $data['head']['jsEasyExt']   = ['order'=>80,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_ROOT.'assets/jquery-easyui/easyui-extensions.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];

// Font Awesome Working kit
// <script src="https://kit.fontawesome.com/302352c521.js" crossorigin="anonymous"></script>

        // add return to admin link
//        $page = get_page_by_path('bizuno');
        $href = get_home_url();
        if ( current_user_can('editor') || current_user_can('administrator') ) {
            $data['header']['divs']['right']['data']['child']['settings']['child']['wpAdmin'] =
                ['order'=>90,'label'=>'WP Admin','icon'=>'wp-admin','required'=>true,'events'=>['onClick'=>"window.location='$href';"]];
        }
    }

    /**
     * Platform specific DOM, in this case is the full page
     * @param type $data
     */
    public function viewDOM($data, $scope='default') {
        msgDebug("\nEntering viewDOM");
//        if (empty($GLOBALS['bizunoPortal'])) { // for some reason in wp-admin, the div height starts at 0px, set to 100%
//            $data['jsReady'][] = "jqBiz('#bizBody').css('height', '100%');";
//            unset($data['header']['divs']['right']['data']['child']['settings']['child']['logout']);
//        }
        $dom  = '';
        if ($scope=='div') {
            $dom .= $this->renderHead($data);
        } else {
            $this->setEnvHTML($data); // load the <head> HTML for pages
            $dom .= "<!DOCTYPE HTML>\n";
            $dom .= "<html>\n";
            $dom .= "<head>\n";
            $dom .= $this->renderHead($data);
            $dom .= "</head>\n";
            $dom .= "<body>\n";
        }
        $dom .= '  <div id="bizBody" class="easyui-navpanel">'."\n";
        $dom .= $this->renderDivs($data);
        $dom .= "  </div>\n";
        $dom .= $this->renderJS($data);
        $dom .= '  <iframe id="attachIFrame" src="" style="display:none;visibility:hidden;"></iframe>'; // For file downloads
        $dom .= '  <div class="modal"></div><div id="divChart"></div><div id="navPopup" class="easyui-navpanel"></div>';
        if ($scope=='div') { } // do nothing
        else               { $dom .= "\n</body>\n</html>"; }
        return $dom;
    }
}
