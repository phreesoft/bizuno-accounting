<?php
/*
 * Bizuno Tools methods
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
 * @version    6.x Last Update: 2023-05-07
 * @filesource /controllers/bizuno/tools.php
 */

namespace bizuno;

class bizunoTools {
    public $moduleID = 'bizuno';
    public $supportEmail;
    public $reasons;

    function __construct()
    {
        $this->lang        = getLang($this->moduleID);
        $this->supportEmail= defined('BIZUNO_SUPPORT_EMAIL') ? BIZUNO_SUPPORT_EMAIL : '';
        $this->reasons     = [
            'question'  => $this->lang['ticket_question'],
            'bug'       => $this->lang['ticket_bug'],
            'suggestion'=> $this->lang['ticket_suggestion'],
            'account'   => $this->lang['ticket_my_account']];
    }

    /**
     * Support ticket page structure
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function ticketMain(&$layout=[])
    {
        $reasons = [['id'=>'none', 'text' => lang('select')]];
        foreach ($this->reasons as $key => $value) { $reasons[] = ['id'=>$key, 'text'=>$value]; }
        $values  = dbGetRow(BIZUNO_DB_PREFIX."users", "admin_id=".getUserCache('profile', 'admin_id', false, 0));
        $machines= [['id'=>'pc','text'=>'PC'],['id'=>'mac','text'=>'Mac'],['id'=>'mobile','text'=>'Mobile Phone'],['id'=>'tablet','text'=>'Tablet'],['id'=>'other','text'=>'Other (list below)']];
        $os      = [['id'=>'windows','text'=>'Windows'],['id'=>'osx','text'=>'Apple OSX'],['id'=>'ios','text'=>'iPhone IOS'],['id'=>'android','text'=>'Android'],['id'=>'other','text'=>'Other (list below)']];
        $browsers= [['id'=>'firefox','text'=>'Firefox'],['id'=>'chrome','text'=>'Chrome'],['id'=>'safari','text'=>'Safari'],['id'=>'edge','text'=>'MS Edge'],['id'=>'ie','text'=>'Internet Explorer'],['id'=>'other','text'=>'Other (list below)']];
        $fields = [
            'ticketURL'  => ['order'=>15,'break'=>true,'attr'=>['type'=>'hidden','value'=>$_SERVER['HTTP_HOST']]],
            'langDesc'   => ['order'=>20,'break'=>true,'html'=>$this->lang['ticket_desc'],'attr'=>['type'=>'raw']],
            'selReason'  => ['order'=>25,'break'=>true,'label'=>lang('reason'), 'values'=>$reasons, 'attr'=>['type'=>'select']],
            'selMachine' => ['order'=>30,'break'=>true,'label'=>lang('Machine'),'values'=>$machines,'attr'=>['type'=>'select']],
            'selOS'      => ['order'=>35,'break'=>true,'label'=>lang('OS'),     'values'=>$os,      'attr'=>['type'=>'select']],
            'selBrowser' => ['order'=>40,'break'=>true,'label'=>lang('Browser'),'values'=>$browsers,'attr'=>['type'=>'select']],
            'ticketUser' => ['order'=>45,'break'=>true,'label'=>lang('address_book_primary_name'),'attr'=>['value'=>$values['title'],'size'=>40]],
            'ticketEmail'=> ['order'=>50,'break'=>true,'label'=>lang('email'),'attr'=>['value'=>$values['email'],'size'=>60]],
            'ticketPhone'=> ['order'=>55,'break'=>true,'label'=>lang('telephone')],
            'ticketDesc' => ['order'=>60,'break'=>true,'label'=>lang('description'),'attr'=>['type'=>'textarea','rows'=>8,'cols'=>60]],
            'ticketFile' => ['order'=>65,'label'=>$this->lang['ticket_attachment'],'attr'=>['type'=>'file']], // break is auto-removed
            'ticketPhone'=> ['order'=>70,'html'=>"<br />",'attr'=>['type'=>'raw']],
            'btnSubmit'  => ['order'=>75,'events'=>['onClick'=>"jqBiz('#frmTicket').submit();"],'attr'=>['type'=>'button','value'=>lang('submit')]]];
        $data = ['type'=>'page','title'=>lang('support'),
            'divs'  => ['tcktMain'=>['order'=>50,'type'=>'divs','divs'=>[
                'head'   => ['order'=>10,'type'=>'html',  'html'=>"<h1>".lang('support')."</h1>"],
                'formBOF'=> ['order'=>15,'type'=>'form',  'key' =>'frmTicket'],
                'body'   => ['order'=>50,'type'=>'fields','keys'=>array_keys($fields)],
                'formEOF'=> ['order'=>85,'type'=>'html',  'html'=>"</form>"]]]],
            'forms' => ['frmTicket'=>['attr'=>['type'=>'form','method'=>'post','action'=>BIZUNO_AJAX."&bizRt=bizuno/tools/ticketSave",'enctype'=>"multipart/form-data"]]],
            'fields'=> $fields];
        $layout = array_replace_recursive($layout, viewMain(), $data);

    }

    /**
     * Support ticket emailed to Bizuno BizNerds
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function ticketSave(&$layout=[])
    {
        global $io;
        bizAutoLoad(BIZBOOKS_ROOT.'model/mail.php', 'bizunoMailer');
        $user   = clean('ticketUser', 'text', 'post');
        $email  = clean('ticketEmail','text', 'post');
        $url    = clean('ticketURL',  'text', 'post');
        $tel    = clean('ticketPhone','text', 'post');
        $type   = clean('selReason',  'text', 'post');
        $box    = clean('selMachine', 'text', 'post');
        $os     = clean('selOS',      'text', 'post');
        $brwsr  = clean('selBrowser', 'text', 'post');
        $msg    = str_replace("\n", '<br />', clean('ticketDesc', 'text', 'post'));
        $bizName= getModuleCache('bizuno', 'settings', 'company', 'primary_name');
        $subject= "Support Ticket: $bizName - $user ($email)";
        $message= "$msg<br /><br />Reason: $type<br />Phone: $tel<br />Ref: $url ($box; $os; $brwsr)<br />";
        if (empty($this->supportEmail)) { return msgAdd("You do not have a support email address defined for your business , Please visit the PhreeSoft website for support."); }
        $toName = defined('BIZUNO_SUPPORT_NAME') ? BIZUNO_SUPPORT_NAME : $this->supportEmail;
        msgDebug("\nfiles array: ".print_r($_FILES['ticketFile'], true));
        $mail   = new bizunoMailer($this->supportEmail, $toName, $subject, $message, $email, $user);
        if (!empty($_FILES['ticketFile']['name'])) { if ($io->validateUpload('ticketFile', '', $io->getValidExt('file'))) {
            $mail->attach($_FILES['ticketFile']['tmp_name'], $_FILES['ticketFile']['name']);
        } }
        $mail->sendMail();
        msgAdd("Your email has been sent to the PhreeSoft Support team. We'll be in contact with you shortly.", 'success');
        $this->ticketMain($layout);
    }

    /**
     * Creates/changes the encryption key
     */
    public function encryptionChange()
    {
        if (!validateSecurity('bizuno', 'admin', 4)) { return; }
        bizAutoLoad(BIZBOOKS_ROOT."model/encrypter.php", 'encryption');
        $old_key= clean('orig','password', 'get');
        $new_key= clean('new', 'password', 'get');
        $confirm= clean('dup', 'password', 'get');
        $current= getModuleCache('bizuno', 'encKey');
        if (empty($current)) { $current = ':'; } // key is not set
        $stack  = explode(':', $current);
        if ($current<>':' && md5($stack[1] . $old_key) <> $stack[0]) { return msgAdd(lang('err_login_failed')); }
        if (strlen($new_key) < getModuleCache('bizuno', 'settings', 'general', 'password_min', 8) || $new_key != $confirm) {
            return msgAdd(lang('err_encrypt_failed'));
        }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'data_security');
        if (sizeof($result) > 0) { // convert old encrypt to new encrypt
            $enc = new encryption();
            foreach ($result as $key => $row) {
                unset($result[$key]['id']);
                $result[$key]['enc_value'] = $enc->encrypt($new_key, $enc->decrypt($old_key, $row['enc_value']));
            }
            $keys = array_keys($result[0]);
            $sql  = "INSERT INTO ".BIZUNO_DB_PREFIX."data_security (`".implode('`, `', array_keys($keys))."`) VALUES ";
            foreach ($result as $row) { $sql .= "(`".implode("`, `", $row)."`),"; }
            $sql .= substr($sql, 0, -1);
            dbTransactionStart();
            dbGetResult("TRUNCATE ".BIZUNO_DB_PREFIX."data_security"); // empty the db
            dbGetResult($sql); // write the table
            dbTransactionCommit();
        }
        $newEnc = encryptValue($new_key);
        setModuleCache('bizuno', 'encKey', false, $newEnc);
        setUserCache('profile', 'admin_encrypt', $new_key);
        msgLog($this->lang['msg_encryption_changed']);
        msgAdd($this->lang['msg_encryption_changed'], 'success');
    }

    /**
     * deletes all encryption rows from the db table that have expired dates
     */
    public function encryptionClean()
    {
        $date = clean('data', ['format'=>'date','default'=>biz_date('Y-m-d')], 'get');
        $output = dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."data_security WHERE exp_date<'$date'", 'delete');
        if ($output === false) {
            msgAdd("There was an error deleting records!");
        } else {
            msgAdd("Success, the number of records removed was: $output", 'success');
        }
    }

    /**
     * This function extends the PhreeBooks module close fiscal year function to handle Bizuno operations
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function fyCloseHome(&$layout=[])
    {
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        $html  = "<p>"."Closing the fiscal year for the Bizuno module consist of deleting audit log entries during or before the fiscal year being closed. "
                . "To prevent the these entries from being removed, check the box below."."</p>";
        $html .= html5('bizuno_keep', ['label' => 'Do not delete audit log entries during or before this closing fiscal year', 'position'=>'after','attr'=>['type'=>'checkbox','value'=>'1']]);
        $layout['tabs']['tabFyClose']['divs'][$this->moduleID] = ['order'=>50,'label'=>$this->lang['title'],'type'=>'html','html'=>$html];
    }

    /**
     * Hook to PhreeBooks Close FY method, adds tasks to the queue to execute AFTER PhreeBooks processes the journal
     */
    public function fyClose()
    {
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        $skip = clean('bizuno_keep', 'boolean', 'post');
        if ($skip) { return; } // user wants to keep all records, nothing to do here, move on
        $cron = getUserCache('cron', 'fyClose');
        $cron['taskClose'][] = ['mID'=>$this->moduleID];
        setUserCache('cron', 'fyClose', $cron);
    }

    /**
     * continuation of fiscal year close, db purge and old folder purge, as necessary
     * @return string
     */
    public function fyCloseNext($settings=[], &$cron=[])
    {
        if (!$security = validateSecurity('bizuno', 'admin', 4)) { return; }
        $endDate = $cron['fyEndDate'];
        if (!$endDate) { return; }
        $dateFull = $endDate.' 23:59:59';
        $cnt = dbGetValue(BIZUNO_DB_PREFIX.'audit_log', 'COUNT(*) AS cnt', "`date`<='$dateFull'", false);
        $cron['msg'][] = "Read $cnt records to delete from table: audit_log";
        msgDebug("\nExecuting sql: DELETE FROM ".BIZUNO_DB_PREFIX."audit_log WHERE `date`<='$dateFull'");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."audit_log WHERE `date`<='$dateFull'");
        // data_security
        $countC = dbGetValue(BIZUNO_DB_PREFIX.'data_security', 'count(*) AS cnt', "exp_date<='$endDate'", false);
        $cron['msg'][] = "The total number of expired records removed from data_security was $countC";
        msgDebug("\nThe total number of expired records to remove from data_security is $countC");
        msgDebug("\nReady to execute sql: DELETE from ".BIZUNO_DB_PREFIX."data_security WHERE exp_date<='$endDate'");
        dbGetResult("DELETE from ".BIZUNO_DB_PREFIX."data_security WHERE exp_date<='$endDate'");
        return "Finished processing tables audit_log and data_security";
    }

    /**
     * Verifies the comments from the newest release match the database comments
     * @param type $verbose
     * @return type
     */
    public function repairComments($verbose=true)
    {
        $tables = [];
        include(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php"); // loads $tables
        $this->getExtTables($tables);
        foreach ($tables as $table => $tProps) { // as defined by code
            if (!dbTableExists(BIZUNO_DB_PREFIX.$table)) { continue; }
            $stmt = dbGetResult("SHOW FULL COLUMNS FROM ".BIZUNO_DB_PREFIX."$table");
            if (!$stmt) { return msgAdd("No results for table $table! Bailing"); }
            $structure = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($structure as $field) {
                $default = in_array($field['Default'], ['CURRENT_TIMESTAMP']) ? $field['Default'] : "'{$field['Default']}'"; // don't quote mysql reserved words
                $params  = $field['Type'].' ';
                $params .= $field['Null']=='NO'      ? 'NOT NULL '         : 'NULL ';
                $params .= !empty($field['Default']) ? "DEFAULT $default " : '';
                $params .= $field['Extra']           ? $field['Extra'].' ' : '';
                $newComment = !empty($tProps['fields'][$field['Field']]['comment']) ? $tProps['fields'][$field['Field']]['comment'] : $field['Comment'];
                if ($newComment == $field['Comment']) { continue; } // if not changed, do nothing
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."$table` CHANGE `{$field['Field']}` `{$field['Field']}` $params COMMENT '$newComment'");
            }
        }
        // now verify Phreeform structure
        $phreeform = [];
        include(BIZBOOKS_ROOT."controllers/bizuno/install/phreeform.php");
        foreach ($phreeform as $grp => $rows) {
            $gID = dbGetValue(BIZUNO_DB_PREFIX.'phreeform', 'id', "group_id='$grp' AND mime_type='dir'");
            if (empty($gID)) {
                msgDebug("Adding main group $grp - {$rows['title']}");
                $gID = dbWrite(BIZUNO_DB_PREFIX.'phreeform', ['parent_id'=>0, 'group_id'=>$grp, 'mime_type'=>'dir', 'title'=>$rows['title'], 'create_date'=>biz_date('Y-m-d'), 'last_update'=>biz_date('Y-m-d')]);
            }
            foreach ($rows['folders'] as $gname => $props) {
                $fID = dbGetValue(BIZUNO_DB_PREFIX.'phreeform', 'id', "group_id='$gname' AND mime_type='dir'");
                if (empty($fID)) {
                    msgDebug("Adding subgroup $gname - {$props['title']}");
                    dbWrite(BIZUNO_DB_PREFIX.'phreeform', ['parent_id'=>$gID, 'group_id'=>$gname, 'mime_type'=>'dir', 'title'=>$props['title'], 'create_date'=>biz_date('Y-m-d'), 'last_update'=>biz_date('Y-m-d')]);
                }
            }
        }
        if ($verbose) { msgAdd("finished!"); }
    }

    /**
     * Verifies the current database table structure matches the core application and extensions.
     * CAUTION: CAN TAKE A LONG TIME TO RUN
     * @param boolean $verbose - Returns 'Finished' if set to true when complete.
     * @return null
     */
    public function repairTables($verbose=true)
    {
        $tables = [];
        include(BIZBOOKS_ROOT."controllers/bizuno/install/tables.php"); // loads $tables
        $this->getExtTables($tables);
        foreach ($tables as $table => $props) {
            $exists = !dbTableExists(BIZUNO_DB_PREFIX.$table) ? false : true;
            $fields = [];
            foreach ($props['fields'] as $field => $values) {
                $temp = ($exists ? "CHANGE `$field` " : '' ) . "`$field` ".$values['format']." ".$values['attr'];
                if (isset($values['comment'])) { $temp .= " COMMENT '".$values['comment']."'"; }
                $fields[] = $temp;
            }
            if ($exists) {
                msgDebug("\nAltering table: $table");
                $sql = "ALTER TABLE `".BIZUNO_DB_PREFIX."$table` ".implode(', ', $fields);
            } else { // add new table
                msgDebug("\nCreating table: $table");
                $sql = "CREATE TABLE IF NOT EXISTS `".BIZUNO_DB_PREFIX."$table` (".implode(', ', $fields).", ".$props['keys']." ) ".$props['attr'];
            }
            dbGetResult($sql);
        }
        if ($verbose) { msgAdd("finished!"); }
    }

    /**
     * Updates the current_status db table with a modified values set by user in Settings
     */
    public function statusSave()
    {
        if (!$security = validateSecurity('bizuno', 'admin', 3)) { return; }
        $tmp = dbLoadStructure(BIZUNO_DB_PREFIX.'current_status');
        foreach ($tmp as $key => $value) { $structure['stat_'.$key] = $value; } // prepend the index key for status values
        $tmp1 = requestData($structure);
        foreach ($tmp1 as $key => $value) { // restore the original table field names
            $newKey = str_replace('stat_', '', $key);
            $values[$newKey] = $value;
        }
        dbWrite(BIZUNO_DB_PREFIX."current_status", $values, 'update');
        msgAdd(lang('msg_settings_saved'), 'success');
    }

    /**
     * Get the tables from the Pro extension
     * @param type $tables
     * @return type
     */
    private function getExtTables(&$tables=[])
    {
        if (!is_dir(BIZBOOKS_EXT.'controllers/')) { return; }
        $dirs = scandir(BIZBOOKS_EXT.'controllers/');
        msgDebug("\nScanning extensions returned: ".print_r($dirs, true));
        foreach ($dirs as $ext) {
            if (in_array($ext, ['.', '..']) || !file_exists(BIZBOOKS_EXT."controllers/$ext/admin.php")) { continue; }
            include_once(BIZBOOKS_EXT."controllers/$ext/admin.php");
            if (!class_exists("\\bizuno\\{$ext}Admin")) { continue; }
            $fqcn  = "\\bizuno\\{$ext}Admin";
            $admin = new $fqcn();
            if (!empty($admin->tables)) {
                msgDebug("\nUpdating table list for extension $ext");
                $tables = array_merge($tables, $admin->tables); }
        }
    }
}
