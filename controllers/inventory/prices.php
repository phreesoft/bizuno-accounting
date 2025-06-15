<?php
/*
 * Functions related to inventory pricing for customers and vendors
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
 * @version    6.x Last Update: 2024-02-01
 * @filesource /controllers/inventory/prices.php
 */

namespace bizuno;

class inventoryPrices
{
    public  $moduleID = 'inventory';
    private $first = 0; // first price for level calculations
    private $methods = ['quantity','bySKU','byContact','fxdDiscount']; // order is important

    function __construct()
    {
        $this->lang      = getLang($this->moduleID);
        $this->type      = clean('type', ['format'=>'char', 'default'=>'c'], 'get');
        $this->helpSuffix= "-$this->type";
        $this->qtySource = ['1'=>lang('direct_entry'), '2'=>lang('inventory_item_cost'), '3'=>lang('inventory_full_price'), '4'=>lang('price_level_1'), '5'=>lang('fixed_discount')];
        $this->qtyAdj    = ['0'=>lang('none'), '1'=>lang('decrease_by_amount'), '2'=>lang('decrease_by_percent'), '3'=>lang('increase_by_amount'), '4'=>lang('increase_by_percent')];
        $this->qtyRnd    = ['0'=>lang('none'), '1'=>lang('next_integer'), '2'=>lang('next_fraction'), '3'=>lang('next_increment')];
    }

    /**
     * Entry point for the prices manager
     * @param array $layout - structure for the main inventory prices page
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, 1)) { return; }
        $mID  = clean('mID','alpha_num','get');
        $cID  = clean('cID','integer',  'get');
        $iID  = clean('iID','integer',  'get');
        $mod  = clean('mod','text',     'get');
        $title= sprintf(lang('tbd_prices'), lang('contacts_type_'.$this->type));
        // pull the pkg sell rows from the inventory db
        $sellPkgData = !empty($iID) ? dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'price_byItem', "id=$iID") : '[]';
        if (empty($sellPkgData)) { $sellPkgData = '[]'; }
        $jsHead = "
var invSellPkgData = $sellPkgData;
function sellPkgSave() {
    jqBiz('#dgSellQtys').edatagrid('saveRow');
    var items = jqBiz('#dgSellQtys').datagrid('getData');
    var sellQtyVals = JSON.stringify(items);
    jsonAction('inventory/prices/saveSellPkgs', jqBiz('#id').val(), sellQtyVals);
}";
        $data = ['type'=>'page','title'=>$title,
            'divs'     => [
                'prices' => ['order'=>50,'type'=>'accordion','key'=>'accPrices']],
            'accordion'=> ['accPrices'=>['divs'=>[
                'divPricesMgr' =>['order'=>30,'label'=>$title,'type'=>'datagrid','key'=>'dgPricesMgr'],
                'divPricesSet' =>['order'=>50,'label'=>lang('details'),'type'=>'html','html'=>"<p>".$this->lang['msg_no_price_sheets']."</p>"]]]],
            'datagrid' => ['dgPricesMgr' => $this->dgPrices('dgPricesMgr', $this->type, $security, $mID, $cID, $iID, $mod)],
            'jsHead'   => ['sellQtys'=>$jsHead],
            'jsReady'  => ['init'=>"bizFocus('search', 'dgPricesMgr');"]];
        if ('inventory'==$mod) {
            $data['toolbars']['tbSellPkg']   = ['icons'=>[
                'save' => ['order'=>20,'hidden'=>$security >1?false:true,'events'=>['onClick'=>"sellPkgSave();"]]]];
            $data['datagrid']['dgSellQtys'] = $this->dgSellQtys('dgSellQtys');
            $data['accordion']['accPrices']['divs']['divSellPkgs'] = ['order'=>80,'label'=>'Sell Units','type'=>'divs','divs'=>[
                'toolbar'=> ['order'=> 5,'type'=>'toolbar', 'key' =>'tbSellPkg'],
                'heading'=> ['order'=>10,'type'=>'html',    'html'=>"<h1>Sell Packages</h1>"],
                'dgSell' => ['order'=>15,'type'=>'datagrid','key' =>'dgSellQtys']]];
        }
        if ($mod) { // if mod then in a tab of some sort
            $data['type'] = 'divHTML'; // just the div html
            $layout = array_replace_recursive($layout, $data);
            return;
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * This method pulls the data from the database to populate the grid
     * @param $layout - Structure coming in
     * @return array grid structure to load data from database
     */
    public function managerRows(&$layout=[])
    {
        $mID  = clean('mID','alpha_num','get');
        $cID  = clean('cID','integer',  'get');
        $iID  = clean('iID','integer',  'get');
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, 1)) { return; }
        $_POST['search'] = getSearch('search');
        msgDebug("\n ready to build prices datagrid, security = $security");
        $structure = $this->dgPrices('dgPricesMgr', $this->type, $security, $mID, $cID, $iID);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'dgPricesMgr','datagrid'=>['dgPricesMgr'=>$structure]]);
    }

    /**
     * Stores the users preferences for filters
     */
    private function managerSettings()
    {
        $data = ['path'=>'invPrices'.$this->type, 'values'=>  [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>'method'],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     * Structure to add a new price sheet
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function add(&$layout=[])
    {
        $mod  = clean('mod', 'text', 'get');
        $meths= [];
        if (!sizeof(getModuleCache('inventory', 'prices'))) {
            msgAdd("Please add some price methods first, My Business -> Settings -> Inventory Module -> Prices tab");
            $html = '&nbsp';
        } else {
            foreach (getModuleCache('inventory', 'prices') as $mID => $settings) {
                msgDebug("\nworking with module ID $mID with settings = ".print_r($settings, true));
                if (empty($settings['status'])) { continue; }
                $fqcn = "\\bizuno\\$mID";
                bizAutoLoad($settings['path']."$mID.php", $fqcn);
//                $priceSet = getModuleCache('inventory','prices',$mID,'settings');
                $tmp = new $fqcn($settings);
                if (empty($mod) || (isset($tmp->structure['hooks']) && array_key_exists($mod, $tmp->structure['hooks']))) {
                    if ($settings['status']) { $meths[] = ['id'=>$mID, 'text'=>$settings['title']]; }
                }
            }
            $html  = '<p>'.lang('desc_new_price_sheets')."</p>";
            $html .= html5('methodID',['values'=>$meths,'attr'=>['type'=>'select']]);
            $html .= html5('iconGO',  ['icon'=>'next','events'=>['onClick'=>"accordionEdit('accPrices','dgPricesMgr','divPricesSet','".jsLang('details')."','inventory/prices/edit&type=$this->type&mod=$mod&mID='+bizSelGet('methodID'),0); bizWindowClose('winNewPrice');"]]);
        }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['winNewPrice'=>['order'=>50,'type'=>'html','html'=>$html]]]);
    }

    /**
     * This method is a wrapper to set up the edit structure, it requires the specific method to populate the form
     * @param string $layout - typically the $_GET variables containing the necessary variables
     * @return array - structure to render the detail editor HTML
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        $mID = clean('mID', ['format'=>'text','default'=>'quantity'], 'request');
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory_prices');
        if ($rID) {
            $row     = dbGetRow(BIZUNO_DB_PREFIX.'inventory_prices', "id=$rID");
            $settings= json_decode($row['settings'], true);
            $mID     = $row['method'];
        } else { // set the defaults
            $row     = ['id'=>0, 'method'=>$mID, 'contact_type'=>$this->type];
            $settings= ['attr'=>'', 'title'=>''];
        }
        $row['currency'] = getDefaultCurrency(); // force currency to be the users default
        unset($structure['settings']);
        $structure['contact_id']['label']   = lang('contacts_short_name');
        $structure['inventory_id']['label'] = lang('sku');
        $structure['ref_id']['attr']['type']= 'select';
        $structure['ref_id']['label']       = $this->lang['price_sheet_to_override'];
        $structure['ref_id']['values']      = $this->quantityList();
        dbStructureFill($structure, $row);
        $data = ['type'=>'divHTML','title'=>'Price Sheet Type',
            'divs'    => ['tbPrices'=>['order'=> 1,'type'=>'toolbar','key'=>'tbPrices']],
            'toolbars'=> ['tbPrices'=>['icons'=>[
                'save' => ['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"if (preSubmitPrices()) divSubmit('inventory/prices/save&type=$this->type&mID=$mID', 'divPricesSet');"]]]]],
            'fields'  => $structure,
            'values'  => ['qtySource'=>$this->qtySource, 'qtyAdj'=>$this->qtyAdj, 'qtyRnd'=>$this->qtyRnd]];
        msgDebug("\nmID = $mID");
        if (empty($mID)) { $mID = 'quantity'; } // just in case there is an issue with a price method and mID is null
        $priceSet = getModuleCache($this->moduleID, 'prices', $mID);
        msgDebug("\nmethod Settings = ".print_r($priceSet, true));
        $fqcn = "\\bizuno\\$mID";
        bizAutoLoad($priceSet['path']."$mID.php", $fqcn);
        $meth = new $fqcn($priceSet);
        $meth->priceRender($data, $settings);
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Method to save a new/edited price sheet
     * @param type $layout
     * @return type
     */
    public function save(&$layout=[])
    {
        $mID = clean('mID', 'text', 'get');
        $rID = clean('id'.$mID, 'text', 'post');
        $_POST['contact_type'.$mID] = $this->type;
        if (!$mID) { return msgAdd('Cannot save, no method passed!'); }
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, $rID?3:2)) { return; }
        $fqcn = "\\bizuno\\$mID";
        bizAutoLoad(getModuleCache('inventory', 'prices')[$mID]['path']."$mID.php", $fqcn);
        $priceSet = getModuleCache('inventory','prices',$mID,'settings');
        $meth = new $fqcn($priceSet);
        if ($meth->priceSave()) { msgAdd(lang('msg_record_saved'), 'success'); }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accPrices').accordion('select', 0); bizGridReload('dgPricesMgr'); jqBiz('#divPricesSet').html('&nbsp;');"]]);
    }

    public function saveSellPkgs(&$layout=[])
    {
        msgDebug("\nentering saveSellPkgs");
        $iID = clean('rID' , 'integer','get');
        $data= clean('data', 'json',   'get');
        if (empty($iID)) { return ("Invalid SKU ID!"); }
        dbWrite(BIZUNO_DB_PREFIX.'inventory', ['price_byItem'=>json_encode($data)], 'update', "id=$iID");
        $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID");
        msgLog(lang('prices').'-'.lang('save')." for SKU: $sku");
        msgAdd("Sell package levels saved for SKU: $sku!", 'success');
    }

    /**
     * Copies a price sheet to a newly named price sheet with all settings
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function copy(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, 2)) { return; }
        $rID     = clean('rID', 'integer','get');
        $newTitle= clean('data','text',   'get');
        $sheet   = dbGetRow(BIZUNO_DB_PREFIX.'inventory_prices', "id=$rID");
        $settings= json_decode($sheet['settings'], true);
        $oldTitle= isset($settings['title']) ? $settings['title'] : '';
        $dup     = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_prices', 'settings', "id<>$rID AND contact_type='$this->type'");
        foreach ($dup as $row) {
            $props = json_decode($row['settings'], true);
            if ($props['title'] == $settings['title']) { return msgAdd(lang('duplicate_title')); }
        }
        unset($sheet['id']);
        foreach ($settings as $key => $value) {
          switch ($key) {
            case 'title':       $settings[$key] = $newTitle;     break;
            case 'last_update': $settings[$key] = biz_date('Y-m-d'); break;
            default: // leave them alone
          }
        }
        $sheet['settings'] = json_encode($settings);
        $nID = $_GET['nID'] = dbWrite(BIZUNO_DB_PREFIX.'inventory_prices', $sheet);
        msgLog(lang('prices').' '.lang('copy')." - $oldTitle => $newTitle");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"accordionEdit('accPrices', 'dgPricesMgr', 'divPricesSet', '".lang('settings')."', 'inventory/prices/edit', $nID);"]]);
    }

    /**
     * Deletes a price sheet from the database
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, 4)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('The record was not deleted, the proper id was not passed!'); }
        $result   = dbGetRow(BIZUNO_DB_PREFIX.'inventory_prices', "id=$rID");
        $settings = json_decode($result['settings'], true);
        msgLog(lang('prices').' '.lang('delete')." - Title: ".(isset($settings['title']) ? $settings['title'] : '-')." (iID=".$result['inventory_id']."; cID=".$result['contact_id']."; rID=$rID)");
        $layout = array_replace_recursive($layout, [
            'content' => ['action'=>'eval','actionData'=>"jqBiz('#accPrices').accordion('select', 0); bizGridReload('dgPricesMgr'); jqBiz('#divPricesSet').html('&nbsp;');"],
            'dbAction'=> ['inventory_prices'=>"DELETE FROM ".BIZUNO_DB_PREFIX."inventory_prices WHERE id=$rID OR ref_id=$rID"]]);
    }

    /**
     * retrieves the price sheet details for a given SKU to create a pop up window
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function details(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 1)) { return; }
        $rID   = clean('rID', 'integer','get');
        $sku   = clean('sku', 'text',   'get');
        $cID   = clean('cID', 'integer','get');
        if     ($rID) { $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'sku', 'item_cost', 'full_price'], "id=$rID"); }
        elseif ($sku) { $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'sku', 'item_cost', 'full_price'], "sku='".addslashes($sku)."'"); }
        else   { return msgAdd("Bad SKU sent!"); }
        $cost  = clean('itemCost', ['format'=>'float','default'=>$inv['item_cost']], 'get');
        $full  = clean('fullPrice',['format'=>'float','default'=>$inv['full_price']],'get');
        $layout['args'] = ['sku'=>$inv['sku'], 'cID'=>$cID, 'cost'=>$cost, 'full'=>$full];
        compose('inventory', 'prices', 'quote', $layout);
        $rows[]= ['group'=>lang('general'),'text'=>"<div style='float:right'>".viewFormat($layout['content']['price'], 'currency').'</div><div>'.lang('price')."</div>"];
        $rows[]= ['group'=>lang('general'),'text'=>"<div style='float:right'>".viewFormat($full, 'currency').'</div><div>'.lang('inventory_full_price')."</div>"];
        if (validateSecurity('phreebooks', 'j6_mgr', 1, false)) {
            $rows[] = ['group'=>lang('general'),'text'=>"<div style='float:right'>".viewFormat($cost, 'currency').'</div><div>'.lang('inventory_item_cost')."</div>"];
        }
        if (!empty($layout['content']['sheets'])) { foreach ($layout['content']['sheets'] as $level) {
            $rows[] = ['group'=>$level['title'],'text'=>"<div style='float:right'>".lang('price').'</div><div>'.lang('qty')."</div>"];
            foreach ($level['levels'] as $entry) {
                $rows[] = ['group'=>$level['title'],'text'=>"<div style='float:right'>".viewFormat($entry['price'], 'currency').'</div><div>'.(float)$entry['qty']."</div>"];
            }
        } }
        $data = ['type'=>'popup', 'title'=>lang('inventory_prices', $this->type), 'attr'=>['id'=>'winPrices','width'=>300,'height'=>700],
            'divs'  => ['winStatus'=>['order'=>50,'options'=>['groupField'=>"'group'",'data'=>"pricesData"],'type'=>'list','key' =>'lstPrices']],
            'lists' => ['lstPrices'=>[]], // handled as JavaScript data
            'jsHead'=> ['init'=>"var pricesData = ".json_encode($rows).";"]];
        $layout = array_merge_recursive($layout, $data);
    }

    /**
     * Retrieves the best price for a given customer/SKU using available price sheets
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function quote(&$layout=[])
    {
        $defaults = ['qty'=>1, 'sku'=>'', 'iID'=>0, 'UPC'=>0, 'cID'=>0, 'cost'=>0, 'full'=>0];
        if (empty($layout['args'])) { // may be passed as GET variables
            $this->type = clean('type', ['format'=>'char', 'default'=>'c'],'get');
            $layout['args'] = [
                'qty' => clean('qty', ['format'=>'integer', 'default'=>1], 'get'),
                'sku' => clean('sku', 'text',   'get'),
                'cID' => clean('cID', 'integer','get'),
                'iID' => clean('rID', 'integer','get')];
        }
        $layout['args'] = array_replace($defaults, $layout['args']); // fills out all of the indexes in args
        msgDebug("\nProcessing inventory/prices/quote with processed args = ".print_r($layout['args'], true));
        $iSec = validateSecurity('inventory', 'prices_'.$this->type, 1, false);
        $pSec = $this->type=='v' ? validateSecurity('phreebooks', 'j6_mgr', 1, false) : validateSecurity('phreebooks', 'j12_mgr', 1, false);
        if (!$security = max($iSec, $pSec)) { return msgAdd(lang('err_no_permission')." [".'prices_'.$this->type." OR jX_mgr]"); }
        $args = $this->quoteInitArgs($layout['args']);
        msgDebug("\nFinding quote with values = ".print_r($args, true));
        $prices = ['sheets'=>[]];
        $this->pricesLevels($prices, $args);
        $this->quoteExtractPrices($prices, $args);
        $layout = array_replace_recursive($layout, ['args'=>$args, 'content'=>$prices]);
    }

    private function quoteInitArgs(&$args=[])
    {
        $inv   = [];
        $cont  = !empty($args['cID']) ? dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['type', 'price_sheet'], "id={$args['cID']}") : ['type'=>$this->type, 'price_sheet'=>''];
        $filter= '';
        if     (!empty($args['iID'])) { $filter = "id = {$args['iID']}"; }
        elseif (!empty($args['sku'])) { $filter = "sku='".addslashes($args['sku'])."'"; }
        elseif (!empty($args['UPC'])) { $filter = "upc='{$args['UPC']}'"; }
        if (!empty($filter)) {
            $inv  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'item_cost', 'full_price', 'sale_price', 'price_sheet_c', 'price_sheet_v'], $filter);
        }
        msgDebug("\nRead inventory record with results = ".print_r($inv, true));
        if ( empty($inv)) { return []; }
        if (!empty($args['cost'])) { $inv['item_cost'] = $args['cost']; }
        if (!empty($args['full'])) { $inv['full_price']= $args['full']; }
        return ['iID'=>$inv['id'],           'qty'    =>abs($args['qty']), // to properly handle negative sales/purchases and still get pricing based on method
            'iSheetc'=>$inv['price_sheet_c'],'iSheetv'=>$inv['price_sheet_v'],
            'cID'    =>$args['cID'],         'cType'  =>$this->type,
            'cSheet' =>$cont['price_sheet'], // @todo cSheetC, cSheetV
            'iCost'  =>$inv['item_cost'],    'iLand'  =>$inv['item_cost'], // landed cost
            'iList'  =>$inv['full_price'],   'iSale'  =>$inv['sale_price']];
    }
            // Inventory stock levels need adjusting
    private function quoteExtractPrices(&$prices=[], $args=[])
    {
        msgDebug("\nEntering quoteExtractPrices with args = ".print_r($args, true));
        $prices['price']        = $args['cType']=='v' ? $args['iCost'] : $args['iList'];
        $prices['regular_price']= $prices['price']; // This needs to go, not used
        $prices['sale_price']   = $args['iSale'];
        $prices['price_msrp']   = $args['iList'];
        $prices['price_cost']   = $args['iCost'];
        $prices['price_landed'] = $args['iLand'];
        if (empty($prices['locked']) && !empty($prices['discount'])) { // calculate the discounts if applicable
            $levels = explode(';', $prices['discount']['encoded']);
            foreach ($levels as $key => $level) {
                $price = !empty($prices['sheets'][0]['levels'][$key]) ? $prices['sheets'][0]['levels'][$key]['price'] : $prices['price'];
                $formula   = explode(':', $level);
                $formula[0]= $price; // insert the current price in there
                $prices['sheets'][0]['levels'][$key]['price'] = $this->calcPrice(implode(':', $formula));
            }
        }
        foreach ($prices['sheets'] as $sheet) {
            foreach ($sheet['levels'] as $level) {
                if (!isset($level['qty'])) { $level['qty']=1; }
                if ($args['qty'] >= $level['qty']) { $prices['price'] = !empty($prices['price']) ? min($prices['price'], $level['price']) : $level['price']; }
            }
        }
        msgDebug("\nLeaving quoteExtractPrices after discount with prices = ".print_r($prices, true));
    }

    /**
     * Determines the price matrix for a given SKU and customer
     * @param array $layout - structure coming in
     * @param string $sku [default: ''] - inventory item SKU
     * @param integer $cID [default: 0] - Contact ID, can be customer or vendor
     * @return array - price matrix for the given customer and SKU, default price sheet or special pricing applied
     */
    public function quoteLevels(&$layout=[], $sku='')
    {
        msgDebug("\nEntering quoteLevels with sku = $sku");
        $layout['args']['sku'] = $sku;
        compose('inventory', 'prices', 'quote', $layout);
        if (!empty($layout['content']['sheets']) && is_array($layout['content']['sheets'])) {
            $sheets   = array_shift($layout['content']['sheets']);
            $skuPrices= $sheets['levels']; // first sheet is the default from the quote method
        } else {
            $skuPrices= [['qty'=>1, 'price'=>$layout['content']['price']]];
        }
        return $skuPrices;
    }

    /**
     * Retrieves the price levels for a given price sheet, sets the new low price if needed
     * @param array $prices - current working array with pricing values
     * @param array $values - contains information to retrieve proper price for a given SKU
     */
    public function pricesLevels(&$prices, $values)
    {
        msgDebug ("\nEntering inventory/prices/pricesLevels");
        $sorted = sortOrder(getModuleCache('inventory', 'prices'));
        msgDebug ("\nAfter sorting: ".print_r($sorted, true));
        foreach ($sorted as $meth => $settings) { // start with the sorted methods
            msgDebug("\nLooking at method = $meth with settings: ".print_r($settings, true));
            if (empty($settings['path'])) { continue; }
            $fqcn= "\\bizuno\\$meth";
            bizAutoLoad($settings['path']."$meth.php", $fqcn);
            $est = new $fqcn($settings['settings']);
            if (method_exists($est, 'priceQuote')) { $est->priceQuote($prices, $values); }
            msgDebug("\nprices after $meth = ".print_r($prices, true));
        }
    }

    /**
     * Creates a list of available price sheets to use in a view drop down
     * @param char $type - contact type, choices are c and v
     * @param boolean $addNull - [default false] set to true to create a None option at the first position
     * @param boolean $addFixed - [default false] set to true to
     * @return array - list of method:quantity price sheets ready for render
     */
    public function quantityList($type='c', $addNull=false, $addFixed=false)
    {
        $output = [];
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_prices', "method IN ('quantity', 'fxdDiscount') AND contact_type='$type'");
        foreach ($result as $row) {
            $settings = json_decode($row['settings'], true);
            $output[] = ['id'=>$row['id'], 'text'=>$settings['title']];
        }
        $temp = [];
        foreach ($output as $key => $value) { $temp[$key] = $value['text']; }
        array_multisort($temp, SORT_ASC, $output);
        if ($addFixed){ array_unshift($output, ['id'=>-1, 'text'=>lang('locked')]); }
        if ($addNull) { array_unshift($output, ['id'=> 0, 'text'=>lang('none')]); }
        return $output;
    }

    /**
     * Decodes a price sheet setting and returns lowest price and array of values
     * @param float $cost - Item cost as retrieved from inventory database table
     * @param float $full - Full price as retrieved from inventory database table
     * @param float $quan - Number of units to price
     * @param string $encoded_levels - Encoded price levels to build pricing array
     * @return array - calculated price after applying price sheet and pricing levels
     */
    protected function decodeQuantity($cost, $full, $quan, $encoded_levels)
    {
        $price_levels= explode(';', $encoded_levels);
        $prices      = [];
        $this->first = 0;
        for ($i=0, $j=1; $i < sizeof($price_levels); $i++, $j++) { // 0.00:1.000:5:2:20.000::0.000
            $level_info = explode(':', $price_levels[$i]);
            $level_info[0] = !empty($level_info[0]) ? $level_info[0] : ($i==0 ? $full : 0);
            $level_info[1] = !empty($level_info[1]) ? $level_info[1] : $j;
            $price = $this->calcPrice(implode(':', $level_info), $cost, $full);
            if ($j == 1) { $this->first = $price; } // save level 1 pricing for later if needed
            if (!empty($level_info[2])) { $prices[$i] = ['qty'=>$level_info[1], 'price'=>$price]; }
        }
        $price = 0;
        if (is_array($prices)) { foreach ($prices as $value) { if ($quan >= $value['qty']) { $price = $value['price']; } } }
        return ['price'=>$price, 'levels'=>$price ? $prices : []];
    }

    /**
     * Calculates the price from the encoded price sheet grid
     * @param string $encoded - encoded price discount
     * @param float $cost
     * @param float $full
     * @return float - calculated price
     */
    private function calcPrice($encoded, $cost=0, $full=0)
    {
        msgdebug("\nEntering calcPrice with encoded = $encoded, cost = $cost and full = $full");
        $defaults= [0, 1, 0, 0, 0, 0, 0]; // price:qty:src:adj:adj_val:rnd:rnd_val
        $args    = array_replace($defaults, explode(':', $encoded));
        $price   = $args[0];
        switch ($args[2]) { // source
            case 0: $price = 0;            break; // Not Used
            case 1:                        break; // Direct Entry
            case 2: $price = $cost;        break; // Last Cost
            case 3: $price = $full;        break; // Retail Price
            case 4: $price = $this->first; break; // Price Level 1
            case 5: $price = $price;       break; // Fixed Discount/Increase
        }
        switch ($args[3]) { // adjustment
            case 0:                                      break; // None
            case 1: $price -= $args[4];                  break; // Decrease by Amount
            case 2: $price -= $price * ($args[4] / 100); break; // Decrease by Percent
            case 3: $price += $args[4];                  break; // Increase by Amount
            case 4: $price += $price * ($args[4] / 100); break; // Increase by Percent
        }
        switch ($args[5]) { // round
            case 1: $price = ceil($price); break; // Next Integer (whole dollar)
            case 2: // Constant remainder (cents)
                $remainder = $args[6];
                if ($remainder < 0) { $remainder = 0; } // don't allow less than zero adjustments
                // convert to fraction if greater than 1 (user left out decimal point)
                if ($remainder >= 1) { $remainder = '.' . $args[6]; }
                $price = floor($price) + $remainder;
                break;
            case 3: // Next Increment (round to next value)
                $remainder = $args[6];
                if ($remainder <= 0) { $price = ceil($price); } // don't allow less than zero adjustments, assume zero
                else { $price = ceil($price / $remainder) * $remainder; }
                break;
        }
        return $price;
    }

    /**
     *
     * @param string $name - REQUIRED - grid ID
     * @param string $type - contact type, acceptable values are c or v
     * @param number $security - access control
     * @param text $mID - Method ID, if present will restrict output to specified method
     * @param integer $cID - Contact ID, if present will restrict output to specified contact
     * @param integer $iID - Inventory ID, if present will restrict output to specified inventory item
     * @return array structure
     */
    private function dgPrices($name, $type='c', $security=0, $mID='', $cID=0, $iID=0, $mod='')
    {
        $this->managerSettings();
        $data = ['id'=>$name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'idField'=>'id', 'url'=>BIZUNO_AJAX."&bizRt=inventory/prices/managerRows&type=$type".($mID?"&mID=$mID":'').($cID?"&cID=$cID":'').($iID?"&iID=$iID":'')],
            'events' => [
                'onDblClickRow'=> "function(rowIndex, rowData) { accordionEdit('accPrices','dgPricesMgr','divPricesSet','".jsLang('details')."','inventory/prices/edit&type=$type".($mod?"&mod=$mod":'')."',rowData.id); }",
                'rowStyler'    => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; } if (row.default==1) { return {class:'row-default'}; } }"],
            'source' => [
                'tables' => [
                    'prices'   => ['table'=>BIZUNO_DB_PREFIX.'inventory_prices'],
                    'inventory'=> ['table'=>BIZUNO_DB_PREFIX.'inventory','join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."inventory_prices.inventory_id=".BIZUNO_DB_PREFIX."inventory.id"]],
                'search' => ['inventory_prices.settings', 'inventory_prices.method', 'inventory.sku', 'inventory.description_short', 'inventory.description_purchase', 'inventory.description_sales'],
                'actions'=> [
                    'newPrices'=> ['order'=>10,'icon'=>'new',  'events'=>['onClick'=>"windowEdit('inventory/prices/add&type=$type".($mod?"&mod=$mod":'').($cID?"&cID=$cID":'').($iID?"&iID=$iID":'')."','winNewPrice','".jsLang('inventory_prices_method')."',400,200);"]],
                    'clrPrices'=> ['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search', ''); ".$name."Reload();"]]],
                'filters'=> [
                    'search'   => ['order'=>90,'html'  =>['attr'=>['id'=>'search','value'=>$this->defaults['search']]]],
                    'typePrice'=> ['order'=>99,'hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."inventory_prices.contact_type='$type'"]],
                'sort' => ['s0' =>['order'=>10,'field' =>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'footnotes'=> ['codes'=>lang('color_codes').': <span class="row-default">'.lang('default').'</span>'],
            'columns'  => [
                'id'          => ['order'=>0,'field'=>BIZUNO_DB_PREFIX.'inventory_prices.id',          'attr'=>['hidden'=>true]],
                'inactive'    => ['order'=>0,'field'=>BIZUNO_DB_PREFIX.'inventory_prices.inactive',    'attr'=>['hidden'=>true]],
//              'currency'    => ['order'=>0,'field'=>BIZUNO_DB_PREFIX.'inventory_prices.currency',    'attr'=>['hidden'=>true]],
                'default'     => ['order'=>0,'field'=>'settings:default','attr'=>['hidden'=>true]],
//              'inventory_id'=> ['order'=>0,'field'=>BIZUNO_DB_PREFIX.'inventory_prices.inventory_id','attr'=>['hidden'=>true]],
                'action'      => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit' => ['order'=>30,'icon'=>'edit', 'hidden'=>$security>2?false:true,'events'=>['onClick'=>"accordionEdit('accPrices','dgPricesMgr','divPricesSet','".jsLang('settings')."','inventory/prices/edit&type=$type',idTBD);"]],
                        'copy' => ['order'=>30,'icon'=>'copy', 'hidden'=>$security>1?false:true,'events'=>['onClick'=>"var title=prompt('".lang('msg_entry_copy')."'); if (title!=null) jsonAction('inventory/prices/copy', idTBD, title);"]],
                        'trash'=> ['order'=>90,'icon'=>'trash','hidden'=>$security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('inventory/prices/delete', idTBD);"]]]],
                'title'       => ['order'=>10, 'field'=>'settings:title',   'label'=>lang('title'),
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'method'      => ['order'=>20, 'field'=>BIZUNO_DB_PREFIX.'inventory_prices.method',    'label'=>lang('method'),
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'ref_id'      => ['order'=>30, 'field'=>BIZUNO_DB_PREFIX.'inventory_prices.ref_id',    'label'=>lang('reference'),'format'=>'dbVal;'.BIZUNO_DB_PREFIX.'inventory_prices;settings:title;id',
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'contact_id'  => ['order'=>40, 'field'=>BIZUNO_DB_PREFIX.'inventory_prices.contact_id','label'=>lang('address_book_primary_name'),'format'=>'contactName',
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'inventory_id'=> ['order'=>50,'field'=>BIZUNO_DB_PREFIX.'inventory_prices.inventory_id','label'=>lang('description'),'alias'=>'inventory_id','process'=>'inv_shrt',
                    'attr' => ['sortable'=>false, 'resizable'=>true]],
                'inv_desc'    => ['order'=>60, 'field'=>BIZUNO_DB_PREFIX.'inventory.sku','label'=>lang('sku'),'alias'=>'inventory_id','process'=>'inv_sku',
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'last_update' => ['order'=>70, 'field'=>'settings:last_update',   'label'=>lang('last_update'),'format'=>'date',
                    'attr' => ['sortable'=>true, 'resizable'=>true]]]];
        $cList  = $iList = [];
        if ($mID) { $data['source']['filters']['mID'] = ['order'=>99, 'hidden'=>true, 'sql'=>"method='$mID'"]; }
        if ($cID) { $data['source']['filters']['cID'] = ['order'=>99, 'hidden'=>true, 'sql'=>"contact_id=$cID"]; }
        elseif ($this->defaults['search']) { // see if searching within contact
            $cFields = ['primary_name'];
            $contacts = dbGetMulti(BIZUNO_DB_PREFIX."address_book", dbGetSearch($this->defaults['search'], $cFields), "primary_name {$this->defaults['order']}", ['ref_id']);
            foreach ($contacts as $cID) { $cList[] = $cID['ref_id']; }
        }
        if ($iID) { $data['source']['filters']['iID'] = ['order'=>99, 'hidden'=>true, 'sql'=>"inventory_id=$iID"]; }
        elseif ($this->defaults['search']) {
            $iFields = ['sku', 'description_short', 'description_purchase', 'description_sales'];
            $inventory = dbGetMulti(BIZUNO_DB_PREFIX."inventory", dbGetSearch($this->defaults['search'], $iFields), "description_short {$this->defaults['order']}", ['id']);
            foreach ($inventory as $iID) { $iList[] = $iID['id']; }
        }
        if (sizeof($cList) && sizeof($iList)) {
            $data['source']['filters']['addSrch'] = ['order'=>99,'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."inventory_prices.contact_id IN (".implode(',',$cList).") OR inventory_prices.inventory_id IN (".implode(',',$iList).")"];
        } elseif (sizeof($cList)) {
            $data['source']['filters']['addSrch'] = ['order'=>99,'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."inventory_prices.contact_id IN (".implode(',',$cList).")"];
        } elseif (sizeof($iList)) {
            $data['source']['filters']['addSrch'] = ['order'=>99,'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."inventory_prices.inventory_id IN (".implode(',',$iList).")"];
        } elseif (!$this->defaults['search']) { // not in contacts or inventory managers, must be prices manager by contact type and not searching (or method quantity goes away)
            unset($data['source']['tables']['inventory']);
            $data['source']['search'] = ['settings', 'method'];
//            $data['columns']['inventory_id']['field'] = BIZUNO_DB_PREFIX.'inventory_prices.inventory_id';
//            $data['columns']['inventory_id']['format']= 'dbVal;inventory;description_short;id';
        }
        if (in_array($GLOBALS['myDevice'], ['mobile','tablet'])) {
            $data['columns']['title']['attr']['hidden']      = true;
            $data['columns']['method']['attr']['hidden']     = true;
            $data['columns']['ref_id']['attr']['hidden']     = true;
            $data['columns']['last_update']['attr']['hidden']= true;
        }
        return $data;
    }

    /**
     * Grid structure for quantity based pricing
     * @param string $name - DOM field name
     * @return array - grid structure
     */
    protected function dgQuantity($name) {
        return ['id'=>$name, 'type'=>'edatagrid',
            'attr'     => ['toolbar'=>"#{$name}Toolbar", 'rownumbers'=>true],
            'events'   => ['data'=> $name.'Data',
                'onLoadSuccess'=> "function(row) { var rows=jqBiz('#$name').edatagrid('getData'); if (rows.total == 0) jqBiz('#$name').edatagrid('addRow'); }",
                'onClickRow'   => "function(rowIndex) { jqBiz('#$name').edatagrid('editRow', rowIndex); }"],
            'source'   => ['actions'=>['new'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'footnotes'=> ['currency'=>lang('msg_default_currency_assumed')],
            'columns'  => [
                'action'  => ['order'=> 1,'label'=>lang('action'), 'attr'=>[],
                    'actions'=> ['trash'=>  ['icon'=>'trash','order'=>20,'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]],
                    'events' => ['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"]],
                'qty'     => ['order'=>10,'label'=>lang('qty'), 'attr'=>['align'=>'right'],
                    'events'=>  ['editor'=>"{type:'numberbox',options:{formatter:function(value){return formatPrecise(value);}}}"]],
                'source'  => ['order'=>20,'label'=>lang('source'), 'attr'=>['sortable'=>true, 'resizable'=>true, 'align'=>'center'],
                    'events' => ['formatter'=>"function(value){ return getTextValue(qtySource, value); }",
                        'editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:qtySource,value:'1'}}"]],
                'adjType' => ['order'=>30,'label'=>lang('adjustment'),'attr' =>[],
                    'events' => ['formatter'=>"function(value){ return getTextValue(qtyAdj, value); }",
                        'editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:qtyAdj}}"]],
                'adjValue'=> ['order'=>40,'label'=>$this->lang['adj_value'], 'attr'=>['align'=>'center', 'size'=>'10'],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'rndType' => ['order'=>50,'label'=>lang('rounding'),'attr' =>[],
                    'events' => ['formatter'=>"function(value){ return getTextValue(qtyRnd, value); }",'editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:qtyRnd}}"]],
                'rndValue'=> ['order'=>60,'label'=>$this->lang['rnd_value'], 'attr'=>['align'=>'center', 'size'=>'10'],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'price'   => ['order'=>70,'label'=>lang('price'), 'attr'=>['hidden'=>true, 'align'=>'right', 'size'=>'10'],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'margin'  => ['order'=>80,'label'=>lang('margin'),'attr'=>['hidden'=>true, 'align'=>'right', 'size'=>'10']]]];
    }

    /**
     * Grid structure for selling in quantity units, 10/pkg, 100/box, etc.
     * @param string $name - DOM field name
     * @param boolean $locked - [default true] leave unlocked if no journal activity has been entered for this sku
     * @return string - grid structure
     */
    private function dgSellQtys($name)
    {
        $data = ['id'=>$name, 'type'=>'edatagrid',
            'attr'   => ['pagination'=>false, 'toolbar'=>"#{$name}Toolbar"],
            'events' => ['data'      =>'invSellPkgData'], // doesn't work: 'onLoadSuccess'=>"function(data) { this.datagrid('resize'); }"
            'source' => ['actions'=>['newItem'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> ['id'=>['order'=>0,'attr'=>['hidden'=>true]],
                'action'=> ['order'=>1,'label'=>lang('action'),
                    'events' => ['formatter'=> "function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'    => ['order'=>80,'icon'=>'trash', 'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'code'  => ['order'=>20,'label'=>'Code (2 Char)','attr'=>['editor'=>'text','sortable'=>true,'resizable'=>true]],
                'label' => ['order'=>30,'label'=>lang('label'),  'attr'=>['editor'=>'text','sortable'=>true,'resizable'=>true]],
                'qty'   => ['order'=>40,'label'=>lang('qty'),    'attr'=>['editor'=>'text','value'=>1,'resizable'=>true,'align'=>'right']],
                'weight'=> ['order'=>50,'label'=>lang('weight'), 'attr'=>['editor'=>'text','resizable'=>true,'align'=>'right']],
                'price' => ['order'=>60,'label'=>lang('price'),  'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatCurrency(value); }",'editor'=>"{type:'numberbox'}"]]]];
        return $data;
    }

    /**
     * Decodes the price sheet settings for quantity based pricing and returns array of values for datagrid display
     * @param string $prices - encoded price value
     * @return array - ready to display in datagrid
     */
    protected function getPrices($prices='')
    {
        msgDebug("\nWorking with price string: $prices");
        $price_levels = explode(';', $prices);
        $arrData = [];
        for ($i=0; $i<sizeof($price_levels); $i++) {
            $level_info = explode(':', $price_levels[$i]);
            $arrData[] = [
                'price'   => isset($level_info[0]) ? $level_info[0] : 0,
                'qty'     => isset($level_info[1]) ? $level_info[1] : ($i+1),
                'source'  => isset($level_info[2]) ? $level_info[2] : '1',
                'adjType' => isset($level_info[3]) ? $level_info[3] : '',
                'adjValue'=> isset($level_info[4]) ? $level_info[4] : 0,
                'rndType' => isset($level_info[5]) ? $level_info[5] : '',
                'rndValue'=> isset($level_info[6]) ? $level_info[6] : 0];
        }
        return $arrData;
    }
}
