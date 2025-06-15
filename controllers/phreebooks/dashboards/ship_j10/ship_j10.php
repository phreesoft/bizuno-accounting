<?php
/*
 * PhreeBooks dashboard - Reminder for Customer Sales Orders that are due to ship today
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
 * @version    6.x Last Update: 2023-03-06
 * @filesource /controllers/phreebooks/dashboards/ship_j10/ship_j10.php
 */

namespace bizuno;

class ship_j10
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'ship_j10';
    public  $category = 'customers';
    private $sendEmail= false;
    private $emailList= [];


    function __construct($settings)
    {
        $this->security= getUserCache('security', 'j10_mgr', false, 0);
        $this->lang    = getMethLang($this->moduleID, $this->methodDir, $this->code);
        $defaults      = ['jID'=>10,'notified'=>"{}",'max_rows'=>20,'users'=>'-1','roles'=>'-1','reps'=>'0','store_id'=>-1,'selRep'=>0,'num_rows'=>0,'order'=>'asc'];
        $this->trim    = 20; // length to trim primary_name to fit in frame
        $this->order   = ['asc'=>lang('increasing'), 'desc'=>lang('decreasing')];
        $this->today   = biz_date('Y-m-d');
        $this->choices = getModuleCache('contacts','statuses');
        $this->settings= array_replace_recursive($defaults, $settings);
    }

    public function settingsStructure()
    {
        $roles = viewRoleDropdown();
        array_unshift($roles, ['id'=>'-1', 'text'=>lang('all')]);
        return [
            'notified'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['jID']]],
            'jID'     => ['attr'=>['type'=>'hidden','value'=>$this->settings['jID']]],
            'max_rows'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['max_rows']]],
            'users'   => ['label'=>lang('users'), 'position'=>'after','values'=>listUsers(),'attr'=>['type'=>'select','value'=>$this->settings['users'],'size'=>10, 'multiple'=>'multiple']],
            'roles'   => ['label'=>lang('groups'),'position'=>'after','values'=>listRoles(),'attr'=>['type'=>'select','value'=>$this->settings['roles'],'size'=>10, 'multiple'=>'multiple']],
            'reps'    => ['label'=>lang('just_reps'),'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['reps']]],
            'store_id'=> ['order'=>10,'break'=>true,'position'=>'after','label'=>lang('store_id'),'values'=>dbGetStores(true),'attr'=>['type'=>'select','value'=>$this->settings['store_id']]],
            'selRep'  => ['order'=>20,'break'=>true,'position'=>'after','label'=>lang('contacts_rep_id_c'),'position'=>'after','values'=>$roles,'attr'=>['type'=>'select','value'=>$this->settings['selRep']]],
            'num_rows'=> ['order'=>30,'break'=>true,'position'=>'after','label'=>lang('limit_results'),'options'=>['min'=>0,'max'=>50,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['num_rows']]],
            'order'   => ['order'=>40,'break'=>true,'position'=>'after','label'=>lang('sort_order'),   'values'=>viewKeyDropdown($this->order),'attr'=>['type'=>'select','value'=>$this->settings['order']]]];
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function render(&$layout=[])
    {
        global $currencies;
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'getInvoiceInfo', 'function');
        $selRep = !empty($this->settings['reps']) && getUserCache('security', 'admin', false, 0)<3 ? 0 : $this->settings['selRep'];
        $struc = $this->settingsStructure();
        $filter= "m.journal_id={$this->settings['jID']} AND m.closed='0' AND i.gl_type='itm' AND i.date_1<='$this->today'";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($this->settings['store_id'] > -1) {
            $filter .= " AND store_id='{$this->settings['store_id']}'";
        }
        if ($selRep==0 && $this->settings['reps'] && getUserCache('profile', 'contact_id', false, '0')) { // None by the select, so limit to current rep ID
            $filter.= " AND rep_id='".getUserCache('profile', 'contact_id', false, '0')."'";
        } elseif ($selRep>0) { // Admin requesting Specific Rep
            $filter.= " AND rep_id='$selRep'";
        } // else all sales
        if (!empty(getUserCache('profile', 'restrict_store'))) { $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        $order = "ORDER BY " . ($this->settings['order']=='desc' ? 'm.post_date DESC, m.invoice_num DESC' : 'm.post_date, m.invoice_num');
        $sql   = "SELECT m.id, m.journal_id, m.post_date, m.store_id, m.contact_id_b, m.primary_name_b, m.invoice_num, m.total_amount, m.currency, m.currency_rate, i.id AS iID, i.qty
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id WHERE $filter $order";
        $stmt  = dbGetResult($sql);
        $result= $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $rID   = 0;
        $output= [];
        foreach ($result as $row) {
            // Check for already shipped
            $lineTotal = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'SUM(qty)', "item_ref_id={$row['iID']}", false);
            if ($lineTotal >= $row['qty']) { continue; } // filled
            if ($row['id'] == $rID) { continue; } // prevent dups
            $output[] = $row;
            $rID = $row['id'];
        }
        if (empty($output)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            bizAutoLoad(BIZBOOKS_ROOT.'model/mail.php', 'bizunoMailer');
            // get the notified list
            $settings = getModuleCache($this->moduleID, $this->methodDir, $this->code, []);
            $notified = $settings['settings']['notified'];
            if (empty($notified['date']) || $notified['date'] <> $this->today) { $notified = ['date'=>$this->today, 'rIDs'=>[]]; }
            msgDebug("\nNotified = ".print_r($notified, true));
            foreach ($output as $entry) {
                $currencies->iso  = $entry['currency'];
                $currencies->rate = $entry['currency_rate'];
                $this->notifyCheck($notified, $entry);
                $store  = sizeof(getModuleCache('bizuno', 'stores')) > 1 ? "[".viewFormat($entry['store_id'], 'storeID')."]" : '-';
                $left   = biz_date('m/d', strtotime($entry['post_date']))." $store ".$this->rowStyler($entry['contact_id_b'], viewText($entry['primary_name_b'], $this->trim));
                $right  = '';
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID={$this->settings['jID']}&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
            if ($this->sendEmail && !empty($this->emailList)) { $this->notifyEmail(); }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $settings['settings']['notified'] = $notified;
            setModuleCache($this->moduleID, $this->methodDir, $this->code, $settings);
        }
        $filter = ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($this->settings['order']).(!empty($this->settings['num_rows']) ? " ({$this->settings['num_rows']});" : '');
        $layout = array_merge_recursive($layout, [
            'divs'  => [
                'admin'=>['divs'=>['body'=>['order'=>50,'type'=>'fields','keys'=>[$this->code.'store_id',$this->code.'selRep', $this->code.'num_rows', $this->code.'order']]]],
                'head' =>['order'=>40,'type'=>'html','html'=>$filter,'hidden'=>getModuleCache('bizuno','settings','general','hide_filters',0)],
                'body' =>['order'=>50,'type'=>'list','key'=>$this->code]],
            'fields' => [
                $this->code.'store_id'=> array_merge_recursive($struc['store_id'],['events'=>['onChange'=>"jqBiz('#{$this->code}num_rows').keyup();"]]),
                $this->code.'selRep'  => array_merge_recursive($struc['selRep'],  ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]]),
                $this->code.'num_rows'=> array_merge_recursive($struc['num_rows'],['events'=>['onChange'=>"jqBiz('#{$this->code}num_rows').keyup();"]]),
                $this->code.'order'   => array_merge_recursive($struc['order'],   ['events'=>['onChange'=>"dashSubmit('$this->moduleID:$this->code', 0);"]])],
            'lists'  => [$this->code=>$rows],
            'jsReady'=>['init'=>"dashDelay('$this->moduleID:$this->code', 0, '{$this->code}num_rows');"]]);
    }

    /**
     *
     */
    public function save()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $settings= [
            'store_id'=> clean($this->code.'store_id',['format'=>'integer','default'=>0],'post'), // default needs to be zero or clean will not allow zero setting, returns default
            'selRep'  => clean($this->code.'selRep',  'integer','post'),
            'num_rows'=> clean($this->code.'num_rows','integer','post'),
            'order'   => clean($this->code.'order',   'cmd',    'post')];
        dbWrite(BIZUNO_DB_PREFIX.'users_profiles', ['settings'=>json_encode($settings)], 'update', "user_id=".getUserCache('profile', 'admin_id', false, 0)." AND dashboard_id='$this->code' AND menu_id='$menu_id'");
    }

    /**
     *
     * @param array $notified
     * @param type $entry
     * @return type
     */
    private function notifyCheck(&$notified, $entry)
    {
        if ($notified['date'] == $this->today && in_array($entry['id'], $notified['rIDs'])) { return; } // notified already
        msgDebug("\nAdding record {$entry['id']} un-notified invoice # {$entry['invoice_num']} with customer: {$entry['primary_name_b']}");
        $notified['rIDs'][]= $entry['id'];
        $this->emailList[] = ['invNum'=>$entry['invoice_num'], 'name'=>$entry['primary_name_b']];
        $this->sendEmail   = true;
    }

    /**
     *
     */
    private function notifyEmail()
    {
        $html = '';
        msgDebug("\nEmail list before email: ".print_r($this->emailList, true));
        foreach ($this->emailList as $row) { $html .= "SO #{$row['invNum']}: {$row['name']}<br />"; }
        $fromEmail = 'do-not-reply@phreesoft.com';
        $toEmail   = getModuleCache('bizuno', 'settings', 'company', 'email');
        $toName    = getModuleCache('bizuno', 'settings', 'company', 'contact');
        $msgSubject= sprintf($this->lang['email_subject'], viewFormat($this->today, 'date'));
        $msgBody   = sprintf($this->lang['email_body'], $html);
        $mail    = new bizunoMailer($toEmail, $toName, $msgSubject, $msgBody, $fromEmail);
        $mail->sendMail();
        msgAdd($msgBody);
        msgLog($msgSubject);
    }

    private function rowStyler($cID, $cText)
    {
        $cStatus= dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'inactive', "id=$cID");
        foreach ($this->choices as $status) {
            if (empty($status['color'])) { continue; }
            if ($status['id']==$cStatus) { return '<span class="row-'.$status['color'].'">'.$cText.'</span>'; }
        }
        return $cText;
    }
}
