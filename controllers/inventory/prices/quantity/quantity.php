<?php
/*
 * Inventory - Prices by quantity method
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
 * @filesource /controllers/inventory/prices/quantity.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT."controllers/inventory/prices.php", 'inventoryPrices');

class quantity extends inventoryPrices
{
    public $moduleID = 'inventory';
    public $methodDir= 'prices';
    public $code     = 'quantity';
    public $required = true;

    public function __construct()
    {
        parent::__construct();
        $this->lang    = array_merge($this->lang, getMethLang($this->moduleID, $this->methodDir, $this->code));
        $this->settings= ['order'=>10];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return ['order'=>['label'=>lang('order'),'position'=>'after','attr'=>['type'=>'integer','size'=>'3','value'=>$this->settings['order']]]];
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
        $defAttr= ['break'=>true,'label'=>lang('default'),'attr'=>['type'=>'selNoYes']];
        if (!empty($settings['default'])) { $defAttr['attr']['checked'] = true; }
        $layout['fields']['id'      .$this->code] = $layout['fields']['id']; // hidden
        $layout['fields']['item'    .$this->code] = ['attr'=>['type'=>'hidden']];
        $layout['fields']['title'   .$this->code] = ['order'=>10,'label'=>lang('title'),'break'=>true,'attr'=>['value'=>$settings['title']]];
        $layout['fields']['default' .$this->code] = $defAttr;
        $layout['fields']['currency'.$this->code] = array_merge($layout['fields']['currency'],['order'=>70,'break'=>true]);
        $layout['fields']['locked'  .$this->code] = ['label'=>'Prevent from applying discounts?','attr'=>['type'=>'checkbox','checked'=>!empty($settings['locked'])? true : false]];
        $keys = ['id'.$this->code,'item'.$this->code,'title'.$this->code,'default'.$this->code,'contact_id'.$this->code,'inventory_id'.$this->code,'ref_id'.$this->code,'currency'.$this->code,'locked'.$this->code];
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
        $layout['divs']['divPrices'] = ['order'=>10,'label'=>lang('general'),'type'=>'divs','divs'=>[
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
     * @param string $layout
     * @return true if successful, NULL and messageStack with error message if failed
     */
    public function priceSave(&$layout=[])
    {
        $rID    = clean('id'     .$this->code,'integer','post');
        $data   = clean('item'   .$this->code,'json',   'post');
        $title  = clean('title'  .$this->code,'text',   'post');
        $default= clean('default'.$this->code,'char',   'post');
        if (!$security = validateSecurity('inventory', 'prices_'.$this->type, $rID?3:2)) { return; }
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX."inventory_prices"), $this->code);
        $values['method'] = $this->code;
        // check for duplicate title's
        if ($title) {
            $dup = dbGetMulti(BIZUNO_DB_PREFIX."inventory_prices", "id<>$rID AND method='$this->code' AND contact_type='$this->type'");
            foreach ($dup as $row) {
                $props = json_decode($row['settings'], true);
                if (isset($props['title']) && $props['title'] == $title) { return msgAdd(lang('duplicate_title')); }
            }
        }
        msgDebug("\ndecoded data = ".print_r($data, true));
        if (!isset($data['total']) || !isset($data['rows'])) { return; }
        $levels = $data['rows'];
        if ($data['total'] == 0) { return; }
        $prices = [];
        foreach ($levels as $level) {
            if ($level['source'] && $level['qty']) {
                $temp = [$level['price'], $level['qty'], $level['source'], $level['adjType'],
                    $level['adjValue'], $level['rndType'], $level['rndValue']];
                $prices[] = implode(':', $temp);
            }
        }
        $settings = [
            'title'      => $title,
            'last_update'=> biz_date('Y-m-d'),
            'attr'       => implode(';', $prices),
            'default'    => $default,
            'locked'     => clean('locked'.$this->code, 'integer', 'post')];
        $values['settings'] = json_encode($settings);
        $result = dbWrite(BIZUNO_DB_PREFIX.'inventory_prices', $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $_POST['id'] = $result; } // for customization
        msgLog(lang('prices').'-'.lang('save')." - $this->code; $title (rID=$rID)");
        return true;
    }

    /**
     * This function determines the price for a given SKU and returns the entries for prices drop down
     * @param array &$prices - current pricing array to be added to
     * @param array $args - details needed to calculate proper price
     * @return array $prices by reference
     */
    public function priceQuote(&$prices, $args)
    {
        $type = $args['cType'];
        $sheets= dbGetMulti(BIZUNO_DB_PREFIX.'inventory_prices', "method='$this->code' AND contact_type='{$args['cType']}'");
        foreach ($sheets as $row) {
            $settings = json_decode($row['settings'], true);
            msgDebug("\nPrice sheet quantity working on row ".print_r($row, true));
            $levels = $this->decodeQuantity($args['iCost'], $args['iList'], $args['qty'], $settings['attr']);
            if (( empty($args['cSheet']) && !empty($settings['default'])) ||
               ( !empty($args['cSheet']) && $args['cSheet']==$row['id'] ) ||
               ( !empty($args['iSheet'.$type]) && $args['iSheet'.$type]==$row['id'])) {
                $prices['sheets'][]= ['title'=>$settings['title'], 'levels'=>$levels['levels']];
                $prices['method']  = $this->code;
                $prices['locked']  = $settings['locked'];
            }
        }
    }
}
