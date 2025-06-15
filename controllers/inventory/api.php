<?php
/*
 * module Inventory API support functions
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
 * @version    6.x Last Update: 2023-11-26
 * @filesource /controllers/inventory/api.php
 */

namespace bizuno;

class inventoryApi
{
    public $moduleID = 'inventory';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * This method builds the div for operating the API to import data, information includes import templates and forms, export forms
     * @param array $request - input data passed as array of tags, may also be passed as $_POST variables
     */
    public function inventoryAPI(&$layout)
    {
        $skips = [];
        for ($i=0; $i<6; $i++) { $skips[] = ['id'=>$i, 'text'=>$i]; }
        $fields = [
            'btnInvapi_tpl' => ['icon'=>'download','label'=>lang('download'),'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_AJAX."&bizRt=inventory/api/apiTemplate');"]],
            'selInvSkips'   => ['values'=>$skips, 'attr'=>['type'=>'spinner','value'=>2]],
            'fileInventory' => ['attr'=>['type'=>'file']],
            'btnInvapi_imp' => ['icon'=>'import','label'=>lang('import'),'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmInvApiImport').submit();"]],
            'btnInvapi_exp' => ['icon'=>'export','label'=>lang('export'),'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_AJAX."&bizRt=inventory/api/apiExport');"]]];
        $forms = ['frmInvApiImport'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=inventory/api/apiImport"]]];
        $html = '<p>'.$this->lang['invapi_desc'].'</p>
<p>'.$this->lang['invapi_template'].html5('', $fields['btnInvapi_tpl']).'</p><hr />'.html5('frmInvApiImport',  $forms['frmInvApiImport']).'
<p>'.$this->lang['invapi_skips']   .html5('selInvSkips',  $fields['selInvSkips']).'</p>
<p>'.$this->lang['invapi_import']  .html5('fileInventory',$fields['fileInventory']).html5('btnInvapi_imp', $fields['btnInvapi_imp']).'</p></form><hr />
<p>'.$this->lang['invapi_export']  .html5('', $fields['btnInvapi_exp']).'</p>';
        $layout['tabs']['tabAPI']['divs'][$this->moduleID] = ['order'=>40,'label'=>getModuleCache($this->moduleID, 'properties', 'title'),'type'=>'html','html'=>$html];
        $layout['jsReady'][$this->moduleID] = "ajaxForm('frmInvApiImport');";
    }

    /**
     *
     */
    public function apiTemplate()
    {
        $tables = [];
        require(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php");
        $map    = $tables['inventory']['fields'];
        $header = $props  = [];
        $fields = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        foreach ($fields as $field => $settings) {
            if (isset($map[$field]['import']) && !$map[$field]['import']) { continue; } // skip values that cannot be imported
            $header[]= csvEncapsulate($settings['tag']);
            $req = isset($map[$field]['required']) && $map[$field]['required'] ? ' [Required]' : ' [Optional]';
            $desc= isset($map[$field]['desc']) ? " - {$map[$field]['desc']}" : (isset($settings['label']) ? " - {$settings['label']}" : '');
            $props[] = csvEncapsulate($settings['tag'].$req.$desc);
        }
        $content = implode(",", $header)."\n\nField Information:\n".implode("\n",$props);
        $io = new \bizuno\io();
        $io->download('data', $content, 'InventoryTemplate.csv');
    }

    /**
     * Imports csv data/file into the inventory table, Uploaded files support field names and tags, direct import only supports field names
     * @param array $layout - structure coming in
     * @param type $rows - if data is provided only field names are allowed
     * @param type $verbose - turns off success messages to allow iteration
     * @return modified $layout
     */
    public function apiImport(&$layout, $rows=false, $verbose=true)
    {
        if (!$security = validateSecurity('bizuno', 'admin', 2)) { return; }
        set_time_limit(600); // 10 minutes
        $structure= dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        $tables= $map = [];
        require(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php"); // replaces $map
        $map   = $tables['inventory']['fields'];
        if (!$rows) { $rows = $this->prepData($structure); }
        $cnt   = $newCnt = $updCnt = 0;
        foreach ($rows as $row) {
            $sqlData = [];
            foreach ($row as $tag => $value) {
                if (!empty($this->template[$tag])) { $sqlData[$this->template[$tag]]= trim(str_replace('""', '"', $value)); } // if tags are used
                if (!empty($structure[$tag]))      { $sqlData[$tag]                 = trim(str_replace('""', '"', $value)); } // if field names are used
            }
            if (!isset($sqlData['sku'])) { return msgAdd("The SKU field cannot be found and is a required field. The operation was aborted!"); }
            if (!$sqlData['sku']) { msgAdd(sprintf("Missing SKU on row: %s. The row will be skipped", $cnt+1)); continue; }
            if (empty($sqlData['inventory_type'])) { $sqlData['inventory_type'] = 'si'; }
// @todo - remove after invenotry sku field in new business is reduced to less than 24 characters
//            if (strlen($sqlData['sku']) > 24) {
//                $tmp = substr($sqlData['sku'], 0, 24);
//                msgAdd ("SKU: {$sqlData['sku']} is greater than 24 characters, it has been truncated to $tmp", 'info');
//                $sqlData['sku'] = $tmp;
//            }
            // clean out the un-importable fields
            foreach ($map as $field => $settings) { if (empty($settings['import'])) { unset($sqlData[$field]); } }
            $rID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($sqlData['sku'])."'");
            $sqlData['last_update'] = biz_date('Y-m-d');
            if ($rID) {
                $sqlData['id'] = $rID;
                unset($sqlData['inventory_type']);
                $updCnt++;
            } else {
                if (!isset($sqlData['inventory_type'])) { $sqlData['inventory_type'] = 'si'; }
                $type   = $sqlData['inventory_type'];
                $sales_ = getModuleCache('inventory', 'settings', 'phreebooks', "sales_$type");
                $inv_   = getModuleCache('inventory', 'settings', 'phreebooks', "inv_$type");
                $cogs_  = getModuleCache('inventory', 'settings', 'phreebooks', "cogs_$type");
                $method_= getModuleCache('inventory', 'settings', 'phreebooks', "method_$type");
                if (!isset($sqlData['gl_sales']))   { $sqlData['gl_sales']   = $sales_; }
                if (!isset($sqlData['gl_inv']))     { $sqlData['gl_inv']     = $inv_; }
                if (!isset($sqlData['gl_cogs']))    { $sqlData['gl_cogs']    = $cogs_; }
                if (!isset($sqlData['cost_method'])){ $sqlData['cost_method']= $method_; }
                $sqlData['creation_date'] = biz_date('Y-m-d');
                msgAdd("Added: {$sqlData['sku']} - {$sqlData['description_short']}", 'info');
                $newCnt++;
            }
            if ($rID && $security < 2) { msgAdd('Your permissions prevent altering an existing record, the entry will be skipped!'); continue; }
            validateData($structure, $sqlData);
            msgDebug("\nReady to write, data = ".print_r($sqlData, true));
            dbWrite(BIZUNO_DB_PREFIX.'inventory', $sqlData, $rID?'update':'insert', "id=$rID");
            $cnt++;
        }
        if ($verbose) { msgAdd(sprintf("Imported total rows: %s, Added: %s, Updated: %s", $cnt, $newCnt, $updCnt), 'success'); }
        msgLog(sprintf("Imported total rows: %s, Added: %s, Updated: %s", $cnt, $newCnt, $updCnt));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    /**
     * reads the uploaded file and converts it into a keyed array
     * @param array $fields - table field structure
     * @return array - keyed data array of file contents
     */
    private function prepData($fields)
    {
        global $io;
        $skipRows= clean('selInvSkips', ['format'=>'integer', 'default'=>0], 'post');
        if (!$io->validateUpload('fileInventory', '', ['csv','txt'])) { return; } // removed type=text as windows edited files fail the test
        $this->template = $output = [];
        foreach ($fields as $field => $props) { $this->template[$props['tag']] = trim($field); }
        $rows    = array_map('str_getcsv', file($_FILES['fileInventory']['tmp_name']));
        for ($i=0; $i<$skipRows; $i++) { array_shift($rows); }
        $head    = array_shift($rows);
        foreach ($rows as $row) { $output[] = array_combine($head, $row); }
        return $output;
    }

    /**
     * Exports the inventory table in csv format including all custom fields.
     * @return doesn't unless there is an error, exits script on success
     */
    public function apiExport()
    {
        global $io;
        if (!$security = validateSecurity('bizuno', 'admin', 1)) { return; }
        $tables = $header = $output = [];
        require(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php");
        $map    = $tables['inventory']['fields'];
        $fields = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        foreach ($fields as $field => $settings) { if (!empty($map[$field]['export'])) { $header[] = $settings['tag']; } }
        $values = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', '', 'sku');
        foreach ($values as $row) {
            $data = [];
            foreach ($fields as $field => $settings) { if (!empty($map[$field]['export'])) { $data[]= csvEncapsulate($row[$field]); } }
            $output[] = implode(',', $data);
        }
        $content= implode(",", $header)."\n".implode("\n", $output);
        $io->download('data', $content, 'Inventory-'.biz_date('Y-m-d').'.csv');
    }
}