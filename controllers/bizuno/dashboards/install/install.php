<?php
/*
 * Bizuno dashboard - Install
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
 * @version    6.x Last Update: 2023-02-10
 * @filesource /controllers/bizuno/dashboards/install/install.php
 */

namespace bizuno;

class install
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'install';
    public $noSettings= true;
    public $noCollapse= true;
    public $noClose   = true;

    function __construct()
    {
        $this->bizID   = getUserCache('profile', 'biz_id');
        $this->security= !empty($this->bizID) ? 1 : 0; // only for the portal to log in
        $this->hidden  = true;
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
    }

    public function render(&$layout=[])
    {
        if (!function_exists('curl_init')) { msgAdd('Bizuno needs cURL to run properly. Please install/enable cURL PHP extension before performing any Input/Output operations.'); }
        if (empty($this->bizID)) { return 'Biz_id cannot be zero!'; }
        $lang['userDesc']= "Please set a username (email only) and password to set as your administrator of your business.";
        $lang['dbDesc']  = "Since your db tables have not been set, we'll need your database credentials to make sure we can connect to your db.";
        $locale= localeLoadDB();
        $year  = biz_date('Y');
        for ($i=2; $i>=0; $i--) { $years[] = ['id'=>$year - $i, 'text'=>$year - $i]; }
        $fields= [
            'inst_desc'   => ['order'=>10,'html'=>$this->lang['instructions'],'attr'=>['type'=>'raw']],
            'user_desc'   => ['order'=>20,'html'=>$lang['userDesc'],   'attr'=>['type'=>'raw']],
            'user_name'   => ['order'=>21,'label'=>'User Email',       'attr'=>['size'=>40]],
            'user_pass'   => ['order'=>22,'label'=>'Password',         'attr'=>['type'=>'password']],
            'db_desc'     => ['order'=>30,'html'=>$lang['dbDesc'],     'attr'=>['type'=>'raw']],
            'dbHost'      => ['order'=>31,'label'=>'Database Host',    'attr'=>['value'=>$GLOBALS['dbPortal']['host']]],
            'dbName'      => ['order'=>32,'label'=>'Database Name',    'attr'=>['value'=>$GLOBALS['dbPortal']['name']]],
            'dbUser'      => ['order'=>33,'label'=>'Database Username','attr'=>['value'=>$GLOBALS['dbPortal']['user']]],
            'dbPass'      => ['order'=>34,'label'=>'Database Password','attr'=>['value'=>$GLOBALS['dbPortal']['pass']]],
            'dbPrfx'      => ['order'=>35,'label'=>'Database Prefix',  'attr'=>['value'=>$GLOBALS['dbPortal']['prefix']]],
            'biz_title'   => ['order'=>40,'label'=>$this->lang['biz_title'],'attr'=>['value'=>portalGetBizIDVal($this->bizID, 'title'),'maxlength'=>16]],
            'biz_lang'    => ['order'=>41,'label'=>$this->lang['biz_lang'],    'values'=>viewLanguages(true),     'attr'=>['type'=>'select','value'=>'en_US']],
            'biz_timezone'=> ['order'=>42,'label'=>$this->lang['biz_timezone'],'values'=>viewTimeZoneSel($locale),'attr'=>['type'=>'select','value'=>$this->guessTimeZone($locale)]],
            'biz_currency'=> ['order'=>43,'label'=>$this->lang['biz_currency'],'values'=>viewCurrencySel($locale),'attr'=>['type'=>'select','value'=>'USD']],
            'biz_chart'   => ['order'=>44,'label'=>$this->lang['biz_chart'],   'values'=>localeLoadCharts(),      'attr'=>['type'=>'select','value'=>"locale/en_US/charts/retailCorp.xml"]],
            'biz_fy'      => ['order'=>45,'label'=>$this->lang['biz_fy'],      'values'=>$years, 'attr'=>['type'=>'select','value'=>biz_date('Y')]]];
        $data = [
            'toolbars'=> ['tbInstall'=>['icons'=> [
                'instNext'=> ['order'=>20,'icon'=>'next', 'label'=>lang('next'),
                    'events'=>['onClick'=>"jqBiz('#instNext').linkbutton({ iconCls:'iconL-loading',text:'' }); divSubmit('bizuno/admin/installPreFlight&bizID=$this->bizID', 'divInstall');"]]]]],
            'divs'    => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbInstall'],
                'divBOF' => ['order'=>15,'type'=>'html',   'html'=>'<div id="divInstall"><p>'.$this->lang['intro'].'</p>'],
                'body'   => ['order'=>50,'type'=>'fields', 'keys'=>[]], // keys set below
                'divEOF' => ['order'=>85,'type'=>'html',   'html'=>"</div>"]],
            'fields'  => $fields];
        $keys = ['inst_desc','biz_title','biz_lang','biz_timezone','biz_currency','biz_chart','biz_fy','btnInstall'];
        if (!getUserCache('profile', 'email')) {
            $keys = array_merge($keys, ['user_desc','user_name','user_pass']);
        }
        if (!dbTableExists(BIZUNO_DB_PREFIX.'users') && !in_array(BIZUNO_HOST,['phreesoft','wordpress'])) {
            $keys = array_merge($keys, ['db_desc','dbHost','dbUser','dbPass','dbPrfx']);
        }
        $data['divs']['body']['keys'] = $keys;
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * try to guess time zone by client ip
     * @return string
     */
    private function guessTimeZone($locale=[])
    {
        if (empty($locale)) { $locale= localeLoadDB(); }
        $ipInfo= file_get_contents('http://ip-api.com/json/'.$_SERVER['REMOTE_ADDR']);
        $data  = json_decode($ipInfo);
        $output= 'America/New_York';
        if (empty($data->timezone)) { return $output; }
        foreach ($locale->Timezone as $value) {
            if ($data->timezone == $value->Code) { $output = $value->Code;  break; }
        }
        return $output;
    }
}
