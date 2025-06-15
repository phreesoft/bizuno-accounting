<?php
/*
 * Tools methods for Inventory Module
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
 * @version    6.x Last Update: 2024-03-01
 * @filesource /controllers/inventory/tools.php
 */

namespace bizuno;

class inventoryTools
{
    public $moduleID = 'inventory';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        $this->inv_types = explode(",", COG_ITEM_TYPES);
    }

    /**
     * Generates a pop up bar chart for monthly sales of inventory items
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function chartSales(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        $sku   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
        if (!$rID) { return msgAdd(lang('err_bad_id')); }
        $struc = $this->chartSalesData($sku);
        $output= ['divID'=>"chartInventoryChart",'type'=>'column','attr'=>['legend'=>'none','title'=>lang('sales')],'data'=>array_values($struc)];
        $action= BIZUNO_AJAX."&bizRt=inventory/tools/chartSalesGo&sku=$sku";
        $js    = "ajaxDownload('frmInventoryChart');\n";
        $js   .= "var dataInventoryChart = ".json_encode($output).";\n";
        $js   .= "function funcInventoryChart() { drawBizunoChart(dataInventoryChart); };";
        $js   .= "google.charts.load('current', {'packages':['corechart']});\n";
        $js   .= "google.charts.setOnLoadCallback(funcInventoryChart);\n";
        $layout = array_merge_recursive($layout, ['type'=>'divHTML',
            'divs'  => [
                'body'  =>['order'=>50,'type'=>'html',  'html'=>'<div style="width:100%" id="chartInventoryChart"></div>'],
                'divExp'=>['order'=>70,'type'=>'html',  'html'=>'<form id="frmInventoryChart" action="'.$action.'"></form>'],
                'btnExp'=>['order'=>90,'type'=>'fields','keys'=>['icnExp']]],
            'fields'=> ['icnExp'=>['attr'=>['type'=>'button','value'=>lang('download_data')],'events'=>['onClick'=>"jqBiz('#frmInventoryChart').submit();"]]],
            'jsHead'=> ['init'=>$js]]);
        }

    private function chartSalesData($sku)
    {
        $dates = localeGetDates(localeCalculateDate(biz_date('Y-m-d'), 0, 0, -1));
        $jIDs  = '(12,13)';
        msgDebug("\nDates = ".print_r($dates, true));
          $sql = "SELECT MONTH(m.post_date) AS month, YEAR(m.post_date) AS year, SUM(i.credit_amount+i.debit_amount) AS total
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE i.sku='$sku' and m.journal_id IN $jIDs AND m.post_date>='{$dates['ThisYear']}-{$dates['ThisMonth']}-01'
              GROUP BY year, month LIMIT 12";
        msgDebug("\nSQL = $sql");
        if (!$stmt = dbGetResult($sql)) { return msgAdd(lang('err_bad_sql')); }
        $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nresult = ".print_r($result, true));
        $precision = getModuleCache('phreebooks', 'currency', 'iso')[getDefaultCurrency()]['dec_len'];
        $struc[] = [lang('date'), lang('total')];
        for ($i = 0; $i < 12; $i++) { // since we have 12 months to work with we need 12 array entries
            $struc[$dates['ThisYear'].$dates['ThisMonth']] = [$dates['ThisYear'].'-'.$dates['ThisMonth'], 0];
            $dates['ThisMonth']++;
              if ($dates['ThisMonth'] == 13) {
                  $dates['ThisYear']++;
                  $dates['ThisMonth'] = 1;
              }
        }
        foreach ($result as $row) {
            if (isset($struc[$row['year'].$row['month']])) { $struc[$row['year'].$row['month']][1] = round($row['total'], $precision); }
          }
        return $struc;
    }

    public function chartSalesGo()
    {
        global $io;
        $sku   = clean('sku', 'text', 'get');
        $struc = $this->chartSalesData($sku);
        $output= [];
        foreach ($struc as $row) { $output[] = implode(",", $row); }
        $io->download('data', implode("\n", $output), "SKU-Sales-$sku.csv");
    }

    /**
     * Works with dashboard inv-stock to re-generate data and download
     * @global type $io
     */
    public function invDataGo()
    {
        global $io;
        $fqdn = "\\bizuno\\inv_stock";
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/dashboards/inv_stock/inv_stock.php', $fqdn);
        $dash = new $fqdn();
        $data = $dash->getData();
        foreach ($data as $row) { $output[] = '"'.implode('","', $row).'"'; }
        $io->download('data', implode("\n", $output), "InvValData-".biz_date('Y-m-d').".csv");
    }

    /**
     * Downloads the stock aging data
     * @global class $io - I/O class
     * @return exits if successful, msg otherwise
     */
    public function stockAging()
    {
        global $io;
        $ttlQty = $ttlCost = 0;
        $output = [];
        $this->ageFld = dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'shelf_life') ? true : false;
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "remaining>0", 'post_date', ['sku', 'post_date', 'remaining', 'unit_cost']);
        $raw[] = [lang('post_date'), lang('sku'), lang('inventory_description_short'), lang('remaining'), lang('value')];
        foreach ($rows as $row) {
            $ageDate = $this->getAgingValue($row['sku']);
            msgDebug("\nsku {$row['sku']} comparing ageDate: $ageDate with post date: {$row['post_date']}");
            if ($row['post_date'] >= $ageDate) { continue; }
            $ttlQty += $row['remaining'];
            $value   = $row['unit_cost'] * $row['remaining'];
            $ttlCost+= $value;
            $raw[]   = [viewFormat($row['post_date'], 'date'), $row['sku'], viewProcess($row['sku'], 'sku_name'), intval($row['remaining']), viewFormat($value, 'currency')];
        }
        $raw[]  = [jslang('total'), '', '', intval($ttlQty), viewFormat($ttlCost,'currency')];
        if (sizeof($raw) < 2) { return msgAdd('There are no items aged over their expected aging date!'); }
        foreach ($raw as $row) { $output[] = '"'.implode('","', $row).'"'; }
        $io->download('data', implode("\n", $output), "Stock-Aging-".biz_date('Y-m-d').".csv");
    }

    /**
     * Retrieves the aging date based on the SKU provided
     * @param string $sku - sku to search
     * @return string - aged date to compare for filter
     */
    private function getAgingValue($sku)
    {
        if (!empty($this->skuDates[$sku])) { return $this->skuDates[$sku]; }
        $numWeeks = $this->ageFld ? dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'shelf_life', "sku='$sku'") : $this->struc['defAge']['attr']['value'];
        $this->skuDates[$sku] = localeCalculateDate(biz_date('Y-m-d'), -($numWeeks * 7));
        msgDebug("\n num weeks = $numWeeks and calculated date = {$this->skuDates[$sku]}");
        return $this->skuDates[$sku];
    }

    /**
     * This function balances the inventory stock levels with the inventory_history table
     */
    public function historyTestRepair()
    {
        if (!$security = validateSecurity('bizuno', 'admin', 3)) { return; }
        $action   = clean('data', ['format'=>'alpha_num', 'default'=>'test'], 'get');
        $precision= 1 / pow(10, getModuleCache('bizuno', 'settings', 'locale', 'number_precision', 2));
        $roundPrec= getModuleCache('bizuno', 'settings', 'locale', 'number_precision', 2);
        $result0  = dbGetMulti(BIZUNO_DB_PREFIX."journal_cogs_owed");
        $owed     = [];
        foreach ($result0 as $row) {
            if (!isset($owed[$row['sku']])) { $owed[$row['sku']] = 0; }
            $owed[$row['sku']] += $row['qty'];
        }
        // fetch the inventory items that we track COGS and get qty on hand
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", $this->inv_types)."')", 'sku', ['sku','qty_stock']);
        $cnt    = 0;
        $repair = [];
        foreach ($result as $row) { // for each item, find the history remaining Qty's
            // check for quantity on hand not rounded properly
            $on_hand = round($row['qty_stock'], $roundPrec);
            if ($on_hand <> $row['qty_stock']) {
                $repair[$row['sku']] = $on_hand;
                if ($action <> 'fix') {
                    $dispVal = round($on_hand, $roundPrec);
                    msgAdd(sprintf($this->lang['inv_tools_stock_rounding_error'], $row['sku'], $row['qty_stock'], $dispVal));
                    $cnt++;
                }
            }
            // now check with inventory history
            $remaining= dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', "SUM(remaining) AS remaining", "sku='".addslashes($row['sku'])."'", false);
            $cog_owed = isset($owed[$row['sku']]) ? $owed[$row['sku']] : 0;
            $cog_diff = round($remaining - $cog_owed, $roundPrec);
            if ($on_hand <> $cog_diff && abs($on_hand-$cog_diff) > 0.01) {
                $repair[$row['sku']] = $cog_diff;
                if ($action <> 'fix') {
                    msgAdd(sprintf($this->lang['inv_tools_out_of_balance'], $row['sku'], $on_hand, $cog_diff));
                    $cnt++;
                }
            }
            msgDebug("\nsku = {$row['sku']}, qty_stock = {$row['qty_stock']}, on_hand = $on_hand, cog_diff = $cog_diff, remaining = $remaining, owed = $cog_owed");
        }
        if ($action == 'fix') {
            // zero out balances that are less than the precision
            dbWrite(BIZUNO_DB_PREFIX.'inventory_history', ['remaining'=>0], 'update', "remaining<$precision");
            if (sizeof($repair) > 0) { foreach ($repair as $key => $value) {
                // commented out, the value has already been rounded.
//                $value = round($value, $roundPrec);
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_stock'=>$value], 'update', "sku='".addslashes($key)."'");
                msgAdd(sprintf($this->lang['inv_tools_balance_corrected'], $key, $value), 'info');
            } }
        }
        if ($cnt == 0) { msgAdd($this->lang['inv_tools_in_balance'], 'info'); }
        msgLog($this->lang['inv_tools_val_inv']);
    }

    /**
     * Re-aligns table inventory.qty_alloc with open activities.
     * Here, the function is mostly an entry point that resets all qty_on alloc values to zero, they will
     * be reset to the proper value through mods in the extensions.
     */
    public function qtyAllocRepair()
    {
        dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_alloc'=>0], 'update', "qty_alloc<>0");
        msgAdd(lang('msg_database_write'), 'success');
    }

    /**
     * This function balances the open sales orders and purchase orders with the displayed levels from the inventory table
     */
    public function onOrderRepair()
    {
        if (!$security = validateSecurity('bizuno', 'admin', 3)) { return; }
        $skuList = [];
        $this->inv_types[] = 'ns'; // add some more that should be checked
        $jItems = $this->getJournalQty(); // fetch the PO's and SO's balances
        $items  = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','",$this->inv_types)."')", 'sku', ['id','sku','qty_so','qty_po']);
        foreach ($items as $row) {
            $adjPO = false;
            if (isset($jItems[4][$row['sku']]) && $jItems[4][$row['sku']] != $row['qty_po']) {
                $adjPO = max(0, round($jItems[4][$row['sku']], 4));
            } elseif (!isset($jItems[4][$row['sku']]) && $row['qty_po'] != 0) {
                $adjPO = 0;
            }
            if ($adjPO !== false) {
                $skuList[] = sprintf('Quantity of SKU: %s on %s was adjusted to %f', $row['sku'], lang('journal_main_journal_id_4'), $adjPO);
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_po'=>$adjPO], 'update', "id={$row['id']}");
            }
            $adjSO = false;
            if (isset($jItems[10][$row['sku']]) && $jItems[10][$row['sku']] != $row['qty_so']) {
                $adjSO = max(0, round($jItems[10][$row['sku']], 4));
            } elseif (!isset($jItems[10][$row['sku']]) && $row['qty_so'] != 0) {
                $adjSO = 0;
            }
            if ($adjSO !== false) {
                $skuList[] = sprintf('Quantity of SKU: %s on %s was adjusted to %f', $row['sku'], lang('journal_main_journal_id_10'), $adjSO);
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_so'=>$adjSO], 'update', "id={$row['id']}");
            }
        }
        msgLog($this->lang['inv_tools_repair_so_po']);
        if (sizeof($skuList) > 0) { return msgAdd(implode("<br />", $skuList), 'caution'); }
        msgAdd($this->lang['inv_tools_so_po_result'], 'success');
    }

    /**
     * Checks order status for order balances, items received/shipped
     * @return array - indexed by journal_id total qty on SO, PO
     */
    private function getJournalQty()
    {
        $item_list = [];
        $orders = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "closed='0' AND journal_id IN (4,10)", '', ['id', 'journal_id']);
        foreach ($orders as $row) {
            $ordr_items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$row['id']} AND gl_type='itm'", '', ['id', 'sku', 'qty']);
            foreach ($ordr_items as $item) {
                if (!isset($item_list[$row['journal_id']][$item['sku']])) { $item_list[$row['journal_id']][$item['sku']] = 0; }
                $filled = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty) AS qty", "item_ref_id={$item['id']}", false);
                if (empty($filled)) { $filled = 0; }
//              msgDebug("\nWorking with sku: {$item['sku']} with quantity: {$item['qty']} and filled: $filled");
                // in the case when more are received than ordered, don't let qty_po, qty_so go negative (doesn't make sense)
                $item_list[$row['journal_id']][$item['sku']] += max(0, $item['qty'] - $filled);
            }
        }
        msgDebug("\nReturning from getJournalQty with list =  .print_r($item_list, true)");
        return $item_list;
    }

    /**
     * Re-prices all assemblies based on current item costs, best done after new item costing has been done completed, through ajax steps
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function priceAssy(&$layout=[])
    {
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('ma','sa')", 'sku', ['id', 'sku']);
        if (sizeof($result) == 0) { return msgAdd("No assemblies found to process!"); }
        foreach ($result as $row) { $rows[] = ['id'=>$row['id']]; }
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCache('cron', 'priceAssy', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows, 'noCost'=>[], 'noQty'=>[]]);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('priceAssy', 'inventory/tools/priceAssyNext');"]]);
    }

    /**
     * Controller for re-costing assemblies, manages a block of 100 SKUs per iteration
     * @param type $layout
     */
    public function priceAssyNext(&$layout=[])
    {
//      global $io;
        $blockCnt = 100;
        $cron = getUserCache('cron', 'priceAssy');
        while ($blockCnt > 0) {
            $row  = array_shift($cron['rows']);
            if (empty($row)) { break; }
            $cost = $this->dbGetInvAssyCost($row['id'], $cron);
            if ($cost > 0) { dbWrite(BIZUNO_DB_PREFIX.'inventory', ['item_cost'=>$cost], 'update', "id={$row['id']}"); }
            $cron['cnt']++;
            $blockCnt--;
        }
        if (sizeof($cron['rows']) == 0) {
            msgLog("inventory Tools (re-cost Assemblies) - ({$cron['total']} records)");
            $output = "<p>Errors found during assembly costing:</p>";
            if (sizeof($cron['noCost'])>0) { $output .= "SKUs with no cost sub-item:<br />"    .implode("<br />", $cron['noCost']); }
            if (sizeof($cron['noQty']) >0) { $output .= "SKUs with no quantity sub-item:<br />".implode("<br />", $cron['noQty']); }
            // Uncomment the following line to create a file of subassembly parts with no cost AND no quantity
//          $io->fileWrite($output, 'temp/assy_errors.txt');
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} SKUs<br />$output",'baseID'=>'priceAssy','urlID'=>'inventory/tools/priceAssyNext']];
            $allCron = getUserCache('cron');
            unset($allCron['priceAssy']);
            setUserCache('cron', false, $allCron);
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCache('cron', 'priceAssy', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed {$cron['cnt']} of {$cron['total']} SKUs",'baseID'=>'priceAssy','urlID'=>'inventory/tools/priceAssyNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }
    /**
     * Recalculates the inventory history table quantities based on journal entries
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function recalcHistory(&$layout=[])
    {
        global $io;
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", $this->inv_types)."') AND inactive='0'", 'sku', ['id']);
        if (sizeof($result) == 0) { return msgAdd('No inventory found to process!'); }
        foreach ($result as $row) { $rows[] = $row['id']; }
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCache('cron', 'recalcHistory', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows]);
        $output = 'Inventory Reconciliation tool run '.date('Y-m-d')."\n";
        $output.= "SKU,Store,Journal Qty,History Qty\n";
        $io->fileWrite($output, 'temp/recalc_inv.csv', true, false, true);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('recalcHistory', 'inventory/tools/recalcHistoryNext');"]]);
    }

    /**
     * Block process recalculation of inventory history table
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function recalcHistoryNext(&$layout=[])
    {
        global $io;
        $output  = [];
        $blockCnt= 100;
        $stores  = getModuleCache('bizuno', 'stores');
        $cron    = getUserCache('cron', 'recalcHistory');
        while ($blockCnt > 0) {
            $hist   = [];
            $id     = array_shift($cron['rows']);
            if (empty($id)) { break; }
            $sku    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$id");
            $result = $this->skuDrillDownActivity($sku);
            $endBal = array_pop($result['cur_bal']);
            msgDebug("\ntotals from drilldown = ".print_r($result['totals'], true));
            // fetch the inventory history balances
            $dbHist = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "sku='".addslashes($sku)."' AND remaining>0", 'post_date', ['remaining', 'store_id']);
            foreach ($dbHist as $row) {
                if (!isset($hist['b'.$row['store_id']])) { $hist['b'.$row['store_id']] = 0; }
                $hist['b'.$row['store_id']] += $row['remaining'];
            }
            msgDebug("\nbalances from db table = ".print_r($hist, true));
            foreach ($stores as $store) {
                $strHist= floatval(isset($hist[$store['id']]) ? $hist[$store['id']] : 0);
                $strBal = isset($endBal[$store['id']]) ? $endBal[$store['id']] : 0;
                if ($strHist<>$strBal) {
                    msgAdd("Inventory mismatch for sku = $sku, store {$store['text']}: Journal balance = $strBal and History balance = $strHist", 'trap');
                    $output[] = "$sku,{$store['text']},$strBal,$strHist";
                }
            }
            $cron['cnt']++;
            $blockCnt--;
        }
        if (!empty($output)) {
            msgDebug("\nWriting output to file: ".print_r($output, true));
            $io->fileWrite(implode("\n", $output)."\n", 'temp/recalc_inv.csv', true, true);
        }
        if (sizeof($cron['rows']) == 0) {
            msgLog("Inventory Tools (test/repair history) - ({$cron['total']} records)");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} SKUs",'baseID'=>'recalcHistory','urlID'=>'inventory/tools/recalcHistoryNext']];
            $allCron = getUserCache('cron');
            unset($allCron['recalcHistory']);
            setUserCache('cron', false, $allCron);
//            $io->download('file', 'temp/', 'recalc_inv.csv', false); // causes error
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCache('cron', 'recalcHistory', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed {$cron['cnt']} of {$cron['total']} SKUs",'baseID'=>'recalcHistory','urlID'=>'inventory/tools/recalcHistoryNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $rID
     * @return int
     */
    private function dbGetInvAssyCost($rID=0, &$cron=[])
    {
        $cost = 0;
        $skip = false;
        if (empty($rID)) { return $cost; }
        $iID  = intval($rID);
        $items= dbGetMulti(BIZUNO_DB_PREFIX.'inventory_assy_list', "ref_id=$iID");
        if (empty($items)) { $items[] = ['qty'=>1, 'sku'=>dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID")]; } // for non-assemblies
        foreach ($items as $row) {
            if (empty($row['sku'])) { continue; }
            if (empty($GLOBALS['inventory'][$row['sku']]['unit_cost'])) {
                $skuDtl = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['inventory_type','item_cost'], "sku='".addslashes($row['sku'])."'");
                if (!in_array($skuDtl, $this->inv_types)) { continue; } // not tracked so ignore cost
                if (empty($skuDtl['item_cost'])) {
                    $cron['noCost'][] = $row['sku'];
                    $skip = true;
                }
                $GLOBALS['inventory'][$row['sku']]['unit_cost'] = $skuDtl['item_cost'];
            }
            if (empty($row['qty'])) {
                $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID");
                $cron['noQty'][] = "Assy sku: $sku with BOM SKU: {$row['sku']}";
                $skip = true;
            }
            $cost+= $row['qty'] * $GLOBALS['inventory'][$row['sku']]['unit_cost'];
        }
        return !$skip ? $cost : 0;
    }

    public function skuDrillDown(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'admin', 3)) { return; }
        $skuID = clean('rID', 'integer','get');
        $date  = clean('data', 'date',  'get'); // start date, do we want an end date?
        $stores= getModuleCache('bizuno', 'stores');
        $sku   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$skuID");
        msgDebug("\Entering skuDrillDown with skuID = $skuID and sku = $sku and date = $date");
        $result= $this->skuDrillDownActivity($sku, 1, $date);
        // generate the HTML
        $html  = '<table>';
        $html .= '<tr><th style="border:1px solid black;">Ref #</th><th style="border:1px solid black;">Journal</th>';
        $html .= '<th style="border:1px solid black;">Post Date</th><th style="border:1px solid black;">Store</th><th style="border:1px solid black;">Qty</th>';
        foreach ($stores as $store) { $html .= '<th style="border:1px solid black;">'.$store['text'].'</th>'; }
        $html.= '</tr>';
        // generate the begBal row
        $html .= '<tr><td colspan="5" style="border:1px solid black;text-align: right;">Beginning Balance&nbsp;</td>';
        foreach ($result['beg_bal'] as $bb) { $html .= '<td style="border:1px solid black;text-align: center;">'.$bb.'</td>'; }
        $html .= '</tr>';
        $curBal = $result['beg_bal'];
        foreach ($result['rows'] as $row) {
            $curBal['b'.$row['store']] += $row['qty'];
            $html .= '<tr>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID={$row['jID']}&rID={$row['id']}');"],'attr'=>['type'=>'button','value'=>"#{$row['ref']}"]]).'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.viewFormat($row['jID'], 'j_desc')   .'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.viewFormat($row['date'], 'date')     .'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.viewFormat($row['store'], 'contactID').'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.$row['qty'].'</td>';
            foreach ($stores as $store) { $html .= '<td style="border:1px solid black;text-align: center;">'.$curBal['b'.$store['id']].'</td>'; }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $data  = ['type'=>'popup','attr'=>['id'=>'invTotals'],'title'=>"Store balances for SKU: $sku",
            'divs' => ['body'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout = array_replace_recursive($layout, $data);
    }

    private function skuDrillDownActivity($sku='', $qtyReqd=1, $strtDate='')
    {
        if (empty($strtDate)) { $strtDate = '2000-01-01'; }
        $posts = [];
        $jIDs  = '6,7,12,13,14,15,16,19,21';
        $stores= getModuleCache('bizuno', 'stores');
        foreach ($stores as $store) {
            $storeIDs[] = $store['id'];
            $dateBal['b'.$store['id']] = 0;
            $jrnlBal['b'.$store['id']] = 0;
        }
        $curStk= $this->getCurrentStock($sku); // balances from the history table by store
        $stmt  = dbGetResult("SELECT m.id, m.journal_id, m.post_date, m.store_id, m.invoice_num, m.so_po_ref_id, i.qty, i.gl_type FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.journal_id IN ($jIDs) AND i.sku='".addslashes($sku)."' ORDER BY m.post_date");
        $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
//      msgDebug("\nResult from search with sku: $sku and qtyReqd = $qtyReqd and date = $strtDate is: ".print_r($result, true));
        while ($row=array_shift($result)) {
            if (in_array($row['journal_id'], [7,12,19])) { $row['qty'] = -$row['qty']; }
            if (!in_array($row['store_id'], $storeIDs))  { $row['store_id'] = 0; }
            $jrnlBal['b'.$row['store_id']] += $row['qty'];
            if ($row['post_date']<$strtDate) { // freeze the balance at this date as the beginning balance
                $dateBal = $jrnlBal;
                continue;
            }
            if ($row['journal_id']==15) {
                if (!in_array($row['so_po_ref_id'], $storeIDs)) { $row['so_po_ref_id'] = 0; }
// @TODO remove after 2023-01-31 - rewrite next line so if so_po_ref_id is not a store then make it zero
if ($row['post_date']<'2020-01-01') { $row['so_po_ref_id'] = 2; } // patch for transfers between defunct stores
                $qty = abs($row['qty']); // make sure it's positive, sometimes the db reverses the lines, assumes that all store transfers are positive numbers
                $posts[] = ['id'=>$row['id'], 'ref'=>$row['invoice_num'], 'jID'=>$row['journal_id'], 'date'=>$row['post_date'], 'store'=>$row['store_id'],    'qty'=> $qty*$qtyReqd];
                $posts[] = ['id'=>$row['id'], 'ref'=>$row['invoice_num'], 'jID'=>$row['journal_id'], 'date'=>$row['post_date'], 'store'=>$row['so_po_ref_id'],'qty'=>-$qty*$qtyReqd];
                $row = array_shift($result); // dump the corresponding row
            } else {
                $posts[] = ['id'=>$row['id'], 'ref'=>$row['invoice_num'], 'jID'=>$row['journal_id'], 'date'=>$row['post_date'], 'store'=>$row['store_id'], 'qty'=>$row['qty']*$qtyReqd];
            }
        }
        foreach ($stores as $store) {
            $begBal['b'.$store['id']] = $curStk['b'.$store['id']] - $jrnlBal['b'.$store['id']] - $dateBal['b'.$store['id']];
        }
        msgDebug("\nfinished calculations, begBal = ".print_r($begBal, true));
        msgDebug("\nfinished calculations, jrnlBal = ".print_r($jrnlBal, true));
        msgDebug("\nfinished calculations, dateBal = ".print_r($dateBal, true));
        $output = ['beg_bal'=>$begBal, 'rows'=>$posts, 'cur_bal'=>$curStk];
        msgDebug("\nReturning from skuDrillDownActivity with output = ".print_r($output, true));
        return $output;
    }

    /**
    * Gets the quantity in stock by branch
    * @param type $sku
    * @return type
    */
   private function getCurrentStock($sku='') {
        $stores= getModuleCache('bizuno', 'stores');
        foreach ($stores as $store) { $output['b'.$store['id']] = 0; }
        // get history table values
        $hist  = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "sku='".addslashes($sku)."' AND remaining>0");
//      msgDebug("\nRead balance from inventory history: ".print_r($balance, true));
        foreach ($hist as $row) { $output['b'.$row['store_id']] += $row['remaining']; }
        // subtract stock owed
        $owed  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_cogs_owed', "sku='".ADDSLASHES($sku)."'");
        foreach ($owed as $row) { $output['b'.$row['store_id']] -= $row['qty']; }
        msgDebug("\nLeaving getCurrentStock with output = ".print_r($output, true));
        return $output;
   }

    /**
     * This function extends the PhreeBooks module close fiscal year method
     */
    public function fyCloseHome(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        $html  = "<p>Closing the fiscal year for the Inventory module consist of deleting inventory items that are no longer referenced in the general journal during or before the fiscal year being closed. "
               . "To prevent the these inventory items from being removed, check the box below.</p>";
        $html .= html5('inventory_keep', ['label' => 'Do not delete inventory items that are not referenced during or before this closing fiscal year', 'position'=>'after','attr'=>['type'=>'checkbox','value'=>'1']]);
        $layout['tabs']['tabFyClose']['divs'][$this->moduleID] = ['order'=>55,'label'=>$this->lang['title'],'type'=>'html','html'=>$html];
    }

    /**
     * Hook to PhreeBooks Close FY method, adds tasks to the queue to execute AFTER PhreeBooks processes the journal
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function fyClose()
    {
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        $skip = clean('inventory_keep', 'boolean', 'post');
        if ($skip) { return; } // user wants to keep all records, nothing to do here, move on
        $cron = getUserCache('cron', 'fyClose');
        $cron['taskPost'][] = ['mID'=>$this->moduleID, 'settings'=>['cnt'=>1, 'rID'=>0]]; // ,'method'=>'fyCloseNext']; // assumed method == fyCloseNext, no settings
        setUserCache('cron', 'fyClose', $cron);
    }

    /**
     * Executes a step in the fiscal close procedure, controls all steps for this module
     * @param array $settings - Properties for the fiscal year close operation
     * @return string - message with current status
     */
    public function fyCloseNext($settings=[], &$cron=[])
    {
        $blockSize = 25;
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        if (!isset($cron[$this->moduleID]['total'])) {
            $cron[$this->moduleID]['total'] = dbGetValue(BIZUNO_DB_PREFIX."inventory", 'COUNT(*) AS cnt', "", false);
        }
        $totalBlock = ceil($cron[$this->moduleID]['total'] / $blockSize);
        $output = $this->fyCloseStep($settings['rID'], $blockSize, $cron['msg']);
//if ($settings['cnt'] > 4) { $output['finished'] = true; }
        if (!$output['finished']) { // more to process, re-queue
            $settings['cnt']++;
            msgDebug("\nRequeuing inventory with rID = {$output['rID']}");
            array_unshift($cron['taskPost'], ['mID'=>$this->moduleID, 'settings'=>['cnt'=>$settings['cnt'], 'rID'=>$output['rID']]]);
        } else { // we're done, run the sync attachments tool
            msgDebug("\nFinished inventory, checking attachments");
            $this->syncAttachments();
        }
        // Need to add these results to a log that can be downloaded from the backup folder.
        return "Finished processing block {$settings['cnt']} of $totalBlock for module $this->moduleID: deleted {$output['deleted']} records";
    }

    /**
     * Just executes a single step
     * @param integer $rID - starting record id for this step
     * @param integer $blockSize - number of records to delete in a single step
     * @return array - status and data for the next step, number of records deleted
     */
    private function fyCloseStep($rID, $blockSize, &$msg=[])
    {
        $count = 0;
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "id>$rID", 'id', ['id','sku','inactive','description_short'], $blockSize);
        foreach ($result as $row) {
            $rID = $row['id']; // set the highest rID for next iteration
            if (!$row['inactive']) { continue; }
            if (!$row['sku']) {
                msgAdd("There is not SKU value for record {$row['id']}, This should never happen! The record will be skipped.");
                continue;
            }
            $exists = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'ref_id', "sku='{$row['sku']}'");
            if (!$exists) {
                $msg[] = "Deleting inventory id={$row['id']}, {$row['sku']} - {$row['description_short']}";
                msgDebug("\nDeleting inventory id={$row['id']}, {$row['sku']} - {$row['description_short']}");
                dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_prices    WHERE inventory_id={$row['id']}");
                dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_history   WHERE sku='{$row['sku']}'");
                dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_assy_list WHERE ref_id={$row['id']}");
                dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory           WHERE id={$row['id']}");
                $count++;
            }
        }
        return ['rID'=>$rID, 'finished'=>sizeof($result)<$blockSize ? true : false, 'deleted'=>$count];
    }

    /**
     * Synchronizes actual attachment files with the flag in the inventory table
     */
    private function syncAttachments()
    {
        $io = new \bizuno\io();
        $files = $io->folderRead(getModuleCache('inventory', 'properties', 'attachPath'));
        foreach ($files as $attachment) {
            $tID = substr($attachment, 4); // remove rID_
            $rID = substr($tID, 0, strpos($tID, '_'));
            if (empty($rID)) { continue; }
            $exists = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
            if (!$exists) {
                msgDebug("\nDeleting attachment for rID = $rID and file: $attachment");
                $io->fileDelete(getModuleCache('inventory', 'properties', 'attachPath')."/$attachment");
            } elseif (!$exists['attach']) {
                msgDebug("\nSetting attachment flag for id = $rID and file: $attachment");
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['attach'=>'1'], 'update', "id=$rID");
            }
        }
    }
}
