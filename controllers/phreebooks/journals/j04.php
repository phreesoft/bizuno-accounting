<?php
/*
 * PhreeBooks journal class for Journal 4, Vendor Purchase Order
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
 * @version    6.x Last Update: 2024-01-30
 * @filesource /controllers/phreebooks/journals/j04.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journals/common.php", 'jCommon');

class j04 extends jCommon
{
    public $journalID = 4;

    function __construct($main=[], $item=[])
    {
        parent::__construct();
        $this->main = $main;
        $this->items= $item;
    }

/*******************************************************************************************************************/
// START Edit Methods
/*******************************************************************************************************************/
    /**
     * Tailors the structure for the specific journal
     */
    public function getDataItem()
    {
        $structure= dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item', $this->journalID);
        $dbData   = [];
        foreach ($this->items as $row) {
            if ($row['gl_type'] <> 'itm') { continue; } // not an item record, skip
            if (empty($row['qty'])) { $row['qty'] = 0; }
            if (is_null($row['sku'])) { $row['sku'] = ''; } // bug fix for easyui combogrid, doesn't like null value
            $row['description']= str_replace("\n", " ", $row['description']); // fixed bug with \n in description field
		    $row['bal']        = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty)", "item_ref_id={$row['id']} AND gl_type='itm'", false); // so/po - filled
            if (!isset($row['price'])) { $row['price'] = $row['qty'] ? (($row['credit_amount']+$row['debit_amount'])/$row['qty']) : 0; }
            if ($row['sku']) { // now fetch some inventory details for the datagrid
                $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['qty_stock', 'item_weight'], "sku='{$row['sku']}'");
                // Commented this out. Not sure why this was here as it deflates stock by the PO Quantity.
//              $row['qty_stock']  = $inv['qty_stock'] - $row['qty'];
                $row['qty_stock']  = $inv['qty_stock'];
                $row['item_weight']= $inv['item_weight'];
            }
            $dbData[] = $row;
        }
        $map['credit_amount']= ['type'=>'trash'];
        $map['debit_amount'] = ['type'=>'field','index'=>'total'];
        $this->dgDataItem = formatDatagrid($dbData, 'datagridData', $structure, $map);
    }

    /**
     * Customizes the layout for this particular journal
     * @param array $data - Current working structure
     */
    public function customizeView(&$data, $rID=0)
    {
        $fldKeys = ['id','journal_id','so_po_ref_id','terms','override_user','override_pass','recur_id','recur_frequency','item_array','xChild','xAction','store_id',
            'purch_order_id','invoice_num','waiting','closed','terms_text','terms_edit','post_date','terminal_date','rep_id','currency','currency_rate'];
        $fldAddr = ['contact_id','address_id','primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'];
        $data['jsHead']['datagridData'] = $this->dgDataItem;
        $data['datagrid']['item'] = $this->dgOrders('dgJournalItem', 'v');
        $data['datagrid']['item']['columns']['action']['actions']['trash']['display'] = "typeof row.bal==='undefined' || row.bal==0 || row.bal==null"; // do not allow trash if line has received qty
        if ($rID) { unset($data['datagrid']['item']['source']['actions']['insertRow']); } // only allow insert for new orders
        $data['fields']['gl_acct_id']['attr']['value']   = getModuleCache('phreebooks', 'settings', 'vendors', 'gl_payables');
        $data['fields']['terminal_date']['attr']['value']= getTermsDate($data['fields']['terms']['attr']['value'], 'v');
        $data['fields']['waiting']['label'] = lang('confirmed');
//      $isWaiting = isset($data['fields']['waiting']['attr']['checked']) && $data['fields']['waiting']['attr']['checked'] ? '1' : '0';
//      $data['fields']['waiting'] = ['attr'=>  ['type'=>'hidden', 'value'=>$isWaiting]]; // field not used
        $data['divs']['divDetail'] = ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
            'billAD'  => ['order'=>10,'type'=>'panel','key'=>'billAD', 'classes'=>['block25']],
            'shipAD'  => ['order'=>20,'type'=>'panel','key'=>'shipAD', 'classes'=>['block25']],
            'props'   => ['order'=>30,'type'=>'panel','key'=>'props',  'classes'=>['block25']],
            'totals'  => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
            'dgItems' => ['order'=>50,'type'=>'panel','key'=>'dgItems','classes'=>['block99']],
            'divAtch' => ['order'=>90,'type'=>'panel','key'=>'divAtch','classes'=>['block50']],
            'divNotes'=> ['order'=>95,'type'=>'panel','key'=>'notes',  'classes'=>['block50']]]];
        $data['divs']['other']    = ['order'=>70,'type'=>'html','html'=>'<div id="shippingVal"></div>'];
        $data['panels']['billAD'] = ['label'=>lang('bill_to'),'type'=>'address','attr'=>['id'=>'address_b'],'fields'=>$fldAddr,
                'settings'=>['type'=>'v','suffix'=>'_b','search'=>true,'copy'=>true,'update'=>true,'validate'=>true,'fill'=>'both','required'=>true,'store'=>false,'cols'=>false]];
        $data['panels']['shipAD'] = ['label'=>lang('ship_to'),'type'=>'address','attr'=>['id'=>'address_s'],'fields'=>$fldAddr,
                'settings'=>['suffix'=>'_s','search'=>true,'update'=>true,'validate'=>true,'drop'=>true,'cols'=>false]];
        $data['panels']['props']  = ['label'=>lang('details'),'type'=>'fields', 'keys'   =>$fldKeys];
        $data['panels']['totals'] = ['label'=>lang('totals'), 'type'=>'totals', 'content'=>$data['totals']];
        $data['panels']['dgItems']= ['type'=>'datagrid','key'=>'item'];
    }

/*******************************************************************************************************************/
// START Post Journal Function
/*******************************************************************************************************************/
    public function Post()
    {
        msgDebug("\n/********* Posting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        $this->setItemDefaults(); // makes sure the journal_item fields have a value
        $this->unSetCOGSRows(); // they will be regenerated during the post
        if (!$this->postMain())          { return; }
        if (!$this->postItem())          { return; }
        if (!$this->postInventory())     { return; }
        if (!$this->postJournalHistory()){ return; }
        if (!$this->setStatusClosed())   { return; }
        msgDebug("\n*************** end Posting Journal ******************* id = {$this->main['id']}\n\n");
        return true;
    }

    public function unPost()
    {
        msgDebug("\n/********* unPosting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        if (!$this->unPostJournalHistory()){ return; }    // unPost the chart values before inventory where COG rows are removed
        if (!$this->unPostInventory())     { return; }
        if (!$this->unPostMain())          { return; }
        if (!$this->unPostItem())          { return; }
        msgDebug("\n*************** end unPosting Journal ******************* id = {$this->main['id']}\n\n");
        return true;
    }

    /**
     * Get re-post records - applies to journals 3, 4, 9, 10
     * @return array - journal id's that need to be re-posted as a result of this post
     */
    public function getRepostData()
    {
        msgDebug("\n  j04 - Checking for re-post records ... ");
        return $this->getRepostSale();
    }

    /**
     * Post journal item array to journal history table
     * applies to journal 3, 4, 9, 10
     * @return boolean - true
     */
    private function postJournalHistory()
    {
        msgDebug("\n  Posting Chart Balances... end Posting Chart Balances with no action.");
        return true;
    }

    /**
     * unPosts journal item array from journal history table
     * applies to journal 3, 4, 9, 10
     * @return boolean - true
     */
    private function unPostJournalHistory() {
        msgDebug("\n  unPosting Chart Balances... end unPosting Chart Balances with no action.");
        return true;
    }

    /**
     * Post inventory
     * @return boolean true on success, null on error
     */
    private function postInventory()
    {
        msgDebug("\n  Posting Inventory ...");
        foreach ($this->items as $row) {
            if (empty($row['sku']) || $row['gl_type'] <> 'itm') { continue; } // skip all rows without a SKU
            $item_cost = 0;
            if (getModuleCache('inventory', 'settings', 'general', 'auto_cost') == 'PO' && $row['qty']) {
                $item_cost = $row['debit_amount'] / $row['qty'];
            }
            if (!$this->setInvStatus($row['sku'], 'qty_po', $row['qty'], $item_cost, $row['description'])) { return false; }
        }
        msgDebug("\n  end Posting Inventory.");
        return true;
    }

    /**
     * unPost inventory
     * @return boolean true on success, null on error
     */
    private function unPostInventory()
    {
        msgDebug("\n  unPosting Inventory ...");
        foreach ($this->items as $row) {
            if (empty($row['sku']) || $row['gl_type'] <> 'itm') { continue; } // skip all rows without a SKU
            if (!$this->setInvStatus($row['sku'], 'qty_po', -$row['qty'])) { return; }
        }
        msgDebug("\n  end unPosting Inventory.");
        return true;
    }

    /**
     * Tests for CLOSED checkbox manually checked/unchecked to adjust qty on PO and avoid re-posting. If so all other post values are ignored.
     * @param type $data - Journal POST data (and uPost data to test against
     */
    public function quickPost($data)
    {
        $force = false;
        if (!empty($data['main']['closed']) &&  empty($data['uMain']['closed'])) { $force = 'close'; }
        if ( empty($data['main']['closed']) && !empty($data['uMain']['closed'])) { $force = 'open'; }
        if (!$force) { return; }
        $item_array = $this->getStkBalance($data['main']['id']); // user wants to force close/open the journal entry
        foreach ($item_array as $row) {
            $bal = $row['ordered'] - $row['processed'];
            if ($bal <= 0 || empty($row['sku'])) { continue; }
            $type = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "sku='{$row['sku']}'");
            if (strpos(COG_ITEM_TYPES, $type) === false) { continue; }
            $amt = $force=='open' ? $bal : -$bal;
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET qty_po = qty_po + $amt WHERE sku='{$row['sku']}'");
        }
        $pClose= $force=='open' ? '0' : '1';
        $pDate = $force=='open' ? 'null' : biz_date('Y-m-d');
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['closed'=>$pClose,'closed_date'=>$pDate], 'update', "id='{$data['main']['id']}'");
        return true;
    }

    /**
     * Checks and sets/clears the closed status of a journal entry
     * Affects journals - 4, 10
     * @return boolean true
     */
    private function setStatusClosed()
    {
        // closed can occur many ways including:
        //   forced closure through so/po form (from so/po journal - adjust qty on so/po)
        //     NOTE: this cannot happen here as dependent sales/purchases will re-open the entry
        //   all quantities are reduced to zero (from so/po journal - should be deleted instead but it's possible)
        msgDebug("\n  Checking for closed entry.");
        // determine if all items quantities have been entered as zero
        $item_rows_all_zero = true;
        foreach ($this->items as $row) {
            if (!empty($row['qty']) && $row['gl_type']=='itm') { $item_rows_all_zero = false; } // at least one qty is non-zero
        }
        if ($item_rows_all_zero) { $this->setCloseStatus($this->main['id'], true); }
        return true;
    }
}
