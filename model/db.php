<?php
/*
 * Database methods using PDO library
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
 * @copyright  2008-2024, PhreeSoft Inc.
 * @license    http://opensource.org/licenses/OSL-3.0  Open Software License (OSL 3.0)
 * @version    6.x Last Update: 2024-03-24
 * @filesource /model/db.php
 */

namespace bizuno;

class db extends \PDO
{
    var     $total_count = 0;
    var     $total_time  = 0;
    public  $connected   = false;
    private $max_input_size = 60; // maximum input form field display length for larger db fields

    /**
     * Constructor to connect to db, sets $connected to true if successful, returns if no params sent
     * @param array $dbData - database credentials to auto-connect, if set
     */
    function __construct($dbData)
    {
        if (empty($dbData['host']) || empty($dbData['name']) || empty($dbData['user']) || empty($dbData['pass'])) { return; }
        $driver = !empty($dbData['type']) ? $dbData['type'] : 'mysql';
        $dns    = "{$dbData['type']}:host={$dbData['host']};dbname={$dbData['name']}";
        $user   = $dbData['user'];
        $pass   = $dbData['pass'];
        $this->driver = $driver;
        switch($driver) {
            default:
            case "mysql":
                try { parent::__construct($dns, $user, $pass); }
                catch (PDOException $e) { exit("\nDB Connection failed, error: ".$e->getMessage()); } // ." with db settings: ".print_r($dbData, true)
                $this->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                $this->exec("SET character_set_results='utf8', character_set_client='utf8', character_set_connection='utf8', character_set_database='utf8', character_set_server='utf8'");
                break;
        }
        $this->connected = true;
    }

    /**
     * Generic SQL query wrapper for executing queries, has error logging and debug messages
     * @param string $sql - The SQL statement
     * @param string $action [default: stmt] - action to perform, choices are: insert, update, delete, row, rows, stmt
     * @return false on error, array or statement on success depending on request
     */
    function Execute($sql, $action='stmt', $verbose=false)
    {
        if (!$this->connected) { die('ERROR: Not connected to the db!'); }
        msgDebug("\nEntering Execute with action $action and sql: $sql");
        $error     = false;
        $output    = false;
        $msgResult = '';
        $time_start= explode(' ', microtime());
        switch ($action) {
            case 'insert': // returns id of new row inserted
                if (false !== $this->exec($sql)) { $output = $this->lastInsertId(); } else { $error = true; }
                $msgResult = "row ID = $output";
                break;
            case 'update':
            case 'delete': // returns affected rows
                $output = $this->exec($sql);
                if ($output === false) { $error = true; }
                $msgResult = "number of affected rows = $output";
                break;
            case 'row': // returns single table row
                $stmt = $this->query($sql);
                if ($stmt) {
                    $output = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if (!is_array($output)) { $output = []; }
                }
                else { $output = []; $error = true; }
                $msgResult = "number of fields = ".sizeof($output);
                break;
            case 'rows': // returns array of one or more table rows
                $stmt = $this->query($sql);
                if ($stmt) {
                    $output = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $stmt->closeCursor();
                } else { $output = []; $error = true; }
                $msgResult = "number of rows = ".sizeof($output);
                break;
            default:
            case 'stmt': // PDO Statement
                if (!$output = $this->query($sql)) { $error = true; }
                break;
        }
        $time_end = explode (' ', microtime());
        $query_time = $time_end[1] + $time_end[0] - $time_start[1] - $time_start[0];
        $this->total_time += $query_time;
        $this->total_count++;
        msgDebug("\nReturning result (in $query_time ms): $msgResult");
        if ($error) {
            $errorMsg = "\nSQL Error: ".print_r($this->errorInfo(), true);
            msgDebug($errorMsg, 'trap');
            // below fails in multi-business since BIZUNO_DB_PREFIX is not yet defined, maybe use prefix from construct to handle all cases
            if ($verbose || (dbTableExists(BIZUNO_DB_PREFIX.'configuration'))) { msgAdd($errorMsg); } // make sure we're installed
        }
        return $output;
    }

    /**
     * Used to update a database table to the Bizuno structure. Mostly for conversion from anther base (i.e. PhreeBooks)
     * @param string $table - The database table to update (performs an ALTER TABLE SQL command)
     * @param string $field - The database field name to alter
     * @param array $props - information pulled from the table structure of settings to add to the altered field.
     * @return boolean - Nothing of interest, just writes the table
     */
    function alterField($table, $field, $props=[])
    {
        if (!$table || !$field) { return; }
        $comment = $temp = [];
        $sql = "ALTER TABLE $table CHANGE `$field` `$field` ";
        if (!empty($props['dbType'])) { $sql .= ' '.$props['dbType']; }
        if (!empty($props['collate'])){ $sql .= " CHARACTER SET utf8 COLLATE ".$props['collate']; }
        if ( isset($props['null']) && strtolower($props['null'])=='no') { $sql .= ' NOT NULL'; }
        if ( isset($props['default'])) { $sql .= " DEFAULT '".$props['default']."'"; }
        if (!empty($props['extra']))  { $sql .= ' '.$props['extra']; }
        if (!empty($props['tag']))    { $comment['tag']  = $props['tag']; }
        if (!empty($props['tab']))    { $comment['tab']  = $props['tab']; }
        if (!empty($props['order']))  { $comment['order']= $props['order']; }
        if (!empty($props['label']))  { $comment['label']= $props['label']; }
        if (!empty($props['group']))  { $comment['group']= $props['group']; }
        if (!empty($props['type']))   { $comment['type'] = $props['type']; }
        if (!empty($props['req']))    { $comment['req']  = $props['req']; }
        foreach ($comment as $key => $value) { $temp[] = "$key:$value"; }
        if (sizeof($temp) > 0) { $sql .= " COMMENT '".implode(';',$temp)."'"; }
        dbGetResult($sql);
    }

    /**
     * This function builds the table structure as a basis for building pages and reading/writing the db
     * @param string $table - The database table to examine.
     * @param string $suffix - [optional, default ''] Loads the language based on the added suffix, used for multiplexed tables.
     * @param string $prefix - [optional, default ''] Loads the language based on the added prefix, used for multiplexed tables.
     * @param string $lang - [optional, default []] Language Overrides
     * @return array $output - [optional, default ''] Contains the table structural settings, indexed by the field name
     */
    public function loadStructure($table, $suffix='', $prefix='', $lang=[])
    {
        $output = [];
        if (!empty($GLOBALS['bizTables'][$table])) { return $GLOBALS['bizTables'][$table]; } // already loaded
        if (!$oResult = $this->query("SHOW FULL COLUMNS FROM $table")) {
            msgAdd("Failed loading structure for table: $table");
            return [];
        }
        $base_table= str_replace(BIZUNO_DB_PREFIX, '', $table);
        $result    = $oResult->fetchAll();
        $order     = 1;
        foreach ($result as $row) {
            $comment = [];
            if (!empty($row['Comment'])) {
                $temp = explode(';', $row['Comment']);
                foreach ($temp as $entry) {
                    $param = explode(':', $entry, 2);
                    $comment[trim($param[0])] = trim($param[1]);
                }
            }
            $output[$row['Field']] = [
                'table'  => $base_table,
                'dbfield'=> $table.'.'.$row['Field'], //id,
                'dbType' => $row['Type'],
                'field'  => $row['Field'],
                'break'  => true,
                'null'   => $row['Null'], //NO, YES
                'collate'=> $row['Collation'],
                'key'    => $row['Key'], //PRI,
                'default'=> $row['Default'], //'',
                'extra'  => $row['Extra'], //auto_increment,
                'comment'=> $row['Comment'],
                'tag'    => $prefix.(isset($comment['tag'])?$comment['tag']:$row['Field']).$suffix,
                'tab'    => isset($comment['tab'])  ? $comment['tab']  : 0,
                'group'  => isset($comment['group'])? $comment['group']: '',
                'col'    => isset($comment['col'])  ? $comment['col']  : 1,
                'order'  => isset($comment['order'])? $comment['order']: $order,
                'label'  => $this->guessLabel($table, $row['Field'], $suffix, $comment, $lang),
                'attr'   => $this->buildAttr($row, $comment)];
            $trash = [];
            $output[$row['Field']]['format'] = isset($comment['format']) ? $comment['format'] : $this->guessFormat($trash, $row['Type']); // db data type
            if (in_array(substr($row['Type'], 0, 4), ["ENUM", "enum"])) {
                $keys   = explode(',', str_replace(["ENUM", "enum", "(", ")", "'"], '', $row['Type']));
                $values = isset($comment['opts']) ? explode(':', $comment['opts']) : $keys;
                foreach ($keys as $idx => $key) {
                    $output[$row['Field']]['opts'][] = ['id'=>trim($key), 'text'=>isset($values[$idx]) ? trim($values[$idx]) : trim($key)];
                }
            }
            $order++;
        }
        $GLOBALS['bizTables'][$table] = $output; // save structure globally
        return $output;
    }

    private function guessLabel($table, $field, $suffix, $comment, $lang) {
        if (isset($comment['label'])){ return $comment['label']; }
        if (isset($lang[$field]))    { return $lang[$field]; }
        return pullTableLabel($table, $field, $suffix);
    }

    private function guessFormat(&$data, $type)
    {
        $data_type = (strpos($type,'(') === false) ? strtolower($type) : strtolower(substr($type,0,strpos($type,'(')));
        switch ($data_type) {
            case 'date':      $data['type']='date';    break;
            case 'time':      $data['type']='time';    break;
            case 'datetime':
            case 'timestamp': $data['type']='datetime';break;
            case 'bigint':
            case 'int':
            case 'mediumint':
            case 'smallint':
            case 'tinyint':   $data['type']='integer'; break;
            case 'decimal':
            case 'double':
            case 'float':     $data['type']='float';   break;
            case 'enum':
            case 'set':       $data['type']='select';  break;
            default:
            case 'char':
            case 'varchar':
            case 'tinyblob':
            case 'tinytext':
                $data['type']      = 'text';
                $data['size']      = min($this->max_input_size, trim(substr($type, strpos($type,'(')+1, strpos($type,')')-strpos($type,'(')-1)));
                $data['maxlength'] = trim(substr($type, strpos($type,'(')+1, strpos($type,')')-strpos($type,'(')-1));
                if ($data['maxlength'] > 128) { $data['type'] = 'textarea'; } break;
            case 'blob':
            case 'text':
            case 'mediumblob':
            case 'mediumtext':
            case 'longblob':
            case 'longtext':
                $data['type']      = 'textarea';
                $data['maxlength'] = '65535'; break;
        }
        return $data['type'];
    }

    /**
     * Builds the attributes to best guess the HTML structure, primarily used to build HTML input tags
     * @param array $fields - Contains the indexed field settings
     * @param array $comment - contains the working COMMENT array to build the attributes, stuff not contained in the basic mySQL table information
     * @return array $output - becomes the 'attr' index of the database field
     */
    private function buildAttr($fields, $comment)
    {
        $result= ['value'=>''];
        $this->guessFormat($result, $fields['Type']);
        if (isset($comment['type'])) { $result['type'] = $comment['type']; } // reset type if specified, messes up dropdowns
        if (isset($comment['req']) && $comment['req'] == '1') { $result['required'] = 'true'; }
        if ($fields['Default']) { $result['value'] = $fields['Default']; }
        switch ($result['type']) { // now some special cases
            case 'checkbox':
                if (isset($result['value']) && $result['value']) { $result['checked'] = 'checked'; }
                $result['value'] = 1;
                break;
            case 'select':   unset($result['size']); break;
            case 'textarea': $result['cols'] = 60; $result['rows'] = 4; break; // @todo this is a problem as it needs to vary depending on screen
            default:
        }
        return $result;
    }
}

/**
 * This function is a wrapper to start a db transaction
 * @global type $db - database connection
 */
function dbTransactionStart()
{
    global $db;
    msgDebug("\n/************ STARTING TRANSACTION *************/");
    if (!$db->inTransaction()) {
        if (!$db->beginTransaction()) { msgAdd("Houston, we have a problem. Failed starting transaction!"); }
    }
}

/**
 * This function is a wrapper to commit a db transaction
 * @global type $db - database connection
 */
function dbTransactionCommit()
{
    global $db;
    msgDebug("\n/************ COMMITTING TRANSACTION *************/");
    if ($db->inTransaction()) {
        if (!$db->commit()) { msgAdd("Houston, we have a problem. Failed committing transaction!"); }
    }
}

/**
 * This function is a wrapper to roll back a db transaction
 * @global type $db - database connection
 */
function dbTransactionRollback()
{
    global $db;
    msgDebug("\nRolling Back Transaction.");
    if (!$db->rollBack()) { msgAdd("Trying to roll back transaction when no transactions are active!"); }
}

/**
 * Writes values to the db, can be used for both inserting new rows or updating based on specified criteria
 * @global object $db - database connection
 * @param string $table - Database table (need to add prefix first)
 * @param array $data example array("field_name" => 'value_update' ...)
 * @param string $action choices are insert [DEFAULT] or update
 * @param string $parameters make up the WHERE statement during an update, only used for action == update
 * @param $quote [default: true] - quotes added to field, if turned off allows SQL methods
 * @return record id for insert, affected rows for update, false on error
 */
function dbWrite($table, $data, $action='insert', $parameters='', $quote=true)
{
    global $db;
    if (!is_object($db) || !$db->connected || !is_array($data) || sizeof($data) == 0) { return; }
    $sql = dbWritePrep($table, $data, $action, $parameters, $quote);
    return $db->Execute($sql, $action);
}

function wpdbWrite($table, $data, $action='insert', $parameters='', $quote=true)
{
    global $portalDB;
    $sql = dbWritePrep($table, $data, $action, $parameters, $quote);
    return $portalDB->Execute($sql, $action);
}

function dbWritePrep($table, $data, $action='insert', $parameters='', $quote=true)
{
    $columns = [];
    if ($action == 'insert') {
        $query = "INSERT INTO $table (`".implode('`, `', array_keys($data))."`) VALUES (";
        foreach ($data as $value) {
            if (is_array($value)) {
                msgDebug("\nExpecting string and instead got: ".print_r($value, true));
                msgAdd("Expecting string and instead got: ".print_r($value, true), 'caution');
            }
            if (is_null($value)) { $value = 'null'; }
            switch ((string)$value) {
                case 'NOW()':
                case 'now()': $query .= "now(), "; break;
                case 'NULL':
                case 'null':  $query .= "null, ";  break;
                default:      $query .= $quote ? "'".addslashes($value)."', " : "$value, "; break;
            }
        }
        $query = substr($query, 0, -2) . ')';
    } elseif ($action == 'update') {
        foreach ($data as $column => $value) {
            switch ((string)$value) {
                case 'NOW()':
                case 'now()': $columns[] = "`$column`=NOW()"; break;
                case 'NULL':
                case 'null':  $columns[] = "`$column`=NULL";  break;
                default:      $columns[] = $quote ? "`$column`='".addslashes($value)."'" : "`$column`=$value"; break;
            }
        }
        $query = "UPDATE $table SET ".implode(', ', $columns) . ($parameters<>'' ? " WHERE $parameters" : '');
    }
    return $query;
}

/**
 * Write the cache to the db if changes have been made
 * @global array $bizunoUser - User cache structure
 * @global array $bizunoLang - Language translation array
 * @global array $bizunoMod - Module cache structure
 * @param string $usrEmail - Users email address
 * @param string $lang - (xx_XX) ISO language to use for saving the language translations to a file in the myFiles folder
 * @return null - cache is written to database
 */
function dbWriteCache($usrEmail=false, $lang=false)
{
    global $bizunoUser, $bizunoMod;
    msgDebug("\nentering dbWriteCache");
    if (!biz_validate_user() || empty(getUserCache('profile', 'biz_id')) || !empty($GLOBALS['bizuno_not_installed'])) {
        return msgDebug("\nTrying to write to cache but user is not logged in or Bizuno not installed!");
    }
    if (!$usrEmail) { $usrEmail = $bizunoUser['profile']['email']; }
    if ($lang) { dbWriteLang(); } // save the language registry
    // save the users new cache
    if (!empty($GLOBALS['updateUserCache']) || !empty($GLOBALS['dbClearCache'])) {
        msgDebug("\nWriting user table");
        // BOF - Delete old registry entries
        unset($bizunoUser['profile']['menu'],$bizunoUser['profile']['biz_title'],$bizunoUser['security']); // clean up some unneeded fields
        unset($bizunoUser['profile']['ssl'], $bizunoUser['profile']['cache_date']);
        // EOF - Delete old registry entries
        $data = ['settings'=>json_encode($bizunoUser), 'cache_date'=>!empty($GLOBALS['dbClearCache']) ? 'null' : biz_date('Y-m-d H:i:s')];
        dbWrite(BIZUNO_DB_PREFIX.'users', $data, 'update', "email='$usrEmail'");
        unset($GLOBALS['updateUserCache'], $GLOBALS['dbClearCache']); // only once
    }
    if (isset($GLOBALS['updateModuleCache']) && empty($GLOBALS['noBizunoDB'])) {
        // BOF - Delete old registry entries
        unset($bizunoMod['bizuno']['shop']); // clean up some unneeded fields
        // EOF - Delete old registry entries
        ksort($bizunoMod);
        foreach ($bizunoMod as $module => $settings) {
            if (!empty($GLOBALS['updateModuleCache'][$module])) {
                if (!isset($settings['properties'])) { continue; } // update by another module with this module not loaded, skip
                msgDebug("\nWriting config table for module: $module");
                dbWrite(BIZUNO_DB_PREFIX.'configuration', ['config_value'=>json_encode($settings)], 'update', "config_key='$module'");
                unset($GLOBALS['updateModuleCache'][$module]);
            }
        }
    }
}

/**
 * Writes the language file to the users cache in JSON format. Saves rebuilding every pass through
 * @param string $iso - ISO language to store (xx_XX), defaults to users profile setting
 */
function dbWriteLang($iso='')
{
    global $io, $bizunoLang;
    msgDebug("\nEntering dbWriteLang, with passed iso = $iso");
    ksort($bizunoLang);
    if ( empty($iso)) { $iso = getUserCache('profile', 'language'); }
    msgDebug("\nin dbWriteLang, writing lang file to iso = $iso");
    if (!empty($iso)) { $io->fileWrite(json_encode($bizunoLang), "cache/lang_{$iso}.json", false, false, true); }
}

/**
 * Clears the users cache by making it 'expired' for an individual user (if passed) or all users if not
 * @param string $scope - set to all to make all users reload on next page access
 */
function dbClearCache($scope='')
{
    msgDebug("\nSetting global variable dbClearCache");
    $GLOBALS['dbClearCache'] = true;
    if ('all'==$scope) {
        msgDebug("\nClearing cache for all users");
        dbWrite(BIZUNO_DB_PREFIX.'users', ['cache_date'=>'null'], 'update');
    }
}

function dbCleanRoles($strRoles='') {
    $arrRoles = explode(":", $strRoles);
    if (empty($arrRoles)) { return []; }
    $output = [];
    foreach ($arrRoles as $role) {
        if     (in_array($role, [-1, 0])) { $output[] = $role; } // handles All and None
        elseif (dbGetValue(BIZUNO_DB_PREFIX.'roles', 'id', "id=$role")) { $output[] = $role; }
    }
    return $output;
}

function dbCleanUsers($strUsers='') {
    $arrUsers = explode(":", $strUsers);
    if (empty($arrUsers)) { return []; }
    $output = [];
    foreach ($arrUsers as $user) {
        if     (in_array($user, [-1, 0])) { $output[] = $user; } // handles All and None
        elseif (dbGetValue(BIZUNO_DB_PREFIX.'users', 'admin_id', "admin_id=$user")) { $output[] = $user; }
    }
    return $output;
}

/**
 * Wrapper to test if the users db connection is valid
 * @global object $db - database object to do the work
 * @return boolean - true if connected to db, false otherwise
 */
function dbConnected()
{
    global $db;
    if (!is_object($db)) { return false; }
    return $db->connected ? true : false;
}

/**
 * Dumps the DB (or table) to a gzipped file into a specified folder
 * @param string $filename - Name of the file to create
 * @param string $dirWrite - Folder in the user root to write to, defaults to backups/
 * @param type $dbTable - (Default ALL TABLES), set to table name for a single table
 */
function dbDump($filename='bizuno_backup', $dirWrite='', $dbTable='')
{
    global $io;
    // set execution time limit to a large number to allow extra time
    set_time_limit(20000);
    $dbHost = $GLOBALS['dbBizuno']['host'];
    $dbUser = $GLOBALS['dbBizuno']['user'];
    $dbPass = $GLOBALS['dbBizuno']['pass'];
    $dbName = $GLOBALS['dbBizuno']['name'];
    $dbPath = BIZUNO_DATA.$dirWrite;
    $dbFile = $filename.".sql.gz";
    if (!$dbTable && BIZUNO_DB_PREFIX <> '') { // fetch table list (will be entire db if no prefix)
        if (!$stmt= dbGetResult("SHOW TABLES FROM $dbName LIKE '".BIZUNO_DB_PREFIX."%'")) { return; }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows) { foreach ($rows as $row) { $dbTable .= array_shift($row).' '; } }
    }
    if (!$io->validatePath($dirWrite.$dbFile, true)) { return; }
    $cmd    = "mysqldump --opt -h $dbHost -u $dbUser -p$dbPass $dbName $dbTable | gzip > $dbPath$dbFile";
    msgDebug("\n Executing command: $cmd");
    if (!function_exists('exec')) { msgAdd("php exec is disabled, the backup cannot be achieved this way!"); }
    $result = exec($cmd, $retValue);
    if (!file_exists($dbPath.$dbFile)) { return; } // for some reason the dump failed, could be out of disk space
    chmod($dbPath.$dbFile, 0664);
    return true;
}

/**
 * Restores a sql file to the users db, DANGER as this wipes the current db
 * @param string $filename - source file to use to restore
 */
function dbRestore($filename)
{
    ini_set('memory_limit','1024M');
    set_time_limit(20000);
    msgDebug("\npath = ".BIZBOOKS_ROOT."...myFolder.../$filename");
    $dbFile = BIZUNO_DATA.$filename;
    $dbHost = $GLOBALS['dbBizuno']['host'];
    $dbUser = $GLOBALS['dbBizuno']['user'];
    $dbPass = $GLOBALS['dbBizuno']['pass'];
    $dbName = $GLOBALS['dbBizuno']['name'];
    $ext = strtolower(pathinfo($dbFile, PATHINFO_EXTENSION));
    if (in_array($ext, ['sql'])) { // raw sql in text format
        $cmd = "mysql --host=$dbHost --user=$dbUser --password=$dbPass --default_character_set=utf8 --database=$dbName < $dbFile";
    } elseif (in_array($ext, ['zip'])) { // in zip format
        $cmd = "unzip -p $dbFile | mysql --host=$dbHost --user=$dbUser --password=$dbPass --default_character_set=utf8 --database=$dbName";
    } else { // assume gz format
        $cmd = "gunzip < $dbFile | mysql --host=$dbHost --user=$dbUser --password=$dbPass --default_character_set=utf8 --database=$dbName";
    }
    msgDebug("\n Executing command: $cmd");
    if (!function_exists('exec')) { return msgAdd("php exec is disabled, the restore cannot be achieved this way!"); }
    $result = exec($cmd, $output, $retValue);
    msgDebug("\n returned result: ".print_r($result,  true));
    // @todo - this should not be so bad as the --verbose was removed which echoed every line. NEEDS TESTING USING SMALL BACKUP FILE.
//  msgDebug("\n returned output: ".print_r($output,  true)); // echoes the uncompressed sql, VERY LONG makes large debug files!
    msgDebug("\n returned status value: " .print_r($retValue, true));
    return (!empty($retValue)) ? false : true;
}

/**
 * Deletes rows from the db based on the specified filter, if no filter is sent, a delete is not performed (as a safety measure)
 * @global type $db - database connection
 * @param string $table database table to act upon
 * @param string $filter [default: false] - forms the WHERE statement
 * @return integer - number of affected rows
 */
function dbDelete($table, $filter=false)
{
    global $db;
    if (!$filter) { return; }
    $sql = "DELETE FROM $table".($filter ? " WHERE $filter" : '');
    $row = $db->Execute($sql, 'delete');
    return $row;
}

/**
 * Pulls a value or array of values from a db table row
 * @global type $db - database connection
 * @param string $table - database table name
 * @param mixed $field - one (string) or more (array) fields to retrieve
 * @param string $filter - [default: false] criteria to limit results
 * @param boolean $quote - [default: true] to add quotes to field names, false to leave off for executing SQL functions, i.e. SUM(field)
 * @param boolean $verbose [default: false] - true to show any errors or messages if there is a problem
 * @return multitype - false if no results, string if field is string, keyed array if field is array
 */
function dbGetValue($table, $field, $filter=false, $quote=true, $verbose=false)
{
    global $db;
    if (!is_object($db)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return false; }
    if (!$db->connected) { msgDebug("\nnot connected to the db!!!"); return false; }
    $is_array = is_array($field) ? true : false;
    if (!is_array($field)) { $field = [$field]; }
    if ($quote) { $table = "`$table`"; }
    $sql = "SELECT ".($quote ? ("`".implode('`, `', $field)."`") : implode(', ', $field))." FROM $table".($filter ? " WHERE $filter" : '')." LIMIT 1";
    $result = $db->Execute($sql, 'row', $verbose);
    if ($result === false) { return; }
    if ($is_array) { return $result; }
    return is_array($result) ? array_shift($result) : false;
}

function wpdbGetValue($table, $field, $filter=false, $quote=true)
{
    global $portalDB;
    if (!is_object($portalDB)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return false; }
    if (!$portalDB->connected) { msgDebug("\nnot connected to the db!!!"); return false; }
    $is_array = is_array($field) ? true : false;
    if (!is_array($field)) { $field = [$field]; }
    if ($quote) { $table = "`$table`"; }
    $sql = "SELECT ".($quote ? ("`".implode('`, `', $field)."`") : implode(', ', $field))." FROM $table".($filter ? " WHERE $filter" : '')." LIMIT 1";
    $result = $portalDB->Execute($sql, 'row');
    if ($result === false) { return; }
    if ($is_array) { return $result; }
    return is_array($result) ? array_shift($result) : false;
}

/**
 * Pulls a single row from a db table
 * @global type $db - database connection
 * @param string $table - database table name, prefix will be added
 * @param string $filter - query filter parameters,
 * @return array - table row results, false if error/no data
 */
function dbGetRow($table, $filter='', $quote=true, $verbose=false)
{
    global $db;
    if (!is_object($db)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return false; }
    if (!$db->connected) { msgDebug("\nNot connected to the db!!!"); return false; }
    if ($quote) { $sql = "SELECT * FROM `$table`".($filter ? " WHERE $filter" : '')." LIMIT 1"; }
    else        { $sql = "SELECT * FROM $table".($filter ? " WHERE $filter" : '')." LIMIT 1"; }
    $row = $db->Execute($sql, 'row', $verbose);
    return $row;
}

function wpdbGetRow($table, $filter='', $quote=true, $verbose=false)
{
    global $portalDB;
    if ($quote){ $sql = "SELECT * FROM `$table`".($filter ? " WHERE $filter" : '')." LIMIT 1"; }
    else       { $sql = "SELECT * FROM $table"  .($filter ? " WHERE $filter" : '')." LIMIT 1"; }
    $row = $portalDB->Execute($sql, 'row', $verbose);
    return $row;
}

/**
 * Pulls multiple rows from a db table
 * @global type $db - database connection
 * @param string $table - db table name
 * @param string $filter - criteria to limit data
 * @param string $order - sort order of result
 * @param mixed $field - Leave blank for all fields in row (*), otherwise fields may be string or array
 * @param integer $limit - [default 0 - no limit] limit the number of results returned
 * @param boolean $quote - [default true] Specifies if quotes should be placed around the field names, false if already escaped
 * @return array - empty for no hits or array of rows (keyed array) for one or more hits
 */
function dbGetMulti($table, $filter='', $order='', $field='*', $limit=0, $quote=true)
{
    global $db;
    if (!is_object($db)) { msgDebug("\ndb is NOT an object, need to initialize!!!"); return []; }
    if (!$db->connected) { msgDebug("\nnot connected to the db!!!"); return []; }
    if (is_array($field)) { $field = $quote ? "`".implode('`, `', $field)."`" : implode(', ', $field); }
    elseif ($field!="*")  { $field = $quote ? "`$field`" : $field; }
    $sql = "SELECT $field FROM $table".($filter ? " WHERE $filter" : '').(trim($order) ? " ORDER BY $order" : '');
    if ($limit) { $sql .= " LIMIT $limit"; }
    return $db->Execute($sql, 'rows');
}

function wpdbGetMulti($table, $filter='', $order='', $field='*', $limit=0, $quote=true)
{
    global $portalDB;
    if (!is_object($portalDB)) { return false; }
    if (!$portalDB->connected) { return false; }
    if (is_array($field)) { $field = $quote ? "`".implode('`, `', $field)."`" : implode(', ', $field); }
    elseif ($field!="*")  { $field = $quote ? "`$field`" : $field; }
    $sql = "SELECT $field FROM $table".($filter ? " WHERE $filter" : '').(trim($order) ? " ORDER BY $order" : '');
    if ($limit) { $sql .= " LIMIT $limit"; }
    return $portalDB->Execute($sql, 'rows');
}

/**
 * Executes a query and returns the resulting PDO statement
 * @global object $db - database connection
 * @param string $sql - the QUOTED, ESCAPED SQL to be executed
 * @param string $action [default: stmt] - action to perform, i.e. expected results
 * @return PDOStatement - Must be handled by caller to properly process results
 */
function dbGetResult($sql, $action='stmt')
{
    global $db;
    if (!is_object($db)) { return false; }
    if (!$db->connected) { return false; }
    return $db->Execute($sql, $action);
}

function wpdbGetResult($sql, $action='stmt')
{
    global $portalDB;
    if (!is_object($portalDB)) { return false; }
    if (!$portalDB->connected) { return false; }
    return $portalDB->Execute($sql, $action);
}

/**
 * This function takes an array of actions and executed them. Typical usage is after custom processing and end of script.
 * @param array $data - structure of resulting processing
 * @param string $hide_result - whether to show errors/success of hide message output
 * @return boolean - success or error
 */
function dbAction(&$data, $hide_result=false)
{
    $error = false;
    if (!is_array($data['dbAction'])) { return; } // nothing to do
    msgDebug("\nIn dbAction starting transaction with size of array = ".sizeof($data['dbAction']));
    dbTransactionStart();
    foreach ($data['dbAction'] as $table => $sql) {
        msgDebug("\nDatabase action for table $table and sql: $sql");
        if (!dbGetResult($sql)) { $error = true; }
    }
    if ($error) {
        if (!$hide_result) { msgAdd(lang('err_database_write')); }
        dbTransactionRollback();
        return;
    }
    if (!$hide_result) { msgAdd(lang('msg_database_write'), 'success'); }
    dbTransactionCommit();
    return true;
}

/**
 * Pulls the next status value from the table current_status, increments it, and stores the next value. This function should be used within a transaction to assure proper incrementing of the reference value.
 * @param string $field - Table current_status field name to retrieve the value
 * @return string - The current reference value
 */
function dbPullReference($field='')
{
    if (!$field) { return; }
    $ref = dbGetValue(BIZUNO_DB_PREFIX.'current_status', $field);
    $output = $ref;
    $ref++;
    msgDebug("\nRetrieved for field: $field value: $output and incremented to get $ref");
    dbWrite(BIZUNO_DB_PREFIX.'current_status', [$field => $ref], 'update');
    return $output;
}

/**
 * Pulls a specific index from the settings field from a within a table for a single row.
 * @param string $table - db table name
 * @param string $index - index within the settings to extract information
 * @param string $filter - db filter used to restrict results to a single row
 * @return string - Result of setting, null if not present
 */
function dbPullSetting($table, $index, $filter = false)
{
    $settings = json_decode(dbGetValue($table, 'settings', $filter), true);
    return (isset($settings[$index])) ? $settings[$index] : $index;
}

/**
 * Checks for the existence of a table
 * @param string $table - db table to look for
 * @return boolean - true if table exist, false otherwise
 */
function dbTableExists($table)
{
    if (!$stmt = dbGetResult("SHOW TABLES LIKE '$table'")) { return; }
    if (!$row  = $stmt->fetch(\PDO::FETCH_ASSOC)) { return; }
    $value = array_shift($row);
    if (false === $value) { return; }
    return ($value==$table) ? true : false;
}

/**
 * Checks to see if a field exists within a table
 * @global type $db - database connection
 * @param string $table - db table containing the field to search for
 * @param string $field - field within the table to search for
 * @return boolean - true if field exists, false otherwise
 */
function dbFieldExists($table, $field)
{
    global $db;
    $result = $db->query("SHOW FIELDS FROM `$table`");
    if (!$result) { return; }
    foreach ($result as $row) {
        if ($row['Field'] == $field) { return true; }
    }
}

/**
 * Wrapper to load the structure of a table to parse input variables
 * @global class $db - database connection
 * @param string $table - db table
 * @param string $suffix - [default: ''] suffix to use when building attributes
 * @param string $prefix - [default: ''] prefix to use when building attributes
 * @param array $lang - Language overrides
 * @return array - table properties
 */
function dbLoadStructure($table, $suffix='', $prefix='', $lang=[])
{
    global $db;
    msgDebug("\nEntering dbLoadStructure with table: $table, suffix = $suffix, prefix = $prefix");
    return $db->loadStructure($table, $suffix, $prefix, $lang);
}

/**
 * This function merges database data into the structure attributes
 * @param array $structure - database table structure
 * @param array $data - database data row to fill $attr['value']
 * @param string $suffix [default: ''] - adds a suffix to the index if present
 * @return array - modified $structure
 */
function dbStructureFill(&$structure, $data=[], $suffix='')
{
    if (!is_array($data)) { return; }
    foreach ($structure as $field => $values) {
        if (!isset($values['attr']['type'])) { $values['attr']['type'] = 'text'; }
        switch ($values['attr']['type']) {
            case 'checkbox':
                if (isset($data[$field.$suffix])) { // only adjust if the field is present, prevents clearing flag if field is not part of merge
                    if ($data[$field.$suffix]) { $structure[$field]['attr']['checked'] = 'checked'; } else { unset($structure[$field]['attr']['checked']); }
                }
                break;
            default:
                $structure[$field]['attr']['value'] = isset($data[$field.$suffix]) ? $data[$field.$suffix] : '';
        }
    }
}

/**
 * This function builds the SQL and loads into an array the result of the query.
 * @param array $data - the structure to build the SQL and read data
 * @return array - integer [total] - total number of rows, array [rows] - row data - can be sent directly to view
 */
function dbTableRead($data)
{
    global $currencies;
    $sqlTables = '';
    // Need to force strict mode if more than one table as fields with same name will overlap resulting in bad content, i.e. column id gets last tables value
    if (sizeof($data['source']['tables']) > 1) { $data['strict'] = true; }
    foreach ($data['source']['tables'] as $table) {
        $sqlTables .= isset($table['join']) && strlen($table['join'])>0 ?  ' '.$table['join'] : '';
        $sqlTables .= ' '.$table['table'];
        $sqlTables .= isset($table['links'])&& strlen($table['links'])>0? ' ON '.$table['links'] : '';
    }
    $criteria = [];
    if (!empty($data['source']['filters'])) {
        foreach ($data['source']['filters'] as $key => $value) {
            if ($key == 'search') {
                if (isset($value['attr']) && isset($value['attr']['value'])) {
                    $criteria[] = dbGetSearch($value['attr']['value'], $data['source']['search']);
                }
            } else {
                if (!empty($value['sql'])) { $criteria[] = $value['sql']; }
            }
        }
    }
    $sqlCriteria = implode(' AND ', $criteria);
    $order = [];
    if (!empty($data['source']['sort'])) {
        $sortOrder = sortOrder($data['source']['sort']);
        foreach ($sortOrder as $value) { if (strlen($value['field']) > 1) { $order[] = $value['field']; } }
    }
    $sqlOrder= !empty($order) ? implode(', ', $order) : '';
    $output  = ['total' => 0, 'rows'=> []];
    if (isset($data['strict']) && $data['strict']) {
        $aFields   = [];
        foreach ($data['columns'] as $key => $value) {
            if ($key == 'action') { continue; } // skip action column
            if (isset($value['field']) && strpos($value['field'], ":") !== false) { // look for embedded settings
                $parts = explode(":", $value['field'], 2);
                $aFields[] = "{$parts[0]} AS `{$parts[0]}`";
            } elseif (isset($value['field'])) {
                $aFields[] = $value['field']." AS `$key`";
            }
        }
        if (!$temp = dbGetMulti($sqlTables, $sqlCriteria, $sqlOrder, $aFields, 0, false)) { return $output; }
        $output['total'] = sizeof($temp);
        $result = array_slice($temp, ($data['page']-1)*$data['rows'], $data['rows']);
        msgDebug("\n started with ".$output['total']." rows, page = {$data['page']}, rows = {$data['rows']}, resulted in effective row count = ".sizeof($result));
    } else { // pull all columns irregardless of the field list
        $limit   = isset($data['rows']) && isset($data['page']) ? (($data['page']-1)*$data['rows']).", ".$data['rows'] : 0;
        $output['total'] = dbGetValue($sqlTables, 'count(*) AS cnt', $sqlCriteria, false);
        msgDebug("\n total rows via count(*) = ".$output['total']);
        if (!$result = dbGetMulti($sqlTables, $sqlCriteria, $sqlOrder, '*', $limit)) { return $output; }
    }
    foreach ($result as $row) {
        $GLOBALS['currentRow'] = $row; // save the raw data for aliases and formatting alterations of data
        if (isset($row['currency'])) {
            $currencies->iso  = $row['currency']; // @todo this needs to temporarily set a value in viewFormatter for processing
            $currencies->rate = isset($row['currency_rate']) ? $row['currency_rate'] : 1;
        }
        foreach ($data['columns'] as $key => $value) {
            if (isset($value['field']) && strpos($value['field'], ":") !== false) { // look for embedded settings
                $parts = explode(":", $value['field'], 2);
                $tmp = json_decode($row[$parts[0]], true);
                $row[$key] = isset($parts[1]) && isset($tmp[$parts[1]]) ? $tmp[$parts[1]] : '';
            }
            if (isset($value['alias'])) { $row[$key] = $GLOBALS['currentRow'][$value['alias']]; }
        }
        foreach ($row as $key => $value) {
            if (!empty($data['columns'][$key]['process'])){ $row[$key] = viewProcess($row[$key], $data['columns'][$key]['process']); }
            if (!empty($data['columns'][$key]['format'])) { $row[$key] = viewFormat ($row[$key], $data['columns'][$key]['format']); }
        }
        $output['rows'][] = $row;
    }
    return $output;
}

/**
 * Pulls data from a db table and builds a data array for a drop down HTML5 input field
 * @param string $table - db table name
 * @param string $id - table field name to be used as the id of the select drop down
 * @param string $field - table field name to be used as the description of the select drop down
 * @param string $filter - SQL filter to limit results
 * @param string $nullText - description to use for no selection (null id assumed)
 * @return array $output - formatted result array to be used for HTML5 input type select render function
 */
function dbBuildDropdown($table, $id='id', $field='description', $filter='', $nullText='')
{
    $output = [];
    if ($nullText) { $output[] = ['id'=>'0', 'text'=>$nullText]; }
    $sql = "SELECT $id AS id, $field AS text FROM $table"; // no ` as sql function may be used
    if ($filter) { $sql .= " WHERE $filter"; }
    if (!$stmt = dbGetResult($sql)) { return $output; }
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($result as $row) { $output[] = ['id'=>$row['id'], 'text'=>$row['text']]; }
    return $output;
}

/**
 * Tries to find a contact record to match a set of submitted fields, currently set to use primary_name, address1, and city
 * @TODO - If the same email is used for BOTH customer and vendor then the wrong ID may be returned
 * @param type $value - the value to use to find the contact
 * @param type $args - arguments to use as filters
 * @return array [contact_id, address_id] if found, false if not found
 */
function dbGetContact($value=0, $args=[])
{
    msgDebug("\nEntering dbGetContact with value = $value and args = ".print_r($args, true));
    $cID   = 0;
    $output= [];
    switch ($args['type']) {
        default:
        case 'cID':  $cID = $value;
            break;
        case 'aID':  $cID = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'ref_id', "TYPE='m' AND address_id='$value'");
            break;
        case 'email':$cID = dbGetValue(BIZUNO_DB_PREFIX.'address_book', 'ref_id', "TYPE='m' AND email='$value'");
            break;
    }
    if (empty($cID)) { // cID = 0, get HQ values
        $output['address']['main'] = addressLoad(); // defaults to my business address
    } else {
        $output['contact'] = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
        $addrs = dbGetMulti(BIZUNO_DB_PREFIX.'address_book', "ref_id=$cID");
        foreach ($addrs as $addr) {
            switch ($addr['type']) {
                case 'm': $output['address']['main']  = $addr; break;
                case 'b': $output['address']['bill'][]= $addr; break;
                case 's': $output['address']['ship'][]= $addr; break;
            }
        }
    }
    return $output;
}

function dbGetDashSettings($menu='home', $dashID='') {
    if ($menu == 'portal') { return []; }
    $fields = dbGetRow(BIZUNO_DB_PREFIX.'users_profiles', "user_id='".getUserCache('profile', 'admin_id', false, 0)."' AND menu_id='$menu' AND dashboard_id='$dashID'");
    $settings= json_decode($fields['settings'], true);
    if (!is_array($settings)) { $settings = []; }
    return $settings;
}

/**
 * Calculates the current balance of a GL account, will add back current row on edits.
 * @param string $glAcct
 * @param db_date $postDate
 * @param integer $omitID
 * @return float
 */
function dbGetGLBalance($glAcct, $postDate='', $omitID=0) {
    if (empty($postDate)) { $postDate = biz_date('Y-m-d'); }
    $row     = dbGetRow(BIZUNO_DB_PREFIX.'journal_periods', "start_date<='$postDate' AND end_date>='$postDate'");
    if (!$row) { return msgAdd(sprintf(lang('err_gl_post_date_invalid'), $postDate)); }
    $balance = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'beginning_balance', "period={$row['period']} AND gl_account='$glAcct'");
    $balance+= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'SUM(debit_amount - credit_amount) as balance', "gl_account='$glAcct' AND post_date>='{$row['start_date']}' AND post_date<='$postDate'", false);
    // add back the record amount if editing
    if (!empty($omitID)) { $balance += dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'credit_amount', "ref_id=$omitID AND gl_type='ttl'"); }
    msgDebug("returning from dbGetGLBalance with balance = $balance");
    return $balance;
}

/**
 * Retrieves the cost of an inventory assembly using the item_cost field from the inventory table
 * @param integer $rID - inventory table record ID
 * @return float - calculated cost of the inventory item
 */
function dbGetInvAssyCost($rID=0)
{
    $cost = 0;
    if (empty($rID)) { return $cost; }
    $iID  = intval($rID);
    $items= dbGetMulti(BIZUNO_DB_PREFIX.'inventory_assy_list', "ref_id=$iID");
    if (empty($items)) { $items[] = ['qty'=>1, 'sku'=>dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID")]; } // for non-assemblies
    foreach ($items as $row) {
        if (empty($GLOBALS['inventory'][$row['sku']]['unit_cost'])) {
            $GLOBALS['inventory'][$row['sku']]['unit_cost'] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "sku='{$row['sku']}'");
        }
        $cost+= $row['qty'] * $GLOBALS['inventory'][$row['sku']]['unit_cost'];
    }
    return $cost;
}

/**
 * Takes the journal main ID and calculates the cogs for all items on the order using the inventory item_cost values
 */
function dbGetOrderCOGS($mainID=0)
{
    $cogs = 0;
    $items= dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=".(int)$mainID." AND gl_type='itm'", '', ['sku']);
    foreach ($items as $item) {
        if (empty($item['sku'])) { continue; }
        $cogs += dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "sku='".addslashes($item['sku'])."'");
    }
    return $cogs;
}

/**
 * Takes the journal main ID and calculates the cogs for all items on the order using the inventory item_cost values
 */
function dbGetCOGSj12($mainID=0)
{
    $row = dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id=".(int)$mainID." AND gl_type='cog'");
    $cogs = round($row['debit_amount'] + $row['credit_amount'], 2);
    msgDebug("\nEntering dbGetCOGSj12 with id=$mainID and calculated cogs = $cogs");
    return $cogs;
}

/**
 *
 * @param type $roleID
 * @return type
 */
function dbGetRoleMenu($roleID=0)
{
    if (empty($roleID)) { $roleID = getUserCache('profile', 'role_id'); }
    $role = dbGetValue(BIZUNO_DB_PREFIX.'roles', 'settings', "id='$roleID'");
    if (empty($roleID)) { return []; } // not logged in or permission allowed
    $settings = json_decode($role, true);
    $settings['quickBar']['settings']['label'] = getUserCache('profile', 'title'); // set the user name to current user
    if (sizeof($GLOBALS['bizPortal']) > 1) { // for multi-biz usesrs, add ability to switch businesses to quickBar
        $settings['quickBar']['settings']['child']['newBiz'] = ['order'=>85,'label'=>'Switch Businesses','icon'=>'update','required'=>true,'events'=>['onClick'=>"jsonAction('bizuno/portal/changeBiz');"]];
    }
    return ['menuBar'=>['child'=>isset($settings['menuBar']) ? $settings['menuBar'] : []], 'quickBar'=>['child'=>$settings['quickBar']]];
}

/**
 *
 * @param type $search
 * @param type $fields
 * @return type
 */
function dbGetSearch($search, $fields)
{
    $search_text = addslashes($search);
    return "(".implode(" LIKE '%$search_text%' OR ", $fields)." LIKE '%$search_text%')";
}

function dbGetSecurity($roleID=0)
{
    if (empty($roleID)) { $roleID = getUserCache('profile', 'role_id'); } // not logged in or no access allowed
    $role = dbGetValue(BIZUNO_DB_PREFIX.'roles', 'settings', "id='$roleID'");
    if (empty($role)) { return []; } // no role_id defined
    $settings = json_decode($role, true);
    return $settings['security'];
}

/**
 * Creates a list of available stores, including main store for use in views
 * @param boolean $addAll - [default false] Adds option All at top of list
 * @return array - ready to render as pull down
 */
function dbGetStores($addAll=false)
{
    if ($addAll) { $output[] = ['id'=>-1, 'text'=>lang('all')]; }
    $output[] = ['id'=>0, 'text'=> getModuleCache('bizuno', 'settings', 'company', 'id')];
    $result = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "type='b'", 'short_name');
    foreach ($result as $row) { $output[] = ['id'=>$row['id'], 'text'=>$row['short_name']]; }
    return $output;
}

    /**
     * Finds the quantity of a SKU in stock at a given store or all stores
     * @param string $sku - SKU to find store stock
     * @param integer $storeID - Store ID, default -1 - All
     * @return type
     */
function dbGetStoreQtyStock($sku, $storeID=-1)
{
    $crit = "sku='$sku'";
    if ($storeID>-1) { $crit .= " AND store_id=$storeID"; }
    $store_bal = dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', "SUM(remaining)", $crit, false);
    $qty_owed  = dbGetValue(BIZUNO_DB_PREFIX.'journal_cogs_owed', "SUM(qty)",       $crit, false);
    return ($store_bal - $qty_owed);
}

/**
 * Retrieves fiscal year period details
 * @param integer $period - period to get data on
 * @return array - details of requested fiscal year period information
 */
function dbGetPeriodInfo($period)
{
    $values     = dbGetRow  (BIZUNO_DB_PREFIX.'journal_periods', "period='$period'");
    $period_min = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', "MIN(period)", "fiscal_year={$values['fiscal_year']}", false);
    $period_max = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', "MAX(period)", "fiscal_year={$values['fiscal_year']}", false);
    $fy_max     = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', ['MAX(fiscal_year) AS fiscal_year', 'MAX(period) AS period'], "", false);
    $output = [
        'period'       => $period,
        'period_start' => $values['start_date'],
        'period_end'   => $values['end_date'],
        'fiscal_year'  => $values['fiscal_year'],
        'period_min'   => $period_min,
        'period_max'   => $period_max,
        'fy_max'       => $fy_max['fiscal_year'],
        'fy_period_max'=> $fy_max['period']];
    msgDebug("\nCalculating period information, returning with values: ".print_r($output, true));
    return $output;
}

/**
 * Calculates fiscal dates, pulled from journal_period table
 * @param integer $period - The period to gather the db inform from
 * @return array - database table row results for the specified period
 */
function dbGetFiscalDates($period)
{
    $result = dbGetRow(BIZUNO_DB_PREFIX."journal_periods", "period=$period");
    msgDebug("\nCalculating fiscal dates with period = $period. Resulted in: ".print_r($result, true));
    if (!$result) { // post_date is out of range of defined accounting periods
        return msgAdd(sprintf(lang('err_gl_post_date_invalid'), "period $period"));
    }
    return $result;
}

/**
 * Generates a drop down list of the fiscal years in the system
 * @return array - formatted result array to be used for HTML5 input type select render function
 */
function dbFiscalDropDown()
{
    $stmt   = dbGetResult("SELECT DISTINCT fiscal_year FROM ".BIZUNO_DB_PREFIX."journal_periods GROUP BY fiscal_year ORDER BY fiscal_year ASC");
    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $output = [];
    foreach ($result as $row) { $output[] = ['id'=>$row['fiscal_year'], 'text'=>$row['fiscal_year']]; }
    return $output;
}

/**
 * Generates a drop down list of GL Accounts
 * @param string $inc_sel - include Please Select at beginning of drop down
 * @param array $limits - GL account types to restrict list to
 * @return array - formatted result array to be used for HTML5 input type select render function
 */
function dbGLDropDown($inc_sel=true, $limits=[], $hideInactive=true)
{
    $output = [];
    if ($inc_sel) { $output[] = ['id'=>'0', 'text'=>lang('select')]; }
    $chart = getModuleCache('phreebooks', 'chart', 'accounts');
    foreach ($chart as $row) {
        if ($hideInactive && !empty($row['inactive'])) { continue; }
        if (sizeof($limits)==0 || in_array($row['type'], $limits)) {
            $output[] = ['id'=>$row['id'], 'text'=>$row['id'].' : '.$row['title']];
        }
    }
    return $output;
}

/**
 * Generates the date part of the SQL WHERE clause based on the encoded data.
 * Also generates the textual description for reports and forms
 * @param string $dateType - encoded date to format, typically: format:start_date:end_date
 * @param string $df - field to use in the table (less the DB_PREFIX)
 * @return array - [sql, description, start_date, end_date]
 */
function dbSqlDates($dateType='a', $df=false) {
    msgDebug("\nEntering dbSqlDates with dateType = $dateType and date field = $df");
    if (!$df) { $df = 'post_date'; }
    $dates = localeGetDates();
    $DateArray = explode(':', $dateType);
    $tnow = time();
    $dbeg = '1969-01-01';
    $dend = '2029-12-31';
    switch ($DateArray[0]) {
        case "a": // All, skip the date addition to the where statement, all dates in db
        case "all": // old way
            $sql  = '';
            $desc = '';
            break;
        case "b": // Date Range
            $sql  = '';
            $desc = lang('date_range');
            if ($DateArray[1] <> '') {
                $dbeg = clean($DateArray[1], 'date');
                $sql .= "$df>='$dbeg'";
                $desc.= ' '.lang('from').' '.$DateArray[1];
            }
            if ($DateArray[2] <> '') { // a value entered, check
                if (strlen($sql) > 0) { $sql .= ' AND '; }
                $dend = localeCalculateDate(clean($DateArray[2], 'date'), 1);
                $sql .= "$df<'$dend'";
                $desc.= ' '.lang('to').' '.$DateArray[2];
            }
            $desc .= '; ';
            break;
        case "c": // Today (specify range for datetime type fields to match for time parts)
            $dbeg = $dates['Today'];
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' = '.viewDate($dates['Today']).'; ';
            break;
        case "d": // This Week
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], biz_date('j', $tnow) - biz_date('w', $tnow), $dates['ThisYear']));
            $dend = localeCalculateDate(biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], biz_date('j', $tnow) - biz_date('w', $tnow)+6, $dates['ThisYear'])), 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate(localeCalculateDate($dend, -1)).'; ';
            break;
        case "e": // This Week to Date
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], biz_date('j', $tnow)-biz_date('w', $tnow), $dates['ThisYear']));
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "f": // This Month
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], 1, $dates['ThisYear']));
            $dend = localeCalculateDate(biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], $dates['TotalDays'], $dates['ThisYear'])), 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate(localeCalculateDate($dend, -1)).'; ';
            break;
        case "g": // This Month to Date
            $dbeg = biz_date('Y-m-d', mktime(0, 0, 0, $dates['ThisMonth'], 1, $dates['ThisYear']));
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "h": // This Quarter
            $QtrStrt = getModuleCache('phreebooks', 'fy', 'period') - ((getModuleCache('phreebooks', 'fy', 'period') - 1) % 3);
            $temp = dbGetFiscalDates($QtrStrt);
            $dbeg = $temp['start_date'];
            $temp = dbGetFiscalDates($QtrStrt + 2);
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'; ';
            break;
        case "i": // Quarter to Date
            $QtrStrt = getModuleCache('phreebooks', 'fy', 'period') - ((getModuleCache('phreebooks', 'fy', 'period') - 1) % 3);
            $temp = dbGetFiscalDates($QtrStrt);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "j": // This Year
            $YrStrt= getModuleCache('phreebooks', 'fy', 'period_min');
            $temp1 = dbGetFiscalDates($YrStrt);
            $dbeg  = $temp1['start_date'];
            $temp2 = dbGetFiscalDates($YrStrt + 11);
            $dend  = localeCalculateDate($temp2['end_date'], 1);
            $sql   = "$df>='$dbeg' AND $df<'$dend'";
            $desc  = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp2['end_date']).'; ';
            break;
        case "k": // Year to Date
            $YrStrt = getModuleCache('phreebooks', 'fy', 'period_min');
            $temp = dbGetFiscalDates($YrStrt);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dates['Today']).'; ';
            break;
        case "l": // This Period
            $temp = dbGetFiscalDates(getModuleCache('phreebooks', 'fy', 'period'));
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('period').' '.getModuleCache('phreebooks', 'fy', 'period').' ('.viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'); ';
            break;
        case 'm': // Last Fiscal Year
            $minPer = getModuleCache('phreebooks', 'fy', 'period_min');
            if ($minPer > 12) {
                $temp1 = dbGetFiscalDates($minPer-12);
                $dbeg  = $temp1['start_date'];
                $temp2 = dbGetFiscalDates($minPer-1);
                $dend  = localeCalculateDate($temp2['end_date'], 1);
                $desc  = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp2['end_date']).'; ';
            } else {
                $dbeg = '2000-01-01';
                $dend = '2000-12-31';
                $desc = lang('date_range').' No Data Available';
            }
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            break;
        case 'n': // Last Fiscal Year to Date (through same month-day of last fiscal year)
            $period = getModuleCache('phreebooks', 'fy', 'period_min');
            if ($period > 12) {
                $temp = dbGetFiscalDates($period-12);
                $dbeg = $temp['start_date'];
                $dend = localeCalculateDate($dates['Today'], 1, 0, -1);
                $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($dend).'; ';
            } else {
                $dbeg = '2000-01-01';
                $dend = '2000-12-31';
                $desc = lang('date_range').' No Data Available';
            }
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            break;
        case 'o': // Reseverd for later
            break;
        case 'p': // Reseverd for later
            break;
        case 'q': // Reseverd for later
            break;
        case 'r': // Last Quarter
            $QtrStrt = (getModuleCache('phreebooks', 'fy', 'period') - ((getModuleCache('phreebooks', 'fy', 'period') - 1) % 3) - 3);
            $temp = dbGetFiscalDates($QtrStrt);
            $dbeg = $temp['start_date'];
            $temp = dbGetFiscalDates($QtrStrt + 2);
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('date_range').' '.lang('from').' '.viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'; ';
            break;
        case 's': // Last Period
            $lPer = getModuleCache('phreebooks', 'fy', 'period') - 1;
            $temp = dbGetFiscalDates($lPer);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($temp['end_date'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('period')." $lPer (".viewDate($dbeg).' '.lang('to').' '.viewDate($temp['end_date']).'); ';
            break;
        case 't': // Last 30 days
            $dbeg = localeCalculateDate($dates['Today'], -30);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_30_days');
            break;
        case 'u': // Reseverd for later
            break;
        case 'v': // last 60 days
            $dbeg = localeCalculateDate($dates['Today'], -60);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_60_days');
            break;
        case 'w': // Last 90 days
            $dbeg = localeCalculateDate($dates['Today'], -90);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_90_days');
            break;
        case 'x': // Last 6 Months
            $dbeg = localeCalculateDate($dates['Today'], 0, -6);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_90_days');
            break;
        case 'y': // Last 12 Months
            $dbeg = localeCalculateDate($dates['Today'], 0, 0, -1);
            $dend = localeCalculateDate($dates['Today'], 1);
            $sql  = "$df>='$dbeg' AND $df<'$dend'";
            $desc = lang('last_90_days');
            break;
        default: // date by period (integer)
        case "z":
            if (!intval($DateArray[0])) { $DateArray[0] = getModuleCache('phreebooks', 'fy', 'period'); }
            $temp = dbGetFiscalDates($DateArray[0]);
            $dbeg = $temp['start_date'];
            $dend = localeCalculateDate($temp['end_date'], 1);
            // Assumes the table has a field named period
            $sql  = "period='{$DateArray[0]}'";
//          $sql  = "$df>='$dbeg' AND $df<'$dend'"; // was this before but breaks for trial balance report
            $desc = lang('period')." {$DateArray[0]} (".viewFormat($temp['start_date'], 'date')." - ".viewFormat($temp['end_date'], 'date')."); ";
            break;
    }
    return ['sql'=>$sql,'description'=>$desc,'start_date'=>$dbeg,'end_date'=>$dend];
}
/**
 * Generates the db sql criteria for present and historical accounting quarters
 * @param type $range - choice from select drop down to build sql
 * @param string $df - db Field to set criteria
 * @return array
 */
function dbSqlDatesQrtrs($range=0, $df='')
{
    $dates= dbSqlDates('h'); // this quarter
    switch ($range) {
        default: // current quarter
        case 0:  $ds = $dates['start_date'];                              $de = $dates['end_date'];                             break;
        case 1:  $ds = localeCalculateDate($dates['start_date'], 0,  -3); $de =localeCalculateDate($dates['end_date'], 0,  -3); break;
        case 2:  $ds = localeCalculateDate($dates['start_date'], 0,  -6); $de =localeCalculateDate($dates['end_date'], 0,  -6); break;
        case 3:  $ds = localeCalculateDate($dates['start_date'], 0,  -9); $de =localeCalculateDate($dates['end_date'], 0,  -9); break;
        case 4:  $ds = localeCalculateDate($dates['start_date'], 0, -12); $de =localeCalculateDate($dates['end_date'], 0, -12); break;
        case 5:  $ds = localeCalculateDate($dates['start_date'], 0, -15); $de =localeCalculateDate($dates['end_date'], 0, -15); break;
    }
    $sql  = "$df>='$ds' AND $df<'$de'";
    $desc = lang('date_range').' '.lang('from').' '.viewDate($ds).' '.lang('to').' '.viewDate(localeCalculateDate($de, -1)).'; ';
    return ['sql'=>$sql,'description'=>$desc,'start_date'=>$ds,'end_date'=>$de];
}
/**
 * Prepares a drop down values list of users
 * @param boolean $active_only - [default: true] Restrict list to active users only, default true
 * @param boolean $showNone - [default: true] Show None option (appears after ShowAll option and showSelect option
 * @param boolean $showAll - [default: true] Show All option (appears second, first if showSelect is false)
 * @return array - list of users in array ready for view in a HTML list element
 */
function listUsers($active_only=true, $showNone=true, $showAll=true)
{
    $output = [];
    if ($showAll)  { $output[] = ['id'=>'-1','text'=>lang('all')]; }
    if ($showNone) { $output[] = ['id'=>'0', 'text'=>lang('none')]; }
    $result = dbGetMulti(BIZUNO_DB_PREFIX."users", $active_only ? "inactive='0'" : '', 'title');
    foreach ($result as $row) { $output[] = ['id'=>$row['admin_id'], 'text'=>$row['title']]; }
    return $output;
}

/**
 * Prepares a drop down values list of roles
 * @param boolean $active_only - Restrict list to active roles only, default true
 * @param boolean $showNone - Show the None selection after the showAll and before the list
 * @param boolean $showAll - Show the All selection first
 * @return array - list of roles in array ready for view in a HTML list element
 */
function listRoles($active_only=true, $showNone=true, $showAll=true)
{
    $output = [];
    if ($showAll)  { $output[] = ['id'=>'-1','text'=>lang('all')]; }
    if ($showNone) { $output[] = ['id'=>'0', 'text'=>lang('none')]; }
    $result = dbGetMulti(BIZUNO_DB_PREFIX.'roles', $active_only ? "inactive='0'" : '', 'title');
    foreach ($result as $row) { $output[] = ['id'=>$row['id'], 'text'=>$row['title']]; }
    return $output;
}

/**
 * Converts the variable type to a string replacement, mostly used to build JavaScript variables
 * @param mixed $value - The value to encode
 * @return mixed - Value encoded
 */
function encodeType($value)
{
    switch (gettype($value)) {
        case "boolean": return $value ? 'true' : 'false';
        default:
        case "resource":
        case "integer":
        case "double":  return $value; // no quotes required
        case "NULL":
        case "string":  return "'".str_replace("'", "\'", $value)."'"; // add quotes
        case "array":
        case "object":  return json_encode($value);
    }
}

/**
 * Validates the existence of a tab for which a custom field is placed, otherwise creates it
 * @param string $mID - [Required] Module ID, i.e. inventory, extFixedAssets, etc.
 * @param string $tID - [Required] Table Name, i.e. inventory, contacts, etc.
 * @param string $title - [Required] Tab title to search for, must match exactly, best to use lang() to match.
 * @param integer $order - [Default 50] Sort order of the tab within the list
 * @return integer - ID of the tab, either existing or newly created
 */
function validateTab($mID, $tID, $title, $order=50)
{
    if (!dbFieldExists(BIZUNO_DB_PREFIX.'current_status', 'next_tab_id')) { // PhreeBooks conversion may not have this field
        dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."current_status ADD `next_tab_id` INT(11) NOT NULL DEFAULT '1' COMMENT 'type:hidden;tag:NextTabID;order:14'");
    }
    $tabs = getModuleCache($mID, 'tabs');
    foreach ($tabs as $id => $tab) { if ($tab['table_id']==$tID && $tab['title']==$title) { return $id; } }
    $id = dbGetValue(BIZUNO_DB_PREFIX.'current_status', 'next_tab_id');
    msgDebug("\nRetrieved id: ".print_r($id, true)." from validateTab");
    dbWrite(BIZUNO_DB_PREFIX.'current_status', ['next_tab_id'=>($id+1)], 'update');
    $tabs[$id] = ['table_id'=>$tID, 'title'=>$title, 'sort_order'=>$order];
    setModuleCache($mID, 'tabs', false, $tabs);
    return $id;
}
