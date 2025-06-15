<?php
/*
 * Functions to support API operations through Bizuno
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
 * @version    6.x Last Update: 2022-12-09
 * @filesource /controllers/bizuno/import.php
 */

namespace bizuno;

class bizunoImport
{
    public $moduleID = 'bizuno';

    function __construct()
    {
        $this->lang = getLang('bizuno'); // needs to be hardcoded as this is extended by extensions
    }

    /**
     * Main entry point structure for the import/export operations
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function impExpMain(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'impexp', 2)) { return; }
        $title= lang('bizuno_impexp');
        $data = ['title'=> $title,
            'divs'    => [
                'toolbar'=> ['order'=>20,'type'=>'toolbar','key' =>'tbImpExp'],
                'heading'=> ['order'=>30,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'biz_io' => ['order'=>60,'type'=>'tabs',   'key' =>'tabImpExp']],
            'tabs'=>[
                'tabImpExp'=>['divs'=>['module'=>['order'=>10,'type'=>'divs','label'=>lang('module'),'divs'=>['body'=>['order'=>50,'type'=>'tabs','key'=>'tabAPI']]]]],
                'tabAPI'   => ['styles'=>['height'=>'300px'],'attr'=>['tabPosition'=>'left', 'fit'=>true, 'headerWidth'=>250]]],
            'lang'    => $this->lang];
        $apis = getModuleCache('bizuno', 'api', false, false, []);
        msgDebug("\nLooking for APIs = ".print_r($apis, true));
        foreach ($apis as $settings) {
            $parts= explode('/', $settings['path']);
            $path = bizAutoLoadMap(getModuleCache($parts[0], 'properties', 'path'));
            msgDebug("\npath = $path and parts = ".print_r($parts, true));
            if (empty($path)) { continue; }
            if (file_exists ($path."/{$parts[1]}.php")) {
                $fqcn = "\\bizuno\\".$parts[0].ucfirst($parts[1]);
                bizAutoLoad($path."/{$parts[1]}.php", $fqcn);
                $tmp = new $fqcn();
                $tmp->{$parts[2]}($data); // looks like phreebooksAPI($data)
            }
        }
        portalMigrateGetMgr($layout);
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }
}
