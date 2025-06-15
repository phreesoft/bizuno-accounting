<?php
/*
 * Inventory Prices - bySKU method
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
 * @version    6.x Last Update: 2024-03-08
 * @filesource /controllers/inventory/prices/bySKU.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/prices.php', 'inventoryPrices');

class bySKU extends inventoryPrices
{
    public $moduleID = 'inventory';
    public $methodDir= 'prices';
    public $code     = 'bySKU';

    public function __construct()
    {
        parent::__construct();
        $this->lang    = array_merge($this->lang, getMethLang($this->moduleID, $this->methodDir, $this->code));
        $this->settings= ['order'=>50];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
        $this->structure= ['hooks'=>['inventory'=>['main'=>[
            'edit'   => ['order'=>50,'page'=>$this->code,'class'=>$this->code],
            'delete' => ['order'=>70,'page'=>$this->code,'class'=>$this->code]]]]];
    }

    public function settingsStructure()
    {
        return ['order'=>['label'=>lang('order'),'position'=>'after','attr'=>['type'=>'integer','size'=>'3','value'=>$this->settings['order']]]];
    }

    /**
     * Extends /inventory/main/edit
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        $type = clean('type', ['format'=>'char','default'=>'c'], 'get');
        $iID  = clean('rID', 'integer', 'get');
        if (!$security = validateSecurity('inventory', 'prices_'.$type, 3, false)) { return; }
        if (!$iID) { return; }// cannot add prices until the sku has been saved and exists as prices are added asyncronously
        $layout['tabs']['tabInventory']['divs'][$this->code] = ['order'=>40, 'label'=>lang('prices'), 'type'=>'html', 'html'=>'',
            'options'=>['show_lock'=>1,'href'=>"'".BIZUNO_AJAX."&bizRt=inventory/prices/manager&type=$type&security=$security&iID=$iID&mod={$GLOBALS['bizunoModule']}'"]];
    }

    /**
     * Extends /inventory/main/delete
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        $type = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'type', "id=$rID");
        if (!$security = validateSecurity('inventory', 'prices_'.$type, 4, false)) { return; }
        if ($rID && sizeof($layout['dbAction']) > 0) {
            $layout['dbAction']['price_bySKU'] = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_prices WHERE inventory_id=$rID";
        }
    }

    /**
     * This function renders the HTML form to build the pricing strategy
     * @param array $layout - source data to build the form
     * @param array $settings - attributes containing the prices and level information
     * @return modified $layout
     */
    public function priceRender(&$layout=[], $settings=[])
    {
        $prices = isset($settings['attr']) ? $settings['attr'] : '';
        $layout['values']['prices'] = $this->getPrices($prices);
        $mod    = clean('mod', 'text', 'request'); // in specific module, can be either post or get
        $inInv  = $mod=='inventory' ? true : false;
        $layout['fields']['id'          .$this->code] = $layout['fields']['id']; // hidden
        $layout['fields']['item'        .$this->code] = ['attr'=>['type'=>'hidden']];
        $layout['fields']['inventory_id'.$this->code] = $inInv ? ['attr'=>['type'=>'hidden']] : $layout['fields']['inventory_id'];
        $layout['fields']['ref_id'      .$this->code] = $layout['fields']['ref_id'];
        $layout['fields']['currency'    .$this->code] = $layout['fields']['currency'];
        $layout['fields']['locked'      .$this->code] = ['label'=>'Prevent from applying discounts?','attr'=>['type'=>'checkbox','checked'=>!empty($settings['locked'])? true : false]];
        $keys = ['id'.$this->code,'item'.$this->code,'contact_id'.$this->code,'inventory_id'.$this->code,'ref_id'.$this->code,'currency'.$this->code,'locked'.$this->code];
        $jsHead = "
var dgPricesSetData = ".json_encode($layout['values']['prices']).";
var qtySource = "      .json_encode(viewKeyDropdown($layout['values']['qtySource'])).";
var qtyAdj    = "      .json_encode(viewKeyDropdown($layout['values']['qtyAdj'])).";
var qtyRnd    = "      .json_encode(viewKeyDropdown($layout['values']['qtyRnd'])).";
function preSubmitPrices() {
    jqBiz('#dgPricesSet').edatagrid('saveRow');
    var items = jqBiz('#dgPricesSet').datagrid('getData');
    var serializedItems = JSON.stringify(items);
    jqBiz('#item$this->code').val(serializedItems);
    return true;
}";
        if ($inInv) { // we're in the inventory form, hide inventory_id field and set to current form value
            $layout['jsReady'][$this->code] = "jqBiz('#inventory_id$this->code').val(jqBiz('#id').val());";
        } else {
            $iID = $layout['fields']['inventory_id'.$this->code]['attr']['value'];
            if ($iID) {
                $name = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "id=$iID");
                $layout['fields']['inventory_id'.$this->code]['defaults']['data'] = "iData$this->code";
                $jsHead .= "\nvar iData$this->code = ".json_encode([['id'=>$iID,'description_short'=>$name]]).";";
            }
            $layout['fields']['inventory_id'.$this->code]['attr']['id']  = "inventory_id$this->code";
            $layout['fields']['inventory_id'.$this->code]['attr']['type']= 'inventory';
        }
        $layout['divs']['divPrices'] = ['order'=>10,'type'=>'divs','divs'=>[
            'lblBody' => ['order'=>10,'type'=>'html','html'=>"<h2>{$this->lang['title']}</h2>"],
            'byCBody' => ['order'=>20,'type'=>'fields','keys'=>$keys],
            'byCdg'   => ['order'=>50,'type'=>'datagrid','key'=>'dgPricesSet']]];
        $layout['jsHead'][$this->code] = $jsHead;
        $layout['datagrid']['dgPricesSet'] = $this->dgQuantity('dgPricesSet');
        $layout['datagrid']['dgPricesSet']['columns']['price']['attr']['hidden']  = false;
        $layout['datagrid']['dgPricesSet']['columns']['margin']['attr']['hidden'] = false;
    }

    /**
     * This method saves the form contents for quantity pricing into the database, it is called from method: inventoryPrices:save
     * @return true if successful, NULL and messageStack with error message if failed
     */
    public function priceSave()
    {
        $rID  = clean('id'.$this->code, 'integer', 'post');
        $data = clean('item'.$this->code, 'json', 'post');
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, $rID?3:2)) { return; }
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX."inventory_prices"), $this->code);
        $values['method'] = $this->code;
        msgDebug("decoded data = ".print_r($data, true));
        $levels = $data['rows'];
        $prices = [];
        foreach ($levels as $level) {
            if ($level['source'] && $level['qty']) {
                $temp = [$level['price'], $level['qty'], $level['source'], $level['adjType'],
                    $level['adjValue'], $level['rndType'], $level['rndValue']];
               $prices[] = implode(':', $temp);
            }
        }
        $settings = [
            'locked'     => clean('locked'.$this->code, 'integer', 'post'),
            'last_update'=> biz_date('Y-m-d'),
            'attr'       => implode(';', $prices)];
        $values['settings'] = json_encode($settings);
        $result = dbWrite(BIZUNO_DB_PREFIX.'inventory_prices', $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $_POST['id'] = $result; } // for customization
        msgLog(lang('prices').'-'.lang('save')." - $this->code; SKU: ".$values['inventory_id']." (rID=$rID)");
        return true;
    }

    /**
     * This function determines the price for a given sku and returns the entries for prices drop down
     * @param array $prices - current pricing array to be added to
     * @param array $args -
     * @return array $prices by reference
     */
    public function priceQuote(&$prices, $args=[])
    {
        $sheets = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_prices', "contact_type='{$args['cType']}' AND method='$this->code' AND inventory_id='{$args['iID']}' AND contact_id=''");
        foreach ($sheets as $row) {
            $settings= json_decode($row['settings'], true);
            $levels  = $this->decodeQuantity($args['iCost'], $args['iList'], $args['qty'], $settings['attr']);
            $temp = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'inventory_prices', 'settings', "id={$row['ref_id']}"), true);
            $prices['method']  = $this->code;
            $prices['locked']  = $settings['locked'];
            $prices['sheets'][]= ['title'=>$this->lang['title'].' - '.(isset($temp['title']) ? $temp['title'] : ''),
                'levels' => $levels['levels']];
        }
    }
}
