<?php
/*
 * Handles the import/export and other API related operations
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
 * @version    6.x Last Update: 2022-04-30
 * @filesource /controllers/contacts/api.php
 */

namespace bizuno;

class contactsApi
{
    public $moduleID = 'contacts';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * This method builds the div for operating the API to import data, information includes import templates and forms, export forms
     * @param array $layout - input data passed as array of tags, may also be passed as $_POST variables
     */
    public function contactsAPI(&$layout)
    {
        $fields = [
            'btnConapi_tpl'=> ['icon'=>'download','label'=>lang('download'),'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_AJAX."&bizRt=contacts/api/apiTemplate');"]],
            'fileContacts' => ['attr'=>['type'=>'file']],
            'btnConapi_imp'=> ['icon'=>'import','label'=>lang('import'),'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmConApiImport').submit();"]],
            'btnConapi_exp'=> ['icon'=>'export','label'=>lang('export'),'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_AJAX."&bizRt=contacts/api/apiExport');"]]];
        $forms = ['frmConApiImport'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=contacts/api/apiImport"]]];
        $html  = '<p>'.$this->lang['conapi_desc'].'</p>
<p>'.$this->lang['conapi_template'].html5('', $fields['btnConapi_tpl']).'</p><hr />'.html5('frmConApiImport',  $forms['frmConApiImport']).'
<p>'.$this->lang['conapi_import']  .html5('fileContacts', $fields['fileContacts']).html5('', $fields['btnConapi_imp'])."</p></form>\n<hr />
<p>".$this->lang['conapi_export']  .html5('', $fields['btnConapi_exp']).'</p>';
        $layout['jsReady']['contactsImport'] = "ajaxForm('frmConApiImport');";
        $layout['tabs']['tabAPI']['divs'][$this->moduleID] = ['order'=>20,'label'=>getModuleCache($this->moduleID, 'properties', 'title'),'type'=>'html','html'=>$html];
    }

    /**
     * Sets the import templates used to map received data to Bizuno structure, downloads to user
     * Doesn't return if successful
     */
    public function apiTemplate()
    {
        $tables = [];
        require(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php");
        $aMap   = $tables['address_book']['fields'];
        $cMap   = $tables['contacts']['fields'];
        $header = [];
        $props  = [];
        $cFields= dbLoadStructure(BIZUNO_DB_PREFIX.'contacts', '', 'Contact');
        foreach ($cFields as $field => $settings) {
            if (isset($cMap[$field]['import']) && !$cMap[$field]['import']) { continue; } // skip values that cannot be imported
            $header[]= csvEncapsulate($settings['tag']);
            $req = !empty($cMap[$field]['required']) ? ' [Required]' : ' [Optional]';
            $desc= isset($cMap[$field]['desc']) ? " - {$cMap[$field]['desc']}" : (isset($settings['label']) ? " - {$settings['label']}" : '');
            $props[] = csvEncapsulate($settings['tag'].$req.$desc);
        }
        $mFields = dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', '', 'MainAddress');
        foreach ($mFields as $field => $settings) {
            if (empty($aMap[$field]['import'])) { continue; } // skip values that cannot be imported
            $header[]= csvEncapsulate($settings['tag']);
            $req = !empty($aMap[$field]['required']) ? ' [Required]' : ' [Optional]';
            $desc= isset($aMap[$field]['desc']) ? " - {$aMap[$field]['desc']}" : (isset($settings['label']) ? " - {$settings['label']}" : '');
            $props[] = csvEncapsulate($settings['tag'].$req.$desc);
        }
        unset($GLOBALS['bizTables'][BIZUNO_DB_PREFIX.'address_book']);
        $sFields = dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', '', 'ShipAddress');
        foreach ($sFields as $field => $settings) {
            if (empty($aMap[$field]['import'])) { continue; } // skip values that cannot be imported
            $header[]= csvEncapsulate($settings['tag']);
            $req = !empty($aMap[$field]['required']) ? ' [Required]' : ' [Optional]';
            $desc= isset($aMap[$field]['desc']) ? " - {$aMap[$field]['desc']}" : (isset($settings['label']) ? " - {$settings['label']}" : '');
            $props[] = csvEncapsulate($settings['tag'].$req.$desc);
        }
        $content = implode(",", $header)."\n\nField Information:\n".implode("\n",$props);
        $io = new \bizuno\io();
        $io->download('data', $content, 'ContactsTemplate.csv');
    }

    /**
     * Imports from a csv file to the contacts database table according to the template
     * @param type $layout - structure coming in
     * @return modified layout
     */
    public function apiImport(&$layout, $rows=false, $verbose=true)
    {
        if (!$security = validateSecurity('bizuno', 'admin', 2)) { return; }
        $tables  = $map = $this->template = [];
        require(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php"); // replaces $map
        $cMap    = $tables['contacts']['fields'];
        $cFields = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts', '', 'Contact');
        $aMap    = $tables['address_book']['fields'];
        $amFields= dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', '', 'MainAddress');
        unset($GLOBALS['bizTables'][BIZUNO_DB_PREFIX.'address_book']);
        $asFields= dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', '', 'ShipAddress');
        foreach ($cFields  as $field => $props) { $template[$props['tag']] = trim($field); }
        foreach ($amFields as $field => $props) { $template[$props['tag']] = trim($field); }
        foreach ($asFields as $field => $props) { $template[$props['tag']] = trim($field); }
        if (!$rows) { $rows = $this->prepData(); }
        $cnt     = $newCnt = $updCnt = 0;
        dbTransactionStart();
        foreach ($rows as $row) {
            $cData = $amData = $asData = [];
            foreach ($row as $tag => $value) { if (isset($template[$tag])) {
                if (strpos($tag, 'Contact')    ===0) { $cData[$template[$tag]] = trim(str_replace('""', '"', $value)); }
                if (strpos($tag, 'MainAddress')===0) { $amData[$template[$tag]]= trim(str_replace('""', '"', $value)); }
                if (strpos($tag, 'ShipAddress')===0) { $asData[$template[$tag]]= trim(str_replace('""', '"', $value)); }
            } }
            // commented out to skip blank rows
            if (!isset($cData['short_name'])) { return msgAdd("The Contact ID field cannot be found and is a required field. The operation was aborted!"); }
            if (empty($cData['type']))       { msgAdd(sprintf("Missing Type on row: %s. The row will be skipped!", $cnt+1)); continue; }
            $cData['type'] = trim(strtolower($cData['type']));
            if (!in_array($cData['type'], ['c','v','b','i','e','j'])) { msgAdd("Contact: {$cData['short_name']} has an invalid type, skipping!"); continue; }
            if (empty($cData['short_name'])) { $cData['short_name'] = $this->getShortName($cData['type'], $amData); }
            if (empty($cData['short_name'])) { msgAdd(sprintf("The Contact ID cannot be auto-set on primary_name: %s. The row will be skipped!", $amData['primary_name'])); continue; }
            // clean out the un-importable fields
            foreach ($cMap as $field => $settings) { if (!$settings['import']) { unset($cData[$field]); } }
            foreach ($aMap as $field => $settings) { if (!$settings['import']) { unset($amData[$field]); } }
            foreach ($aMap as $field => $settings) { if (!$settings['import']) { unset($asData[$field]); } }
            $cID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes($cData['short_name'])."' AND type='{$cData['type']}'");
            if (!isset($cData['last_update'])) { $cData['last_update'] = biz_date('Y-m-d'); }
            if ($cID) {
                $cData['id'] = $cID;
                if ($security < 2) { msgAdd('Your permissions prevent altering an existing record, the entry will be skipped!'); continue; }
                validateData($cFields, $cData);
                dbWrite(BIZUNO_DB_PREFIX.'contacts', $cData, 'update', "id=$cID");
                $isNew = false;
                $updCnt++;
            } else {
                $defGL = $cData['type']=='v' ? getModuleCache('phreebooks', 'settings', 'vendors', 'gl_expense') : getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales');
                if (!isset($cData['gl_account']) || !$cData['gl_account']) { $cData['gl_account'] = $defGL; }
                $cData['first_date'] = biz_date('Y-m-d');
                validateData($cFields, $cData);
                $cID   = dbWrite(BIZUNO_DB_PREFIX.'contacts', $cData);
                $isNew = true;
                $newCnt++;
            }
            $mID = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'address_id', "ref_id=$cID AND type='m'");
            if (!empty($mID)) { $amData['address_id']= $mID; }
            if ($isNew || !empty($amData['primary_name'])) {
                if (!isset($amData['primary_name'])) { $amData['primary_name'] = $cData['short_name']; }
                $amData['ref_id']= $cID;
                $amData['type']  = 'm';
                validateData($amFields, $amData);
                dbWrite(BIZUNO_DB_PREFIX.'address_book', $amData, $mID?'update':'insert', "address_id=$mID");
                if (!empty($asData['primary_name'])) {
                    $sID = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'address_id', "ref_id=$cID AND type='s' AND primary_name='".addslashes($asData['primary_name'])."'");
                    if (!empty($sID)) { $asData['address_id']= $sID; }
                    $asData['ref_id']= $cID;
                    $asData['type']  = 's';
                    validateData($asFields, $asData);
                    dbWrite(BIZUNO_DB_PREFIX.'address_book', $asData, $sID?'update':'insert', "address_id=$sID");
                }
            }
            $cnt++;
        }
        dbTransactionCommit();
        if ($verbose) { msgAdd(sprintf("Imported total rows: %s, Added: %s, Updated: %s", $cnt, $newCnt, $updCnt), 'info'); }
        msgLog(sprintf("Imported total rows: %s, Added: %s, Updated: %s", $cnt, $newCnt, $updCnt));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    /**
     * reads the uploaded file and converts it into a keyed array
     * @param array $fields - table field structure
     * @return array - keyed data array of file contents
     */
    private function prepData()
    {
        global $io;
        if (!$io->validateUpload('fileContacts', '', ['csv'])) { return; } // removed type=text as windows edited files fail the test
        $output= [];
        $rows  = array_map('str_getcsv', file($_FILES['fileContacts']['tmp_name']));
        $head  = array_shift($rows);
        foreach ($rows as $row) { $output[] = array_combine($head, $row); }
        return $output;
    }

    /**
     * Auto generates a short name based on the users preferences
     */
    private function getShortName($type='c', $address=[])
    {
        $meth = getModuleCache('contacts', 'settings', 'general', "short_name_$type", 'auto');
        if ($meth=='email' && !empty($address['email']))      { return $address['email']; }
        if ($meth=='tele'  && !empty($address['telephone1'])) { return $address['telephone1']; }
        // else fall through and generate the next auto-increment
        $str_field = $type=='c' ? 'next_cust_id_num' : 'next_vend_id_num';
        $output = $next = dbGetValue(BIZUNO_DB_PREFIX."current_status", $str_field);
        $next++;
        dbWrite(BIZUNO_DB_PREFIX."current_status", [$str_field => $next], 'update');
        return $output;
    }

    /**
     * Exports the inventory table in csv format including all custom fields.
     * @return doesn't unless there is an error, exits script on success
     */
    /**
     * Exports data from the database table contacts to a user
     * Doesn't return if successful
     */
    public function apiExport()
    {
        global $io;
        if (!$security = validateSecurity('bizuno', 'admin', 1)) { return; }
        $tables  = $merged = $output = [];
        require(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php");
        $aMap    = $tables['address_book']['fields'];
        $cMap    = $tables['contacts']['fields'];
        $cFields = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts',     '', 'Contact');
        $amFields= dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', '', 'MainAddress');
        unset($GLOBALS['bizTables'][BIZUNO_DB_PREFIX.'address_book']); // needed to erase cache of table structure since prefix changed
        $asFields= dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', '', 'ShipAddress');
        $header  = [];
        foreach ($cFields  as $field => $settings) { if (!empty($cMap[$field]['export'])) { $header[] = $settings['tag']; } }
        foreach ($amFields as $field => $settings) { if (!empty($aMap[$field]['export'])) { $header[] = $settings['tag']; } }
        foreach ($asFields as $field => $settings) { if (!empty($aMap[$field]['export'])) { $header[] = $settings['tag']; } }
        $cValues = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', '', 'short_name');
        $aValues = dbGetMulti(BIZUNO_DB_PREFIX.'address_book');
         // merge the contacts table and address_table
        foreach ($cValues as $row) { $merged[$row['id']]['contact']         = $row; }
        foreach ($aValues as $row) { $merged[$row['ref_id']][$row['type']][]= $row; }
        foreach ($merged  as $row) {
            if (!isset($row['contact'])) { continue; }
            $data = [];
            foreach ($cFields  as $field => $settings) { if (!empty($cMap[$field]['export'])) { $data[]= csvEncapsulate($row['contact'][$field]); } }
            foreach ($amFields as $field => $settings) { if (!empty($aMap[$field]['export'])) { $data[]= csvEncapsulate($row['m'][0][$field]); } }
            foreach ($asFields as $field => $settings) { if (!empty($aMap[$field]['export'])) { $data[]= isset($row['s'][0][$field]) ? csvEncapsulate($row['s'][0][$field]) : ''; } }
            $output[] = implode(',', $data);
        }
        msgDebug("\noutput = ".print_r($output, true));
        $io->download('data', implode(",", $header)."\n".implode("\n", $output), 'Contacts-'.biz_date('Y-m-d').'.csv');
    }
}