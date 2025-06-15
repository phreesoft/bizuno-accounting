<?php
/*
 * Inventory module support functions
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
 * @version    6.x Last Update: 2024-03-26
 * @filesource /controllers/inventory/functions.php
 */

namespace bizuno;

/**
 * Processes a value by format, used in PhreeForm
 * @global array $report - report structure
 * @param mixed $value - value to process
 * @param type $format - what to do with the value
 * @return mixed, returns $value if no formats match otherwise the formatted value
 */
function inventoryProcess($value, $format='')
{
    global $report;
    switch ($format) {
        case 'image_sku': return dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "sku='".addslashes($value)."'");
        case 'inv_image': return dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "id='".intval($value)."'");
        case 'inv_sku':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku',                 "id="  .intval($value)))        ? $result : '';
        case 'inv_shrt':  return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short',   "id="  .intval($value)))        ? $result : '';
        case 'sku_name':  return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short',   "sku='".addslashes($value)."'"))? $result : '';
        case 'inv_assy':  return dbGetInvAssyCost($value);
        case 'inv_j06_id':return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_purchase',"id='$value'")) ? $result : $value;
        case 'inv_j06':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_purchase',"sku='".addslashes($value)."'"))? $result : $value;
        case 'inv_j12_id':return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_sales',   "id='$value'")) ? $result : $value;
        case 'inv_j12':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_sales',   "sku='".addslashes($value)."'"))? $result : $value;
        case 'inv_mv0':   $range = 'm0';
        case 'inv_mv1':   if (empty($range)) { $range = 'm1'; }
        case 'inv_mv3':   if (empty($range)) { $range = 'm3'; }
        case 'inv_mv6':   if (empty($range)) { $range = 'm6'; }
        case 'inv_mv12':  if (empty($range)) { $range = 'm12';}
                          return viewInvSales($value, $range); // value passed should be the SKU
        case 'inv_stk':   return viewInvMinStk($value); // value passed should be the SKU
        default:
    }
    if (substr($format, 0, 5) == 'skuPS') { // get the sku price based on the price sheet passed
        if (!$value) { return ''; }
        $fld   = explode(':', $format);
        if (empty($report->currentValues['id']) || empty($report->currentValues['unit_price']) || empty($report->currentValues['full_price'])) { // need to get the sku details
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id','item_cost','full_price'], "sku='".addslashes($value)."'");
        } else { $inv = $report->currentValues; }
        $values= ['iID'=>$inv['id'], 'iCost'=>$inv['item_cost'],'iList'=>$inv['full_price'],'iSheetc'=>$fld[1],'iSheetv'=>$fld[1],'cID'=>0,'cSheet'=>$fld[1],'cType'=>'c','qty'=>1];
        $prices= [];
        bizAutoLoad(BIZBOOKS_ROOT."controllers/inventory/prices.php", 'inventoryPrices');
        $mgr   = new inventoryPrices();
        $mgr->pricesLevels($prices, $values);
        return $prices['price'];
    }
    return $value;
}

function invIsTracked($type) {
    $tracked = explode(',', COG_ITEM_TYPES);
    return in_array($type, $tracked) ? true : false;
}

/**
 * Calculates the quantity of a given SKU available to sell
 * @param array $item - pulled directly from the inventory db
 * @return type
 */
function availableQty($item=[], $args=[])
{
    if (empty($item['id']))            { return 0; }
    $tracked  = explode(',', COG_ITEM_TYPES);
    $incAssy  = isset($args['incAssy'])  ? $args['incAssy']  : getModuleCache('inventory', 'settings', 'general', 'inc_assemblies', 1);
    $incCommit= isset($args['incCommit'])? $args['incCommit']: getModuleCache('inventory', 'settings', 'general', 'inc_committed', 1);
    if (empty($item['qty_stock']))     { $item['qty_stock']      = 0; }
    if (empty($item['qty_so']))        { $item['qty_so']         = 0; }
    if (empty($item['qty_alloc']))     { $item['qty_alloc']      = 0; }
    if (empty($item['inventory_type'])){ $item['inventory_type'] = 'si'; }
    if (strpos(COG_ITEM_TYPES, $item['inventory_type']) === false) { $item['qty_stock'] = 1; } // Fix some special cases, non-stock types need qty > 0
    msgDebug("\nIn availableQty with incAssy = $incAssy and incCommit = $incCommit");
    if ($incAssy && in_array($item['inventory_type'], ['ma', 'sa'])) { // for assemblies, see how many we can build
        $sql = "SELECT a.qty, i.qty_stock FROM ".BIZUNO_DB_PREFIX."inventory i JOIN ".BIZUNO_DB_PREFIX."inventory_assy_list a ON i.sku=a.sku
            WHERE a.ref_id=".$item['id']." AND i.inventory_type IN ('".implode("','", $tracked)."')";
        $stmt = dbGetResult($sql);
        if ($stmt) {
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            msgDebug("\nAssy parts = ".print_r($result, true));
            $min_qty= 9999;
            foreach ($result as $row) { $min_qty = $row['qty'] == 0 ? 0 : min($min_qty, floor($row['qty_stock'] / $row['qty'])); }
            $item['qty_stock'] += $min_qty;
        }
        msgDebug("\nAfter assembly item[qty_stock] = ".print_r($item['qty_stock'], true));
    }
    if ($incCommit) { $item['qty_stock'] -= ($item['qty_so'] + $item['qty_alloc']); }
    $toSell = max(0, $item['qty_stock']);
    msgDebug("\nReturning with available = $toSell");
    return $toSell;
}
