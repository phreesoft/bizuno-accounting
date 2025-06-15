<?php
/*
 * Administration functions for PhreeBooks module
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
 * @version    6.x Last Update: 2023-10-12
 * @filesource /controllers/phreebooks/admin.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');

class phreebooksAdmin {

    public $moduleID = 'phreebooks';

    function __construct() {
        $this->lang = getLang($this->moduleID);
        $this->totalMethods = ['balance', 'balanceBeg', 'balanceEnd', 'debitcredit', 'discountChk', 'subtotal', 'subtotalChk', 'tax_item', 'total'];
        $values = getModuleCache('phreebooks', 'chart', 'defaults', getDefaultCurrency());
        $this->glDefaults = [
            'asset'        => isset($values[6]) ? $values[6] : '',
            'cash'         => isset($values[0]) ? $values[0] : '',
            'cash_ach'     => isset($values[0]) ? $values[0] : '',
            'expense'      => isset($values[34])? $values[34]: '',
            'inventory'    => isset($values[4]) ? $values[4] : '',
            'liability'    => isset($values[22])? $values[22]: '',
            'payables'     => isset($values[20])? $values[20]: '',
            'receivables'  => isset($values[2]) ? $values[2] : '',
            'sales'        => isset($values[30])? $values[30]: ''];
        $this->assets    = [0, 2, 4, 6, 8, 12, 32, 34]; // gl_account types that are assets
        $this->settings  = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure = [
            'dirMethods'=> 'totals',
            'attachPath'=> 'data/phreebooks/uploads/',
            'menuBar'   => ['child'=>[
                'banking' => ['order'=>40,'label'=>lang('banking'),'group'=>'bnk','icon'=>'bank','events'=>['onClick'=>"hrefClick('bizuno/main/bizunoHome&menuID=banking');"],'child'=>[
                    'j17_mgr' => ['order'=>70,'label'=>lang('journal_main_journal_id_17'),'icon'=>'payment',   'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=17');"]],
                    'j18_mgr' => ['order'=>10,'label'=>lang('journal_main_journal_id_18'),'icon'=>'payment',   'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=18');"]],
                    'j20_bulk'=> ['order'=>55,'label'=>lang('phreebooks_manager_bulk'),   'icon'=>'bank-check','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=20&bizAction=bulk');"]],
                    'j20_mgr' => ['order'=>50,'label'=>lang('journal_main_journal_id_20'),'icon'=>'bank-check','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=20');"]],
                    'j22_mgr' => ['order'=>20,'label'=>lang('journal_main_journal_id_22'),'icon'=>'bank-check','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=22');"]],
                    'register'=> ['order'=>80,'label'=>lang('phreebooks_register'),       'icon'=>'register',  'events'=>['onClick'=>"hrefClick('phreebooks/register/manager');"]],
                    'recon'   => ['order'=>85,'label'=>lang('phreebooks_recon'),          'icon'=>'apply',     'events'=>['onClick'=>"hrefClick('phreebooks/reconcile/manager');"]],
                    'rpt_bank'=> ['order'=>99,'label'=>lang('reports'),                   'icon'=>'mimeDoc',   'events'=>['onClick'=>"hrefClick('phreeform/main/manager&gID=bnk');"]]]],
                'customers' => ['child'=>[
                    'sales' => ['order'=>45,'label'=>lang('journal_main_journal_id_12_mgr'), 'icon'=>'sales', 'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=12&mgr=1');"],'child'=>[
                        'j9_mgr' => ['order'=>30,'label'=>lang('journal_main_journal_id_9'), 'icon'=>'quote', 'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=9');"]],
                        'j10_mgr'=> ['order'=>20,'label'=>lang('journal_main_journal_id_10'),'icon'=>'order', 'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=10');"]],
                        'j12_mgr'=> ['order'=>10,'label'=>lang('journal_main_journal_id_12'),'icon'=>'sales', 'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=12');"]],
                        'j13_mgr'=> ['order'=>40,'label'=>lang('journal_main_journal_id_13'),'icon'=>'credit','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=13');"]]]]]],
                'inventory' => ['child'=>[
                    'j14_mgr' => ['order'=>35,'label'=>lang('journal_main_journal_id_14'),'icon'=>'tools',  'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=14');"]],
                    'j16_mgr' => ['order'=>50,'label'=>lang('journal_main_journal_id_16'),'icon'=>'inv-adj','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=16');"]]]],
                'ledger' => ['order'=>50,'label'=>lang('general_ledger'),'group'=>'gl','icon'=>'journal','events'=>['onClick'=>"hrefClick('bizuno/main/bizunoHome&menuID=ledger');"],'child'=>[
                    'j2_mgr'  => ['order'=>10, 'label'=>lang('journal_main_journal_id_2'),'icon'=>'journal','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=2');"]],
//                  'cashflow'=> ['order'=>80,'label'=>lang('cash_flow')),       'icon'=>'linechart','events'=>['onClick'=>"hrefClick('phreebooks/budget/cashFlow');"]],
                    'budget'  => ['order'=>90,'label'=>lang('phreebooks_budget'),'icon'=>'budget',   'events'=>['onClick'=>"hrefClick('phreebooks/budget/manager');"]],
                    'rpt_jrnl'=> ['order'=>99,'label'=>lang('reports'),          'icon'=>'mimeDoc',  'events'=>['onClick'=>"hrefClick('phreeform/main/manager&gID=gl');"]]]],
                'tools' => ['child'=>[
                    'j0_mgr'  => ['order'=>75,'label'=>lang('journal_main_journal_id_0'),'icon'=>'search','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=0');"]]]],
                'vendors' => ['child'=>[
                    'purch' => ['order'=>45,'label'=>lang('journal_main_journal_id_6_mgr'), 'icon'=>'purchase','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=6&mgr=1');"],'child'=>[
                        'j3_mgr' => ['order'=>30,'label'=>lang('journal_main_journal_id_3'),'icon'=>'quote',   'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=3');"]],
                        'j4_mgr' => ['order'=>20,'label'=>lang('journal_main_journal_id_4'),'icon'=>'order',   'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=4');"]],
                        'j6_mgr' => ['order'=>10,'label'=>lang('journal_main_journal_id_6'),'icon'=>'purchase','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=6');"]],
                        'j7_mgr' => ['order'=>40,'label'=>lang('journal_main_journal_id_7'),'icon'=>'credit',  'events'=>['onClick'=>"hrefClick('phreebooks/main/manager&jID=7');"]]]]]]]],
            'hooks' => [
                'bizuno' => [
                    'admin' => ['loadBrowserSession'=>['order'=>50]],
                    'roles' => [
                        'edit'=>['order'=>10,'method'=>'rolesEdit'],
                        'save'=>['order'=>10,'method'=>'rolesSave']],
                    'users' => [
                        'edit'=>['order'=>10,'method'=>'usersEdit'],
                        'save'=>['order'=>10,'method'=>'usersSave']]]],
            'api' => ['path' => 'phreebooks/api/journalAPI', 'attr' => ['jID' => 12]]]; // default to import sales
        $this->phreeformProcessing = [
            'bnkReg'    => ['text'=>lang('bank_register_format'),            'group'=>lang('banking')],
            'bnkCard'   => ['text'=>lang('bank_cc_card_type'),               'group'=>lang('banking')],
            'bnkHint'   => ['text'=>lang('bank_cc_card_hint'),               'group'=>lang('banking')],
            'bnkCode'   => ['text'=>lang('bank_cc_card_code'),               'group'=>lang('banking')],
            'rep_id'    => ['text'=>lang('contacts_rep_id_c'),               'module'=>'bizuno','function'=>'viewFormat'],
            'taxTitle'  => ['text'=>lang('tax_rates_title'),                 'module'=>'bizuno','function'=>'viewFormat'],
            'cTerms'    => ['text'=>lang('terms')." (contact ID)",           'module'=>'bizuno','function'=>'viewFormat'],
            'terms'     => ['text'=>lang('terms')." (".lang('customers').")",'module'=>'bizuno','function'=>'viewFormat'],
            'terms_v'   => ['text'=>lang('terms')." (".lang('vendors').")",  'module'=>'bizuno','function'=>'viewFormat'],
            'AgeCur'    => ['text'=>$this->lang['pb_inv_age_00']],
            'Age30'     => ['text'=>$this->lang['pb_inv_age_30']],
            'Age60'     => ['text'=>$this->lang['pb_inv_age_60']],
            'Age90'     => ['text'=>$this->lang['pb_inv_age_90']],
            'Age120'    => ['text'=>$this->lang['pb_inv_age_120']],
            'Age61'     => ['text'=>$this->lang['pb_inv_age_61']],
            'Age91'     => ['text'=>$this->lang['pb_inv_age_91']],
            'Age121'    => ['text'=>$this->lang['pb_inv_age_121']],
            'age_00'    => ['text'=>$this->lang['pb_cnt_age_00']],
            'age_30'    => ['text'=>$this->lang['pb_cnt_age_30']],
            'age_60'    => ['text'=>$this->lang['pb_cnt_age_60']],
            'age_90'    => ['text'=>$this->lang['pb_cnt_age_90']],
            'age_120'   => ['text'=>$this->lang['pb_cnt_age_120']],
            'age_61'    => ['text'=>$this->lang['pb_cnt_age_61']],
            'age_91'    => ['text'=>$this->lang['pb_cnt_age_91']],
            'age_121'   => ['text'=>$this->lang['pb_cnt_age_121']],
            'begBal'    => ['text'=>lang('beginning_balance')],
            'endBal'    => ['text'=>lang('ending_balance')],
            'subTotal'  => ['text'=>lang('subtotal')],
            'invBalance'=> ['text'=>lang('balance')],
            'invCOGS'   => ['text'=>lang('inventory_cogs')],
            'invRefNum' => ['text'=>lang('journal_main_invoice_num_2')],
            'invUnit'   => ['text'=>$this->lang['pb_inv_unit']],
            'itmTaxAmt' => ['text'=>$this->lang['pb_line_item_tax']],
            'orderCOGS' => ['text'=>lang('pb_order_cogs')],
            'pmtDate'   => ['text'=>lang('payment_due_date')],
            'pmtDisc'   => ['text'=>lang('payment_discount')],
            'pmtNet'    => ['text'=>lang('payment_net_due')],
            'paymentDue'=> ['text'=>lang('payment_due')],
            'paymentRcv'=> ['text'=>lang('payment_received')],
            'paymentRef'=> ['text'=>lang('payment_reference')],
            'ship_bal'  => ['text'=>lang('shipped_balance')],
            'shipBalVal'=> ['text'=>lang('shipped_balance_value')],
            'ship_prior'=> ['text'=>lang('shipped_prior')],
            'taxJrnl'   => ['text'=>$this->lang['pb_tax_by_journal']],
            'taxRate'   => ['text'=>lang('tax_rates_tax_rate')." (taxID)"],
            'ttlJrnl'   => ['text'=>$this->lang['pb_total_by_journal']],
            'soStatus'  => ['text'=>$this->lang['pb_so_status']],
            'isCur'     => ['text'=>lang('gl_acct_type_30')],
            'isYtd'     => ['text'=>$this->lang['pb_is_ytd']],
            'isBdgt'    => ['text'=>lang('budget')],
            'isBytd'    => ['text'=>$this->lang['pb_is_budget_ytd']],
            'isLcur'    => ['text'=>$this->lang['ly_actual']],
            'isLytd'    => ['text'=>$this->lang['pb_is_last_ytd']],
            'isLBgt'    => ['text'=>$this->lang['ly_budget']],
            'isLBtd'    => ['text'=>$this->lang['pb_is_last_bdgt_ytd']]];
        setProcessingDefaults($this->phreeformProcessing, $this->moduleID, $this->lang['title']);
        $this->phreeformFormatting = [
            'j_desc'    => ['text'=>lang('journal_main_journal_id'),'module'=>'bizuno','function'=>'viewFormat'],
            'glType'    => ['text'=>lang('gl_acct_type'),           'module'=>'bizuno','function'=>'viewFormat'],
            'glTitle'   => ['text'=>lang('gl_acct_title'),          'module'=>'bizuno','function'=>'viewFormat'],
            'glActive'  => ['text'=>lang('gl_acct_active'),         'module'=>'bizuno','function'=>'viewFormat']];
        setProcessingDefaults($this->phreeformFormatting, $this->moduleID, $this->lang['title']);
        $this->notes = [$this->lang['note_phreebooks_install_1'],$this->lang['note_phreebooks_install_2'],$this->lang['note_phreebooks_install_3']];
    }

    /**
     * Sets the user defined settings structure
     * @return array - structure used in the main settings tab
     */
    public function settingsStructure() {
        $data = [
            'general'  => ['order'=>10,'label'=>lang('general'),'fields'=>[
                'round_tax_auth' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'shipping_taxed' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'isolate_stores' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'upd_rep_terms'  => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]],
            'customers'=> ['order'=>20,'label'=>lang('customers'),'fields'=>[
                'gl_receivables' => ['attr'=>['type'=>'ledger','id'=>'customers_gl_receivables', 'value'=>$this->glDefaults['receivables']]],
                'gl_sales'       => ['attr'=>['type'=>'ledger','id'=>'customers_gl_sales',       'value'=>$this->glDefaults['sales']]],
                'gl_cash'        => ['attr'=>['type'=>'ledger','id'=>'customers_gl_cash',        'value'=>$this->glDefaults['cash']]],
                'gl_cash_ach'    => ['attr'=>['type'=>'ledger','id'=>'customers_gl_cash_ach',    'value'=>$this->glDefaults['cash_ach']]],
                'gl_discount'    => ['attr'=>['type'=>'ledger','id'=>'customers_gl_discount',    'value'=>$this->glDefaults['sales']]],
                'gl_deposit'     => ['attr'=>['type'=>'ledger','id'=>'customers_gl_deposit',     'value'=>$this->glDefaults['liability']]],
                'gl_liability'   => ['attr'=>['type'=>'ledger','id'=>'customers_gl_liability',   'value'=>$this->glDefaults['liability']]],
                'gl_expense'     => ['attr'=>['type'=>'ledger','id'=>'customers_gl_expense',     'value'=>$this->glDefaults['expense']]],
                'tax_rate_id_c'  => ['label'=>lang('sales_tax'),'defaults'=>['type'=>'c','target'=>'contacts'], 'attr'=>['type'=>'tax','value'=>0]],
                'terms_text'     => ['break'=>false],
                'terms'          => ['break'=>false,'attr'=>['type'=>'hidden','value'=>'2']],
                'terms_edit'     => ['icon'=>'settings','label'=>lang('terms'),'attr'=>['type'=>'hidden'],'events'=>['onClick'=>"jsonAction('contacts/main/editTerms&type=c&prefix=customers_&default=1', 0, jqBiz('#customers_terms').val());"]],
                'show_status'    => ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'include_all'    => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'ck_dup_po'      => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]],
            'vendors'  => ['order'=>30,'label'=>lang('vendors'),'fields'=>[
                'gl_payables'    => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_payables',    'value'=>$this->glDefaults['payables']]],
                'gl_purchases'   => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_purchases',   'value'=>$this->glDefaults['inventory']]],
                'gl_cash'        => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_cash',        'value'=>$this->glDefaults['cash']]],
                'gl_cash_ach'    => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_cash_ach',    'value'=>$this->glDefaults['cash_ach']]],
                'gl_discount'    => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_discount',    'value'=>$this->glDefaults['payables']]],
                'gl_deposit'     => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_deposit',     'value'=>$this->glDefaults['asset']]],
                'gl_liability'   => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_liability',   'value'=>$this->glDefaults['liability']]],
                'gl_expense'     => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_expense',     'value'=>$this->glDefaults['expense']]],
                'tax_rate_id_v'  => ['label'=>lang('purchase_tax'),'defaults'=>['type'=>'v','target'=>'contacts'], 'attr'=>['type'=>'tax','value'=>0]],
                'terms_text'     => ['break'=>false],
                'terms'          => ['break'=>false,'attr'=>['type'=>'hidden','value'=>'3:0:0:30:1000.00']],
                'terms_edit'     => ['icon'=>'settings','label'=>lang('terms'),'attr'=>['type'=>'hidden'],'events'=>['onClick'=>"jsonAction('contacts/main/editTerms&type=v&prefix=vendors_&default=1', 0, jqBiz('#vendors_terms').val());"]],
                'show_status'    => ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'rm_item_ship'   => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]]];
        settingsFill($data, $this->moduleID);
        $data['customers']['fields']['terms_text']['attr']['value']= viewTerms($data['customers']['fields']['terms']['attr']['value'],true, 'c');
        $data['vendors']['fields']['terms_text']['attr']['value']  = viewTerms($data['vendors']['fields']['terms']['attr']['value'],  true, 'v');
        return $data;
    }

    /**
     * Special initialization actions set during first startup and cache creation
     * @return boolean true
     */
    public function initialize() {
        periodAutoUpdate();
        return true; // successful
    }

    /**
     * User settings main entry page
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminHome(&$layout = []) {
        if (!$security = validateSecurity('bizuno', 'admin', 1)) { return; }
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/currency.php", 'phreebooksCurrency');
        $currency= new phreebooksCurrency();
        $repost  = $this->getViewRepost();
        $fields  = [
            'glTestDesc'   => ['order'=>10,'html'=>$this->lang['pbtools_gl_test_desc'],'attr'=>['type'=>'raw']],
            'btnRepairGL'  => ['order'=>20,'attr'=>['type'=>'button','value'=>lang('start')],'events'=>['onClick'=>"jsonAction('phreebooks/tools/glRepair');"]],
            'pruneCogsDesc'=> ['order'=>10,'html'=>$this->lang['pb_prune_cogs_desc'],'attr'=>['type'=>'raw']],
            'btnPruneCogs' => ['order'=>20,'attr'=>['type'=>'button', 'value'=>lang('start')],'events'=>['onClick'=>"jsonAction('phreebooks/tools/pruneCogs');"]],
            'cleanAtchDesc'=> ['order'=>10,'html'=>$this->lang['pb_attach_clean_desc'],'attr'=>['type'=>'raw']],
            'dateAtchCln'  => ['order'=>20,'attr'=> ['type'=>'date',  'value'=>localeCalculateDate(biz_date('Y-m-d'), 0, -6)]],
            'btnAtchCln'   => ['order'=>30,'attr'=> ['type'=>'button','value'=>lang('start')],
                'events' => ['onClick'=>"if (confirm('".$this->lang['pb_attach_clean_confirm']."')) jsonAction('phreebooks/tools/cleanAttach', 0, jqBiz('#dateAtchCln').datebox('getValue'));"]],
            'purgeGlDesc'  => ['order'=>10,'html'=>$this->lang['msg_gl_db_purge_confirm'],'attr'=>['type'=>'raw']],
            'purge_db'     => ['order'=>20,'styles'=>["text-align"=>"right"],'attr'=>['size'=>"7"]],
            'btn_purge'    => ['order'=>30,'attr'=>['type'=>'button', 'value'=>$this->lang['phreebooks_purge_db_journal']],
                'events' => ['onClick'=>"if (confirm('".$this->lang['msg_gl_db_purge_confirm']."')) jsonAction('phreebooks/tools/glPurge', 0, jqBiz('#purge_db').val());"]]];
        $data    = [
            'tabs'    => ['tabAdmin'=>['divs'=>[
                'tabGL'    => ['order'=>20,'label'=>lang('phreebooks_chart_of_accts'),'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=phreebooks/chart/manager'"]],
                'tabCur'   => ['order'=>30,'label'=>lang('currencies'),     'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=phreebooks/currency/manager'"]],
                'tabTaxc'  => ['order'=>40,'label'=>lang('sales_tax'),      'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=phreebooks/tax/manager&type=c'"]],
                'tabTaxv'  => ['order'=>50,'label'=>lang('purchase_tax'),   'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=phreebooks/tax/manager&type=v'"]],
                'tabFY'    => ['order'=>80,'label'=>lang('fiscal_calendar'),'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=phreebooks/admin/managerFY'"]],
                'tabTools' => ['order'=>90,'label'=>$this->lang['journal_tools'],'type'=>'divs','divs'=>[
                    'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                        'testGL'   => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'testGL'],
                        'cleanAtch'=> ['order'=>20,'type'=>'panel','classes'=>['block33'],'key'=>'cleanAtch'],
                        'pruneCOGS'=> ['order'=>40,'type'=>'panel','classes'=>['block33'],'key'=>'pruneCOGS'],
                        'repostGL' => ['order'=>70,'type'=>'panel','classes'=>['block66'],'key'=>'repostGL'],
                        'purgeGL'  => ['order'=>90,'type'=>'panel','hidden'=> $security>4?false:true,'classes'=>['block33'],'key'=>'purgeGL']]]]]]]],
            'panels'  => [
                'repostGL' => ['label'=>$this->lang['phreebooks_repost_title'],'type'=>'html',  'html'=>$repost],
                'testGL'   => ['label'=>$this->lang['title_gl_test'],          'type'=>'fields','keys'=>['glTestDesc','btnRepairGL']],
                'pruneCOGS'=> ['label'=>$this->lang['pb_prune_cogs_title'],    'type'=>'fields','keys'=>['pruneCogsDesc','btnPruneCogs']],
                'cleanAtch'=> ['label'=>$this->lang['pb_attach_clean_title'],  'type'=>'fields','keys'=>['cleanAtchDesc','dateAtchCln','btnAtchCln']],
                'purgeGL'  => ['label'=>$this->lang['msg_gl_db_purge'],        'type'=>'fields','keys'=>['purgeGlDesc','purge_db','btn_purge']]],
            'datagrid'=> ['dgCurrency'  =>$currency->dgCurrency('dgCurrency', $security)],
            'forms'   => [
                'frmCurrency'=> ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/currency/save"]]],
            'fields'  => $fields,
            'jsHead'  => ['dataCurrency'=>"var dataCurrency = ".json_encode(array_values(getModuleCache('phreebooks','currency','iso'))).";"],
            'jsBody'  => ['init'=>"jqBiz('#repost_begin').datebox({ required:true }); jqBiz('#repost_end').datebox({ required:true });"]]; // ajaxForm('frmImpTax');
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
    }

    private function getViewRepost()
    {
        $repost      = ['position'=>'after','attr'=>['type'=>'checkbox']]; // label comes later
        $repost_begin= ['attr'=>['type'=>'date',   'value'=>biz_date('Y-m-d')]];
        $repost_end  = ['attr'=>['type'=>'date',   'value'=>biz_date('Y-m-d')]];
        $btn_repost  = ['icon'=>'save','size'=>'large','events'=>['onClick'=>"divSubmit('phreebooks/tools/glRepostBulk', 'glRepost');"]];

        $output  = '<div id="glRepost">';
        $output .= " <p>".$this->lang['msg_gl_repost_journals_confirm']."</p>\n";
        $output .= ' <table style="border-style:none;margin-left:auto;margin-right:auto;">'."\n";
        $output .= "  <tbody>\n";
        $output .= '   <tr class="panel-header">'."\n";
        $output .= "    <th>".lang('gl_acct_type_2')."</th>\n<th>".lang('gl_acct_type_20')."</th>\n<th>".lang('gl_acct_type_0')."</th>\n<th>".lang('gl_acct_type_4')."</th>\n<th>&nbsp;</th>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_main_journal_id_9');
        $output .= "    <td>".html5('jID[9]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_3');
        $output .= "    <td>".html5('jID[3]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_2');
        $output .= "    <td>".html5('jID[2]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_14');
        $output .= "    <td>".html5('jID[14]', $repost)."</td>\n";
        $output .= "    <td>&nbsp;</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_main_journal_id_10');
        $output .= "    <td>".html5('jID[10]', $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_4');
        $output .= "    <td>".html5('jID[4]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_18');
        $output .= "    <td>".html5('jID[18]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_16');
        $output .= "    <td>".html5('jID[16]', $repost)."</td>\n";
        $output .= "    <td>&nbsp;</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_main_journal_id_12');
        $output .= "    <td>".html5('jID[12]', $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_6');
        $output .= "    <td>".html5('jID[6]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_20');
        $output .= "    <td>".html5('jID[20]', $repost)."</td>\n";
        $output .= '    <td style="text-align:right">'.lang('start')."</td>\n";
        $output .= "    <td>".html5('repost_begin',  $repost_begin)."</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_main_journal_id_13');
        $output .= "    <td>".html5('jID[13]', $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_7');
        $output .= "    <td>".html5('jID[7]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_17');
        $output .= "    <td>".html5('jID[17]', $repost)."</td>\n";
        $output .= '    <td style="text-align:right">'.lang('end')."</td>\n";
        $output .= "    <td>".html5('repost_end', $repost_end)."</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_main_journal_id_19');
        $output .= "    <td>".html5('jID[19]', $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_21');
        $output .= "    <td>".html5('jID[21]', $repost)."</td>\n";
        $repost['label'] = lang('journal_main_journal_id_22');
        $output .= "    <td>".html5('jID[22]', $repost)."</td>\n";
        $output .= "    <td>&nbsp;</td>\n";
        $output .= '    <td rowspan="2" style="text-align:right">'.html5('btn_repost', $btn_repost)."</td>\n";
        $output .= "   </tr>\n</tbody>\n</table></div>";
        return $output;
    }

    /**
     * Saves the user defined settings
     */
    public function adminSave() {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }

    public function managerFY(&$layout=[])
    {
        $dbMaxFY= dbGetValue(BIZUNO_DB_PREFIX . "journal_periods", ["MAX(fiscal_year) AS fiscal_year", "MAX(period) AS period"], false, false);
        $maxFY  = $dbMaxFY['fiscal_year'] > 0 ? $dbMaxFY['fiscal_year'] : 0;
        $html   = $this->getViewFY();
        $fields = [
            'btnNewFy'  => ['order'=>10,'attr'=>['type'=>'button','value' => $this->lang['phreebooks_new_fiscal_year']],
                'events' => ['onClick'=>"if (confirm('".sprintf($this->lang['msg_gl_fiscal_year_confirm'], $maxFY + 1)."')) { jqBiz('body').addClass('loading'); jsonAction('phreebooks/tools/fyAdd'); }"]],
            'btnCloseFy'=> ['order'=>20,'attr'=>['type'=>'button','value'=>$this->lang['del_fiscal_year_btn']],'events'=>['onClick'=>"jsonAction('phreebooks/tools/fyCloseValidate');"]],
        ];
        $data = ['type'=>'divHTML',
            'divs'  => ['divFY'=>['order'=>50,'type'=>'divs','divs'=>[
                'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'newFY'  => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'newFY'],
                    'editPer'=> ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'editPer']]]]]],
            'panels'=> [
                'newFY'  => ['label'=>$this->lang['phreebooks_fiscal_years'],   'type'=>'fields','keys'=>['btnNewFy','btnCloseFy']],
                'editPer'=> ['label'=>$this->lang['phreebooks_journal_periods'],'type'=>'html',  'html'=>$html['body']]],
            'fields'=> $fields,
            'jsBody'=> ['init' =>$html['jsBody']]];
        $layout = array_replace_recursive($layout, $data);
    }

    private function getViewFY()
    {
        $FYs       = $outputJS = [];
        $stmt      = dbGetResult("SELECT DISTINCT fiscal_year FROM ".BIZUNO_DB_PREFIX."journal_periods");
        $dbFYs     = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($dbFYs as $row) { $FYs[] = ['id' => $row['fiscal_year'], 'text' => $row['fiscal_year']]; }
        $fy        = clean('fy', ['format'=>'integer', 'default'=>getModuleCache('phreebooks', 'fy', 'fiscal_year', false, biz_date('Y'))], 'get');
        $fiscalY   = ['label'=>lang('phreebooks_fiscal_year'),'values'=>$FYs,'attr'=>['type'=>'select','value'=>$fy],
            'events' => ['onChange'=>"var tab=jqBiz('#tabAdmin').tabs('getSelected'); tab.panel( 'refresh', '".BIZUNO_AJAX."&bizRt=phreebooks/admin/managerFY&fy='+bizSelGet('fy') );"]];
        $btnSaveFy = ['icon'=>'save','size'=>'large',
            'events' => ['onClick'=>"divSubmit('phreebooks/tools/fySave', 'fyCal');"]];
        $max_posted= dbGetValue(BIZUNO_DB_PREFIX."journal_main",    "MAX(period) AS period", false, false);
        $dbPer     = dbGetMulti(BIZUNO_DB_PREFIX."journal_periods", "fiscal_year=$fy", "period");
        $periods   = [];
        foreach ($dbPer as $row) { $periods[$row['period']] = ['start' => $row['start_date'], 'end' => $row['end_date']]; }
        $output    = "<p>".$this->lang['msg_gl_fiscal_year_edit'].'</p>
            <div id="fyCal" style="text-align:center">'.html5('fy', $fiscalY).html5('btnSaveFy', $btnSaveFy).'
            <table style="border-style:none;margin-left:auto;margin-right:auto;">
                <thead class="panel-header">
                    <tr><th width="33%">'.lang('period').'</th><th width="33%">'.lang('start').'</th><th width="33%">'.lang('end')."</th></tr>
                </thead>
                <tbody>\n";
        foreach ($periods as $period => $value) {
            $output .= '    <tr><td style="text-align:center">'.$period."</td>";
            if ($period > $max_posted) { // only allow changes if nothing has been posted above this period
                $output .= '<td>'.html5("pStart[$period]",['attr'=>['type'=>'date','value'=>$value['start']]])."</td>"; // new Date(2012, 6, 1)
                $outputJS[] = "jqBiz('#pStart_$period').datebox({required:true, onSelect:function(date){ var nDate = new Date(date.getFullYear(), date.getMonth(), date.getDate()-1); jqBiz('#pEnd_".($period-1)."').datebox('setValue', nDate); } });\n";
                $output .= '<td>'.html5("pEnd[$period]",  ['attr'=>['type'=>'date','value'=>$value['end']]])."</td>\n";
                $outputJS[] = "jqBiz('#pEnd_$period').datebox({  required:true, onSelect:function(date){ var nDate = new Date(date.getFullYear(), date.getMonth(), date.getDate()+1); jqBiz('#pStart_".($period+1)."').datebox('setValue', nDate); } });\n";
            } else {
                $output .= '<td style="text-align:center">'.viewDate($value['start'])."</td>";
                $output .= '<td style="text-align:center">'.viewDate($value['end'])."</td>\n";
            }
            $output .= "</tr>\n";
        }
        $output .= "</tbody>\n</table>\n</div>";
        return ['body'=>$output,'jsBody'=>$outputJS];
    }

    /**
     * Operations that need to be completed when first installing Bizuno for the PhreeBooks module
     */
    public function installFirst()
    {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/chart.php", 'phreebooksChart');
        $coa = new phreebooksChart();
        msgDebug("\n  Loading chart of accounts");
        $coa->chartInstall(getUserCache('profile', 'chart'));
        msgDebug("\n  Building fiscal year.");
        $current_year= biz_date('Y');
        $start_year  = getUserCache('profile', 'first_fy');
        $start_period= 1;
        $runaway     = 0;
        while ($start_year <= $current_year) {
            setNewFiscalYear($start_year, $start_period, "$start_year-01-01");
            $start_year++;
            $start_period = $start_period + 12;
            $runaway++;
            if ($runaway > 10) { break; }
        }
        msgDebug("\n  Building and checking chart history");
        buildChartOfAccountsHistory();
        msgDebug("\n  Updating current period");
        setModuleCache('phreebooks', 'fy', false, ['period' => '99', 'period_start' => '2099-12-01', 'period_end' => '2099-12-31', 'fiscal_year' => '2099']);
        clearUserCache('profile', 'chart');
        clearUserCache('profile', 'first_fy');
        periodAutoUpdate(false);
    }

    /**
     * Installs the currency settings and total methods at first install, can be modified by user later
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function install(&$layout=[])
    {
        // set the currency settings
        $defISO    = getModuleCache('phreebooks', 'currency', 'defISO');
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/currency.php', 'phreebooksCurrency');
        $currency  = new phreebooksCurrency();
        $currencies= getModuleCache('phreebooks', 'currency', 'iso', false, []);
        $currencies[$defISO] = $currency->currencySettings($defISO);
        setModuleCache('phreebooks', 'currency', 'iso', $currencies);
        // Set the totals methods
        $bAdmin    = new bizunoSettings();
        foreach ($this->totalMethods as $method) {
            $bAdmin->methodInstall($layout, ['module'=>'phreebooks', 'path'=>'totals', 'method'=>$method], false);
        }
    }

    /**
     * Operations needed to build the browser cache at first log in specific to PhreeBooks module
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function loadBrowserSession(&$layout=[])
    {
        $accts = []; // load gl Accounts
        $curISO= getDefaultCurrency();
        $chart = getModuleCache('phreebooks', 'chart', 'accounts');
        $defs  = getModuleCache('phreebooks', 'chart', 'defaults');
        if (is_array($chart)) { foreach ($chart as $row) {
            $row['default']= !empty($defs[$curISO][$row['type']]) && $row['id']==$defs[$curISO][$row['type']] ? 1 : 0;
            $row['asset']  = in_array($row['type'], $this->assets) ? 1 : 0;
            $row['type']   = viewFormat($row['type'], 'glTypeLbl');
            if (empty($row['inactive'])) { $row['inactive'] = '0'; } // for some cases this is not set
            $accts[]       = $row; // need to remove keys
        } }
        $layout['content']['dictionary']    = array_merge($layout['content']['dictionary'], $this->getBrowserLang());
        $layout['content']['glAccounts']    = ['total'=>sizeof($accts),  'rows'=>$accts];
        $cRates = loadTaxes('c');
        $layout['content']['taxRates']['c'] = ['total'=>sizeof($cRates), 'rows'=>$cRates];
        $vRates = loadTaxes('v');
        $layout['content']['taxRates']['v'] = ['total'=>sizeof($vRates), 'rows'=>$vRates];
        msgDebug("\nSending back data = " . print_r($layout['content'], true));
    }

    private function getBrowserLang()
    {
        return [
            'PB_INVOICE_RQD'     => $this->lang['msg_invoice_rqd'],
            'PB_INVOICE_WAITING' => $this->lang['msg_inv_waiting'],
            'PB_NEG_STOCK'       => $this->lang['msg_negative_stock'],
            'PB_RECUR_EDIT'      => $this->lang['msg_recur_edit'],
            'PB_SAVE_AS_CLOSED'  => $this->lang['msg_save_as_closed'],
            'PB_SAVE_AS_LINKED'  => $this->lang['msg_save_as_linked'],
            'PB_GL_ASSET_INC'    => $this->lang['bal_increase'],
            'PB_GL_ASSET_DEC'    => $this->lang['bal_decrease'],
            'PB_DBT_CRT_NOT_ZERO'=> $this->lang['err_debits_credits_not_zero']];
    }

    /**
     * Extends the Roles - Edit - PhreeBooks tab to add Sales and Purchase access
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function rolesEdit(&$layout=[])
    {
        $rID     = clean('rID', 'integer', 'get');
        $settings= json_decode(dbGetValue(BIZUNO_DB_PREFIX.'roles', 'settings', "id=$rID"), true);
        $layout['fields']['phreebooks_sales'] = ['order'=>20,'label'=>$this->lang['pb_role_cust_users'],'tip'=>$this->lang['msg_pb_admin_roles'],
            'attr'=>['type'=>'checkbox','checked'=>!empty($settings['bizuno']['roles']['sales']) ? true : false]];
        $layout['fields']['phreebooks_purch'] = ['order'=>20,'label'=>$this->lang['pb_role_vend_users'], 'tip'=>$this->lang['msg_pb_admin_roles'],
            'attr'=>['type'=>'checkbox','checked'=>!empty($settings['bizuno']['roles']['purch']) ? true : false]];
        $layout['tabs']['tabRoles']['divs']['customers']['divs']['props'] = ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'custSettings'];
        $layout['panels']['custSettings'] = ['label'=>lang('settings'),'type'=>'fields','keys'=>['phreebooks_sales']];
        $layout['tabs']['tabRoles']['divs']['vendors']['divs']['props'] = ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'vendSettings'];
        $layout['panels']['vendSettings'] = ['label'=>lang('settings'),'type'=>'fields','keys'=>['phreebooks_purch']];
    }

    /**
     * Extends the Roles settings to Save the PhereeBooks Specific settings
     * @return boolean null
     */
    public function rolesSave()
    {
        if (empty($rID = clean('id', 'integer', 'post'))) { return; }
        if (!$security = validateSecurity('bizuno', 'roles', $rID?3:2)) { return; }
        $settings = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'roles', 'settings', "id=$rID"), true);
        unset($settings[$this->moduleID]);
        $settings['bizuno']['roles']['sales'] = clean('phreebooks_sales', 'boolean', 'post');
        $settings['bizuno']['roles']['purch'] = clean('phreebooks_purch', 'boolean', 'post');
        dbWrite(BIZUNO_DB_PREFIX.'roles', ['settings'=>json_encode($settings)], 'update', "id=$rID");
    }

    /**
     * Extends the users editor for PhreeBooks specific fields
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function usersEdit(&$layout=[])
    {
        if (!$rID = clean('rID', 'integer', 'get')) { return; }
        if (!$security = validateSecurity('bizuno', 'users', $rID?3:2)) { return; }
        $settings = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'users', 'settings', "admin_id=$rID"), true);
        $profile  = $settings['profile'];
        if (empty($profile['cash_acct'])){ $profile['cash_acct']= getModuleCache('phreebooks', 'settings', 'customers','gl_cash'); }
        if (empty($profile['ar_acct']))  { $profile['ar_acct']  = getModuleCache('phreebooks', 'settings', 'customers','gl_receivables'); }
        if (empty($profile['ap_acct']))  { $profile['ap_acct']  = getModuleCache('phreebooks', 'settings', 'vendors',  'gl_payables'); }
        $fields = [
            'restrict_period'=> ['order'=>10,'break'=>true,'label'=>$this->lang['restrict_period_lbl'],'tip'=>$this->lang['restrict_period_tip'],'attr'=>['type'=>'checkbox','checked'=>!empty($profile['restrict_period']?true:false)]],
            'cash_acct'      => ['order'=>20,'break'=>true,'label'=>$this->lang['gl_cash_lbl'],        'tip'=>$this->lang['gl_cash_tip'],        'attr'=>['type'=>'ledger','value'=>$profile['cash_acct']]],
            'ar_acct'        => ['order'=>30,'break'=>true,'label'=>$this->lang['gl_receivables_lbl'], 'tip'=>$this->lang['gl_receivables_tip'], 'attr'=>['type'=>'ledger','value'=>$profile['ar_acct']]],
            'ap_acct'        => ['order'=>40,'break'=>true,'label'=>$this->lang['gl_purchases_lbl'],   'tip'=>$this->lang['gl_purchases_tip'],   'attr'=>['type'=>'ledger','value'=>$profile['ap_acct']]]];
        if (empty($layout['fields'])) { $layout['fields'] = []; }
        $layout['fields'] = array_merge($layout['fields'], $fields);
        $layout['tabs']['tabUsers']['divs']['phreebooks'] = ['order'=>60,'label'=>$this->lang['title'],'type'=>'divs','classes'=>['areaView'],'divs'=>[
            'pnlPB'=> ['order'=>40,'type'=>'panel','key'=>'pnlPhreeBooks','classes'=>['block33']]]];
        $layout['panels']['pnlPhreeBooks'] = ['label'=>lang('properties'),'type'=>'fields','keys'=>array_keys($fields)];
    }

    /**
     * Extends the users save method with PHreeBooks specific fields
     * @return boolean null
     */
    public function usersSave()
    {
        if (!$rID = clean('admin_id', 'integer', 'post')) { return; }
        if (!$security = validateSecurity('bizuno', 'users', $rID?3:2)) { return; }
        $settings = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'users', 'settings', "admin_id=$rID"), true);
        $settings['profile']['restrict_period']= clean('restrict_period','integer','post');
        $settings['profile']['cash_acct']      = clean('cash_acct',      'cmd',    'post');
        $settings['profile']['ar_acct']        = clean('ar_acct',        'cmd',    'post');
        $settings['profile']['ap_acct']        = clean('ap_acct',        'cmd',    'post');
        dbWrite(BIZUNO_DB_PREFIX.'users', ['settings'=>json_encode($settings)], 'update', "admin_id=$rID");
    }

    /**
     * Saves the users preferred order total sequence and methods used to set the order screen totals fields
     * @return null, but session and registry are updated
     */
    public function orderTotals()
    {
        $data = clean('data', 'text', 'get');
        if (!$data) { return msgAdd("Bad values sent!"); }
        $vals = explode(';', $data);
        $output = [];
        foreach ($vals as $method) {
            $parts = explode(':', $method);
            $idx = array_shift($parts);
            $output[$idx] = [];
            $order = 1;
            foreach ($parts as $val) {
                if ($val) {
                    $output[$idx][] = ['name' => $val, 'order' => $order];
                } // 'path'=>$path_if_not_in_phreebooks /totals folder
                $order++;
            }
        }
        setModuleCache('phreebooks', 'totals', false, $output);
        msgAdd(lang('msg_settings_saved'), 'success');
    }
}
