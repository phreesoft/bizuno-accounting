<?php
/*
 * Module Inventory main functions
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
 * @version    6.x Last Update: 2024-01-29
 * @filesource /controllers/inventory/main.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/functions.php', 'inventoryProcess', 'function');

class inventoryMain
{
    public $moduleID = 'inventory';
    public $pageID   = 'main';

    function __construct()
    {
        $this->lang          = getLang($this->moduleID);
        $this->percent_diff  = 0.10; // the percentage differnece from current value to notify for adjustment
        $this->months_of_data= 12;   // valid values are 1, 3, 6, or 12
        $this->med_avg_diff  = 0.25; // the maximum percentage difference from the median and average, for large swings
        $this->isolate_cogs  = getModuleCache('phreebooks', 'settings', 'general', 'isolate_stores') ? true : false;
        $defaults = [
            'sales'   => getModuleCache('phreebooks', 'chart', 'defaults', getDefaultCurrency())[30],
            'stock'   => getModuleCache('phreebooks', 'chart', 'defaults', getDefaultCurrency())[4],
            'nonstock'=> getModuleCache('phreebooks', 'chart', 'defaults', getDefaultCurrency())[34],
            'cogs'    => getModuleCache('phreebooks', 'chart', 'defaults', getDefaultCurrency())[32],
            'method'  => 'f'];
        $inventoryTypes = [
            'si' => ['id'=>'si','text'=>lang('inventory_inventory_type_si'),'hidden'=>0,'tracked'=>1,'order'=>10,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Stock Item
            'sr' => ['id'=>'sr','text'=>lang('inventory_inventory_type_sr'),'hidden'=>0,'tracked'=>1,'order'=>15,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Serialized
            'ma' => ['id'=>'ma','text'=>lang('inventory_inventory_type_ma'),'hidden'=>0,'tracked'=>1,'order'=>25,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Assembly
            'sa' => ['id'=>'sa','text'=>lang('inventory_inventory_type_sa'),'hidden'=>0,'tracked'=>1,'order'=>30,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Serialized Assembly
            'ns' => ['id'=>'ns','text'=>lang('inventory_inventory_type_ns'),'hidden'=>0,'tracked'=>0,'order'=>35,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Non-stock
            'lb' => ['id'=>'lb','text'=>lang('inventory_inventory_type_lb'),'hidden'=>0,'tracked'=>0,'order'=>40,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Labor
            'sv' => ['id'=>'sv','text'=>lang('inventory_inventory_type_sv'),'hidden'=>0,'tracked'=>0,'order'=>45,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Service
            'sf' => ['id'=>'sf','text'=>lang('inventory_inventory_type_sf'),'hidden'=>0,'tracked'=>0,'order'=>50,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Flat Rate Service
            'ci' => ['id'=>'ci','text'=>lang('inventory_inventory_type_ci'),'hidden'=>0,'tracked'=>0,'order'=>55,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Charge
            'ai' => ['id'=>'ai','text'=>lang('inventory_inventory_type_ai'),'hidden'=>0,'tracked'=>0,'order'=>60,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Activity
            'ds' => ['id'=>'ds','text'=>lang('inventory_inventory_type_ds'),'hidden'=>0,'tracked'=>0,'order'=>65,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Description
            'ia' => ['id'=>'ia','text'=>lang('inventory_inventory_type_ia'),'hidden'=>1,'tracked'=>1,'order'=>99,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>false,'method'=>false], // Assembly Part
            'mi' => ['id'=>'mi','text'=>lang('inventory_inventory_type_mi'),'hidden'=>1,'tracked'=>1,'order'=>99,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>false,'method'=>false]]; // Master Stock Sub Item
        $this->inventoryTypes = array_merge_recursive($inventoryTypes, getModuleCache('inventory', 'phreebooks'));
        $this->dbDefault = [
            'id'           => 0,
            'store_id'     => 0,
            'gl_sales'     => getModuleCache('inventory', 'settings', 'phreebooks', 'sales_si'),
            'gl_inv'       => getModuleCache('inventory', 'settings', 'phreebooks', 'inv_si'),
            'gl_cogs'      => getModuleCache('inventory', 'settings', 'phreebooks', 'cogs_si'),
            'cost_method'  => getModuleCache('inventory', 'settings', 'phreebooks', 'method_si'),
            'tax_rate_id_v'=> getModuleCache('inventory', 'settings', 'general', 'tax_rate_id_v'),
            'tax_rate_id_c'=> getModuleCache('inventory', 'settings', 'general', 'tax_rate_id_c')];
        if (validateSecurity($this->moduleID, 'inv_mgr', 5, false) && !defined('BIZ_RULE_ASSY_UNLOCK') ) { define('BIZ_RULE_ASSY_UNLOCK', true); }
    }

    /**
     * Main entry point for inventory module
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 1)) { return; }
        $title = sprintf(lang('tbd_manager'),lang('gl_acct_type_4'));
        $layout = array_replace_recursive($layout, viewMain(), ['title'=>$title,
            'divs'     => [
                'invMgr' => ['order'=>50,'type'=>'accordion','key' =>'accInventory']],
            'accordion'=> ['accInventory'=>['divs'=>[
                'divInventoryManager'=> ['order'=>30,'label'=>$title,         'type'=>'datagrid','key' =>'manager'],
                'divInventoryDetail' => ['order'=>70,'label'=>lang('details'),'type'=>'html',    'html'=>'&nbsp;']]]],
            'datagrid' => ['manager'=>$this->dgInventory('dgInventory', 'none', $security)],
            'jsReady'  =>['init'=>"bizFocus('search', 'dgInventory');"]]);
    }

    /**
     * Lists inventory rows for the manager grid filtered by users request
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 1)) { return; }
        $rID   = clean('rID',   'integer', 'get');
        $filter= clean('filter',['format'=>'text', 'default'=>'none'], 'get');
        $_POST['search']= getSearch();
        $_POST['rows']  = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'get');
        msgDebug("\n ready to build inventory datagrid, security = $security");
        $structure = $this->dgInventory('dgInventory', $filter, $security);
        if ($rID) { $structure['source']['filters']['rID'] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."inventory.id=$rID"]; }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$structure]]);
    }

    /**
     * Saves the users filter settings in cache
     */
    private function managerSettings()
    {
        $data = ['path'=>'inventory', 'values'=>  [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'method'=>'request'],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX."inventory.sku"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'f0',    'clean'=>'char',   'method'=>'request','default'=>'y'],
            ['index'=>'search','clean'=>'text',   'default'=>''],
        ]];
        if (clean('clr', 'boolean', 'get')) { clearUserCache($data['path']); }
        $this->defaults = updateSelection($data);
    }

    /**
     * Generates the grid structure for managing bills of materials
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerBOM(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 1, false)) { return; }
        $rID     = clean('rID', 'integer', 'get');
        $assyData= "var assyData = ".json_encode(['total'=>0,'rows'=>[]]).";";
        $locked  = false;
        if (!empty($rID)) {
            $sku     = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
            $locked  = defined('BIZ_RULE_ASSY_UNLOCK') && !empty(BIZ_RULE_ASSY_UNLOCK) ? false : dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='$sku'");
            $tmp = [];
            if (!isset($_GET['bID'])) { $_GET['bID'] = -1; } // get values for all stores if specific store not requested
            compose($this->moduleID, $this->pageID, 'managerBOMList', $tmp);
            $assyData= "var assyData = ".json_encode(['total'=>$tmp['content']['total'],'rows'=>$tmp['content']['rows']]).";";
        }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'    => ['divVendGrid'=> ['order'=>30,'type'=>'datagrid','key'=>'dgAssembly']],
            'datagrid'=> ['dgAssembly' => $this->dgAssembly('dgAssembly', $locked)],
            'jsHead'  => ['mgrBOMdata' => $assyData]]);
        if (!$locked) { $layout['jsReady']['mgrBOM'] = "jqBiz('#dgAssembly').edatagrid('addRow');"; }
    }

    /**
     * Lists the rows of a bill of materials
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerBOMList(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 1)) { return; }
        $skuID  = clean('rID', 'integer', 'get');
        $storeID= clean('bID', 'integer', 'get');
        if (empty($skuID)) { return msgAdd("Cannot process assy list, no SKU ID provided!"); }
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory_assy_list', "ref_id=$skuID");
        $total = 0;
        foreach ($result as $key => $row) {
            $crit = "sku='{$row['sku']}'";
            if ($storeID<>-1 && $this->isolate_cogs) { $crit .= " AND store_id=$storeID"; }
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['inventory_type','item_cost'], "sku='{$row['sku']}'");
            $result[$key]['qty_stock']   = invIsTracked($inv['inventory_type']) ? dbGetStoreQtyStock($row['sku'], $storeID) : '-'; // only show stock if tracked in inventory else '-'
            $result[$key]['item_cost']   = invIsTracked($inv['inventory_type']) ? $row['qty'] * $inv['itemCost'] : 0;
            $result[$key]['qty_required']= $row['qty'];
            $total += $row['qty'];
        }
        $footer = [['description'=>lang('total'), 'qty_required'=>viewFormat($total, 'precise')]];
        $layout = array_replace_recursive($layout, ['content'=>['total'=>sizeof($result),'rows'=>$result,'footer'=>$footer]]);
    }

    /**
     * Generates the inventory item edit structure
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function edit(&$layout=[])
    {
        $security    = validateSecurity('inventory', 'inv_mgr', 1);
        $rID         = clean('rID', 'integer', 'get');
        $cost_methods= [['id'=>'f','text'=>lang('inventory_cost_method_f')],['id'=>'l','text'=>lang('inventory_cost_method_l')],['id'=>'a','text'=>lang('inventory_cost_method_a')]];
        $structure   = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        $dbData      = $rID ? dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id='$rID'") : $this->dbDefault;
        dbStructureFill($structure, $dbData);
        $inv_type    = $structure['inventory_type']['attr']['value'];
        $fldProp     = ['id','qty','dg_assy','store_id','sku','inactive','description_short','upc_code','item_weight','lead_time'];
        $fldStatus   = ['qty_min','qty_restock','qty_stock','qty_po','qty_so','qty_alloc'];
        $fldImage    = ['image_with_path'];
        $fldCust     = ['description_sales','full_price','sale_price','tax_rate_id_c','price_sheet_c'];
        $fldVend     = ['description_purchase','item_cost','tax_rate_id_v','price_sheet_v','vendor_id'];
        $fldGL       = ['inventory_type','cost_method','gl_sales','gl_inv','gl_cogs'];
        // add additional fields
        $structure['dg_assy'] = ['attr'=>['type'=>'hidden']];
        if (validateSecurity('inventory', 'prices_c', 1, false)) {
            $structure['full_price']['break'] = false;
            $structure['show_prices_c'] = ['order'=>41,'icon'=>'price','label'=>lang('prices'),'events'=>['onClick'=>"jsonAction('inventory/prices/details&type=c&itemCost='+bizNumGet('item_cost')+'&fullPrice='+bizNumGet('full_price'), $rID);"]];
            $fldCust[] = 'show_prices_c';
        }
        if (validateSecurity('inventory', 'prices_v', 1, false)) {
            $structure['item_cost']['break'] = false;
            $structure['show_prices_v'] = ['order'=>41,'icon'=>'price','label'=>lang('prices'),'events'=>['onClick'=>"jsonAction('inventory/prices/details&type=v&itemCost='+bizNumGet('item_cost')+'&fullPrice='+bizNumGet('full_price'), $rID);"]];
            $fldVend[] = 'show_prices_v';
        }
        if (!empty($structure['image_with_path']['attr']['value'])) {
            $cleanPath = clean($structure['image_with_path']['attr']['value'], 'path_rel');
            if (!file_exists(BIZUNO_DATA."images/$cleanPath")) { $cleanPath = 'images/'; }
            $structure['image_with_path']['attr']['value'] = $cleanPath;
        }
        $imgSrc = $structure['image_with_path']['attr']['value'];
        $imgDir = dirname($structure['image_with_path']['attr']['value']).'/';
        if ($imgDir=='/') { $imgDir = getUserCache('imgMgr', 'lastPath', false , '').'/'; } // pull last folder from cache
        // complete the structure and validate
        $structure['qty']                          = ['order'=>1,'attr'=>['type'=>'hidden','value'=>1]];
        $structure['tax_rate_id_c']['label']       = lang('sales_tax');
        $structure['tax_rate_id_v']['label']       = lang('purchase_tax');
        $structure['qty_stock']['attr']['readonly']= 'readonly';
        $structure['qty_po']['attr']['readonly']   = 'readonly';
        $structure['qty_so']['attr']['readonly']   = 'readonly';
        $structure['qty_alloc']['attr']['readonly']= 'readonly';
        $structure['inventory_type']['values']     = array_values($this->inventoryTypes);
        $structure['cost_method']['values']        = $cost_methods;
        if ($rID) {
            $locked = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='".addslashes($structure['sku']['attr']['value'])."'"); // was inventory_history but if a SO exists will not lock sku field and can change
            $title  = $structure['sku']['attr']['value'].' - '.$structure['description_short']['attr']['value'];
            $structure['where_used']= ['order'=>11,'icon'=>'tools','label'=>lang('inventory_where_used'),'hidden'=>false,'events'=>['onClick'=>"jsonAction('inventory/main/usage', $rID);"]];
            $structure['sku']['break'] = false;
            $fldProp[] = 'where_used';
            if (in_array($inv_type, ['ma','sa']) ) {
                if (isset($structure['show_prices_v'])) { $structure['show_prices_v']['break'] = false; }
                $structure['assy_cost'] = ['order'=>42,'icon'=>'payment','label'=>lang('inventory_assy_cost'),'events'=>['onClick'=>"jsonAction('inventory/main/getCostAssy', $rID);"]];
                $fldVend[] = 'assy_cost';
            }
        } else { // set some defaults
            $locked = false;
            $title  = lang('new');
            $structure['inventory_type']['attr']['value']     = 'si'; // default to stock item
            $structure['inventory_type']['events']            = ['onChange'=>"jsonAction('inventory/main/detailsType', 0, this.value);"];
            $structure['inventory_type']['events']['onChange']= "var type=bizSelGet('inventory_type'); if (invTypeMsg[type]) alert(invTypeMsg[type]);";
        }
        if ($locked) { // check to see if some fields should be locked
            $structure['sku']['attr']['readonly']           = 'readonly';
            $structure['inventory_type']['attr']['readonly']= 'readonly'; // when disabled, data is not passed in POST
            $structure['cost_method']['attr']['readonly']   = 'readonly';
        }
        if (sizeof(getModuleCache('inventory', 'prices'))) {
            bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/prices.php', 'inventoryPrices');
            $tmp = new inventoryPrices();
            $structure['price_sheet_c']['values'] = $tmp->quantityList('c', true);
            $structure['price_sheet_v']['values'] = $tmp->quantityList('v', true);
        } else {
            unset($structure['price_sheet_c'], $structure['price_sheet_v']);
        }
        $structure['tax_rate_id_v']['values'] = viewSalesTaxDropdown('v', 'contacts');
        $structure['tax_rate_id_c']['values'] = viewSalesTaxDropdown('c', 'contacts');
        $structure['vendor_id']['values']     = dbBuildDropdown(BIZUNO_DB_PREFIX."contacts", "id", "short_name", "type='v' AND inactive<>'1' ORDER BY short_name", lang('none'));
        if ($rID && empty($this->inventoryTypes[$inv_type]['gl_inv'])) { $structure['gl_inv']['attr']['type'] = 'hidden'; }
        if ($rID && empty($this->inventoryTypes[$inv_type]['gl_cogs'])){ $structure['gl_cogs']['attr']['type']= 'hidden'; }
        if (sizeof(getModuleCache('phreebooks', 'currency', 'iso'))>1) {
            $structure['full_price']['label'].= ' ('.getDefaultCurrency().')';
            $structure['item_cost']['label'] .= ' ('.getDefaultCurrency().')';
        }
        $hideV= validateSecurity('phreebooks', 'j6_mgr', 1, false) ? false : true;
        $data = ['type'=>'divHTML',
            'divs'    => [
                'toolbar'=> ['order'=> 5,'type'=>'toolbar','key' =>'tbInventory'],
                'heading'=> ['order'=>10,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmInventory'],
                'tabs'   => ['order'=>50,'type'=>'tabs',   'key' =>'tabInventory'],
                'formEOF'=> ['order'=>85,'type'=>'html',   'html'=>'</form>']],
            'toolbars'=> ['tbInventory'=>['icons'=>[
                'save' => ['order'=>20,'hidden'=>$security >1?false:true,      'events'=>['onClick'=>"jqBiz('#frmInventory').submit();"]],
                'new'  => ['order'=>40,'hidden'=>$security >1?false:true,      'events'=>['onClick'=>"accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', 0);"]],
                'trash'=> ['order'=>80,'hidden'=>$rID&&$security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('inventory/main/delete', $rID);"]]]]],
            'tabs'    => ['tabInventory'=> ['divs'=>[
                'general' => ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genProp' => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'genProp'],
                    'genStat' => ['order'=>20,'type'=>'panel','classes'=>['block33'],'key'=>'genStat'],
                    'genImage'=> ['order'=>30,'type'=>'panel','classes'=>['block33'],'key'=>'genImage'],
                    'genCust' => ['order'=>40,'type'=>'panel','classes'=>['block33'],'key'=>'genCust'],
                    'genVend' => ['order'=>50,'type'=>'panel','classes'=>['block33'],'key'=>'genVend','hidden'=>$hideV],
                    'genGL'   => ['order'=>60,'type'=>'panel','classes'=>['block33'],'key'=>'genGL'],
                    'genAtch' => ['order'=>80,'type'=>'panel','classes'=>['block66'],'key'=>'genAtch']]],
                'movement'=> ['order'=>30,'label'=>lang('movement'),'hidden'=>$rID?false:true,'type'=>'html','html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=inventory/history/movement&rID=$rID'"]],
                'history' => ['order'=>35,'label'=>lang('history'), 'hidden'=>$rID?false:true,'type'=>'html','html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=inventory/history/historian&rID=$rID'"]]]]],
            'panels'  => [
                'genProp' => ['label'=>lang('properties'),                              'type'=>'fields','keys'=>$fldProp],
                'genStat' => ['label'=>lang('status'),                                  'type'=>'fields','keys'=>$fldStatus],
                'genImage'=> ['label'=>lang('current_image'),'options'=>['height'=>250],'type'=>'fields','keys'=>$fldImage],
                'genCust' => ['label'=>lang('details').' ('.lang('customers').')',      'type'=>'fields','keys'=>$fldCust],
                'genVend' => ['label'=>lang('details').' ('.lang('vendors').')',        'type'=>'fields','keys'=>$fldVend],
                'genGL'   => ['label'=>lang('details').' ('.lang('general_ledger').')', 'type'=>'fields','keys'=>$fldGL],
                'genAtch' => ['type'=>'attach','defaults'=>['path'=>getModuleCache($this->moduleID,'properties','attachPath'),'prefix'=>"rID_{$rID}_"]]],
            'forms'   => ['frmInventory'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=inventory/main/save"]]],
            'fields'  => $structure,
            'jsHead'  => ['invHead' => "var curIndex=undefined; var invTypeMsg=[]; curIndex=0;
function preSubmit() { bizGridSerializer('dgAssembly', 'dg_assy'); bizGridSerializer('dgVendors', 'invVendors'); return true; }"],
            'jsBody'  => ['init'=>"imgManagerInit('image_with_path', '$imgSrc', '$imgDir', ".json_encode(['style'=>"max-height:200px;max-width:100%;"]).");"],
            'jsReady' => ['init'=>"ajaxForm('frmInventory');\njqBiz('.products ul li:nth-child(3n+3)').addClass('last');"]];
        customTabs($data, 'inventory', 'tabInventory'); // add custom tabs
        if (in_array($data['fields']['inventory_type']['attr']['value'], ['ma','sa'])) { // assembly, add tab
            $data['tabs']['tabInventory']['divs']['bom'] = ['order'=>20,'label'=>lang('inventory_assy_list'),'type'=>'html','html'=>'',
                'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=inventory/main/managerBOM&rID=$rID'"]];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Lists the details of a given inventory item from the database table
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function detailsType(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 2)) { return; }
        $type = clean('data', 'text', 'get');
        if (!$type) { msgAdd("No Type passed!"); }
        msgDebug("\n Loading defaults for type = $type");
        $settings = getModuleCache('inventory', 'phreebooks');
        $data = [
            'sales' => isset($settings['sales_'.$type]) ? $settings['sales_'.$type]  : '',
            'inv'   => isset($settings['inv_'.$type])   ? $settings['inv_'.$type]    : '',
            'cogs'  => isset($settings['cog_'.$type])   ? $settings['cog_'.$type]    : '',
            'method'=> isset($settings['method_'.$type])? $settings['method_'.$type] : 'f'];
        $html  = "jqBiz('#gl_sales').val('".$data['sales']."');";
        $html .= "jqBiz('#gl_inv').val('".$data['inv']."');";
        $html .= "jqBiz('#gl_cogs').val('".$data['cogs']."');";
        $html .= "jqBiz('#cost_method').val('".$data['method']."');";
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval', 'actionData'=>$html]]);
    }

    /**
     * form builder - Merges 2 database inventory items to a single record
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function merge(&$layout=[])
    {
        $icnSave= ['icon'=>'save','label'=>lang('merge'),
            'events'=>['onClick'=>"jsonAction('$this->moduleID/main/mergeSave', jqBiz('#mergeSrc').val(), jqBiz('#mergeDest').val());"]];
        $props  = ['defaults'=>['callback'=>''],'attr'=>['type'=>'inventory']];
        $html   = "<p>".$this->lang['msg_inventory_merge_src'] ."</p><p>".html5('mergeSrc', $props)."</p>".
                  "<p>".$this->lang['msg_inventory_merge_dest']."</p><p>".html5('mergeDest',$props)."</p>".html5('icnMergeSave', $icnSave).
                  "<p>".$this->lang['msg_inventory_merge_note']."</p>";
        $data   = ['type'=>'popup','title'=>$this->lang['inventory_merge'],'attr'=>['id'=>'winMerge'],
            'divs'   => ['body'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>"bizFocus('mergeSrc');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Performs the merge of 2 inventory items in the db
     * @param array $layout - current working structure
     * @return modifed $layout
     */
    public function mergeSave(&$layout=[])
    {
        global $io;
        if (!$security = validateSecurity('inventory', 'inv_mgr', 5)) { return; }
        $srcID   = clean('rID', 'integer', 'get'); // record ID to merge
        $destID  = clean('data','integer', 'get'); // record ID to keep
        if (empty($srcID) || empty($destID)) { return msgAdd("Bad SKU IDs, Source ID = $srcID and Destination ID = $destID"); }
        if ($srcID == $destID)               { return msgAdd("Source and destination SKU cannot be the same!"); }
        $srcSKU  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$srcID");
        $destSKU = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$destID");
        msgAdd(lang('Inventory merge stats').':', 'info');
        msgDebug("\nmergeSave with src SKU = $srcSKU (ID=$srcID) and destSKU = $destSKU (destID=$destID)");
        dbTransactionStart();
        // SKU based changes
        msgDebug("\nReady to write table journal_item to merge from SKU: $srcSKU => $destSKU");
        $jrnlCnt = dbWrite(BIZUNO_DB_PREFIX.'journal_item',       ['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("journal_item table SKU changes: $jrnlCnt;", 'info');
        $AssyCnt = dbWrite(BIZUNO_DB_PREFIX.'inventory_assy_list',['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("inventory_assy_list table SKU changes: $AssyCnt;",'info');
        $histCnt = dbWrite(BIZUNO_DB_PREFIX.'inventory_history',  ['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("inventory_history table SKU changes: $histCnt;",  'info');
        $owedCnt = dbWrite(BIZUNO_DB_PREFIX.'journal_cogs_owed',  ['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("journal_cogs_owed table SKU changes: $owedCnt;",  'info');
        // SKU ID based changes
        msgDebug("\nReady to write table inventory_prices to merge from SKU ID: $srcID => $destID");
        $prceCnt = dbWrite(BIZUNO_DB_PREFIX.'inventory_prices',   ['inventory_id'=>$destID], 'update', "inventory_id=$srcID");
        msgAdd("inventory_prices table SKU changes: $prceCnt;", 'info');
        // Move the main image if not existing at dest
        $srcImg  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "id=$srcID");
        $destImg = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "id=$destID");
        if (empty($destImg && !empty($srcImg))) { // move image to dest and delete from src
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['image_with_path'=>$srcImg], 'update', "id=$destID");
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['image_with_path'=>''],      'update', "id=$srcID");
        }
        // Merge the attachments
        msgDebug("\nMoving file at path: ".getModuleCache($this->moduleID, 'properties', 'attachPath')." from rID_{$srcID}_ to rID_{$destID}_");
        $io->fileMove(getModuleCache($this->moduleID, 'properties', 'attachPath'), "rID_{$srcID}_", "rID_{$destID}_");
        // fix the qty's
        $stks = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['qty_stock','qty_po','qty_so','qty_alloc'], "id=$srcID");
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET qty_stock=qty_stock+{$stks['qty_stock']}, qty_po=qty_po+{$stks['qty_po']}, qty_so=qty_so+{$stks['qty_so']}, qty_alloc=qty_alloc+{$stks['qty_alloc']} WHERE id=$destID");
        dbTransactionCommit();
        // Wrap it up
        msgAdd("Finished Merging SKU = $srcSKU (ID=$srcID) -> destSKU = $destSKU (destID=$destID)", 'info');
        msgLog(lang('inventory').'-'.lang('merge').": $srcSKU => $destSKU");
        $data    = ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winMerge'); bizGridReload('dgInventory');"],
            'dbAction'=> ['inventory'=>"DELETE FROM ".BIZUNO_DB_PREFIX."inventory WHERE id=$srcID"]];
        $layout  = array_replace_recursive($layout, $data);
    }

    /**
     * Generates the structure for inventory properties popup used in PhreeBooks
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function properties(&$layout=[])
    {
        $sku = clean('sku', 'text', 'get');
// @TODO  - BOF Remove after 10/1/2022
        if (empty($sku)) { $sku = clean('data', 'text', 'get'); } // old way - deprecated in common.js
// EOF Remove after 10/1/2022
        if (empty($sku)) { return msgAdd("Bad sku passed!"); }
        $qty = clean('qty', 'float','get');
        if (empty($qty)) { $qty = 1; }
        $_GET['rID'] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='$sku'");
        compose('inventory', 'main', 'edit', $layout);
        if (!empty($layout['fields']['assy_cost']['events']['onClick'])) {
            $event = str_replace('getCostAssy', "getCostAssy&qty=$qty", $layout['fields']['assy_cost']['events']['onClick']);
            $layout['fields']['assy_cost']['events']['onClick'] = $event;
        }
        unset($layout['tabs']['tabInventory']['divs']['general']['divs']['genAtch']);
        unset($layout['divs']['toolbar'], $layout['divs']['formBOF'], $layout['divs']['formEOF']);
        unset($layout['toolbars'], $layout['forms'], $layout['jsHead'], $layout['jsReady']);
    }

    /**
     * Generates the inventory item save structure for recording user updates
     * @param array $layout - structure coming in
     * @param boolean $makeTransaction - [default true] set to false if the save is already a part of another transaction
     * @return modified structure
     */
    public function save(&$layout=[], $makeTransaction=true)
    {
        $type   = clean('inventory_type', ['format'=>'text','default'=>'si'], 'post');
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'inventory'));
        $values['image_with_path'] = clean('image_with_path', 'path_rel', 'post');
        if (!$security = validateSecurity('inventory', 'inv_mgr', isset($values['id']) && $values['id']?3:2)) { return; }
        $rID = isset($values['id']) && $values['id'] ? $values['id'] : 0;
        $dup = dbGetValue(BIZUNO_DB_PREFIX."inventory", 'sku', "sku='".addslashes($values['sku'])."' AND id<>$rID"); // check for duplicate sku's
        if ($dup) { return msgAdd(lang('error_duplicate_id')); }
        if (!$values['sku']) { return msgAdd($this->lang['err_inv_sku_blank']); }
        $readonlys = ['qty_stock','qty_po','qty_so','qty_alloc','creation_date','last_update','last_journal_date']; // some special processing
        foreach ($readonlys as $field) { unset($values[$field]); }
        if (!$rID) { $values['creation_date']= biz_date('Y-m-d h:i:s'); }
        else       { $values['last_update']  = biz_date('Y-m-d h:i:s'); }
        if ($makeTransaction) { dbTransactionStart(); } // START TRANSACTION (needs to be here as we need the id to create links
        $result = dbWrite(BIZUNO_DB_PREFIX."inventory", $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $_POST['id'] = $result; }
        $dgAssy = clean('dg_assy', 'json', 'post');
        if (!empty($dgAssy)) { $this->saveBOM($rID, $type, $values['sku'], $dgAssy); } // handle assemblies
        if ($makeTransaction) { dbTransactionCommit(); }
        $io = new \bizuno\io();
        if ($io->uploadSave('file_attach', getModuleCache('inventory', 'properties', 'attachPath')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['attach'=>'1'], 'update', "id=$rID");
        }
        msgAdd(lang('msg_database_write'), 'success');
        msgLog(lang('inventory').'-'.lang('save')." - ".$values['sku']." (rID=$rID)");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accInventory').accordion('select', 0); bizGridReload('dgInventory'); jqBiz('#divInventoryDetail').html('&nbsp;');"]]);
    }

    /**
     * Saves a bill of materials for inventory type AS, MA
     * @param integer $rID - inventory database record id
     * @param string $type - inventory type
     * @param type $sku - item SKU
     * @param type $dgData - JSON encoded list of inventory items that make up the BOM
     * @return boolean null, BOM is not generated in inventory type is not equal to ma or as
     */
    private function saveBOM($rID, $type, $sku, $dgData)
    {
        if (!in_array($type, ['ma', 'sa'])) { return; }
        $locked = defined('BIZ_RULE_ASSY_UNLOCK') && !empty(BIZ_RULE_ASSY_UNLOCK) ? false : dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='$sku'");
        if ($locked) { return; } // journal entry present , not ok to save
        if (is_array($dgData) && sizeof($dgData) > 0) {
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_assy_list WHERE ref_id=$rID");
            foreach ($dgData['rows'] as $row) {
                if (empty($row['sku'])) { continue; }
                $bom_array = ['ref_id'=>$rID, 'sku'=>$row['sku'], 'description'=>$row['description'], 'qty'=>$row['qty']];
                dbWrite(BIZUNO_DB_PREFIX."inventory_assy_list", $bom_array);
            }
        }
    }

    /**
     * Structure for renaming inventory items
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function rename(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 3)) { return; }
        $rID  = clean('rID', 'integer','get');
        $sku  = dbGetValue(BIZUNO_DB_PREFIX."inventory", 'sku', "id=$rID");
        $GLOBALS['invRenameNewSKU'] = $newSKU = clean('data', 'text', 'get');
        $GLOBALS['invRenameOldSKU'] = $oldSKU = $sku;
        // make sure new SKU is not null
        if (strlen($newSKU) < 1) { return msgAdd($this->lang['err_inv_sku_blank']); }
        // check for duplicate skus
        $found= dbGetValue(BIZUNO_DB_PREFIX."inventory", 'id', "sku='$newSKU'");
        if ($found) { return msgAdd(lang('error_duplicate_id')); }
        $data = ['content'=> ['action'=>'eval', 'actionData'=> "bizGridReload('dgInventory');"],
            'dbAction'    => [
                "inventory"          => "UPDATE ".BIZUNO_DB_PREFIX."inventory           SET sku='".addslashes($newSKU)."' WHERE id='$rID'",
                "inventory_assy_list"=> "UPDATE ".BIZUNO_DB_PREFIX."inventory_assy_list SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'",
                "inventory_history"  => "UPDATE ".BIZUNO_DB_PREFIX."inventory_history   SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'",
                "journal_cogs_owed"  => "UPDATE ".BIZUNO_DB_PREFIX."journal_cogs_owed   SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'",
                "journal_item"       => "UPDATE ".BIZUNO_DB_PREFIX."journal_item        SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'"]];
        msgLog(lang('inventory').' '.lang('rename')." - $oldSKU ($rID) -> $newSKU");
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Structure for copying inventory items
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function copy(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 2)) { return; }
        $rID    = clean('rID', 'integer', 'get');
        $newSKU = clean('data','text', 'get'); // new sku
        if (!$newSKU) { return msgAdd($this->lang['err_inv_sku_blank']); }
        $sku    = dbGetRow(BIZUNO_DB_PREFIX."inventory", "id=$rID");
        $oldSKU = $sku['sku'];
        // check for duplicate skus
        $found = dbGetValue(BIZUNO_DB_PREFIX."inventory", 'id', "sku='$newSKU'");
        if ($found) { return msgAdd(lang('error_duplicate_id')); }
        // clean up the fields (especially the system fields, retain the custom fields)
        foreach ($sku as $key => $value) {
            switch ($key) {
                case 'sku':          $sku[$key] = $newSKU; break; // set the new sku
                case 'creation_date':
                case 'last_update':  $sku[$key] = biz_date('Y-m-d H:i:s'); break;
                case 'id':    // Remove from write list fields
                case 'attach':
                case 'last_journal_date':
                case 'item_cost':
                case 'upc_code':
                case 'image_with_path':
                case 'qty_stock':
                case 'qty_po':
                case 'qty_so':
                case 'qty_alloc': unset($sku[$key]); break;
                default:
            }
        }
        $nID = dbWrite(BIZUNO_DB_PREFIX."inventory", $sku);
        if ($sku['inventory_type'] == 'ma' || $sku['inventory_type'] == 'sa') { // copy assembly list if it's an assembly
            $result = dbGetMulti(BIZUNO_DB_PREFIX."inventory_assy_list", "ref_id = '$rID'");
            foreach ($result as $value) {
                $sqlData = [
                    'ref_id'      => $nID,
                    'sku'         => $value['sku'],
                    'description' => $value['description'],
                    'qty'         => $value['qty'],
                    ];
                dbWrite(BIZUNO_DB_PREFIX."inventory_assy_list", $sqlData);
            }
        }
        $result = dbGetMulti(BIZUNO_DB_PREFIX."inventory_prices", "inventory_id=$rID AND contact_id=0");
        foreach ($result as $value) { // just copy over the price sheets by SKU, skip byContact and others
            unset($value['id']);
            $value['inventory_id'] = $nID;
            dbWrite(BIZUNO_DB_PREFIX."inventory_prices", $value);
        }
        msgLog(lang('inventory').'-'.lang('copy')." - $oldSKU => $newSKU");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"bizGridReload('dgInventory'); accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', $nID);"],
            ]);
    }

    /**
     * Structure for deleting inventory items
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 4)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('Bad Record ID!'); }
        $action= "jqBiz('#accInventory').accordion('select', 0); bizGridReload('dgInventory'); jqBiz('#divInventoryDetail').html('&nbsp;');";
        $item  = dbGetRow(BIZUNO_DB_PREFIX."inventory", "id=$rID");
        if (!$item) { return ['content'=>['action'=>'eval','actionData'=>$action]]; }
        $sku   = clean($item['sku'], 'text');
        // Check to see if this item is part of an assembly
        $block0= dbGetValue(BIZUNO_DB_PREFIX."inventory_assy_list", 'id', "sku='$sku'");
        if ($block0) { return msgAdd($this->lang['err_inv_delete_assy']); }
        $block1= dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'id', "sku='$sku'");
        if ($sku && $block1 && strpos(COG_ITEM_TYPES, $item['inventory_type']) !== false) { return msgAdd($this->lang['err_inv_delete_gl_entry']); }
        $data  = ['content' => ['action'=>'eval','actionData'=>$action],
            'dbAction'=> [
                "inventory"          => "DELETE FROM ".BIZUNO_DB_PREFIX."inventory WHERE id='$rID'",
                "inventory_prices"   => "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_prices WHERE inventory_id='$rID'",
                "inventory_assy_list"=> "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_assy_list WHERE ref_id='$rID'"]];
        $files = glob(getModuleCache('inventory', 'properties', 'attachPath')."rID_{$rID}_*.*");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } } // remove attachments
        msgLog(lang('inventory').' '.lang('delete')." - $sku ($rID)");
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Calculates the cost of building an assembly
     * @return entry is made in the message queue with current assembly cost
     */
    public function getCostAssy($rID=0)
    {
        global $currencies;
        if (!$rID) { $rID = clean('rID', 'integer', 'get'); }
        $cost = dbGetInvAssyCost($rID);
        $currencies = (object)['iso'=>getDefaultCurrency(), 'rate'=>1];
        msgAdd(sprintf($this->lang['msg_inventory_assy_cost'], viewFormat($cost, 'currency')), 'caution');
    }

    /**
     * Inventory grid structure
     * @param string $name - DOM field name
     * @param string $filter - control to limit filtering by inventory type
     * @param integer $security - users security level
     * @return string - grid structure
     */
    private function dgInventory($name, $filter='none', $security=0)
    {
        $this->managerSettings();
        $yes_no_choices = [['id'=>'a','text'=>lang('all')],['id'=>'y','text'=>lang('active')],['id'=>'n','text'=>lang('inactive')]];
        switch ($this->defaults['f0']) { // clean up the filter
            default:
            case 'a': $f0_value = ""; break;
            case 'y': $f0_value = "inactive='0'"; break;
            case 'n': $f0_value = "inactive='1'"; break;
        }
        $data = ['id'=> $name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'     => ['idField'=>'id', 'toolbar'=>"#{$name}Toolbar", 'url'=>BIZUNO_AJAX."&bizRt=inventory/main/managerRows"],
            'events'   => [
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".jsLang('details')."', 'inventory/main/edit', rowData.id); }",
                'rowStyler'    => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; }}"],
            'footnotes'=> ['codes'=>jsLang('color_codes').': <span class="row-inactive">'.jsLang('inactive').'</span>'],
            'source'   => [
                'tables' => ['inventory' => ['table'=>BIZUNO_DB_PREFIX."inventory"]],
                'search' => [BIZUNO_DB_PREFIX.'inventory.id',BIZUNO_DB_PREFIX.'inventory.sku','description_short','description_purchase','description_sales','upc_code'],
                'actions' => [
                    'newInventory'=>['order'=>10,'icon'=>'new',  'hidden'=>$security>1?false:true,'events'=>['onClick'=>"accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', 0);"]],
                    'mergeInv'    =>['order'=>30,'icon'=>'merge','hidden'=>$security>4?false:true,'events'=>['onClick'=>"jsonAction('inventory/main/merge', 0);"]],
                    'clrSearch'   =>['order'=>85,'icon'=>'clear','events'=>['onClick'=>"bizSelSet('f0', 'y'); bizTextSet('search', ''); ".$name."Reload();"]]],
                'filters'=> [
                    'f0'     => ['order'=>10,'label'=>lang('status'),'break'=>true,'sql'=>$f0_value,'values'=> $yes_no_choices,'attr'=>['type'=>'select','value'=>$this->defaults['f0']]],
                    'search' => ['order'=>90,'attr'=>['value'=>$this->defaults['search']]]],
                'sort' => ['s0'=>  ['order'=>10, 'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns'  => [
                'id'            => ['order'=> 0,'field'=>BIZUNO_DB_PREFIX.'inventory.id',      'attr'=>['hidden'=>true]],
                'inactive'      => ['order'=> 0,'field'=>BIZUNO_DB_PREFIX.'inventory.inactive','attr'=>['hidden'=>true]],
                'attach'        => ['order'=> 0,'field'=>'attach',            'attr'=>['hidden'=>true]],
                'inventory_type'=> ['order'=> 0,'field'=>'inventory_type',    'attr'=>['hidden'=>true]],
                'action' => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'prices'=> ['order'=>20,'icon'=>'price',  'events'=>['onClick'=>"jsonAction('inventory/prices/details&type=c', idTBD);"]],
                        'edit'  => ['order'=>30,'icon'=>'edit',   'events'=>['onClick'=>"accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', idTBD);"]],
                        'rename'=> ['order'=>40,'icon'=>'rename', 'hidden'=>$security>2?false:true,'events'=>['onClick'=>"var title=prompt('".$this->lang['msg_sku_entry_rename']."'); if (title!=null) jsonAction('inventory/main/rename', idTBD, title);"]],
                        'copy'  => ['order'=>50,'icon'=>'copy',   'hidden'=>$security>2?false:true,'events'=>['onClick'=>"var title=prompt('".$this->lang['msg_sku_entry_copy']."'); if (title!=null) jsonAction('inventory/main/copy', idTBD, title);"]],
                        'chart' => ['order'=>60,'icon'=>'mimePpt','label'=>lang('sales'),'events'=>['onClick'=>"windowEdit('inventory/tools/chartSales&rID=idTBD', 'myInvChart', '&nbsp;', 600, 500);"]],
                        'trash' => ['order'=>90,'icon'=>'trash',  'hidden'=>$security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('inventory/main/delete', idTBD);"]],
                        'attach'=> ['order'=>95,'icon'=>'attachment','display'=>"row.attach=='1'"]]],
                'sku'              => ['order'=>10,'field'=>BIZUNO_DB_PREFIX.'inventory.sku','label'=>pullTableLabel("inventory", 'sku'), 'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true]],
                'description_short'=> ['order'=>20,'field'=>'description_short','label'=>pullTableLabel("inventory", 'description_short'),'attr'=>['width'=>500,'sortable'=>true,'resizable'=>true]],
                'qty_stock'        => ['order'=>30,'field'=>'qty_stock','format'=>'number','label'=>pullTableLabel("inventory", 'qty_stock'),'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right']],
                'qty_po'           => ['order'=>40,'field'=>'qty_po',   'format'=>'number','label'=>pullTableLabel("inventory", 'qty_po'),   'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right']],
                'qty_so'           => ['order'=>50,'field'=>'qty_so',   'format'=>'number','label'=>pullTableLabel("inventory", 'qty_so'),   'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right']],
                'qty_alloc'        => ['order'=>60,'field'=>'qty_alloc','format'=>'number','label'=>pullTableLabel("inventory", 'qty_alloc'),'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right']]]];
        switch ($filter) {
            case 'stock': $data['source']['filters']['restrict'] = ['order'=>99, 'sql'=>"inventory_type in ('si','sr','ms','mi','ma')"]; break;
            case 'assy':  $data['source']['filters']['restrict'] = ['order'=>99, 'sql'=>"inventory_type in ('ma')"]; break;
            default:
        }
        return $data;
    }

    /**
     * Grid structure for assembly material lists
     * @param string $name - DOM field name
     * @param boolean $locked - [default true] leave unlocked if no journal activity has been entered for this sku
     * @return string - grid structure
     */
    private function dgAssembly($name, $locked=true)
    {
        $data = ['id'  => $name,
            'type'=> 'edatagrid',
            'attr'=> ['pagination'=>false, 'rownumbers'=>true, 'singleSelect'=>true, 'toolbar'=>"#{$name}Toolbar", 'idField'=>'id'],
            'events' => ['data'=>'assyData',
                'onClickRow' => "function(rowIndex, row) { curIndex = rowIndex; }",
                'onBeginEdit'=> "function(rowIndex, row) { curIndex = rowIndex; }",
                'onDestroy'  => "function(rowIndex, row) { curIndex = undefined; }",
                'onAdd'      => "function(rowIndex, row) { curIndex = rowIndex; }"],
            'source' => ['actions'=>['newAssyItem'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns' => ['id'=>['order'=>0,'attr'=>['hidden'=>true]],
                'action'     => ['order'=>1,'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'settings'=> ['order'=>10,'icon'=>'settings','events'=>['onClick'=>"inventoryProperties(bizDGgetRow('$name', bizDGgetIndex('$name')));"]],
                        'trash'   => ['order'=>80,'icon'=>'trash',   'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'sku'=> ['order'=>30,'label'=>lang('sku'),'attr'=>['sortable'=>true,'resizable'=>true,'align'=>'center'],
                    'events' => ['editor'=>"{type:'combogrid',options:{ url:'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&clr=1',
                        width:150, panelWidth:320, delay:500, idField:'sku', textField:'sku', mode:'remote',
                        onClickRow: function (idx, cgData) {
                            bizNumEdSet('$name', curIndex, 'qty', 1);
                            bizTextEdSet('$name', curIndex, 'description', cgData.description_short); },
                        columns:[[{field:'sku',              title:'".lang('sku')."',        width:100},
                                  {field:'description_short',title:'".lang('description')."',width:200}]]
                    }}"]],
                'description'=> ['order'=>40,'label'=>lang('description'),'attr'=>['editor'=>'text','sortable'=>true,'resizable'=>true]],
                'qty'        => ['order'=>50,'label'=>lang('qty_needed'), 'attr'=>['value'=>1,'resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatNumber(value); }",'editor'=>"{type:'numberbox'}"]],
                'item_cost'  => ['order'=>60,'label'=>lang('cost'), 'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatCurrency(value); }"]],
                'qty_stock'  => ['order'=>80,'label'=>pullTableLabel("inventory", 'qty_stock'),'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatNumber(value); }"]],
                'qty_alloc'  => ['order'=>90,'label'=>pullTableLabel("inventory", 'qty_alloc'),'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatNumber(value); }"]]]];
        if ($locked) {
            unset($data['columns']['action']['actions']['trash'], $data['columns']['sku']['events']['editor'], $data['columns']['description']['attr']['editor']);
            unset($data['columns']['qty']['events']['editor'],    $data['source']);
        }
        return $data;
    }

    /**
     * Grid structure for PO/SO history with options
     * @param type $jID
     * @return type
     */
    private function dgJ04J10($jID=10, $sku='')
    {
        $hide_cost= validateSecurity('phreebooks', "j6_mgr", 1, false) ? false : true;
        $stores   = sizeof(getModuleCache('bizuno', 'stores')>1) ? false : true;
        if ($jID==4) {
            $props = ['name'=>'dgJ04','title'=>lang('open_journal_4'), 'data'=>'dataPO'];
            $label = lang('fill_purchase');
            $invID = 6;
            $icon  = 'sales';
            $hide  = validateSecurity('phreebooks', "j4_mgr", 1, false);
        } else { // jID=10
            $props = ['name'=>'dgJ10','title'=>lang('open_journal_10'),'data'=>'dataSO'];
            $label = lang('fill_sale');
            $invID = 12;
            $icon  = 'purchase';
            $hide  = validateSecurity('phreebooks', "j10_mgr", 1, false);
        }
        return ['id' => $props['name'],
            'attr'   => ['title'=>$props['title'], 'pagination'=>false, 'idField'=>'id'],
            'events' => ['url'=>"'".BIZUNO_AJAX."&bizRt=inventory/history/historyRows&jID=$jID&sku=$sku'"],
            'columns'=> ['id'=> ['attr'=>['hidden'=>true]],
                'action'     => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>60,'hidden'=>$hide_cost?true:false],
                    'events' => ['formatter'=>"function(value,row,index) { return {$props['name']}Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'  => ['order'=>20,'icon'=>'edit',  'label'=>lang('edit'),          'hidden'=>$hide>0?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');"]],
                        'toggle'=> ['order'=>40,'icon'=>'toggle','label'=>lang('toggle_status'), 'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"jsonAction('phreebooks/main/toggleWaiting&jID=$jID&dgID={$props['name']}', idTBD);"]],
                        'dates' => ['order'=>50,'icon'=>'date',  'label'=>lang('delivery_dates'),'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"windowEdit('phreebooks/main/deliveryDates&rID=idTBD', 'winDelDates', '".lang('delivery_dates')."', 500, 400);"]],
                        'fill'  => ['order'=>80,'icon'=>$icon,   'label'=>$label,                'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=$invID&bizAction=inv');"]]]],
                'invoice_num'=> ['order'=>20,'label'=>lang('journal_main_invoice_num', $jID),'attr'=>['width'=>100,'resizable'=>true]],
                'store_id'   => ['order'=>30,'label'=>lang('contacts_short_name_b'),   'attr'=>['width'=>100,'resizable'=>true,'hidden'=>$stores]],
                'rep_id'     => ['order'=>30,'label'=>lang('contacts_rep_id_c'),       'attr'=>['width'=>100,'resizable'=>true,'align'=>'center']],
                'post_date'  => ['order'=>40,'label'=>lang('post_date'),               'attr'=>['width'=>150,'resizable'=>true,'sortable'=>true,'align'=>'center']],
                'qty'        => ['order'=>50,'label'=>lang('balance'),                 'attr'=>['width'=>100,'resizable'=>true,'align'=>'center']],
                'date_1'     => ['order'=>60,'label'=>jsLang('journal_item_date_1',10),'attr'=>['width'=>150,'resizable'=>true,'sortable'=>true,'align'=>'center'],
                    'events'=>['styler'=>"function(value,row,index) { if (row.waiting==1) { return {style:'background-color:yellowgreen'}; } }"]]]];
    }

    private function dgJ06J12($jID=12)
    {
        if ($jID==6) {
            $props = ['name'=>'dgJ06','title'=>sprintf(lang('tbd_history'), lang('journal_main_journal_id', '6')), 'data'=>'dataJ6'];
            $label = jsLang('cost');
        } else {
            $props = ['name'=>'dgJ12','title'=>sprintf(lang('tbd_history'), lang('journal_main_journal_id', '12')),'data'=>'dataJ12'];
            $label = jsLang('sales');
        }
        return ['id' => $props['name'],
            'attr'   => ['title'=>$props['title'], 'pagination'=>false, 'width'=>350],
            'events' => ['data' =>$props['data']],
            'columns'=> [
                'year' => ['order'=>20,'label'=>lang('year'), 'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'month'=> ['order'=>30,'label'=>lang('month'),'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'qty'  => ['order'=>40,'label'=>lang('qty'),  'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'total'=> ['order'=>50,'label'=>$label,       'attr'=>['width'=>200,'align'=>'right','resizable'=>true],'events'=>['formatter'=>"function(value) { return formatCurrency(value); }"]]]];
    }

    /**
     * Generates the Where Used? pop up window displaying where a sku is used in other sku's
     * @return usage statistics added to message queue
     */
    public function usage()
    {
        if (!$security = validateSecurity('inventory', 'inv_mgr', 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
        if (empty($sku)) { return msgAdd("Cannot find sku!"); }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_assy_list', "sku='$sku'", 'sku');
        if (sizeof($result)==0) { return msgAdd("Cannot find any usage!"); }
        $output = [];
        foreach ($result as $row) {
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['sku', 'description_short'], "id={$row['ref_id']}");
            if (!$inv) { $this->cleanOrphan($row['ref_id']); }
            else       { $output[] = ['qty'=>$row['qty'], 'sku'=>$inv['sku'], 'desc'=>$inv['description_short']]; }
        }
        $rows = sortOrder($output, 'sku');
        msgAdd("This SKU is used in the following assemblies:", 'caution');
        foreach ($rows as $row) { msgAdd("Qty: {$row['qty']} SKU: {$row['sku']} - {$row['desc']}", 'caution'); }
    }

    /**
     * Generates a list of stock available to build a given number of assemblies to determine if enough product is on hand
     * @return status message is added to user message queue
     */
    public function getStockAssy()
    {
        $sID = clean('rID', 'integer', 'get');
        $qty = clean('qty', ['format'=>'float','default'=>1], 'get');
        if (!$sID) { return msgAdd("Bad record ID!"); }
        $result = dbGetMulti(BIZUNO_DB_PREFIX."inventory_assy_list", "ref_id=$sID");
        if (sizeof($result) == 0) { return msgAdd($this->lang['err_inv_assy_error']); }
        $shortages = [sprintf($this->lang['err_inv_assy_low_stock'], $qty)];
        foreach ($result as $row) {
            $stock = dbGetValue(BIZUNO_DB_PREFIX."inventory", "qty_stock", "sku='{$row['sku']}'");
            if ($row['qty']*$qty > $stock) { $shortages[] = sprintf($this->lang['err_inv_assy_low_list'], $row['sku'], $row['description'], $stock, $row['qty']*$qty); }
        }
        if (sizeof($shortages) > 1) { msgAdd(implode("<br />", $shortages), 'caution'); }
        else { msgAdd($this->lang['msg_inv_assy_stock_good'], 'success'); }
    }

    /**
     * Cleans up the linked inventory database tables if the inventory record is not present
     * @param integer $rID - record ID of the missing inventory item
     * @return null
     */
    private function cleanOrphan($rID=0) {
        if (!$rID) { return; }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_prices WHERE inventory_id='$rID'");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_assy_list WHERE ref_id='$rID'");
    }
}
