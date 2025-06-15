<?php
/*
 * Inventory - Prices by contact method
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
 * @version    6.x Last Update: 2023-12-30
 * @filesource /controllers/inventory/prices/byContact.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/prices.php', 'inventoryPrices');

class byContact extends inventoryPrices
{
    public $moduleID = 'inventory';
    public $methodDir= 'prices';
    public $code     = 'byContact';

    public function __construct()
    {
        parent::__construct();
        $this->lang    = array_merge($this->lang, getMethLang($this->moduleID, $this->methodDir, $this->code));
        $this->settings= ['order'=>20];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
        $this->structure= ['hooks'=>['contacts'=>['main'=>[
            'edit'  => ['order'=>51,'page'=>$this->code,'class'=>$this->code],
            'delete'=> ['order'=>71,'page'=>$this->code,'class'=>$this->code]]]]];
    }

    public function settingsStructure()
    {
        return ['order'=>['label'=>lang('order'),'position'=>'after','attr'=>['type'=>'integer','size'=>'3','value'=>$this->settings['order']]]];
    }

    /**
     * Extends /contacts/main/edit
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        $type = clean('type',['format'=>'char','default'=>'c'], 'get');
        $cID  = clean('rID', 'integer', 'get');
        if (!$security = validateSecurity('inventory', 'prices_'.$type, 3, false)) { return; }
        if (!$cID) { return; }// cannot add prices until the contact has been saved and exists as prices are added asyncronously
        $layout['tabs']['tabContacts']['divs'][$this->code] = ['order'=>35, 'label'=>lang('prices'), 'type'=>'html', 'html'=>'',
            'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=inventory/prices/manager&type=$type&security=$security&mID=$this->code&cID=$cID&mod={$GLOBALS['bizunoModule']}'"]];
    }

    /**
     * Extends /contacts/main/delete
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        $type = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'type', "id=$rID");
        if (!$security = validateSecurity('inventory', 'prices_'.$type, 4, false)) { return; }
        if ($rID && !empty($layout['dbAction'])) {
            $layout['dbAction']['price_byContact'] = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_prices WHERE contact_id=$rID";
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
        msgDebug("\nentering byContact with settings= ".print_r($settings, true));
        $mod    = clean('mod', 'text', 'request'); // in specific module, can be either post or get
        $inContacts = $mod=='contacts' ? true : false;
        $type   = $layout['fields']['contact_type']['attr']['value'];
        $prices = isset($settings['attr']) ? $settings['attr'] : '';
        $layout['values']['prices']  = $this->getPrices($prices);
        $layout['fields']['id'          .$this->code] = $layout['fields']['id']; // hidden
        $layout['fields']['item'        .$this->code] = ['attr'=>['type'=>'hidden']];
        $layout['fields']['contact_id'  .$this->code] = $inContacts ? ['attr'=>['type'=>'hidden']] : $layout['fields']['contact_id'];
        $layout['fields']['inventory_id'.$this->code] = $layout['fields']['inventory_id'];
        $layout['fields']['ref_id'      .$this->code] = $layout['fields']['ref_id'];
        $layout['fields']['currency'    .$this->code] = $layout['fields']['currency'];
        $keys = ['id'.$this->code,'item'.$this->code,'contact_id'.$this->code,'inventory_id'.$this->code,'ref_id'.$this->code,'currency'.$this->code];
        $jsHead = "
var dgPricesSetData = ".json_encode($layout['values']['prices']).";
var qtySource = "      .json_encode(viewKeyDropdown($layout['values']['qtySource'])).";
var qtyAdj    = "      .json_encode(viewKeyDropdown($layout['values']['qtyAdj'])).";
var qtyRnd    = "      .json_encode(viewKeyDropdown($layout['values']['qtyRnd'])).";
var rID = jqBiz('#inventory_id$this->code').val();
function preSubmitPrices() {
    jqBiz('#dgPricesSet').edatagrid('saveRow');
    var items = jqBiz('#dgPricesSet').datagrid('getData');
    var serializedItems = JSON.stringify(items);
    jqBiz('#item$this->code').val(serializedItems);
    return true;
}";
            $iID = $layout['fields']['inventory_id']['attr']['value'];
            if ($iID) {
                $name = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "id=$iID");
                $layout['fields']['inventory_id'.$this->code]['defaults']['data'] = "iData$this->code";
                $jsHead .= "\nvar iData$this->code = ".json_encode([['id'=>$iID,'description_short'=>$name]]).";";
            }
            $layout['fields']['inventory_id'.$this->code]['attr']['id']  = "inventory_id$this->code";
            $layout['fields']['inventory_id'.$this->code]['attr']['type']= 'inventory';
        if ($inContacts) { // we're in the contact form, hide contact_id field and set to current form value
            $layout['jsReady'][$this->code] = "jqBiz('#contact_id$this->code').val(jqBiz('#id').val());";
        } else {
            $cID = $layout['fields']['contact_id'.$this->code]['attr']['value'];
            if ($cID) {
                $name = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'primary_name', "ref_id=$cID AND type='m'");
                $layout['fields']['contact_id'.$this->code]['defaults']['data'] = "cData$this->code";
                $jsHead .= "\nvar cData$this->code = ".json_encode([['id'=>$cID,'primary_name'=>$name]]).";";
            }
            $layout['fields']['contact_id'.$this->code]['defaults']['suffix']= $this->code;
            $layout['fields']['contact_id'.$this->code]['defaults']['type']  = $type;
            $layout['fields']['contact_id'.$this->code]['attr']['type']      = 'contact';
        }
        $layout['divs']['divPrices'] = ['order'=>10,'type'=>'divs','divs'=>[
            'lblBody' => ['order'=>10,'type'=>'html','html'=>"<h2>{$this->lang['title']}</h2>"],
            'byCBody' => ['order'=>20,'type'=>'fields','label'=>$this->lang['title'],'keys'=>$keys],
            'byCdg'   => ['order'=>50,'type'=>'datagrid','key'=>'dgPricesSet']]];
        $layout['jsHead'][$this->code] = $jsHead;
        $layout['datagrid']['dgPricesSet'] = $this->dgQuantity('dgPricesSet');
        $layout['datagrid']['dgPricesSet']['columns']['price']['attr']['hidden']  = false;
        $layout['datagrid']['dgPricesSet']['columns']['margin']['attr']['hidden'] = false;
    }

    /**
     * This method saves the form contents for quantity pricing into the database, it is called from method: inventoryPrices:save
     * @param string $request
     * @return true if successful, NULL and messageStack with error message if failed
     */
    public function priceSave()
    {
        $rID  = clean('id'.$this->code,   'integer','post');
        $data = clean('item'.$this->code, 'json',   'post');
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, $rID?3:2)) { return; }
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'inventory_prices'), $this->code);
        $values['method'] = $this->code;
        if (empty($rID)) { // check for duplicates
            $found = dbGetValue(BIZUNO_DB_PREFIX.'inventory_prices', 'id', "contact_id='{$values['contact_id']}' AND inventory_id='{$values['inventory_id']}'");
            if (!empty($found)) { return msgAdd(lang('error_duplicate_id')); }
        }
        msgDebug("decoded data = ".print_r($data, true));
        $levels = $data['rows'];
        $prices = [];
        foreach ($levels as $level) { if ($level['source'] && $level['qty']) {
            $temp = [$level['price'], $level['qty'], $level['source'], $level['adjType'], $level['adjValue'], $level['rndType'], $level['rndValue']];
            $prices[] = implode(':', $temp);
        } }
        $values['settings'] = json_encode(['last_update'=> biz_date('Y-m-d'), 'attr'=>implode(';', $prices)]);
        $result = dbWrite(BIZUNO_DB_PREFIX.'inventory_prices', $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $_POST['id'] = $result; } // for customization
        msgLog(lang('prices').'-'.lang('save')." - $this->code; contact: $rID; SKU: ".$values['inventory_id']." (rID=$rID)");
        return true;
    }

    /**
     * This function determines the price for a given SKU and returns the entries for prices select
     * @param array $prices - current pricing array to be added to
     * @param array $values - details needed to calculate proper price
     * @return array $prices by reference
     */
    public function priceQuote(&$prices, $values)
    {
        if (empty($values['cID'])) { return; }
        $sheets = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_prices', "method='$this->code' AND inventory_id='{$values['iID']}' AND contact_id='{$values['cID']}'");
        foreach ($sheets as $row) {
            $settings= json_decode($row['settings'], true);
            $levels  = $this->decodeQuantity($values['iCost'], $values['iList'], $values['qty'], $settings['attr']);
            msgDebug("\nMethod = $this->code with attr = ".$settings['attr']." returned levels: ".print_r($levels, true));
            $prices['sheets'][]= ['title'=>$this->lang['title'], 'levels'=>$levels['levels']];
            $prices['method']  = $this->code;
        }
    }
}
