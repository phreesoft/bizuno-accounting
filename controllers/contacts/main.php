<?php
/*
 * Module contacts main methods
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
 * @version    6.x Last Update: 2024-02-09
 * @filesource /controllers/contacts/main.php
 */

namespace bizuno;

class contactsMain
{
    public  $moduleID = 'contacts';
    private $reqFields= ['primary_name', 'address1', 'telephone1', 'email'];
    private $restrict_store = true;
    private $defaults = [];
    private $refTries = 10; // number of attempts to pull a new refernce before punting. Helps fix bad Vendor IDs and Customer IDs
    private $address  = [];

    function __construct($type='c')
    {
        $this->lang = getLang($this->moduleID);
        $rID = clean('rID', 'integer', 'get');
        if ($rID) { $this->type = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'type', "id=$rID"); }
        else      { $this->type = clean('type', ['format'=>'char', 'default'=>$type], 'get'); }
        $this->securityModule = 'contacts';
        $this->securityMenu   = 'mgr_'.$this->type;
        $this->helpSuffix     = "-$this->type";
        switch ($this->type) {
            case 'a': $this->f0_default='a'; break; // all contacts
            case 'b': $this->f0_default='0'; $this->securityMenu = 'mgr_c'; break; // branches, allow access to branches if access to customers
//          case 'c': $this->f0_default='a'; break; // customers
            case 'e': $this->f0_default='0'; break; // employees
//          case 'i': $this->f0_default='a'; break; // crm
//          case 'j': $this->f0_default='a'; break; // jobs/projects
            case 'v': $this->f0_default='0'; break; // vendors
            default:  $this->f0_default='a'; break;
        }
        $postTaxID = clean('tax_rate_id', ['format'=>'integer','default'=>null], 'post');
        $defTaxID  = $this->type=='v' ? getModuleCache('phreebooks', 'settings', 'vendors', 'tax_rate_id_v') : getModuleCache('phreebooks', 'settings', 'customers', 'tax_rate_id_c');
        $this->contact = [
            'id'           => 0,
            'inactive'     => '0',
            'type'         => $this->type,
            'gl_account'   => $this->type=='v'? getModuleCache('phreebooks', 'settings', 'vendors', 'gl_expense') : getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales'),
            'terms'        => '0',
            'price_sheet'  => 0,
            'rep_id'       => 0,
            'store_id'     => 0,
            'gov_id_number'=> '',
            'tax_rate_id'  => $postTaxID !== null ? $postTaxID : $defTaxID,
            'first_date'   => biz_date('Y-m-d'),
            'last_update'  => biz_date('Y-m-d')];
    }

    /**
     * Main manager constructor for all contact types
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $view = clean('view', ['format'=>'text','default'=>'page'], 'get');
        $title= sprintf(lang('tbd_manager'), lang('contacts_type', $this->type));
        if ($rID) {
            $jsReady = "jqBiz(document).ready(function() { accordionEdit('accContacts', 'dgContacts', 'divContactsDetail', '".jsLang('details')."', 'contacts/main/edit&type=$this->type', $rID); });";
        } else {
            $jsReady = "bizFocus('search_$this->type', 'dgContacts');";
        }
        $data = ['type'=>'page','title'=>$title,
            'divs'     => ['contacts'=>['order'=>50,'type'=>'accordion','key'=>'accContacts']],
            'accordion'=> ['accContacts'=>['divs'=>[
                'divContactsManager'=>['order'=>30,'type'=>'datagrid','label'=>$title,         'key' =>'manager'],
                'divContactsDetail' =>['order'=>70,'type'=>'html',    'label'=>lang('details'),'html'=>'&nbsp;']]]],
            'datagrid' =>['manager'=>$this->dgContacts('Contacts', $this->type, $security)],
            'jsReady'  =>['init'=>$jsReady]];
        if ($view == 'div') { // probably a status popup
            $data['type'] = 'divHTML';
            $layout = array_replace_recursive($layout, $data);
        } else {
            $layout = array_replace_recursive($layout, viewMain(), $data);
        }
    }

    /**
     * Main entry point for CRM tab within contact edit
     * @param array $layout -  working structure
     * @return modified $layout
     */
    public function addressManager(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID  = clean('rID',  'integer','get');
        $aType= clean('aType','char',   'get');
        $cType= clean('type', 'char',   'get');
        $data = ['type'=>'divHTML',
            'divs' => ['address'=>['order'=>50,'type'=>'accordion','key'=>"accAddress$aType"]],
            'accordion' => ["accAddress$aType" => ['divs'=>[
                "divAddress{$aType}Manager"=>['order'=>30,'type'=>'datagrid','label'=>sprintf(lang('tbd_manager'), lang('address_book_type', $aType)),'key' =>"addressMgr$aType"],
                "divAddress{$aType}Detail" =>['order'=>70,'type'=>'html',    'label'=>lang('details'),'html'=>'&nbsp;']]]],
            'datagrid'=>["addressMgr$aType"=>$this->dgAddress($rID, $cType, $aType, $security)]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Gets the results to populate the active contact grid
     * @param array $layout -  working structure
     * @return modified $layout
     */
    public function managerRows(&$layout=[])
    {
        $type = clean('type',['format'=>'alpha_num','default'=>$this->type], 'get'); // reload here for multi type searches
        $rID  = clean('rID', 'integer','get');
        if (in_array($type, ['a','b'])) { // a - all contacts, b - branches
            $security = 1; // branches are part of bizuno admin settings and restricted in settings, needs to be read-only here for address searches of branches for users without access to settings
        } else {
            if (!$security = validateSecurity('contacts', "mgr_{$this->type}", 1)) { return; } // has to be only 1 char
        }
        $_POST['search_'.$type] = getSearch(['search_'.$type,'q']);
        $data = $this->dgContacts('Contacts', $type, $security, $rID);
        if ($rID) {
            $_POST['search_'.$type] = ''; // preload hit which is erased if searching is started
            $data['source']['filters']['rID'] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."contacts.id=$rID"];
        }
        $data['strict'] = true;
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$data]]);
    }

    /**
     * Gets the address for a given contact id and of given address type
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function managerRowsAddress(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID  = clean('rID',  'integer','get');
        $cType= clean('type', 'char',   'get');
        $aType= clean('aType','char',   'get');
        if (!$rID) { return msgAdd('No id returned!'); }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'address','datagrid'=>['address'=>$this->dgAddress($rID, $cType, $aType, $security)]]);
    }

    /**
     * Sets the cache registry settings of current user selections
     * @param char $type - contact type code
     */
    private function managerSettings($type=false)
    {
        if (!$type) { $type = $this->type; }
        $data = ['path'=>'contacts_'.$type, 'values'=>[
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'method'=>'request'],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX."contacts.short_name"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'f0_'.$type,'clean'=>'char','default'=>$this->f0_default],
            ['index'=>'search_'.$type,'clean'=>'text','default'=>'']]];
        if (clean('clr', 'boolean', 'get')) { clearUserCache($data['path']); }
        $this->defaults = updateSelection($data);
    }

    /**
     * Editor for all contact types, customized by type specified
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        $status = [];
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts', $this->type);
        // merge data with structure
        $cData = !empty($rID) ? dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$rID") : $this->contact;
        dbStructureFill($structure, $cData);
        $add_book = dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', $this->type);
        if ($rID) { // set some defaults
            $aValue= dbGetRow(BIZUNO_DB_PREFIX.'address_book', "ref_id=$rID AND type='m'");
            dbStructureFill($add_book, $aValue);
            $title = $structure['short_name']['attr']['value'].' - '.$aValue['primary_name'];
            $structure['first_date']['attr']['readonly'] = true;
            $structure['last_update']['attr']['readonly']= true;
        } else {
            $title = lang('new');
            $structure['first_date']['attr']['type'] = 'hidden';
            $structure['last_update']['attr']['type']= 'hidden';
            $add_book['country']['attr']['value']    = getModuleCache('bizuno','settings','company','country');
        }
        foreach (array_keys($add_book) as $idx) { $structure[$idx.'m'] = $add_book[$idx]; }
        $fldAcct = ['short_name','inactive','rep_id','tax_rate_id','price_sheet','store_id','terms','terms_text','terms_edit','first_date','last_update','last_date_1','last_date_2','histPay'];
        $fldCont = ['contact_first','contact_last','flex_field_1','telephone1m','telephone2m','telephone3m','telephone4m','emailm','websitem'];
        $fldProp = ['id','type','account_number','gov_id_number','gl_account','recordID','tax_exempt'];
        // set some special cases
        $structure['type']['attr']['value']  = $this->type;
        $structure['short_name']['tooltip']  = lang('msg_leave_null_to_assign_ref');
        $structure['inactive']['label']      = lang('status');
        $structure['inactive']['values']     = getModuleCache('contacts', 'statuses');
        $structure['rep_id']['values']       = viewRoleDropdown();
        $structure['tax_rate_id']['defaults']= ['value'=>$structure['tax_rate_id']['attr']['value'],'type'=>$this->type,'target'=>'inventory','callback'=>"var foo=0;"];
        // set some new fields
        $structure['terms_text']= ['order'=>61,'label'=>pullTableLabel("contacts", 'terms', $this->type),'break'=>false,
            'attr'=>['value'=>viewTerms($structure['terms']['attr']['value'], true, $this->type), 'readonly'=>'readonly']];
        $structure['terms_edit']= ['order'=>62,'icon'=>'settings','label'=>lang('terms'),'events'=>['onClick'=>"jsonAction('contacts/main/editTerms&type=$this->type',$rID,jqBiz('#terms').val());"]];
        $structure['recordID']  = ['order'=>99,'html'=>'<p>Record ID: '.$structure['id']['attr']['value']."</p>",'attr'=>['type'=>'raw']];
        $structure['histPay']   = ['order'=>95,'attr'=>['type'=>'button','value'=>$this->lang['payment_history']],'events'=>['onClick'=>"jsonAction('contacts/main/historyPayment', $rID);"]];
        if (in_array($this->type, ['c','v'])) { $status = $this->editStatus($structure, $rID); } // Fetch the status fields
        if (sizeof(getModuleCache('inventory', 'prices'))) {
            unset($structure['price_sheet']['attr']['size']);
            bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/prices.php', 'inventoryPrices');
            $tmp = new inventoryPrices();
            $structure['price_sheet']['values'] = $tmp->quantityList($this->type=='b'?'c':$this->type, true);
        }
        switch ($this->type) {
            case 'c': $formID = 'cust:ltr'; break;
            case 'v': $formID = 'vend:ltr'; break;
            default:  $formID = false;
        }
        $data = ['type'=>'divHTML', 'title'=>$title,
            'divs'     => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbContacts'],
                'heading' => ['order'=>15,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmContact'],
                'tabs'    => ['order'=>50,'type'=>'tabs',   'key' =>'tabContacts'],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'toolbars' => [
                'tbContacts' => ['icons' => [
                    'save' => ['order'=>20,'hidden'=>$security >1?false:true,        'events'=>['onClick'=>"if (jqBiz('#frmContact').form('validate')) { jqBiz('body').addClass('loading'); jqBiz('#frmContact').submit(); }"]],
                    'new'  => ['order'=>40,'hidden'=>$security >1?false:true,        'events'=>['onClick'=>"accordionEdit('accContacts', 'dgContacts', 'divContactsDetail', '".lang('details')."', 'contacts/main/edit&type=$this->type', 0);"]],
                    'email'=> ['order'=>60,'hidden'=>$rID && $formID?false:true,     'events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&xfld=contacts.id&xcr=equal&xmin=$rID');"]],
                    'trash'=> ['order'=>80,'hidden'=>$rID && $security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('contacts/main/delete&type=$this->type', $rID, 'reset');"]]]],
                'tbAddressb' => ['icons' => [
                    'saveb' => ['order'=>10,'icon'=>'save','label'=>lang('save'),'hidden'=>$rID && $security >1?false:true,'events'=>['onClick'=>"divSubmit('contacts/main/saveAddress&aType=b&rID=$rID', 'addressb');"]],
                    'newb'  => ['order'=>20,'icon'=>'new', 'label'=>lang('new'), 'hidden'=>$security >1?false:true,        'events'=>['onClick'=>"addressClear('b');"]],
                    'copyb' => ['order'=>30,'icon'=>'copy','label'=>lang('copy'),'hidden'=>$security >1?false:true,        'events'=>['onClick'=>"addressCopy('m', 'b');"]]]],
                'tbAddresss' => ['icons' => [
                    'saves' => ['order'=>10,'icon'=>'save','label'=>lang('save'),'hidden'=>$rID && $security >1?false:true,'events'=>['onClick'=>"divSubmit('contacts/main/saveAddress&aType=s&rID=$rID', 'addresss');"]],
                    'news'  => ['order'=>20,'icon'=>'new', 'label'=>lang('new'), 'hidden'=>$security >1?false:true,        'events'=>['onClick'=>"addressClear('s');"]],
                    'copys' => ['order'=>30,'icon'=>'copy','label'=>lang('copy'),'hidden'=>$security >1?false:true,        'events'=>['onClick'=>"addressCopy('m', 's');"]]]]],
            'tabs'     => ['tabContacts'=>['divs'=>[
                'general' => ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genAddA' => ['order'=>10,'type'=>'panel','key'=>'genAddA','classes'=>['block33']],
                    'genCont' => ['order'=>20,'type'=>'panel','key'=>'genCont','classes'=>['block33']],
                    'genStat' => ['order'=>30,'type'=>'panel','key'=>'genStat','classes'=>['block33']],
                    'genAcct' => ['order'=>40,'type'=>'panel','key'=>'genAcct','classes'=>['block33']],
                    'genProp' => ['order'=>50,'type'=>'panel','key'=>'genProp','classes'=>['block33']],
                    'genAtch' => ['order'=>80,'type'=>'panel','key'=>'genAtch','classes'=>['block66']]]],
                'history' => ['order'=>30,'label'=>lang('history'), 'hidden'=>$rID?false:true,'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/main/history&rID=$rID'"]],
                'wallet'  => ['order'=>35,'label'=>lang('wallet'),'hidden'=>$rID?false:true,'type'=>'html', 'html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=payment/wallet/manager&type=$this->type&rID=$rID'"]],
                'bill_add'=> ['order'=>45,'label'=>lang('address_book_type_b'), 'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/main/addressManager&type=$this->type&aType=b&rID=$rID'"]],
                'ship_add'=> ['order'=>50,'label'=>lang('address_book_type_s'), 'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/main/addressManager&type=$this->type&aType=s&rID=$rID'"]],
                'notes'   => ['order'=>70,'label'=>lang('notes'), 'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/main/getTabNotes&rID=$rID'"]]]]],
            'panels' => [
                'genAddA' => ['label'=>lang('address_book_type_m'),'type'=>'address','fields'=>array_keys($add_book),'settings'=>['limit'=>'a','suffix'=>'m','required'=>true]],
                'genCont' => ['label'=>lang('contact_info'),       'type'=>'fields', 'keys'=>$fldCont],
                'genStat' => ['label'=>$status['label'],           'type'=>'fields', 'keys'=>$status['fields']],
                'genAcct' => ['label'=>lang('account'),            'type'=>'fields', 'keys'=>$fldAcct],
                'genProp' => ['label'=>lang('properties'),         'type'=>'fields', 'keys'=>$fldProp],
                'genAtch' => ['type'=>'attach','defaults'=>['dgName'=>$this->moduleID.'Attach','path'=>getModuleCache($this->moduleID,'properties','attachPath'),'prefix'=>"rID_{$rID}_"]]],
            'forms'    => ['frmContact'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=contacts/main/save&type=$this->type"]]],
            'fields'   => $structure,
            'jsReady'  => ['init'=>"ajaxForm('frmContact');"]];
        if (!in_array($this->type, ['c','v'])) {
            unset($data['tabs']['tabContacts']['divs']['genStat'], $data['tabs']['tabContacts']['divs']['wallet'], $data['tabs']['tabContacts']['divs']['prices']);
        }
        // Some mods for new records
        if (!$rID) { unset($data['tabs']['tabContacts']['divs']['bill_add'], $data['tabs']['tabContacts']['divs']['ship_add']); }
        customTabs($data, 'contacts', 'tabContacts');
        $this->editCustomType($data, $rID); // customize based on type
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Gets a specific address record for a given ID passed through the GET variable
     * @param array $layout - current working structure
     * @return updated $layout
     */
    public function editAddress(&$layout=[])
    {
        $cType = clean('type', 'char', 'get'); // not used
        if (!$security = validateSecurity($this->securityModule, 'mgr_'.$cType, 3)) { return; }
        if (!$cID = clean('cID', 'integer', 'get')) { return msgAdd('No id returned!'); }
        $aID = clean('rID', 'integer', 'get');
        $aType = clean('aType', 'char', 'get');
        $add_book = dbLoadStructure(BIZUNO_DB_PREFIX.'address_book', $this->type);
        if ($aID) {
            $aValue= dbGetRow(BIZUNO_DB_PREFIX."address_book", "address_id=$aID");
            dbStructureFill($add_book, $aValue);
        }
        $add_book['ref_id']['attr']['value'] = $cID;
        foreach (array_keys($add_book) as $idx) { $structure[$idx.$aType] = $add_book[$idx]; }
        $fldCont = ["telephone1$aType","telephone2$aType","telephone3$aType","telephone4$aType","email$aType","website$aType"];
        $data = ['type'=>'divHTML',
            'toolbars'=> [
                'tbAddressi'=> ['icons' => [
                    "save$aType" => ['order'=>10,'icon'=>'save','label'=>lang('save'),'hidden'=>$security >1?false:true,'events'=>['onClick'=>"divSubmit('contacts/main/saveAddress&rID=$cID&aType=$aType&type=$cType', 'addressDiv$aType');"]],
                    "new$aType"  => ['order'=>20,'icon'=>'new', 'label'=>lang('new'), 'hidden'=>$security >1?false:true,'events'=>['onClick'=>"accordionEdit('accAddress$aType', 'dgAddress$aType', 'divAddress{$aType}Detail', '".jsLang('details')."', 'contacts/main/editAddress&aType=$aType&type=$cType&cID=$cID', 0);"]],
                    "copy$aType" => ['order'=>30,'icon'=>'copy','label'=>lang('copy'),'hidden'=>$security >1?false:true,'events'=>['onClick'=>"addressCopy('m', '$aType');"]]]]],
            'divs'   => [
                'crmTB'  => ['order'=>10,'type'=>'toolbar','key'=>'tbAddressi'],
                'general'=> ['order'=>50,'type'=>'divs','attr'=>['id'=>"addressDiv$aType"],'classes'=>['areaView'],'divs'=>[
                    'genCont' => ['order'=>20,'type'=>'panel','key'=>'genCont','classes'=>['block33']],
                    'genAddA' => ['order'=>30,'type'=>'panel','key'=>'genAddA','classes'=>['block33']]]]],
            'panels' => [
                'genCont' => ['label'=>lang('contact_info'),     'type'=>'fields', 'keys'  =>$fldCont],
                'genAddA' => ['label'=>lang('address_book_type'),'type'=>'address','fields'=>array_keys($add_book),'settings'=>['limit'=>'a','suffix'=>$aType,'clear'=>false,'required'=>true]]],
            'fields'   => $structure];
        $layout = array_replace_recursive($layout, $data);
    }

    private function editCustomType(&$data, $rID)
    {
        switch ($this->type) {
            case 'c': // Customers
                $data['tabs']['tabContacts']['divs']['payment'] = ['order'=>60,'label'=>lang('payment'),'hidden'=>$rID && getUserCache('profile', 'admin_encrypt')?false:true,'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=payment/main/manager&rID=$rID'"]];
                break;
            case 'j': // Projects/Jobs
            case 'v': // Vendors
                break;
            case 'e': // Employees
                $data['panels']['genAcct']['keys'] = ['contact_first','contact_last','flex_field_1','short_name','inactive','store_id'];
                $data['panels']['genCont']['keys'] = ['telephone1m','telephone2m','telephone3m','telephone4m','emailm'];
                $data['panels']['genProp']['keys'] = ['id','type','gov_id_number','recordID'];
                $data['fields']['contact_first']['order']= 7;
                $data['fields']['contact_last']['order'] = 8;
                $data['fields']['flex_field_1']['order'] = 25;
                break;
        }
        if (in_array($this->type, ['c','v'])) {
            $jIdx = $this->type=='v' ? "j6_mgr" : "j12_mgr";
            if (!validateSecurity('phreebooks', $jIdx, 1, false)) { $data['tabs']['tabContacts']['divs']['history']['hidden'] = true; }
            if (!getModuleCache('proLgstc', 'properties', 'status')) { unset($data['tabs']['tabContacts']['divs']['ship_add']); }
        } else {
            unset($data['tabs']['tabContacts']['divs']['crm_add'], $data['tabs']['tabContacts']['divs']['history']);
            unset($data['tabs']['tabContacts']['divs']['bill_add'],$data['tabs']['tabContacts']['divs']['ship_add']);
        }
    }

    /**
     * Pull the detailed aging for this contact
     * @param array $structure - Working fields array
     * @param integer $rID - contact record ID
     * @return array with label and fields list
     */
    private function editStatus(&$structure, $rID=0)
    {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/main.php", 'phreebooksMain');
        $layout = [];
        $pb = new phreebooksMain();
        $pb->detailStatus($layout, $rID);
        $idxs = ['current','late30','late60','late90','late120','late121','total'];
        foreach ($idxs as $idx) { $structure[$idx]= !empty($layout['fields'][$idx]) ? $layout['fields'][$idx] : []; }
        return ['label'=>$layout['panels']['genBal']['label'], 'fields'=>['current','late30','late60','late90','late120','late121','total']];
    }

    /**
     * Saves posted data to a contact record
     * @param array $layout - current working structure
     * @param boolean $makeTransaction - [default: true] Makes the save operation a transaction, should only be set to false if this method is part of another transaction
     * @return modified $laylout
     */
    public function save(&$layout=[], $makeTransaction=true)
    {
        $rID  = clean('id', 'integer', 'post');
        $title= clean('short_name', 'text', 'post');
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, $rID?3:2)) { return; }
        if ($makeTransaction) { dbTransactionStart(); } // START TRANSACTION (needs to be here as we need the id to create links
        if (!$result = $this->dbContactSave($this->type, '')) { return; } // Main record
        if (!$rID) { $rID = $result; }
        $_GET['rID'] = $_POST['id'] = $rID; // save for custom processing
        if (!$this->dbAddressSave($rID, 'm', 'm', true)) { return; }   // Address records, main is required
        $this->dbAddressSave($rID, 'b', 'b', false);
        $this->dbAddressSave($rID, 's', 's', false);
        $this->saveLog($layout, $rID);
        if ($makeTransaction) { dbTransactionCommit(); }
        $io = new \bizuno\io();
        if ($io->uploadSave('file_attach', getModuleCache('contacts', 'properties', 'attachPath')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'contacts', ['attach'=>'1'], 'update', "id=$rID");
        }
        msgAdd(lang('msg_record_saved'), 'success'); // doesn't hang if returning to manager
        msgLog(sprintf(lang('tbd_manager'), lang('contacts_type', $this->type))." - ".lang('save')." - $title (rID=$rID)");
        $data = ['content' => ['action'=>'eval','actionData'=>"jqBiz('#accContacts').accordion('select', 0); bizGridReload('dgContacts'); jqBiz('#divContactsDetail').html('&nbsp;');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Saves just a single address in the db
     * @param array $layout - current working structure
     * @return updated $layout
     */
    public function saveAddress(&$layout=[])
    {
        $aType= clean('aType','char','get');
        $rID  = clean('rID',  'integer','get');
        if (!$rID) { return msgAdd(lang('err_bad_id')); }
        $this->dbAddressSave($rID, $aType, $aType, false);
        $dgID = "Address$aType";
        // return to clear address fields and reload datagrid
        $data = ['content' => ['action'=>'eval','actionData'=>"jqBiz('#acc$dgID').accordion('select', 0); bizGridReload('dg$dgID'); jqBiz('#div{$dgID}Detail').html('&nbsp;');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Saves a log entry to a specified contact record
     * @param integer $rID - db record id of the contact to update/save log data
     */
    public function saveNotes()
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return; }
        dbWrite(BIZUNO_DB_PREFIX."address_book", ['notes'=> clean('notesm','text','post')], 'update', "ref_id=$rID AND type='m'");
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /**
     * Saves a log entry to a specified contact record
     * @param integer $rID - db record id of the contact to update/save log data
     */
    public function saveLog(&$layout, $id=0)
    {
        $rID = $id ? $id : clean('rID', 'integer', 'get');
        $action= clean('crm_action','text', 'post');
        $note  = clean('crm_note',  'text', 'post');
        if (!$rID || !$note) { return; }
        $values = [
            'contact_id'=> $rID,
            'entered_by'=> clean('crm_rep_id','integer','post'),
            'log_date'  => clean('crm_date',  'date',   'post'),
            'action'    => $action,
            'notes'     => $note];
        dbWrite(BIZUNO_DB_PREFIX."contacts_log", $values);
        $data = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgLog'); jqBiz('#crm_note').val('');"]];
        if (!$id) { msgAdd(lang('msg_record_saved'), 'success'); } // if stand alone
        $layout = array_replace_recursive($layout, $data);

    }

    /**
     * form builder - Merges 2 database contact ID's to a single record
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function merge(&$layout=[])
    {
        $icnSave= ['icon'=>'save','label'=>lang('merge'),
            'events'=>['onClick'=>"jsonAction('contacts/main/mergeSave&type=$this->type&newBill='+bizCheckBoxGet('newBill'), jqBiz('#mergeSrc').val(), jqBiz('#mergeDest').val());"]];
        $props  = ['defaults'=>['type'=>$this->type,'callback'=>''],'attr'=>['type'=>'contact']];
        $html   = "<p>".$this->lang['msg_contacts_merge_src'] ."</p><p>".html5('mergeSrc', $props)."</p>".
                  "<p>".$this->lang['msg_contacts_merge_dest']."</p><p>".html5('mergeDest',$props)."</p>".
                  "<p>".$this->lang['msg_contacts_merge_bill']."</p><p>".html5('newBill',['attr'=>['type'=>'checkbox']])."</p>".
                  html5('icnMergeSave', $icnSave);
        $data   = ['type'=>'popup','title'=>$this->lang['contacts_merge'],'attr'=>['id'=>'winMerge'],
            'divs'   => ['body'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>"bizFocus('mergeSrc');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Performs the merge of 2 contacts in the db
     * @param array $layout - current working structure
     * @return modifed $layout
     */
    public function mergeSave(&$layout=[])
    {
        global $io;
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 3)) { return; }
        $srcID = clean('rID',    'integer', 'get'); // record ID to merge
        $destID= clean('data',   'integer', 'get'); // record ID to keep
        $billID= clean('newBill','integer', 'get'); // whether to move old main address as billing address of retained entry
        if (!$srcID || !$destID) { return msgAdd("Bad IDs, Source ID = $srcID and Destination ID = $destID"); }
        if ($srcID == $destID)   { return msgAdd("Source and destination cannot be the same!"); }
        msgAdd(lang('stats').': ', 'info');
        $bCnt  = dbWrite(BIZUNO_DB_PREFIX."journal_main",    ['contact_id_b'=>$destID], 'update', "contact_id_b=$srcID");
        msgAdd("journal_main billing = $bCnt; ", 'info');
        $sCnt  = dbWrite(BIZUNO_DB_PREFIX."journal_main",    ['contact_id_s'=>$destID], 'update', "contact_id_s=$srcID");
        msgAdd("journal_main shipping = $sCnt; ", 'info');
        $pCnt  = dbWrite(BIZUNO_DB_PREFIX.'inventory_prices',['contact_id'=>$destID],   'update', "contact_id=$srcID");
        msgAdd("inventory_prices table contact changes = $pCnt; ", 'info');
        if ($billID) {
            dbWrite(BIZUNO_DB_PREFIX.'address_book', ['ref_id'=>$destID, 'type'=>'b'], 'update', "ref_id=$srcID");
            msgAdd('old main address => new bill address; ', 'info');
        } else {
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."address_book WHERE ref_id=$srcID AND type='m'");
            msgAdd('deleted main address; ', 'info');
        }
        $aCnt  = dbWrite(BIZUNO_DB_PREFIX.'address_book', ['ref_id'=>$destID],    'update', "ref_id=$srcID");
        msgAdd("address_book = $aCnt; ", 'info');
        $cCnt  = dbWrite(BIZUNO_DB_PREFIX.'contacts_log', ['contact_id'=>$destID],'update', "contact_id=$srcID");
        msgAdd("contacts_log = $cCnt; ", 'info');
        $dCnt  = dbWrite(BIZUNO_DB_PREFIX.'data_security',['ref_1'=>$destID],     'update', "ref_1=$srcID");
        msgAdd("data_security = $dCnt; ", 'info');
        // special merge keeping specific information from one record or another based on certain criteria
        $srcC  = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$srcID");
        $destC = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$destID");
        dbWrite(BIZUNO_DB_PREFIX.'contacts', ['first_date' => min($srcC['first_date'], $destC['first_date'])],  'update', "id=$destID");
        dbWrite(BIZUNO_DB_PREFIX.'contacts', ['last_update'=> date('Y-m-d H:i:s')], 'update', "id=$destID");
        dbWrite(BIZUNO_DB_PREFIX.'contacts', ['last_date_1'=> max($srcC['last_date_1'],$destC['last_date_1'])], 'update', "id=$destID");
        dbWrite(BIZUNO_DB_PREFIX.'contacts', ['last_date_2'=> max($srcC['last_date_2'],$destC['last_date_2'])], 'update', "id=$destID");
        $fields = ['price_sheet','terms','tax_exempt','tax_rate_id','contact_first','contact_last','flex_field_1','account_number','gov_id_number'];
        foreach ($fields as $field) {
            if (empty($destC[$field]) && !empty($srcC[$field])) {
                dbWrite(BIZUNO_DB_PREFIX.'contacts', [$field=>$srcC[$field]], 'update', "id=$destID");
            }
        }
        msgAdd('deleted contact; ', 'info');
        msgDebug("\nMoving file at path: ".getModuleCache('contacts', 'properties', 'attachPath')." from rID_{$srcID}_ to rID_{$destID}_");
        // Move attachments
        $io->fileMove(getModuleCache('contacts', 'properties', 'attachPath'), "rID_{$srcID}_", "rID_{$destID}_");
        // Move wallet
        $bitBucket=[];
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/payment/wallet.php', 'paymentWallet');
        $wallet = new \bizuno\paymentWallet();
        $srcCID = getWalletID($srcID);
        $destCID= getWalletID($destID);
        msgDebug("\nMerging wallet, Src cID: $srcCID => Dest cID: $destCID");
        $_POST['pfID']          = $srcCID;
        $_POST['short_name_new']= $destCID;
        $wallet->modify($bitBucket);
        msgLog(lang("contacts").'-'.lang('merge')." - $srcID => $destID");
        $data = ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winMerge'); bizGridReload('dgContacts');"],
            'dbAction'=>['contacts'=>"DELETE FROM ".BIZUNO_DB_PREFIX."contacts WHERE id=$srcID"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Deletes a contact records from the database
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 4)) { return; }
        $rID   = clean('rID',  'integer', 'get');
        $action= clean('data', 'text', 'get');
        if (!$rID) { return msgAdd('The record was not deleted, the proper id was not passed!'); }
        // error check, no delete if a journal entry exists
        $block = dbGetValue(BIZUNO_DB_PREFIX."journal_main", 'id', "contact_id_b='$rID' OR contact_id_s='$rID' OR store_id='$rID'");
        if ($block) { return msgAdd($this->lang['err_contacts_delete']); }
        $short_name = dbGetValue(BIZUNO_DB_PREFIX."contacts", 'short_name', "id='$rID'");
        $actionData = "bizGridReload('dgContacts'); accordionEdit('accContacts','dgContacts','divContactsDetail','".jsLang('details')."','contacts/main/edit&type=$this->type', 0);";
        if (!empty($action)) {
            $parts = explode(':', $action);
            switch ($parts[0]) {
                case 'reload': $actionData = "bizGridReload('{$parts[1]}');"; break; // just reload the datagrid
            }
        }
        $data = ['content'=>['action'=>'eval','actionData'=>$actionData],'dbAction'=>[
            BIZUNO_DB_PREFIX."contacts"     => "DELETE FROM ".BIZUNO_DB_PREFIX."contacts WHERE id=$rID",
            BIZUNO_DB_PREFIX."address_book" => "DELETE FROM ".BIZUNO_DB_PREFIX."address_book WHERE ref_id=$rID",
            BIZUNO_DB_PREFIX."data_security"=> "DELETE FROM ".BIZUNO_DB_PREFIX."data_security WHERE ref_1=$rID",
            BIZUNO_DB_PREFIX."contacts_log" => "DELETE FROM ".BIZUNO_DB_PREFIX."contacts_log WHERE contact_id=$rID"]];
        $files = glob(getModuleCache('contacts', 'properties', 'attachPath')."rID_{$rID}_*.zip");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } }
        msgLog(lang('contacts_title')." ".lang('delete')." - $short_name (rID=$rID)");
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Deletes a address record for a given address_book id, typically call through ajax
     * @param type $layout - current working structure
     * @return modified layout
     */
    public function deleteAddress(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 4)) { return; }
        $aID = clean('data', 'integer', 'get');
        if (!$aID) { return msgAdd('No id returned!'); }
        $row = dbGetRow(BIZUNO_DB_PREFIX."address_book", "address_id=$aID");
        $type= $row['type'];
        if ($type == 'm') { return (msgAdd($this->lang['err_contacts_delete_address']));  }
        msgLog(lang('address_book').' '.lang('delete')." - {$row['primary_name']} ($aID)");
        $data = ['content' => ['action'=>'eval','actionData'=>"bizGridReload('dgAddress$type');"],
                 'dbAction'=> [BIZUNO_DB_PREFIX."address_book"=>"DELETE FROM ".BIZUNO_DB_PREFIX."address_book WHERE address_id=$aID"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Ajax call to refresh the history tab of a contact being edited.
     * @param type $layout
     * @return typef'src'
     */
    public function history(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if ($rID) { $type = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'type', "id=$rID"); }
        else      { $type = 'c'; }
        $data = ['type'=>'divHTML',
            'divs'   => [
                'general'=> ['order'=>50,'type'=>'divs','attr'=>['id'=>'crmDiv'],'classes'=>['areaView'],'divs'=>[
                    'dgQuote'=> ['order'=>30,'type'=>'panel','key'=>'dgQuote','classes'=>['block50']],
                    'dgSoPo' => ['order'=>20,'type'=>'panel','key'=>'dgSoPo', 'classes'=>['block50']],
                    'dgInv'  => ['order'=>10,'type'=>'panel','key'=>'dgInv',  'classes'=>['block50']]]]],
            'panels' => [
                'dgQuote'=> ['type'=>'datagrid', 'key'=>'quote'],
                'dgSoPo' => ['type'=>'datagrid', 'key'=>'po_so'],
                'dgInv'  => ['type'=>'datagrid', 'key'=>'inv']],
            'datagrid'=> [
                'quote' => $this->dgHistory('dgHistory09', $type=='v'?3:9,  $rID),
                'po_so' => $this->dgHistory('dgHistory10', $type=='v'?4:10, $rID),
                'inv'   => $this->dgHistory('dgHistory12', $type=='v'?6:12, $rID)]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Shows the details of a contact record, typically used for popups where no editing will take place
     * @param array $layout - structure
     * @return modified $layout
     */
    public function details(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID    = clean('rID', 'integer','get');
        $prefix = clean('prefix','text', 'get');
        $suffix = clean('suffix','text', 'get');
        $fill   = clean('fill',  'char', 'get');
        if (!$rID) { // biz_id=0, send company information
            $address[] = addressLoad();
            $address[0]['type'] = 'm';
            $data = ['prefix'=>$prefix, 'suffix'=>$suffix, 'fill'=>$fill, 'contact'=>[], 'address'=>$address];
        } else {
            $contact= dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$rID");
            $type   = $contact['type']=='v' ? 'vendors' : 'customers';
            // Fix a few things
            $contact['terms_text']   = viewTerms($contact['terms'], true, $contact['type']);
            $contact['terminal_date']= getTermsDate($contact['terms'], $contact['type']);
            if (getModuleCache('proLgstc', 'properties', 'status') && getModuleCache('proLgstc', 'settings', 'general', 'gl_shipping_'.$contact['type'])) {
                $contact['ship_gl_acct_id'] = getModuleCache('proLgstc', 'settings', 'general', 'gl_shipping_'.$contact['type']);
            }
            $address= dbGetMulti(BIZUNO_DB_PREFIX."address_book", "ref_id=$rID", "primary_name");
            $data   = ['prefix'=>$prefix, 'suffix'=>$suffix, 'fill'=>$fill, 'contact'=>$contact, 'address'=>$address];
            $data['showStatus'] = empty(getModuleCache('phreebooks', 'settings', $type, 'show_status')) ? '0' : '1';
        }
        $layout = array_replace_recursive($layout, ['content'=>$data]);
    }

    /**
     *
     * @return type
     */
    public function historyPayment()
    {
        $rID   = clean('rID', 'integer', 'get');
        $terms = viewFormat($rID, 'cTerms');
        $lYear = localeCalculateDate(biz_date('Y-m-d'), 0, 0, -1);
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "contact_id_b=$rID AND journal_id=12 AND closed='1' AND post_date>'$lYear'", 'id', ['journal_id','post_date','closed_date','terms','total_amount']);
        $total = $delta = 0;
        foreach ($rows as $row) {
            $dateDue  = getTermsDate($row['terms'], 'c', $row['post_date']);
            $datetime1= strtotime($row['post_date']);
            $datetime2= strtotime($dateDue);
            $datetime3= strtotime($row['closed_date']);
            $expDays  = ($datetime2 - $datetime1) / (60*60*24);
            $lateDays = ($datetime3 - $datetime1) / (60*60*24);
            $delta   += $lateDays - $expDays;
            $total   += $row['total_amount'];
            msgDebug("\nPost_date = {$row['post_date']} and expected date = $dateDue and actual date = {$row['closed_date']} with delta = $delta and total = {$row['total_amount']}");
        }
        if (empty($rows)) { return msgAdd("No paid invoices this past year!"); }
        $avgSales= viewFormat($total / sizeof($rows), 'currency');
        $avgPmt  = number_format($delta / sizeof($rows), 1);
        msgAdd(sprintf($this->lang['payment_history_resp'], $terms, $avgSales, $avgPmt), 'info');
    }

    /**
     * Builds the contact popup (including hooks and customizations)
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function properties(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd("Bad cID passed!"); }
        compose('contacts', 'main', 'edit', $layout);
        unset($layout['divs']['formBOF'], $layout['divs']['formEOF'], $layout['divs']['toolbar']);
        unset($layout['tabs']['tabContacts']['divs']['general']['divs']['getAttach']);
        unset($layout['jsHead'], $layout['jsReady']);
        $layout['panels']['genAtch']['defaults']['noUpload']= true;
        $layout['panels']['genAtch']['defaults']['delPath'] = '';
    }

    /**
     * Sets the registry cache with address book user settings
     * @param char $type - contact type
     */
    private function managerSettingsAddress($type='m')
    {
        $data = ['path'=>'address'.$type, 'values' => [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX.'address_book.primary_name'],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'search_'.$type,'clean'=>'text','default'=>''],
        ]];
        $this->defaults = \bizuno\updateSelection($data);
    }

    /**
     * This method saves a contact to table: contacts
     * @param string $request - typically the post variables, leave false to use $_POST variables
     * @param char $cType - [default c] contact type, c (customer), v (vendor), e (employee), b (branch), i (CRM), j (projects)
     * @param string $suffix - [default null] field suffix to extract data from the request data
     * @param boolean $required - [default true] field suffix to extract data from the request data
     * @return $rID - record ID of the create/affected contact record
     */
    public function dbContactSave($cType='c', $suffix='', $required=true)
    {
        $rID   = clean('id'.$suffix, 'integer', 'post');
        if (!$security = validateSecurity($this->securityModule, 'mgr_'.$cType, $rID?3:2)) { return; }
        $title = clean('primary_name'.$suffix, 'text', 'post');
        if (empty($title)) { $title = clean('primary_namem'.$suffix, 'text', 'post'); } // may happen either with or without address suffix
        $values= requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'contacts'), $suffix);
        $values['type'] = $cType; // force the type or set if a suffix is used
        if (!$rID) { $values = array_merge($this->contact, $values); }
        else       { $values['last_update'] = biz_date('Y-m-d'); }
        msgDebug("\nWorking with cType = $cType and suffix $suffix and values = ".print_r($values, true));
        // if contact is not required and these fields are set, do not create/update the contacts table
        if (!$required) { if (isset($values['contact_first']) && empty($values['contact_first']) &&
                              isset($values['contact_last'])  && empty($values['contact_last'])  &&
                              empty($title)) { return; } }
        if (!$rID && empty($values['short_name'])) { $values['short_name'] = $this->getNextShortName($values, $cType, $rID); } // auto generate new ID
        if (!empty($values['short_name'])) { // check for duplicate short_names
            $dup = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes($values['short_name'])."' AND type='$cType' AND id<>$rID");
            if ($dup) { return msgAdd(lang('error_duplicate_id')); }
        } else { unset($values['short_name']); } // existing record and no Contact ID passed, leave it alone
        if (empty($values['inactive'])) { $values['inactive'] = 0; } // fixes bug in conversions and prevents null inactive value
        $result = dbWrite(BIZUNO_DB_PREFIX.'contacts', $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $result; }
        $_POST['id'.$suffix] = $rID; // save for customization
        msgDebug("\n  Finished adding/updating contact, id = $rID");
        return $rID;
    }

    /**
     * Generates the short_name for new contacts based on user preferences
     * @param array $values - post variables
     * @param string $cType - suffix from post variables to pull values
     * @param integer $rID - db record ID
     * @return type
     */
    private function getNextShortName($values, $cType, $rID)
    {
        $setting= $this->type=='v' ? 'short_name_v' : 'short_name_c';
        $address= requestData(dbLoadStructure(BIZUNO_DB_PREFIX."address_book"), '');
        if (empty($address)) { $address= requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'address_book'), 'm'); }
        $method = getModuleCache('contacts', 'settings', 'general', $setting, 'auto');
        msgDebug("\nIn getNextShortName with type = $cType and address = ".print_r($address, true));
        if ($method=='email' && !empty($address['email']))      { return $address['email']; }
        if ($method=='tele'  && !empty($address['telephone1'])) { return $address['telephone1']; }
        $output = dbPullReference($this->type=='v' ? 'next_vend_id_num' : 'next_cust_id_num');
        if (!isset($values['short_name'])) { $values['short_name'] = ''; }
        while ($this->refTries > 0) {
            $this->refTries--;
            if (empty($values['short_name'])) { continue; }
            $dup = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes($values['short_name'])."' AND type='$cType' AND id<>$rID");
            if     ($dup && $this->refTries > 0) { $this->getNextShortName($values, $cType, $rID); } // loop back through as this auto ID is used
            elseif ($dup) { return msgAdd(lang('error_duplicate_id')); }
        }
        return $output;
    }

    /**
     * This method saves an address to table: address_book
     * @param number $cID - contact ID, must be non-zero
     * @param string $aType - address type: m (main), b (billing), s (shipping)
     * @param string $suffix - field suffix to extract data from the request data
     * @return $aID - record ID of the create/affected record
     */
    public function dbAddressSave($cID=0, $aType='m', $suffix='', $required=false)
    {
        if (!$cID) { return msgAdd("Error updating address book, the proper contact ID was not passed!"); }
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX."address_book"), $suffix);
        // check for some fields set to indicate if record has data, return false if not
        if (!$required) { if ($this->isBlankForm($values, $this->reqFields)) { return; } }
        $aID = !empty($values['address_id']) ? $values['address_id'] : 0;
        $values['ref_id'] = $cID; // link to contact
        $values['type']   = $aType;
        $result = dbWrite(BIZUNO_DB_PREFIX.'address_book', $values, $aID?'update':'insert', "address_id=$aID");
        if (!$aID) { $aID = $result; } // set the address id for new inserts
        $_POST['address_id'.$suffix] = $aID; // save for customization
        msgDebug("\n  Finished adding/updating address, address_id = $aID");
        return $aID;
    }

    /**
     * Builds address list grid structure
     * @param integer $rID - contact db record id
     * @param char $type - contact type
     * @param char $aType - address type
     * @param integer $security - working security level
     * @return array - grid structure, ready to render
     */
    private function dgAddress($rID=0, $type='', $aType='', $security=0)
    {
        $this->managerSettingsAddress($aType);
        return ['id'=>'dgAddress'.$aType, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'   => ['idField'=>'address_id', 'toolbar'=>"#dgAddress{$aType}Toolbar", 'url'=>BIZUNO_AJAX."&bizRt=contacts/main/managerRowsAddress&type=$type&rID=$rID&aType=$aType"],
            'events' => ['onDblClickRow'=>"function(rowIndex, rowData){ accordionEdit('accAddress$aType', 'dgAddress$aType', 'divAddress{$aType}Detail', '".jsLang('details')."', 'contacts/main/editAddress&aType=$aType&type=$type&cID=$rID', rowData.address_id); }"],
            'source' => [
                'tables'  => ['address_book'=> ['table'=>BIZUNO_DB_PREFIX."address_book"]],
                'search'  => ['primary_name', 'contact', 'telephone1', 'telephone2', 'telephone3', 'telephone4', 'city', 'postal_code', 'email'],
                'actions' => [
                    'newAddress'=>['order'=>10,'icon'=>'new','events'=>['onClick'=>"accordionEdit('accAddress$aType', 'dgAddress$aType', 'divAddress{$aType}Detail', '".jsLang('details')."', 'contacts/main/editAddress&aType=$aType&type=$type&cID=$rID', 0);"]]],
                'filters' => [
                    'search' => ['order'=>90,'attr'=>['id'=>"search_$aType", 'value'=>$this->defaults["search_$aType"]]],
                    'ref_id' => ['order'=>98,'hidden'=> true, 'sql'=>"ref_id=$rID"],
                    'type'   => ['order'=>99,'hidden'=> true, 'sql'=>"type='$aType'"]],
                'sort' =>['s0'=>['order'=>10,'field'=>$this->defaults['sort'].' '.$this->defaults['order']]]],
            'columns' => [
                'address_id' => ['order'=>0,'field'=>'address_id',  'attr'=>['hidden'=>true]],
                'ref_id'     => ['order'=>0,'field'=>'ref_id',      'attr'=>['hidden'=>true]],
                'action'     => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return dgAddress{$aType}Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit' => ['icon'=>'edit', 'size'=>'small', 'order'=>30, 'label'=>lang('edit'), 'hidden'=> $security > 2 ? false : true,
                            'events'=> ['onClick' => "accordionEdit('accAddress$aType', 'dgAddress$aType', 'divAddress{$aType}Detail', '".jsLang('details')."', 'contacts/main/editAddress&aType=$aType&type=$type&cID=$rID', idTBD);"]],
                        'trash'=> ['icon'=>'trash','size'=>'small', 'order'=>90, 'label'=>lang('delete'), 'hidden'=> $security > 3 ? false : true,
                            'events'=> ['onClick' => "if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('contacts/main/deleteAddress', $rID, idTBD);"]]]],
                'primary_name'=> ['order'=>20, 'field'=>'primary_name', 'label'=>pullTableLabel("address_book", 'primary_name', $type),
                    'attr' => ['width'=>240, 'sortable'=>true, 'resizable'=>true]],
                'address1'    => ['order'=>30, 'field'=>'address1', 'label'=>pullTableLabel("address_book", 'address1', $type),
                    'attr' => ['width'=>200, 'sortable'=>true, 'resizable'=>true]],
                'city'        => ['order'=>40, 'field'=>'city', 'label'=>pullTableLabel("address_book", 'city', $type),
                    'attr' => ['width'=> 80, 'sortable'=>true, 'resizable'=>true]],
                'state'       =>  ['order'=>50, 'field'=>'state', 'label'=>pullTableLabel("address_book",'state', $type),
                    'attr' => ['width'=> 60, 'sortable'=>true, 'resizable'=>true]],
                'postal_code' => ['order'=>60, 'field'=>'postal_code', 'label'=>pullTableLabel("address_book", 'postal_code', $type),
                    'attr' => ['width'=> 60, 'sortable'=>true, 'resizable'=>true]],
                'telephone1'  => ['order'=>70,    'field'=>'telephone1', 'label'=>pullTableLabel("address_book", 'telephone1', $type),
                    'attr' => ['width'=>100, 'sortable'=>true, 'resizable'=>true]]]];
    }

    /**
     * This function builds the grid structure for retrieving contacts
     * @param string $name - grid div id
     * @param char $type - contact type, c - customers, v - vendors, etc.
     * @param integer $security - access level range 0-4
     * @param string $rID - contact record id for CRM retrievals to limit results.
     * @return array $data - structure of the grid to render
     */
    protected function dgContacts($name, $type, $security=0, $rID=false)
    {
        $this->managerSettings($type);
        $statuses = array_merge([['id'=>'a','text'=>lang('all')]], getModuleCache('contacts','statuses'));
        $f0_value = "";
        if ($this->defaults['f0_'.$type]<>'a') {
            $f0_value = BIZUNO_DB_PREFIX."contacts.inactive='{$this->defaults['f0_'.$type]}'";
        }
        $data = ['id'=>"dg$name", 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'=> ['idField'=>'id', 'toolbar'=>"#dg{$name}Toolbar", 'url'=>BIZUNO_AJAX."&bizRt=contacts/main/managerRows&type=$type".($rID?"&rID=$rID":'')],
            'events' => [
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('acc$name', 'dg$name', 'div{$name}Detail', '".jsLang('details')."', 'contacts/main/edit&type=$type&ref=$rID', rowData.id); }",
                ],
            'footnotes' => $this->dgContactsFootnotes(),
            'source'    => [
                'tables' => [
                    'contacts'    =>['table'=>BIZUNO_DB_PREFIX.'contacts'],
                    'address_book'=>['table'=>BIZUNO_DB_PREFIX.'address_book','join'=>'join','links'=>BIZUNO_DB_PREFIX."contacts.id=".BIZUNO_DB_PREFIX."address_book.ref_id"]],
                'search' => [
                    BIZUNO_DB_PREFIX.'contacts.id',            BIZUNO_DB_PREFIX.'contacts.short_name',    BIZUNO_DB_PREFIX.'address_book.primary_name',BIZUNO_DB_PREFIX.'address_book.contact',
                    BIZUNO_DB_PREFIX.'address_book.telephone1',BIZUNO_DB_PREFIX.'address_book.telephone2',BIZUNO_DB_PREFIX.'address_book.telephone3',  BIZUNO_DB_PREFIX.'address_book.telephone4',
                    BIZUNO_DB_PREFIX.'address_book.address1',  BIZUNO_DB_PREFIX.'address_book.address2',  BIZUNO_DB_PREFIX.'address_book.city',        BIZUNO_DB_PREFIX.'address_book.postal_code'],
                'actions' => [
                    'newContact'=>['order'=>10,'icon'=>'new',  'events'=>['onClick'=>"accordionEdit('acc$name', 'dg$name', 'div{$name}Detail', '".lang('details')."', 'contacts/main/edit&type=$type&ref=$rID', 0, '');"]],
                    'clrSearch' =>['order'=>50,'icon'=>'clear','events'=>['onClick'=>"jqBiz('#f0_{$type}').val('$this->f0_default'); bizTextSet('search_$type', ''); dg".$name."Reload();"]]],
                'filters' => [
                    "f0_$type"=> ['order'=>10,'break'=>true,'label'=>lang('status'),'sql'=>$f0_value,'values'=>$statuses,
                        'attr'=>['type'=>'select','value'=>$this->defaults['f0_'.$type]]],
                    'search'  => ['order'=>90,'attr'=>['id'=>"search_$type", 'value'=>$this->defaults['search_'.$type]]],
                    'aType'   => ['order'=>99,'hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."address_book.type='m'"]],
                'sort' => ['s0'=>['order'=>10,'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns' => [
                'id'        => ['order'=>0,'field'=>BIZUNO_DB_PREFIX."contacts.id",          'attr'=>['hidden'=>true]],
                'address2'  => ['order'=>0,'field'=>BIZUNO_DB_PREFIX."address_book.address2",'attr'=>['hidden'=>true]],
                'email'     => ['order'=>0,'field'=>BIZUNO_DB_PREFIX."address_book.email",   'attr'=>['hidden'=>true]],
                'inactive'  => ['order'=>0,'field'=>BIZUNO_DB_PREFIX."contacts.inactive",    'attr'=>['hidden'=>true]],
                'attach'    => ['order'=>0,'field'=>BIZUNO_DB_PREFIX."contacts.attach",      'attr'=>['hidden'=>true]],
                'gl_account'=> ['order'=>0,'field'=>BIZUNO_DB_PREFIX."contacts.gl_account",  'attr'=>['hidden'=>true]],
                'action'    => ['order'=>1,'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return dg".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'  => ['icon'=>'edit', 'order'=>20, 'label'=>lang('edit'),
                            'events'=> ['onClick' => "accordionEdit('acc$name', 'dg$name', 'div{$name}Detail', '".lang('details')."', 'contacts/main/edit&type=$type&ref=$rID', idTBD);"]],
                        'delete'=> ['icon'=>'trash', 'order'=>60, 'label'=>lang('delete'),'hidden'=>$security>3?false:true,
                            'events'=> ['onClick' => "if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('contacts/main/delete', idTBD, 'reload:dg$name');"]],
                        'chart' => ['icon'=>'mimePpt', 'order'=>80, 'label'=>lang('sales'),'hidden'=>in_array($type, ['c','v'])?false:true,
                            'events'=> ['onClick' => "windowEdit('contacts/tools/chartSales&rID=idTBD', 'myChart', '&nbsp;', 600, 500);"]],
                        'attach' => ['order'=>95,'icon'=>'attachment','display'=>"row.attach=='1'"]]],
                'short_name'   => ['order'=>10, 'field'=>BIZUNO_DB_PREFIX.'contacts.short_name',
                    'label' => pullTableLabel("contacts", 'short_name', $type),'events'=>['styler'=>$this->dgContactsStyler()],
                    'attr'  => ['width'=>100, 'sortable'=>true, 'resizable'=>true]],
                'type'         => ['order'=>15, 'field'=>BIZUNO_DB_PREFIX."contacts.type", 'format'=>'contactType',
                    'attr'  => ['width'=>240, 'sortable'=>true, 'resizable'=>true, 'hidden'=>true]],
                'primary_name' => ['order'=>20, 'field'=>BIZUNO_DB_PREFIX.'address_book.primary_name',
                    'label' => pullTableLabel("address_book", 'primary_name', $type),
                    'attr'  => ['width'=>240, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['e','i'])?true:false]],
                'contact_first'=> ['order'=>20, 'field' => BIZUNO_DB_PREFIX.'contacts.contact_first',
                    'label' => pullTableLabel("contacts", 'contact_first', $type),
                    'attr'  => ['width'=>100, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['e','i'])?false:true]],
                'contact_last' => ['order'=>25, 'field'=>BIZUNO_DB_PREFIX.'contacts.contact_last',
                    'label' => pullTableLabel("contacts", 'contact_last', $type),
                    'attr'  => ['width'=>100, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['e','i'])?false:true]],
                'flex_field_1'=> ['order'=>30, 'field'=>BIZUNO_DB_PREFIX.'contacts.flex_field_1',
                    'label' => pullTableLabel("contacts", 'flex_field_1', $type),
                    'attr'  => ['width'=>200, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['i'])?false:true]],
                'address1'     => ['order'=>30, 'field'=>BIZUNO_DB_PREFIX.'address_book.address1',
                    'label' => pullTableLabel("address_book", 'address1', $type),
                    'attr'  => ['width'=>200, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['i'])?true:false]],
                'city'    => ['order'=>40, 'field'=>BIZUNO_DB_PREFIX.'address_book.city',
                    'label' => pullTableLabel("address_book", 'city', $type),
                    'attr'  => ['width'=>80, 'sortable'=>true, 'resizable'=>true]],
                'state'=>  ['order'=>50, 'field'=>BIZUNO_DB_PREFIX.'address_book.state',
                    'label' => pullTableLabel("address_book", 'state', $type),
                    'attr'  => ['width'=>60, 'sortable'=>true, 'resizable'=>true]],
                'postal_code' => ['order'=>60, 'field'=>BIZUNO_DB_PREFIX.'address_book.postal_code',
                    'label' => pullTableLabel("address_book", 'postal_code', $type),
                    'attr'  => ['width'=>60, 'sortable'=>true, 'resizable'=>true]],
                'telephone1'  => ['order'=>70, 'field'=>BIZUNO_DB_PREFIX.'address_book.telephone1',
                    'label' => pullTableLabel("address_book", 'telephone1', $type),
                    'attr'  => ['width'=>100, 'sortable'=>true, 'resizable'=>true]]],
            ];
        if ($type <> 'a') {
            $data['source']['filters']['cType'] = ['order'=>98,'hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."contacts.type='$type'"];
        }
        if ($type=='c' || $type == 'v') {
            $data['source']['actions']['mergeContact'] = ['order'=>20,'icon'=>'merge','events'=>['onClick'=>"jsonAction('contacts/main/merge&type=$type', 0);"]];
        } elseif (strlen($type)>1) { // search only certain types, all types are listed (i.e. cv)
            $data['source']['filters']['cType']['sql'] = BIZUNO_DB_PREFIX."contacts.type IN ('".implode("','", str_split($type))."')";
        }
        if ($type == 'i') {
            $data['source']['search'][] = BIZUNO_DB_PREFIX.'contacts.contact_first';
            $data['source']['search'][] = BIZUNO_DB_PREFIX.'contacts.contact_last';
            $data['source']['search'][] = BIZUNO_DB_PREFIX.'contacts.flex_field_1';
        }
        if (getUserCache('profile', 'restrict_user', false, 0)) { // check for user restrictions
            $uID = getUserCache('profile', 'contact_id', false, 0);
            $data['source']['filters']['restrict_user'] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."contacts.rep_id='$uID'"];
        }
        if ($GLOBALS['myDevice'] == 'mobile') {
            $data['columns']['short_name']['attr']['hidden']  = true;
            $data['columns']['flex_field_1']['attr']['hidden']= true;
            $data['columns']['address1']['attr']['hidden']    = true;
            $data['columns']['state']['attr']['hidden']       = true;
            $data['columns']['postal_code']['attr']['hidden'] = true;
        }
        return $data;
    }

    private function dgContactsStyler()
    {
        $html = 'function(value,row,index) { ';
        $statuses = getModuleCache('contacts','statuses');
        msgDebug("\nstatuses = ".print_r($statuses, true));
        foreach ($statuses as $status) {
            if (empty($status['color'])) { continue; }
            $html .= "if (row.inactive=='{$status['id']}') { return {class:'row-{$status['color']}'}; }";
        }
        return $html.'}';
    }

    private function dgContactsFootnotes()
    {
        $html = lang('color_codes').': ';
        $statuses = getModuleCache('contacts','statuses');
        foreach ($statuses as $status) {
            if (empty($status['color'])) { continue; }
            $html .= '<span class="row-'.$status['color'].'">&nbsp;'.$status['text'].'&nbsp;</span>&nbsp;';
        }
        return ['codes'=>$html];
    }

    /**
     *
     * @param string $name - HTML name of the contacts history grid
     * @param integer $jID - PhreeBooks journal ID to set search criteria
     * @param integer $rID - Contact database record id
     * @return array - grid structure
     */
    private function dgHistory($name, $jID, $rID = 0)
    {
        $rows   = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post');
        $page   = clean('page', ['format'=>'integer','default'=>1], 'post');
        $sort   = clean('sort', ['format'=>'text',   'default'=>'post_date'],'post');
        $order  = clean('order',['format'=>'text',   'default'=>'desc'],     'post');
        $gID    = explode(":", getDefaultFormID($jID));
        $jSearch= in_array($jID, [3,4,9,10]) ? $jID : ($jID==6 ? '6,7' : '12,13');
        $sec4_10= in_array($jID, [3,4,9,10]) ? getUserCache('security', "j{$jID}_mgr") : 0;
        switch ($jID) {
            case  6: $jPmt = 20; $cmPmt = 17; break;
            case 12:
            default: $jPmt = 18; $cmPmt = 22; break;
        }
        $data = ['id'=>$name, 'strict'=>true, 'rows'=>$rows, 'page'=>$page, 'title'=>sprintf(lang('tbd_history'), lang('journal_main_journal_id', $jID)),
            'attr'   => ['idField'=>'id','url'=>BIZUNO_AJAX."&bizRt=contacts/main/managerRowsHistory&type=$this->type&jID=$jID&rID=$rID"],
            'source' => [
                'tables' => ['journal_main'=>['table'=>BIZUNO_DB_PREFIX."journal_main"]],
                'filters' => [
                    'jID'  => ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."journal_main.journal_id IN ($jSearch)"],
                    'rID'  => ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."journal_main.contact_id_b='$rID'"]],
                'sort' => ['s0'=>['order'=>10, 'field'=>("$sort $order")]]],
            'columns' => [
                'id'        => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."journal_main.id",        'attr'=>['hidden'=>true]],
                'closed'    => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."journal_main.closed",    'attr'=>['hidden'=>true]],
                'journal_id'=> ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."journal_main.journal_id",'attr'=>['hidden'=>true]],
                'bal_due'   => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."journal_main.id",'process'=>'paymentRcv','attr'=>['hidden'=>true]],
                'action'    => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'       => ['order'=>20,'icon'=>'edit',    'label'=>lang('edit'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');"]],
                        'print'      => ['order'=>40,'icon'=>'print',   'label'=>lang('print'),
                            'events' => ['onClick'=>"var idx=jqBiz('#$name').datagrid('getRowIndex', idTBD); var jID=jqBiz('#$name').datagrid('getRows')[idx].journal_id; ('fitColumns', true); winOpen('phreeformOpen', 'phreeform/render/open&group={$gID[0]}:j'+jID+'&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]],
                        'dates'      => ['order'=>50,'icon'=>'date',   'label'=>lang('delivery_dates'), 'hidden'=>$sec4_10>1?false:true,
                            'events' => ['onClick' => "windowEdit('phreebooks/main/deliveryDates&rID=idTBD', 'winDelDates', '".lang('delivery_dates')."', 500, 400);"],
                            'display'=> "row.journal_id=='4' || row.journal_id=='10'"],
                        'purchase'   => ['order'=>80,'icon'=>'purchase','label'=>lang('fill_purchase'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=6&bizAction=inv');"],
                            'display'=> "row.closed=='0' && (row.journal_id=='3' || row.journal_id=='4')"],
                        'sale'       => ['order'=>80,'icon'=>'sales',   'label'=>lang('fill_sale'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=12&bizAction=inv');"],
                            'display'=> "row.closed=='0' && (row.journal_id=='9' || row.journal_id=='10')"],
                        'payment'    => ['order'=>80,'icon'=>'payment', 'label'=>lang('payment'),
                            'events' => ['onClick' => "var cID=jqBiz('#id').val(); winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=0&jID=$jPmt&bizAction=inv&iID=idTBD&cID='+cID);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='6' || row.journal_id=='12')"],
                        'cmPmt'      => ['order'=>80,'icon'=>'payment', 'label'=>lang('payment'),
                            'events' => ['onClick' => "var cID=jqBiz('#id').val(); winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=0&jID=$cmPmt&bizAction=inv&iID=idTBD&cID='+cID);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='7' || row.journal_id=='13')"]]],
                'invoice_num'   => ['order'=>10, 'field'=>BIZUNO_DB_PREFIX."journal_main.invoice_num",'label'=>pullTableLabel("journal_main", 'invoice_num', $jID),
                    'attr'  => ['width'=>125, 'sortable'=>true, 'resizable'=>true]],
                'purch_order_id'=> ['order'=>20, 'field'=>BIZUNO_DB_PREFIX."journal_main.purch_order_id",'label'=>pullTableLabel("journal_main", 'purch_order_id', $jID),
                    'attr'  => ['width'=>150, 'sortable'=>true, 'resizable'=>true]],
                'post_date'     => ['order'=>30, 'field' => BIZUNO_DB_PREFIX."journal_main.post_date", 'format'=>'date','label' => pullTableLabel("journal_main", 'post_date', $jID),
                    'attr'  => ['width'=>120,'align'=>'center', 'sortable'=>true, 'resizable'=>true]],
                'closed_date'   => ['order'=>40, 'field'=>BIZUNO_DB_PREFIX."journal_main.closed_date",'label'=>in_array($jID, [6, 12]) ? lang('paid') : lang('closed'),
                    'attr'  => ['width'=>120,'align'=>'center', 'sortable'=>true, 'resizable'=>true],
                    'events'=> ['formatter'=>"function(value,row) { return (row.closed=='1' && value!='') ? formatDate(value) : (row.bal_due ? formatCurrency(row.bal_due, false) : ''); }"]],
                'total_amount'  => ['order'=>50, 'field'=>BIZUNO_DB_PREFIX."journal_main.total_amount",'label'=>lang('total'), 'attr'=>['width'=>100, 'align'=>'right', 'sortable'=>true, 'resizable'=>true],
                    'events'=> ['formatter'=>"function(value,row) { return (row.journal_id==7 || row.journal_id==13) ? formatCurrency(-value, false) : formatCurrency(value, false); }"]]]];
        if (in_array($GLOBALS['myDevice'], ['mobile','tablet'])) { $data['columns']['closed_date']['attr']['hidden'] = true; }
        return $data;
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function getTabNotes(&$layout=[])
    {
        if (!$security = validateSecurity($this->securityModule, $this->securityMenu, 1)) { return; }
        $rID   = clean('rID', 'integer','get');
        $notes = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'notes',"ref_id=$rID AND type='m'");
        $fldLog= [
            'notesm'    => ['attr'=>['type'=>'textarea', 'value'=>$notes]],
            'crm_date'  => ['order'=>10,'label'=>lang('date'),  'break'=>true,'attr'=>['type'=>'date', 'value'=>viewDate(biz_date('Y-m-d'))]],
            'crm_rep_id'=> ['order'=>20,'label'=>lang('contacts_log_entered_by'),'break'=>true,'values'=>viewRoleDropdown('all'),'attr'=>['type'=>'select','value'=>getUserCache('profile', 'contact_id', false, '0')]],
            'crm_action'=> ['order'=>30,'label'=>lang('action'),'break'=>true,'values'=>viewKeyDropdown(getModuleCache('contacts', 'actions_crm'), true),'attr'=>['type'=>'select']],
            'crm_note'  => ['order'=>40,'label'=>'','break'=>true,'attr'=>['type'=>'textarea','rows'=>5]]];
        $data  = ['type'=>'divHTML',
            'divs'   => [
                'general'=> ['order'=>50,'type'=>'divs','attr'=>['id'=>'crmDiv'],'classes'=>['areaView'],'divs'=>[
                    'notes'=> ['order'=>30,'type'=>'panel','key'=>'notes','classes'=>['block50'],'attr'=>['id'=>'divNotes']],
                    'cLog' => ['order'=>60,'type'=>'panel','key'=>'cLog', 'classes'=>['block50'],'attr'=>['id'=>'divLog']]]]],
            'toolbars'=> [
                'tbNote'=> ['icons'=>['save'=>['order'=>10,'icon'=>'save','hidden'=>$rID && $security >1?false:true,'events'=>['onClick'=>"divSubmit('contacts/main/saveNotes&rID=$rID', 'divNotes');"]]]],
                'tbLog' => ['icons'=>['save'=>['order'=>10,'icon'=>'save','hidden'=>$rID && $security >1?false:true,'events'=>['onClick'=>"divSubmit('contacts/main/saveLog&rID=$rID', 'divLog');"]]]]],
            'panels' => [
                'notes'=> ['type'=>'divs','divs'=>[
                    'tbNote' => ['order'=>20,'type'=>'toolbar','key'=>'tbNote'],
                    'fldNote'=> ['order'=>50,'type'=>'fields', 'keys'=>['notesm']]]],
                'cLog' => ['label'=>lang('contacts_log'),'type'=>'divs','divs'=>[
                    'tbLog'  => ['order'=>20,'type'=>'toolbar', 'key'=>'tbLog'],
                    'fldLog' => ['order'=>50,'type'=>'fields',  'keys'=>['crm_note','crm_date','crm_rep_id','crm_action']],
                    'dgLog'  => ['order'=>80,'type'=>'datagrid','key'=>'dgLog']]]],
            'fields' => $fldLog,
            'datagrid'=>['dgLog'=>$this->dgLog('dgLog', $rID, $security)]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * builds the history list of orders used on contact history tab
     * @param array $layout - current working structure
     * @return updated $layout
     */
    public function managerRowsHistory(&$layout=[])
    {
        $jID = clean('jID', 'integer', 'get');
        $rID = clean('rID', 'integer', 'get');
        $structure = $this->dgHistory('dgHistory'.$jID, $jID, $rID);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'history','datagrid'=>['history'=>$structure]]);
    }

    /**
     * Gets the rows for the contacts log grid
     * @param array $layout - working structure
     * @return modified $layout
     */
    public function managerRowsLog(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>'log','datagrid'=>['log'=>$this->dgLog('dgLog', $rID)]]);
    }

    /**
     * The method deletes a record from the contacts_log table
     * @param integer $rID - typically a $_GET variable but can also be passed to the function in an array
     * @return array with dbAction and content to remove entry from grid
     */
    public function deleteLog(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd("Bad ID submitted!"); }
        msgLog(lang('contacts_log').' '.lang('delete')." - ($rID)");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"bizGridReload('dgLog');"],
            'dbAction'=> [BIZUNO_DB_PREFIX."contacts_log"=>"DELETE FROM ".BIZUNO_DB_PREFIX."contacts_log WHERE id=$rID"]]);
    }

    /**
     * Builds the grid structure for the contacts log
     * @param string $name - HTML grid field name
     * @param integer $rID - database contact record id
     * @param integer $security - users approved security level
     * @return array - grid structure
     */
    private function dgLog($name, $rID=0, $security=0)
    {
        $rows  = clean('rows', ['format'=>'integer',  'default'=>10],        'post');
        $page  = clean('page', ['format'=>'integer',  'default'=> 1],        'post');
        $sort  = clean('sort', ['format'=>'text',     'default'=>'log_date'],'post');
        $order = clean('order',['format'=>'text',     'default'=>'desc'],    'post');
        $search= clean('search_log',['format'=>'text','default'=>''],        'post');
        return ['id'=>$name, 'rows'=>$rows, 'page'=>$page,
            'attr'   => ['nowrap'=>false, 'toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'url'=>BIZUNO_AJAX."&bizRt=contacts/main/managerRowsLog&rID=$rID"],
            'source' => [
                'tables' => ['contacts_log'=>['table'=>BIZUNO_DB_PREFIX."contacts_log"]],
                'search' => ['notes'],
                'actions' => [
                    'clrSearch' => ['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search_log', ''); {$name}Reload();"]]],
                'filters'=> [
                    'search'=> ['order'=>90,'attr'=>['id'=>"search_log", 'value'=>$search]],
                    'rID'   => ['order'=>99,'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."contacts_log.contact_id='$rID'"]],
                'sort'   => ['s0' => ['order'=>10,'field'=>"$sort $order"]]],
            'columns' => [
                'id' => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."contacts_log.id",'attr'=>['hidden'=>true]],
                'action' => ['order'=>1,'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'logTrash' => ['order'=>80,'icon'=>'trash','label'=>lang('delete'),'hidden'=>$security>3?false:true,
                            'events' => ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('contacts/main/deleteLog', idTBD);"]]]],
                'log_date'  => ['order'=>10,'field'=>"log_date",  'label'=>lang('date'), 'format'=>'date', 'attr'=>['width'=> 50, 'resizable'=>true]],
                'entered_by'=> ['order'=>20,'field'=>"entered_by",'label'=>lang('contacts_log_entered_by'),'attr'=>['width'=> 50, 'resizable'=>true], 'format'=>'contactID'],
                'action'    => ['order'=>30,'field'=>"action",    'label'=>lang('action'),                 'attr'=>['width'=> 50, 'resizable'=>true], 'format'=>'cache:contacts:actions_crm'],
                'notes'     => ['order'=>40,'field'=>"notes",     'label'=>lang('notes'),                  'attr'=>['width'=>200, 'resizable'=>true]]]];
    }

    /**
     * Returns form submitted values only if set and not null
     * @param array $row - Row from db or submitted form
     * @param array $testList - List of fields to test against
     * @return true if ALL the fields exist and do not have a null or zero value, false otherwise
     */
    function isBlankForm($row, $testList=[])
    {
        foreach ($testList as $field) {
            if (isset($row[$field]) && $row[$field]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Builds the editor for contact payment terms
     * @param array $layout - current working structure
     * @param char $defType - contact type
     */
    public function editTerms(&$layout=[], $defType='c')
    {
        $prefix = clean('prefix',['format'=>'text','default'=>''], 'get');
        $default= clean('default',['format'=>'integer','default'=>0], 'get');
        $fields = $this->getTermsDiv($defType, $default);
        $data   = ['type'=>'popup','title'=>lang('terms'),'attr'=>['id'=>'winTerms','width'=>650],
            'toolbars' => ['tbTerms'=>['icons'=>['next'=>['order'=>20,'events'=>['onClick'=>"jqBiz('#frmTerms').submit();"]]]]],
            'divs'     => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbTerms'],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmTerms'],
                'winTerms'=> ['order'=>50,'type'=>'fields', 'keys'=>array_keys($fields)],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'forms'    => ['frmTerms'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=contacts/main/setTerms&prefix=$prefix"]]],
            'fields'   => $fields,
            'jsReady'  => ['init'=>"ajaxForm('frmTerms');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $defType
     * @return array
     */
    private function getTermsDiv($defType='c', $default=0)
    {
        $type   = clean('type',['format'=>'char',   'default'=>$defType],'get');
        $cID    = clean('id',  ['format'=>'integer','default'=>0],       'get');
        $encoded= clean('data',['format'=>'text',   'default'=>false],   'get');
        if     (!$encoded && $cID)      { $encoded = dbGetValue(BIZUNO_DB_PREFIX."contacts", 'terms', "id=$cID"); }
        elseif (!$encoded && $type=='v'){ $encoded = getModuleCache('phreebooks', 'settings', 'vendors', 'terms'); }
        elseif (!$encoded)              { $encoded = getModuleCache('phreebooks', 'settings', 'customers', 'terms'); }
        $terms  = explode(':', $encoded);
        $defNET = isset($terms[3]) && $terms[0]==3 ? $terms[3] : '30';
        $defDOM = isset($terms[3]) && $terms[0]==4 ? $terms[3] : biz_date('Y-m-d');
        $fields = [
            'terms_disc'  => ['options'=>['width'=>40],'attr'=>['type'=>'float',  'value'=>isset($terms[1])?$terms[1]:0,'maxlength'=>3]],
            'terms_early' => ['options'=>['width'=>40],'attr'=>['type'=>'float',  'value'=>isset($terms[2])?$terms[2]:0,'maxlength'=>3]],
            'terms_net'   => ['options'=>['width'=>40],'attr'=>['type'=>'integer','value'=>$defNET,'maxlength'=>'3']]];
        $custom = ' - '.sprintf(lang('contacts_terms_discount'), html5('terms_disc', $fields['terms_disc']), html5('terms_early', $fields['terms_early'])).' '.sprintf(lang('contacts_terms_net'), html5('terms_net',$fields['terms_net']));
        $output = [
            'radio0'    => ['order'=>10,'label'=>lang('contacts_terms_default').' ['.viewTerms('0', false, $terms[0]).']','attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>0,'checked'=>$terms[0]==0?true:false]],
            'radio3'    => ['order'=>20,'break'=>false,'label'=>lang('contacts_terms_custom'),                'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>3,'checked'=>$terms[0]==3?true:false]],
            'r1Disc'    => ['order'=>21,'html' =>$custom,'attr'=>['type'=>'raw']],
            'radio6'    => ['order'=>30,'label'=>lang('contacts_terms_now'),    'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>6,'checked'=>$terms[0]==6?true:false]],
            'radio2'    => ['order'=>40,'label'=>lang('contacts_terms_prepaid'),'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>2,'checked'=>$terms[0]==2?true:false]],
            'radio1'    => ['order'=>50,'label'=>lang('contacts_terms_cod'),    'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>1,'checked'=>$terms[0]==1?true:false]],
            'radio4'    => ['order'=>60,'break'=>false,'label'=>lang('contacts_terms_dom'),                   'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>4,'checked'=>$terms[0]==4?true:false]],
            'terms_date'=> ['order'=>61,'attr' =>['type'=>'date', 'value'=>$defDOM]],
            'radio5'    => ['order'=>70,'label'=>lang('contacts_terms_eom'),    'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>5,'checked'=>$terms[0]==5?true:false]],
            'hr1'       => ['order'=>71,'html' =>'<hr>','attr'=>['type'=>'raw']],
            'credit'    => ['order'=>80,'label'=>lang('contacts_terms_credit_limit'),'attr'=>['type' =>'currency','value'=>isset($terms[4])?$terms[4]:'1000']]];
        if ($default) { unset($output['radio0']); }
        return $output;
    }

    /**
     * Gets translated text for a specified encoded term passed through ajax
     * @param array $layout - current working structure
     * @return array - modified $layout
     */
    public function setTerms(&$layout=[])
    {
        $type  = clean('terms_type','integer','post');
        $prefix= clean('prefix',    'cmd',    'get');
        $enc   = "$type:".clean('terms_disc', 'float', 'post').":".clean('terms_early', 'integer', 'post').":";
        $enc  .= ($type==4 ? clean('terms_date', 'date', 'post') : clean('terms_net', 'integer', 'post')).":".clean('credit', 'currency', 'post');
        msgDebug("\n Received and created encoded terms = $enc");
        $data = ['content'=>['action'=>'eval','actionData'=>"bizTextSet('{$prefix}terms', '$enc'); bizTextSet('{$prefix}terms_text', '".jsLang(viewTerms($enc))."'); bizWindowClose('winTerms');"]];
        $layout = array_replace_recursive($layout, $data);
    }
}
