<?php
/*
 * Module Bizuno main methods
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
 * @version    6.x Last Update: 2023-05-12
 * @filesource /controllers/bizuno/main.php
 */

namespace bizuno;

class bizunoMain
{
    public $moduleID = 'bizuno';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * generates the structure for the home page and any main menu dashboard page
     * @param array $layout - structure coming in
     */
    public function bizunoHome(&$layout)
    {
        $mIDdef= getUserCache('profile', 'admin_id', false, 0) ? 'home' : 'portal';
        $title = getModuleCache('bizuno', 'settings', 'company', 'primary_name');
        if (empty($title)) { $title = portalGetBizIDVal(getUserCache('profile', 'biz_id'), 'title'); }
        $menuID= clean('menuID', ['format'=>'text','default'=>$mIDdef], 'get');
        $data  = ['title'=>"$title - ".getModuleCache('bizuno', 'properties', 'title'),
            'jsHead'=>['menu_id'=>"var menuID='$menuID';"]];
        if ($GLOBALS['myDevice'] != 'mobile' && $mIDdef <> 'portal') {
            $data['header']['divs']['right']['data']['child']['settings']['child']['addDash'] = ['order'=>25,'label'=>lang('add_dashboards'),'icon'=>'winNew','required'=>true,'hideLabel'=>true,'events'=>['onClick'=>"hrefClick('bizuno/dashboard/manager&menuID=$menuID');"]];
        }
        $this->setDashJS($data);
        $cols = getColumns();
        $width= round(100/$cols, 0);
        $html = '';
        for ($i=0; $i<$cols; $i++) { $html .= "\n".'<div style="width:'.$width.'%"></div>'; }
        $data['divs']['bodyDash'] = ['order'=>50,'styles'=>['clear'=>'both'],'attr'=>['id'=>'dashboard'],'type'=>'html','html'=>$html];
        $layout = array_replace_recursive(viewMain(), $data);
        msgDebug("\nlayout bizunoHome = ".print_r($layout, true));
    }

    /**
     *
     * @global type $io
     * @param type $layout
     * @return type
     */
    public function attachRows(&$layout=[])
    {
        global $io;
        if (!validateSecurity('bizuno', 'profile', 1)) { return; }
        $mID   = clean('mID',   'cmd',     'get');
        $prefix= clean('prefix','filename','get');
        if (empty($mID)) { msgAdd('Bad ID'); return; }
        $path  = getModuleCache($mID,'properties','attachPath')."$prefix";
        $rows  = $io->fileReadGlob($path, $io->getValidExt('file'));
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($rows), 'rows'=>$rows])]);
    }

    public function dashboard(&$layout=[])
    {
        $data = [];
        $this->setDashJS($data);
        $menuID= clean('menuID', ['format'=>'text','default'=>'home'], 'get');
        $data['jsHead']['menu_id'] = "var menuID='$menuID';";
        if ($GLOBALS['myDevice'] != 'mobile') { // text and link to add dashboards
//          $linkDash = ['attr'=>['type'=>'a','value'=>lang('add_dashboards'),'href'=>BIZUNO_HOME.'&bizRt=bizuno/dashboard/manager&menuID='.$menuID]];
//          $data['divs']['tbDash'] = ['order'=>10,'classes'=>['datagrid-toolbar'],'styles'=>['min-height'=>'32px'],'attr'=>['id'=>'tbDash'],'type'=>'html','html'=>html5('', $linkDash)];
        }
        $cols = getColumns();
        $width = round(100/$cols, 0);
        $html  = '';
        for ($i=0; $i<$cols; $i++) { $html .= "\n".'<div style="width:'.$width.'%"></div>'; }
        $data['divs']['bodyDash'] = ['order'=>50,'styles'=>['clear'=>'both'],'attr'=>['id'=>'dashboard'],'type'=>'html','html'=>$html];
        $layout = array_replace_recursive(viewMain(), $data);
    }

    /**
     *
     * @param type $data
     */
    private function setDashJS(&$data)
    {
        $cols = getUserCache('profile', 'cols', false, 3);
        $opts = '';
        if (clean('lost',   'cmd','get') == 'true') { $opts .= '&lost=true'; }
        if (clean('newuser','cmd','get') == 'true') { $opts .= '&newuser=true'; }
        $data['jsBody']['jsHome'] = "var panels = new Array();
function getPanelOptions(id) {
    for (var i=0; i<panels.length; i++) if (panels[i].id == id) return panels[i];
    return undefined;
}
function getPortalState(){
    var aa = [];
    for (var columnIndex=0; columnIndex<$cols; columnIndex++){
        var cc = [];
        var panels = jqBiz('#dashboard').portal('getPanels', columnIndex);
        for (var i=0; i<panels.length; i++) cc.push(panels[i].attr('id'));
        aa.push(cc.join(','));
    }
    return aa.join(':');
}
function addPanels(json) {
    if (json.message) displayMessage(json.message);
    for (var i=0; i<json.Dashboard.length; i++) { panels.push(json.Dashboard[i]); }
    var portalState = json.State;
    var columns     = portalState.split(':');
    for (var columnIndex=0; columnIndex<columns.length; columnIndex++){
        var cc = columns[columnIndex].split(',');
        for (var j=0; j<cc.length; j++) {
            var options = getPanelOptions(cc[j]);
            if (options) {
                var p = jqBiz('<div></div>').attr('id',options.id).appendTo('body');
                var panelHref = options.href;
                options.href = '';
                p.panel(options);
                if (isMobile()) { p.panel('panel').draggable('disable'); }
                p.panel({ href:panelHref,onBeforeClose:function() { if (confirm('".jsLang('msg_confirm_delete')."')) { dashboardDelete(this); } else { return false } } });
                jqBiz('#dashboard').portal('add',{ panel:p, columnIndex:columnIndex });
            }
        }
    }
}";
        $data['jsReady']['initDash'] = "jqBiz('#dashboard').portal( { border:false,
    onStateChange:function() { var state = getPortalState(); jqBiz.ajax({ url:'".BIZUNO_AJAX."&bizRt=bizuno/dashboard/organize&menuID='+menuID+'&state='+state }); }
});
jqBiz.ajax({ url: '".BIZUNO_AJAX."&bizRt=bizuno/dashboard/render$opts&menuID='+menuID, success: addPanels });";
        $data['jsResize']['initDash'] = "jqBiz('#dashboard').portal('resize', {width:jqBiz(window).width()} );";
    }

    /**
     * Used to refresh session timer to keep log in alive. Forces sign off after 8 hours if no user actions are detected.
     */
    public function sessionRefresh(&$layout) {
    } // nothing to do, just reset session clock

    /**
     * Loads the countries from the locales.xml file into an array to use on processing
     * @param array $layout - structure coming in
     */
    public function countriesLoad(&$layout) {
        $temp = localeLoadDB();
        $output = [];
        foreach ($temp->country as $value) { $output[] = ['id' => $value->iso3, 'text'=> $value->name]; }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode($output)]);
    }

    /**
     * generates the pop up encryption form
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function encryptionForm(&$layout) {
        if (!validateSecurity('bizuno', 'profile', 1)) { return; }
        $icnSave= ['icon'=>'save','events'=>['onClick'=>"jsonAction('bizuno/main/encryptionSet', 0, jqBiz('#pwEncrypt').val());"]];
        $inpEncr= ['options'=>['value'=>"''"],'attr'=>['type'=>'password','value'=>'']];
        $html   = lang('msg_enter_encrypt_key').'<br />'.html5('pwEncrypt', $inpEncr).html5('', $icnSave);
        $js     = "jqBiz('#winEncrypt').keypress(function(event) {
    var keycode = (event.keyCode ? event.keyCode : event.which);
    if (keycode==13) jsonAction('bizuno/main/encryptionSet', 0, jqBiz('#pwEncrypt').val());
});
bizFocus('pwEncrypt');";
        $data = ['type'=>'divHTML',
            'divs'   => ['divEncrypt'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>$js]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Validates and sets the encryption key, if successful
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function encryptionSet(&$layout)
    {
        if (!validateSecurity('bizuno', 'profile', 1)) { return; }
        $error  = false;
        $key    = clean('data', 'password', 'get');
        $encKey = getModuleCache('bizuno', 'encKey', false, false, '');
        if (!$encKey) { return msgAdd($this->lang['err_encryption_not_set']); }
        if ($key && $encKey) {
            $stack = explode(':', $encKey);
            if (sizeof($stack) != 2) { $error = true; }
            if (md5($stack[1] . $key) <> $stack[0]) { $error = true; }
        } else { $error = true; }
        if ($error) { return msgAdd(lang('err_login_failed')); }
        setUserCache('profile', 'admin_encrypt', $key);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winEncrypt'); jqBiz('#ql_encrypt').hide();"]]);
    }

    /*
     * Downloads a file to the user
     */
    public function fileDownload()
    {
        global $io;
        if (!validateSecurity('phreeform', 'phreeform', 1, true)) { return; } // changed to 'phreeform' security to enable download across Bizuno modules
        $path = clean('pathID', 'path', 'get');
        $file = clean('fileID', 'file', 'get');
        $parts = explode(":", $file, 2);
        if (sizeof($parts) > 1) { // if file contains a prefix the format will be: prefix:prefixFilename
            $dir  = $path.$parts[0];
            $file = str_replace($parts[0], '', $parts[1]);
        } else {
            $dir  = $path;
            $file = $file;
        }
        msgLog(lang('download').' - '.$file);
        msgDebug("\n".lang('download').' - '.$file);
        $io->download('file', $dir, $file);
    }

    /**
     * Deletes a file from the myBiz folder
     * @param array $layout - structure coming in
     */
    public function fileDelete(&$layout=[])
    {
        global $io;
        $secID= clean('secID','cmd', 'get');
        $dgID = clean('rID',  'text','get');
        $file = clean('data', 'text','get');
        if (!validateSecurity('bizuno', !empty($secID)?$secID:'admin', 4)) { return; }
        msgDebug("\nDeleting dgID = $dgID and file = $file");
        $io->fileDelete($file);
        msgLog(lang('delete').' - '.$file);
        msgDebug("\n".lang('delete').' - '.$file);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval',
            'actionData'=>"var row=jqBiz('#$dgID').datagrid('getSelected');
var idx=jqBiz('#$dgID').datagrid('getRowIndex', row);
jqBiz('#$dgID').datagrid('deleteRow', idx);"]]);
    }
}
