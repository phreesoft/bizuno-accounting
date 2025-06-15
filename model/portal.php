<?php
/*
 * Portal class to interface to CMS - Wordpress
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
 * @license    http://opensource.org/licenses/OSL-3.0  Open Software License (OSL 3.0)
 * @version    6.x Last Update: 2023-10-31
 * @filesource /model/portal.php
 */

namespace bizuno;

if (!defined('BIZUNO_KEY')) { define('BIZUNO_KEY', 'sbt8Ms2g8LfeJ67Z'); }

/*********************************************
 * @TODO All of these function need to be rolled into the class if possible
 *********************************************/

/**
 * Validates the credentials of a user to the database
 * @param string $email - username or email address.
 * @param string $pass - password
 * @param bool $verbose - display error message if true
 */
function biz_authenticate($email='', $pass='', $verbose=true) {
    $user = \wp_authenticate( $email, $pass );
    if ($verbose && is_wp_error($user)) { msgAdd($user->get_error_message()); }
    return !is_wp_error($user) ? $user->ID : false;
}

/**
 * Bizuno operates in local time. Returns WordPress safe date in PHP date() format if no timestamp is present, else PHP date() function
 * @param string $format - [default: 'Y-m-d'] From the PHP function date()
 * @param integer $timestamp - Unix timestamp, defaults to now
 * @return string
 */
function biz_date($format='Y-m-d', $timestamp=null) {
    if (!is_null($timestamp)) {
        $local = date( $format, $timestamp );
    } else {
        $local = \wp_date( $format );
    }
//    msgDebug("\nLocal date via type: $format with timestamp: $timestamp is $local, timestamp is: ".\wp_date( 'Y-m-d H:i:s' ));
    return $local;
}

function bizIsActivated($plugin='bizuno-accounting') {
    if (!function_exists('\is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    return \is_plugin_active( "$plugin/$plugin.php" ) ? true : false;
}

/**
 * Validates the user is logged in and returns the creds if true
 */
function biz_validate_user() {
    global $mixer;
    $scramble = clean('bizunoSession', 'text', 'cookie');
    if (empty($scramble)) { // try to see if logged into WordPress
        $usr = \wp_get_current_user();
        return !empty($usr->user_email) ? [$usr->user_email] : false;
    }
    $creds = json_decode(base64_decode($mixer->decrypt(BIZUNO_KEY, $scramble), true));
    return !empty($creds[0]) ? $creds : false;
}

/**
 * Verifies the username and password combination for a specified user ID from the Bizuno tables mapped to the portal
 * @param mixed $email - Bizuno user table, if type = id, then integer, else email
 * @param string $pass - password to verify in the portal
 * @param string $verbose - whether to display error message if login fails
 * @return boolean - true on success, false otherwise
 */
function biz_validate_user_creds($email='', $pass='') {
    msgDebug("\nEntering biz_validate_user_creds with email = $email and pass = ".(!empty($pass) ? '****' : '--empty--'));
    $creds= ['user_login'=>$email, 'user_password'=>$pass, 'remember'=>false];
    $user = \wp_signon( $creds, false );
    if (is_wp_error($user)) { return msgAdd($user->get_error_message()); }
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID);
    setUserCache('profile', 'email', $email);
    set_user_cookie($email);  // just register email since business ID is not known yet
    if (!empty(getUserCache('profile', 'biz_id'))) { return true; }
    setSession([$email, getUserCache('profile', 'biz_id')]);
    return true;
}

function bizGetUser($email='') { // pulls the current user and returns the details
    if (empty($email)) {
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
    }
    $user  = get_user_by( 'email', $email );
    msgDebug("\nReturned user = ".print_r($user, true));
    if (empty($user)) { return []; }
    $bizID = getUserCache('profile', 'biz_id');
    $roleID= get_user_meta( $user->ID, 'bizbooks_role_'.$bizID, true );
    msgDebug("\nUsing user $user->ID and biz $bizID found role: ".print_r($roleID, true));
    return ['id' => $user->ID, 'email' => $user->data->user_email, 'title' => $user->data->display_name, 'role'=>$roleID, 'inactive'=>$user->data->user_status];
}
function bizSetUser($user, $bizID=1) { // $user->user_email
    $roleID= !empty( $_POST['bizbooks_role_'  .$bizID] ) ? $_POST['bizbooks_role_'.$bizID] : 0;
    update_user_meta( $user->ID, 'bizbooks_enable_'.$bizID, 1 );
    update_user_meta( $user->ID, 'bizbooks_role_'  .$bizID, $roleID );
    $biz   = portalGetBizIDVal($bizID);
    $dbTmp = new db($biz);
    if (!$dbTmp || !$dbTmp->connected) { return; }
    if (!userTableExists("{$biz['prefix']}users", $dbTmp)) { return; }
    $userID= $dbTmp->Execute("SELECT admin_id FROM {$biz['prefix']}users WHERE email='$user->user_email' LIMIT 1", 'row');
    if (empty($userID)) {
        $sql = "INSERT INTO {$biz['prefix']}users (`email`, `title`, `role_id`, `settings`) VALUES ('$user->user_email', '$user->display_name', $roleID,
            '".json_encode(['profile'=>['email'=>$user->user_email, 'biz_id'=>$bizID, 'role_id'=>$roleID]])."');";
        $dbTmp->Execute($sql, 'insert');
    }
}
function bizChangeEmail($bizID, $old_email, $new_email) {
    $biz   = portalGetBizIDVal($bizID);
    $dbTmp = new db($biz);
    if (!$dbTmp || !$dbTmp->connected) { return; }
    if (!userTableExists("{$biz['prefix']}users", $dbTmp)) { return; }
    // pull settings from old email and set to new email
    $settings = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'users', 'settings', "email='$old_email'"), true);
    $settings['profile']['email'] = $new_email;
    // update the users table
    dbWrite(BIZUNO_DB_PREFIX.'users', ['email'=>$new_email, 'settings'=>json_encode($settings)], 'update', "email='$old_email'");
}
function bizDelUser($user, $bizID=1) {
    delete_user_meta( $user->ID, 'bizbooks_enable_'.$bizID );
    delete_user_meta( $user->ID, 'bizbooks_role_'  .$bizID );
    // inactivate the users table record in the Bizuno users table
    $biz   = portalGetBizIDVal($bizID);
    $dbTmp = new db($biz);
    if (!$dbTmp || !$dbTmp->connected) { return; }
    if (!userTableExists("{$biz['prefix']}users", $dbTmp)) { return; }
    $userID= $dbTmp->Execute("SELECT admin_id FROM {$biz['prefix']}users WHERE email='$user->user_email' LIMIT 1", 'row');
    if (!empty($userID)) {
        $dbTmp->Execute("UPDATE {$biz['prefix']}users SET inactive='1' WHERE admin_id={$userID['admin_id']};", 'update');
    }
}

/**
 *
 * @global type $bizunoUser
 * @param array $creds
 */
function setSession($creds) {
    global $bizunoUser;
    msgDebug("\nEntering setSession");
    if (isset($creds[0])) { $bizunoUser['profile']['email']  = $creds[0]; }
    $bizunoUser['profile']['biz_id'] = isset($creds[1]) ? $creds[1] : 0;
    $GLOBALS['updateUserCache']  = 0;
    $GLOBALS['updateModuleCache']= [];
}

/**
 * Set the encrypted cookie for the current user
 * @global class $mixer - Encryption class
 * @param string $email - users email address
 * @param integer $bizID - business ID, always 1 for WordPress
 * @param integer $time - future time to set expiration in seconds
 */
function set_user_cookie($email='', $bizID=0, $time=false) {
    global $mixer;
    msgDebug("\nSetting user and session cookie with email = $email and bizID = $bizID and time = $time");
    bizSetCookie('bizunoUser', $email, time()+(60*60*24*7)); // 7 days
    if (empty($time)) { $time = 60*60; } // 1 hour
    $now = time();
    $cookie = $mixer->encrypt(BIZUNO_KEY, base64_encode("[\"$email\",$bizID,$now]"));
    bizSetCookie('bizunoSession', $cookie, $now+$time);
}

/**
 * Sets the default database to use for users that are not logged in to allow for customization
 */
function portalSetDB() {
}

function getWordPressEmail($userID='') {
    $user_info = get_userdata(get_current_user_id());
    if (empty($userID) && !empty($user_info->user_email)) { return $user_info->user_email; }
    return $userID;
}

function bizuno_get_locale() { return \get_locale(); }

/**
 * Fix for Google that changes the format of the language to en-US (underscore to dash)
 * @param string $iso
 */
function cleanLang(&$iso='en_US') {
    $gcln = str_replace('-', '_', $iso);
    $parts= explode('_', $gcln);
    $iso  = strtolower($parts[0]).'_'.strtoupper($parts[1]);
    if (strpos(getUserCache('profile', 'language'), '-') !== false) { setUserCache('profile', 'language', $iso); }
}

/**
 *
 * @param type $table
 * @param type $pdb
 * @return boolean
 */
function userTableExists($table, $pdb) {
    if (!$stmt = $pdb->Execute("SHOW TABLES LIKE '$table'")) { return false; }
    if (!$row  = $stmt->fetch(\PDO::FETCH_ASSOC)) { return false; }
    $value = array_shift($row);
    if (false === $value) { return false; }
    return ($value==$table) ? true : false;
}

function portalRead($table, $criteria='') { return dbGetRow($table, $criteria); }

function portalMulti($table, $filter='', $order='', $field='', $limit=0)  { return dbGetMulti($table, $filter, $order, $field, $limit); }

function portalExecute($sql)  { return dbGetResult ($sql); }

function portalWrite($table, $data=[], $action='insert', $parameters='') {
    if ('business'==$table) { return; }
    return dbWrite($table, $data, $action, $parameters);
}

function portalDelete($email, $bizID) {
    $user = get_user_by( 'email', $email );
    bizDelUser($user, $bizID);
}

function portalUpdateBiz() { }

/**
 * Sets the paths for the modules, core and extensions needed to build the registry
 * *** Sequence is important, do not change! ***
 * @return module keyed array with path the modules requested
 */
function portalModuleList() {
    $modList = [];
    portalModuleListScan($modList, 'BIZBOOKS_ROOT/controllers/');
    portalModuleListScan($modList, 'BIZBOOKS_EXT/controllers/');
    portalModuleListScan($modList, 'BIZUNO_DATA/myExt/controllers/'); // load custom modules
    msgDebug("\nReturning from portalModuleList with list: ".print_r($modList, true));
    return $modList;
}

function portalModuleListScan(&$modList, $path) {
    $absPath= bizAutoLoadMap($path);
    msgDebug("\nIn portalModuleListScan with path = $path and mapped path = $absPath");
    if (!is_dir($absPath)) { return; }
    $custom = scandir($absPath);
    foreach ($custom as $name) {
        if ($name=='.' || $name=='..' || !is_dir($absPath.$name)) { continue; }
        if (file_exists($absPath."$name/admin.php")) { $modList[$name] = $path."$name/"; }
    }
}

/**
 * Validates that the user has permission to access the Plugin and it is activated
 * @return boolean
 */
function portalValidatePlugin($name) {
    if (get_option("bizuno_{$name}_active")) { return true; }
    require_once(ABSPATH.'wp-admin/includes/plugin.php'); // load is_plugin_active function
    if (\is_plugin_active("bizuno-$name/bizuno-$name.php")) { return true; }
    return false;
}

/**
 * Fetches a list of valid businesses the user has access to
 * @return array
 */
function portalGetBizIDs() {
   $email  = getUserCache('profile', 'email');
   $userID = get_current_user_id();
   $output = [];
   foreach ($GLOBALS['bizPortal'] as $idx => $biz) {
       if (empty(get_user_meta( $userID, 'bizbooks_enable_'.$biz['id'], true ))) { continue; }
       $dbTmp = new db($biz);
       if (!$dbTmp || !$dbTmp->connected) {
           msgAdd("Failed connecting to the business: {$biz['title']}!");
           continue;
       }
       if (userTableExists("{$biz['prefix']}configuration", $dbTmp)) {
           $user   = $dbTmp->Execute("SELECT admin_id FROM {$biz['prefix']}users WHERE email='$email' LIMIT 1", 'row');
           if (empty($user)) { continue; } // no access to this business
           $values = $dbTmp->Execute("SELECT * FROM {$biz['prefix']}configuration WHERE config_key='bizuno' LIMIT 1", 'row');
           $setting= $values ? json_decode($values['config_value'], true) : [];
           $GLOBALS['bizPortal'][$idx]['title'] = $setting['settings']['company']['primary_name'];
           $src    = !empty($setting['settings']['company']['logo']) ? BIZBOOKS_URL_FS."&src={$biz['id']}/images/{$setting['settings']['company']['logo']}" : BIZUNO_LOGO;
           $action = "bizuno/portal/login&bizID={$biz['id']}";
       } else { // not installed, make sure they have admin privileges to install
           if( !current_user_can('administrator') ) { continue; }
           $src    = BIZUNO_LOGO;
           $action = "bizuno/portal/login&bizID={$biz['id']}";
       }
       $output["{$biz['id']}"] = ['id'=>$biz['id'], 'title'=>$biz['title'], 'src'=>$src, 'action'=>$action];
   }
   return $output;
}

/**
 *
 * @param type $bizID
 * @param type $idx
 * @return type
 */
function portalGetBizIDVal($bizID, $idx=false) {
    $defBiz = [];
    foreach ($GLOBALS['bizPortal'] as $dbData) {
        if (!empty($dbData['default'])) { $defBiz = $dbData; }
        if ($bizID <> $dbData['id']) { continue; }
        return !empty($idx) ? $dbData[$idx] : $dbData;
    }
    if (empty($defBiz)) { return; }
    return !empty($idx) ? $defBiz[$idx] : $defBiz;
}

function portalGetOption($value) {
    return \get_option($value);
}

/**
 * Returns the pull down list of skins from the bizuno-skins plugin if installed and enabled.
 */
function portalSkins() {
    $output = [];
    $path = BIZBOOKS_ROOT.'assets/jquery-easyui/themes/';
    $choices = scandir($path);
    foreach ($choices as $choice) {
        if (!in_array($choice, ['.','..','icons']) && is_dir($path.$choice)) {
            $output[] = ['id'=>$choice, 'text'=>ucwords(str_replace('-', ' ', $choice))];
        }
    }
    return $output;
}

/**
 * Returns the pull down list of icons from the bizuno-icons plugin if installed and enabled.
 */
function portalIcons(&$icons=[]) {
    $output = [];
    $path = BIZBOOKS_ROOT.'view/icons/';
    $choices = scandir($path);
    foreach ($choices as $choice) {
        if (!in_array($choice, ['.','..']) && is_dir($path.$choice)) {
            $output[] = ['id'=>$choice, 'text'=>ucwords(str_replace('-', ' ', $choice))];
        }
    }
    return $output;
}

/**
 * Returns the path for the requested icon set
 * @param string $icon - Icon set to locate
 * @return string - path the requested icon set
 */
function portalIconPath($icon='default') {
    $path = BIZBOOKS_ROOT.'view/icons/';
    if (!in_array($icon, ['default'])) { $path = BIZBOOKS_ROOT.'assets/icons/'; }
    msgDebug("\nReturning from portalIconPath with icon path = $path");
    return $path;
}

function portalIconURL($icon='default') {
    $path = BIZBOOKS_URL_ROOT.'view/icons/';
    if (!in_array($icon, ['default'])) { $path = BIZBOOKS_URL_ROOT.'assets/icons/'; }
    msgDebug("\nReturning from portalIconURL with icon path = $path");
    return $path;
}

function portalMigrateGetMgr(&$layout=[]) {
    $path = BIZBOOKS_ROOT."controllers/migrate";
    $mods = scandir($path);
    foreach ($mods as $fn) {
        if ($fn=='.' || $fn=='..' || !is_dir($path.$fn)) { continue; }
        require_once($path."$fn/admin.php");
        $fqdn  = "\\bizuno\\{$fn}Admin";
        $ctrl  = new $fqdn();
        $ctrl->impExpMain($layout);
    }
}

function portalMigrateLoad($modID='') {
   $path = BIZBOOKS_ROOT."controllers/migrate/$modID/admin.php";
   if (!bizAutoLoad($path, "{$modID}Admin")) { return msgAdd("class $modID not found as path $path"); }
   $fqdn  = "\\bizuno\\{$modID}Admin";
   return new $fqdn();
}

final class portal
{
    public $api_active= false;
    public $api_local = true;

    function __construct()
    {
        $defaults = ['url'   => '',
            'oauth_client_id'=> '',  'oauth_client_secret'=> '',
            'rest_user_name' => '',  'rest_user_pass'     => '',
            'prefix_order'   => 'WC','prefix_customer'    => 'WC',
            'journal_id'     => 0,   'autodownload'       => 0];
        foreach ($defaults as $key => $default) {
            $this->options[$key] = \get_option ( 'bizuno_api_'.$key, $default );
        }
        if (!function_exists('\is_plugin_active')) { include_once(ABSPATH.'wp-admin/includes/plugin.php'); }
        if ( \is_plugin_active ( 'bizuno-api/bizuno-api.php' ) ) {
            $this->api_active= true;
            $this->api_local = empty( $this->options['url'] ) ? true : false;
        }
        $this->useOauth = \is_plugin_active ( 'oauth2-provider/wp-oauth-server.php' ) ? true : false;
    }

    /**
     * Makes the decision to process an API transaction locally or remote via REST API
     * @param array $layout
     * @param array $args
     * @return type
     */
    public function apiAction($args=[])
    {
        if (!$this->api_active) { return msgAdd("The Free Bizuno API plugin is required for this operation, please install and activate it from the WordPress store."); }
        if ($this->api_local) { // It's local, so just update the db
            if (empty($args['method'])) { return msgAdd("Missing or invalid local method"); }
            $theClass = "\\bizuno\\{$args['class']}";
            $local    = new $theClass($this->options);
            $theMethod= $args['method'];
            $postID   = $local->$theMethod($args['data']);
        } else { // Use REST to connect and transmit data
            $resp     = $this->restRequest($args['type'], $this->options['url'], "wp-json/bizuno-api/v1/{$args['endpoint']}", ['data'=>$args['data']]);
            msgDebug("\napiAction received back from REST: ".print_r($resp, true));
            if (isset($resp['message'])) { msgMerge($resp['message']); }
            $postID   = !empty($resp['ID']) ? $resp['ID'] : 0;
        }
        return !empty($postID) ? $postID : false;
    }

    public function restRequest($type, $server, $endpoint='', $data=[], $opts=[]) {
        // for same server REST requests, need to include cookies to validate
        // COMMENTED OUT, DON'T USE REST FOR SAME SERVER
        $cookies = [];
//      foreach( $_COOKIE as $name => $value ) { $cookies[] = new \WP_Http_Cookie( ['name'=>$name, 'value'=>$value] ); }
        if (!empty($this->useOauth)) {
            msgDebug("\nSending REST request via oAuth");
            $token = $this->restOauthToken();
            $optsEP= array_replace_recursive(['headers'=>['authorization'=>"Bearer $token", 'x-locale'=>'en_US', 'content-type'=>'application/json']], $opts);
        } else {
            msgDebug("\nSending REST request via User/Password");
            $headers= ['email'=>$this->options['rest_user_name'], 'pass'=>$this->options['rest_user_pass']];
            $optsEP = array_replace_recursive(['headers'=>$headers,'cookies'=>$cookies], $opts);
        }
        $url = empty($endpoint) ? $server : "$server/$endpoint";
        msgDebug("\nHeaders: ".print_r($optsEP, true));
        msgDebug("\nSending request of type $type to url $url and data of size : ".sizeof($data));
        $response= json_decode($this->cURL($url, $data, strtolower($type), $optsEP), true);
        msgDebug("\nLast response is: ".print_r($response, true));
        if (empty($response) && !is_array($response)) { msgAdd(sprintf(lang('err_no_communication'), $server), 'trap'); }
        if (isset($response['message']) && is_string($response['message'])) { // unexpected message returned
            msgAdd("Woo restRequest Received back from server: {$response['message']}");
            unset($response['message']);
        }
        return $response;
    }

    /**
     * Fetch oAuth2 token from a RESTful API server
     * @return token if successful, null if error
     */
    public function restOauthToken($server='', $id='', $secret='')
    {
        msgDebug("\nEntering restTokenValidate with path = $server");
        if (empty($server)) { return msgAdd("Error! no server name passed!"); }
        $token = getModuleCache('bizuno', 'rest');
        if (empty($token[$server]['token']) || $token[$server]['expires_in'] < time()-10) { // get a new token for today
            // get an authorization code
            $code = json_decode($this->cURL("{$server}/oauth/authorize", "response_type=code&client_id=$id", 'get'), true);
            if (!is_array($code)) { return msgAdd('A string was returned for the OAuth2 code! Not good.'); }
            // get an access token
            // WHAT TO DO WITH $code['code?']
            $optsA = ['headers'=>['Content-Type'=>'application/x-www-form-urlencoded']];
            $dataA = "grant_type=client_credentials&client_id=$id&client_secret=$secret";
            $tokenA= json_decode($this->cURL("{$server}/oauth/token", $dataA, 'post', $optsA), true);
            if (!is_array($tokenA)) { return msgAdd("A string was returned! Not good."); }
            if (!empty($tokenA['error'])) { return msgAdd("REST Token Request Error: ".print_r($tokenA['errors'], true)); }
            msgDebug("\nread token = {$tokenA['access_token']} and expires_in = {$tokenA['expires_in']}");
            if (empty($tokenA['access_token'])) { return msgAdd("Error retrieving token from $server, all APIs will be unavailable!"); }
            $token[$server]['token']   = $tokenA['access_token'];
            $token[$server]['expires_in']= time()+$tokenA['expires_in'];
            setModuleCache('bizuno', 'rest', '', $token);
        }
        return $token[$server]['token'];
    }

    /**
     * This method retrieves data from a remote server using cURL
     * @param string $url - URL to request data
     * @param string $data - data string, will be attached for get and through setopt as post or an array
     * @param string $type - [default 'get'] Choices are 'get' or 'post'
     * @return result if successful, false (plus messageStack error) if fails
     */
    function cURL($url, $data='', $type='get', $opts=[]) {
        if (strtolower($type)=='get') {
            global $wp_version;
            $args = array(
                'timeout'    => 5,
                'redirection'=> 5,
                'httpversion'=> '1.0',
                'user-agent' => 'WordPress/'.$wp_version.'; ' . home_url(),
                'blocking'   => true,
                'headers'    => !empty($opts['headers']) ? $opts['headers'] : [],
                'cookies'    => !empty($opts['cookies']) ? $opts['cookies'] : [],
                'body'       => null,
                'compress'   => false,
                'decompress' => true,
                'sslverify'  => false,
                'stream'     => false,
                'filename'   => null
            );
            if (is_array($data)) {
                $tmp = [];
                foreach ($data as $key=>$value) { $tmp[] = "$key=".urlencode($value); }
                $vars = implode('&', $tmp);
            } else { $vars = $data;}
            $req = !empty($data) ? $url.'?'.$vars : $url;
            msgDebug("\nCalling WordPress cURL GET with url = $req and args = ".print_r($args, true));
            $response = \wp_remote_get ($req, $args);
        } else { // 'post', 'put', 'delete'
            $args  = [
                'method'     => strtoupper($type),
                'timeout'    => 90,
                'redirection'=> 5,
                'httpversion'=> '1.0',
                'blocking'   => true,
                'headers'    => !empty($opts['headers']) ? $opts['headers'] : [],
                'cookies'    => !empty($opts['cookies']) ? $opts['cookies'] : [],
                'body'       => $data,
                'sslverify'  => false,
            ];
            msgDebug("\nCalling WordPress cURL POST with url = $url and args = ".print_r($args, true));
            $response = \wp_remote_post($url, $args);
        }
        if (\is_wp_error($response)) {
           $error_message = $response->get_error_message();
           msgDebug("\nError using WordPress cURL: $error_message");
           unset($response); // since it is an object
           $response['body'] = '';
        }
        msgDebug("\ncURL Received back from server: ".print_r($response['body'], true));
        return $response['body'];
    }

    /*************************** REST ENDPOINTS *********************************/
    public function accountWalletList($cID=0)
    {
        global $bizunoMod;
        if (empty($cID)) { return []; }
        // need to load the Payment props from cache
        $bizunoMod['payment'] = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='payment'"), true);
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/payment/wallet.php', 'paymentWallet');
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/payment/methods/payfabric/payfabric.php', 'payfabric');
        $wallet = new paymentWallet(); // sets up the Bizuno Environment
        msgDebug("\nSetting the cID, payfabric object = ".print_r($wallet->payfabric, true));
        $wallet->cID = $cID;
        $wallet->type= 'c';
        $wallet->pfID= getWalletID($wallet->cID);
        setUserCache('security', 'j12_mgr', 2);
        msgDebug("\nFetching the wallet");
        $output = $wallet->list();
        msgDebug("\nReturned with wallet: ".print_r($output, true));
        return $output;
    }
}

class portalMail
{
    function __construct() { }

    /**
     * This sends an e-mail to one or more recipients, handles errors.
     * @return boolean - true if successful, false with messageStack errors if not
     */
    public function sendMail()
    {
        if (getModuleCache('bizuno', 'settings', 'mail', 'mail_mode')=='server') { return $this->hostMail(); } // host: server
        $attachments = [];
        // CMS host, i.e. WordPress
        $body   = '<html><body>'.$this->Body.'</body></html>';
        $headers= [
            "Content-Type: text/html; charset=UTF-8",
            "From: "    .$this->cleanAddress($this->FromName, $this->FromEmail),
            "Reply-To: ".$this->cleanAddress($this->FromName, $this->FromEmail)];
        foreach ($this->toEmail as $addr){ $to[]     = $this->cleanAddress($addr['name'], $addr['email']); }
        foreach ($this->toCC as $addr)   { $headers[]= 'Cc: '.$this->cleanAddress($addr['name'], $addr['email']); }
        //***************
        // Added for gMail (OAuth2) type service where all mails have to originate through a single mail account. This fix
        // adds the sender as a BCC so copies are returned to the originators email address. They can auto move them to a
        // specified folder in their mail system so they are not filling up the inbox.
        if (!empty(getModuleCache('bizuno', 'mail', 'oauth2_enable'))) {
            $bizFrom = getModuleCache('bizuno', 'settings', 'company', 'email');
            if ($bizFrom <> $this->FromEmail){ $headers[]= "Bcc: $this->FromName <$this->FromEmail>"; }
        }
        //***************
        msgDebug("\nReady to send CMS host email with headers = ".print_r($headers, true));
        foreach ($this->attach as $file) {
            if (!empty($file['name'])) { // it's in the $_FILES folder, move to where WordPress can get it
                msgDebug("\nMoving file from temp location: {$file['path']} to Bizuno data folder: ".BIZUNO_DATA."temp/{$file['name']}");
                move_uploaded_file($file['path'], BIZUNO_DATA."temp/{$file['name']}");
                $file['path'] = BIZUNO_DATA."temp/{$file['name']}";
            }
            $attachments[]= $file['path'];
        }
        msgDebug("\nAttachments array = ".print_r($attachments, true));
        $success = wp_mail( $to, $this->Subject, $body, $headers, $attachments );
        // remove the temo files
        foreach ($attachments as $file) { unlink($file); }
        return $success ? true : false;
    }

    /**
     * Uses phpMailer to send the message, good for self hosted sites
     */
    private function hostMail()
    {
        global $phpmailer;
        if ( ! ( $phpmailer instanceof PHPMailer ) ) {
            require_once ABSPATH . WPINC . '/class-phpmailer.php';
            require_once ABSPATH . WPINC . '/class-smtp.php';
        }
        error_reporting(E_ALL & ~E_NOTICE); // This is to eliminate errors from undefined constants in phpmailer
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        // For debugging connections and such
//      $mail->SMTPDebug = 2;
//      $mail->Debugoutput = function($str, $level) { msgDebug("\nphpMailer Debug level $level; message: $str"); };
        try {
            $mail->CharSet = defined('CHARSET') ? CHARSET : 'utf-8'; // default "iso-8859-1";
            $mail->isHTML(true); // set email format to HTML
            $mail->SetLanguage(substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2), BIZBOOKS_ROOT."apps/PHPMailer/language/");
            if (!$mail->ValidateAddress($this->FromEmail)) { return msgAdd(sprintf(lang('error_invalid_email'), $this->FromEmail)); }
            $mail->setFrom($this->FromEmail, $this->FromName);
            $mail->addReplyTo($this->FromEmail, $this->FromName);
            $mail->Subject = $this->Subject;
            $mail->Body    = '<html><body>'.$this->Body.'</body></html>';
            // clean message for text only mail recipients
            $textOnly = str_replace(['<br />','<br/>','<BR />','<BR/>','<br>','<BR>'], "\n", $this->Body);
            $mail->AltBody =  strip_tags($textOnly);
            foreach ($this->toEmail as $addr) {
                if (!$mail->ValidateAddress($addr['email'])) { return msgAdd(sprintf(lang('error_invalid_email'), "{$addr['name']} <{$addr['email']}>")); }
                $mail->AddAddress($addr['email'], $addr['name']);
            }
            foreach ($this->toCC as $addr) {
                if (!$mail->ValidateAddress($addr['email'])) { return msgAdd(sprintf(lang('error_invalid_email'), "{$addr['name']} <{$addr['email']}>")); }
                $mail->addCC($addr['email'], $addr['name']);
            }
            foreach ($this->attach as $file) { $mail->AddAttachment($file['path'], $file['name']); }
            $smtp = $this->setTransport();
            if (!empty($smtp['smtp_enable'])) {
                $mail->isSMTP();
                $mail->SMTPAuth = true;
                $mail->Host = $smtp['smtp_host'];
                $mail->Port = $smtp['smtp_port'];
                if ($smtp['smtp_port'] == 587) { $mail->SMTPSecure = 'tls'; }
                $mail->Username = $smtp['smtp_user'];
                $mail->Password = $smtp['smtp_pass'];
            }
            $mail->send();
        } catch (phpmailerException $e) {
            msgAdd(sprintf("Email send failed to: $this->ToName"));
            msgAdd($e->errorMessage());
            return false;
        } catch (Exception $e) {
            msgAdd(sprintf("Email send failed to: $this->ToName"));
            msgAdd($e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Cleans the name and address per WordPress requirements
     * @param string $name
     * @param string $email
     * @return string
     */
    private function cleanAddress($name, $email)
    {
        return clean($name, 'alpha_num').' <'.sanitize_email($email).'>';
    }

    /**
     * Tests to retrieve the sending email address transport preferences
     * @param obj $mail - phpMailer object
     */
    private function setTransport()
    {
        $smtp    = getModuleCache('bizuno', 'settings', 'mail');
        $settings= dbGetValue(BIZUNO_DB_PREFIX.'users', 'settings', "email='{$this->FromEmail}'");
        if (!empty($settings)) {
            $settings = json_decode($settings, true);
            if (!empty($settings['profile']['smtp_enable'])) {
                $smtp = [
                    'smtp_enable'=> $settings['profile']['smtp_enable'],
                    'smtp_host'  => $settings['profile']['smtp_host'],
                    'smtp_port'  => $settings['profile']['smtp_port'],
                    'smtp_user'  => $settings['profile']['smtp_user'],
                    'smtp_pass'  => $settings['profile']['smtp_pass']];
            }
        }
        return $smtp;
    }
}
