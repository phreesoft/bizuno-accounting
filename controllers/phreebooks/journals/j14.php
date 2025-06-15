<?php
/*
 * PhreeBooks journal class for Journal 14, Inventory Assembly
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
 * @version    6.x Last Update: 2022-11-29
 * @filesource /controllers/phreebooks/journals/j14.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journals/common.php", 'jCommon');

class j14 extends jCommon
{
    protected $journalID = 14;

    function __construct($main=[], $item=[])
    {
        parent::__construct();
        $this->main = $main;
        $this->items = $item;
    }

/*******************************************************************************************************************/
// START Edit Methods
/*******************************************************************************************************************/
    /**
     * Tailors the structure for the specific journal
     */
    public function getDataItem($rID=0) {
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item', $this->journalID);
        $structure['sku']['attr']['type']        = 'inventory';
        $structure['sku']['defaults']['idField'] = "'sku'";
        $structure['sku']['defaults']['url']     = "'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&filter=assy&clr=1&bID='+jqBiz('#store_id').val()";
        $structure['sku']['defaults']['callback']= "bizTextSet('description', data.description_short);
    jqBiz('#gl_account').val(data.gl_inv);
    jqBiz('#gl_acct_id').val(data.gl_inv);
    bizNumSet('qty_stock', data.qty_stock);
    jqBiz('#dgJournalItem').datagrid({ url:'".BIZUNO_AJAX."&bizRt=inventory/main/managerBOMList&rID='+data.id });
    bizGridReload('dgJournalItem');
    bizNumSet('qty', 1);";
        $structure['qty_stock']    = ['order'=>80,'break'=>true,'options'=>['width'=>100],'label'=>pullTableLabel('inventory', 'qty_stock'),'attr'=>['type'=>'float','readonly'=>'readonly']];
        $structure['balance']      = ['order'=>90,'break'=>true,'options'=>['width'=>100],'label'=>lang('balance'),'attr'=>['type'=>'float', 'readonly'=>'readonly']];
        $structure['gl_account']['attr']['type'] = 'hidden';
        $structure['sku']['order'] = 15;
        $structure['qty']['order'] = 85;
        $structure['trans_code']['order'] = 95;
        $structure['qty']['label'] = lang('qty_to_assemble');
        $structure['qty']['events']= ['onChange'=>"assyUpdateBalance();"];
        $structure['description']['attr']['type'] = 'text';
        if ($rID) { // merge the data
            $dbItem = dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id='$rID' AND gl_type='asy'");
            dbStructureFill($structure, $dbItem);
            $stock = dbGetValue(BIZUNO_DB_PREFIX."inventory", ['qty_stock', 'description_short'], "sku='{$dbItem['sku']}'");
            $structure['qty_stock']['attr']['value']  = $stock['qty_stock'] - $dbItem['qty'];
            $structure['balance']['attr']['value']    = $stock['qty_stock'];
            $structure['description']['attr']['value']= $stock['description_short'];
            // below doesn't work, probably shoudl add to jsHead and reference variable with data:var
//            $data = [['sku'=>$dbItem['sku'],'description_short'=>$stock['description_short']]];
//            $structure['sku']['defaults']['data']   = json_encode($data);
        }
        $this->items = $structure;
    }

    /**
     * Customizes the layout for this particular journal
     * @param array $data - Current working structure
     * @param integer $rID - current db record ID
     */
    public function customizeView(&$data)
    {
        $fldKeys = ['id','journal_id','gl_account','gl_acct_id','recur_id','item_array','xChild','xAction','store_id',
            'sku','description','post_date','invoice_num','trans_code','qty','qty_stock','balance'];
        unset($data['toolbars']['tbPhreeBooks']['icons']['print'],$data['toolbars']['tbPhreeBooks']['icons']['recur'],$data['toolbars']['tbPhreeBooks']['icons']['payment']);
        $data['datagrid']['item'] = $this->dgAssy('dgJournalItem'); // place different as this dg is on the right, not bottom
        // Just pull in some of the item structure
        $data['fields']['sku']        = $this->items['sku'];
        $data['fields']['description']= $this->items['description'];
        $data['fields']['gl_account'] = $this->items['gl_account'];
        $data['fields']['trans_code'] = $this->items['trans_code'];
        $data['fields']['qty']        = $this->items['qty'];
        $data['fields']['qty_stock']  = $this->items['qty_stock'];
        $data['fields']['balance']    = $this->items['balance'];
        $isWaiting = isset($data['fields']['waiting']['attr']['checked']) && $data['fields']['waiting']['attr']['checked'] ? '1' : '0';
        $data['fields']['waiting']    = ['attr'=>['type'=>'hidden','value'=>$isWaiting]];
        // display the users that posted the assembly
        if (!empty($data['fields']['admin_id']['attr']['value'])) {
            $fldKeys[] = 'user_title';
            $html = 'Posted by User: '.viewFormat($data['fields']['admin_id']['attr']['value'], 'rep_id');
            $data['fields']['user_title'] = ['order'=>99, 'html'=>$html, 'attr'=>['type'=>'raw']];
        }
        // reorganize some fields
        $data['fields']['gl_acct_id']['attr']['type'] = 'hidden';
        $data['fields']['description']['order']= 45;
        $data['fields']['description']['options']['width'] = 300;
        $data['divs']['divDetail']   = ['order'=>50,'type'=>'divs',   'classes'=>['areaView'],'divs'=>[
            'props'  => ['order'=>20,'type'=>'panel','key'=>'props',  'classes'=>['block50']],
            'dgItems'=> ['order'=>30,'type'=>'panel','key'=>'dgItems','classes'=>['block50']],
            'totals' => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
            'divAtch'=> ['order'=>90,'type'=>'panel','key'=>'divAtch','classes'=>['block50']]]];

        $data['panels']['props']  = ['label'=>lang('details'),'type'=>'fields','keys'=>$fldKeys];
        $data['panels']['dgItems']= ['type'=>'datagrid','key'=>'item'];
        $data['panels']['totals'] = ['type'=>'totals',  'content'=>$data['totals']];
        $data['jsHead']['preSubmit'] = "function preSubmit() {
    if (sku == '') return false;
    var item  = {sku:jqBiz('#sku').val(),qty:jqBiz('#qty').val(),description:jqBiz('#description').val(),total:0,gl_account:jqBiz('#gl_account').val()};
    var items = {total:1,rows:[item]};
    var serializedItems = JSON.stringify(items);
    jqBiz('#item_array').val(serializedItems);
    if (!formValidate()) return false;
    return true;
}";
        $data['jsReady']['init'] = "ajaxForm('frmJournal');";
        $data['jsReady']['focus']= "bizFocus('sku');";
    }

/*******************************************************************************************************************/
// START Post Journal Function
/*******************************************************************************************************************/
    public function Post()
    {
        msgDebug("\n/********* Posting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        $desc = lang('journal_main_journal_id_14');
        $desc.= clean('qty','float','post') ? " (".clean('qty','float','post').")" : '';
        $desc.= clean('sku','text', 'post') ? " ".clean('sku','text','post')." -" : '';
        $desc.= " ".clean('description','text','post');
        $this->main['description'] = $desc;
        $this->main['closed'] = '1';
        $this->main['closed_date'] = $this->main['post_date'];
        $this->setItemDefaults(); // makes sure the journal_item fields have a value
        $this->unSetCOGSRows(); // they will be regenerated during the post
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main', 14);
        validateData($structure, $this->main);
        if (!$this->postMain())              { return; }
        if (!$this->postItem())              { return; }
        if (!$this->postInventory())         { return; }
        if (!$this->postJournalHistory())    { return; }
        if (!$this->setStatusClosed('post')) { return; }
        msgDebug("\n*************** end Posting Journal ******************* id = {$this->main['id']}\n\n");
        return true;
    }

    public function unPost()
    {
        msgDebug("\n/********* unPosting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        if (!$this->unPostJournalHistory())    { return; }    // unPost the chart values before inventory where COG rows are removed
        if (!$this->unPostInventory())         { return; }
        if (!$this->unPostMain())              { return; }
        if (!$this->unPostItem())              { return; }
        if (!$this->setStatusClosed('unPost')) { return; } // check to re-open predecessor entries
        msgDebug("\n*************** end unPosting Journal ******************* id = {$this->main['id']}\n\n");
        return true;
    }

    /**
     * Get re-post records - applies to journals 6, 7, 12, 13, 14, 15, 16, 19, 21
     * @return array - journal id's that need to be re-posted as a result of this post
     */
    public function getRepostData()
    {
        msgDebug("\n  j14 - Checking for re-post records ... ");
        $out1 = [];
        $out2 = array_merge($out1, $this->getRepostInv());
        $out3 = array_merge($out2, $this->getRepostInvCOG());
//      $out4 = array_merge($out3, $this->getRepostInvAsy());
        $out5 = array_merge($out3, $this->getRepostPayment());
        msgDebug("\n  j14 - End Checking for Re-post.");
        return $out5;
    }

    /**
     * Post journal item array to journal history table
     * applies to journal 2, 6, 7, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22
     * @return boolean - true
     */
    private function postJournalHistory()
    {
        msgDebug("\n  Posting Chart Balances...");
        if ($this->setJournalHistory()) { return true; }
    }

    /**
     * unPosts journal item array from journal history table
     * applies to journal 2, 6, 7, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22
     * @return boolean - true
     */
    private function unPostJournalHistory() {
        msgDebug("\n  unPosting Chart Balances...");
        if ($this->unSetJournalHistory()) { return true; }
    }

    /**
     * Post inventory
     * @return boolean true on success, null on error
     */
    private function postInventory()
    {
        msgDebug("\n  Posting Inventory ...");
        $str_field = 'qty_stock';
        // adjust inventory stock status levels (also fills inv_list array)
        $item_rows_to_process = count($this->items); // NOTE: variable needs to be here because $this->items may grow within for loop (COGS)
// the cogs rows are added after this loop ..... the code below needs to be rewritten
        for ($i = 0; $i < $item_rows_to_process; $i++) {
            if (!in_array($this->items[$i]['gl_type'], ['itm','adj','asy','xfr'])) { continue; }
            if (isset($this->items[$i]['sku']) && $this->items[$i]['sku'] <> '') {
                $inv_list = $this->items[$i];
                $inv_list['price'] = $this->items[$i]['qty'] ? (($this->items[$i]['debit_amount'] + $this->items[$i]['credit_amount']) / $this->items[$i]['qty']) : 0;
                $assy_cost = $this->setAssyCost($inv_list); // for assembly parts list
                if ($assy_cost === false) { return; }// there was an error
            }
        }
        // update inventory status
        foreach ($this->items as $row) {
            if (empty($row['sku'])) { continue; } // skip all rows without a SKU
            $item_cost = $full_price = 0;
            // commented out as there is a tool now plus this is not the latest cost but based on COGS which may be very different from current price.
//          if ($row['gl_type'] == 'asy' && $row['qty'] > 0) { $item_cost = $row['debit_amount'] / $row['qty']; } // only for the item being assembled
            if (!$this->setInvStatus($row['sku'], $str_field, $row['qty'], $item_cost, $row['description'], $full_price)) { return false; }
        }
        // build the cogs item rows
        $this->setInvCogItems();
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
        if (!$this->rollbackCOGS()) { return false; }
        for ($i = 0; $i < count($this->items); $i++) {
            if (!isset($this->items[$i]['sku']) || !$this->items[$i]['sku']) { continue; }
            if (!$this->setInvStatus($this->items[$i]['sku'], 'qty_stock', -$this->items[$i]['qty'])) { return; }
        }
        if ($this->main['so_po_ref_id'] > 0) { $this->setInvRefBalances($this->main['so_po_ref_id'], false); }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_history WHERE ref_id = {$this->main['id']}");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_cogs_usage WHERE journal_main_id={$this->main['id']}");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_cogs_owed  WHERE journal_main_id={$this->main['id']}");
        msgDebug("\n  end unPosting Inventory.");
        return true;
    }

    /**
     * Checks and sets/clears the closed status of a journal entry
     * Affects journals - 3, 7, 9, 13, 14, 15, 16
     * @param string $action - [default: 'post']
     * @return boolean true
     */
    private function setStatusClosed($action='post')
    {
        msgDebug("\n  Checking for closed entry. action = $action, returning with no action.");
        return true;
    }

    /**
     * Creates the grid structure for inventory assembly line items
     * @param string $name - DOM field name
     * @return array - grid structure
     */
    private function dgAssy($name)
    {
        return ['id' => $name,
            'attr'   => ['rownumbers'=>true,'showFooter'=>true,'pagination'=>false], // override bizuno default
            'events' => ['rowStyler'=>"function(index, row) { if (row.qty_stock-row.qty_required<0) return {class:'row-inactive'}; }"],
            'columns'=> [
                'qty'          => ['order'=> 0,'attr' =>['hidden'=>true]],
                'sku'          => ['order'=>20,'label'=>lang('sku'),'attr'=>['align'=>'center']],
                'description'  => ['order'=>30,'label'=>lang('description')],
                'qty_stock'    => ['order'=>40,'label'=>pullTableLabel('inventory','qty_stock'),'attr'=>['align'=>'right'],
                    'events'=>['formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'qty_required' => ['order'=>50,'label'=>lang('qty_required'),'attr'=>['align'=>'right'],
                    'events'=>['formatter'=>"function(value,row){ return formatNumber(value); }"]]]];
    }
}
