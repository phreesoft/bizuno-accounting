<?php
/**
 * Plugin Name: Bizuno Accounting/ERP/CRM
 * Plugin URI:  https://www.phreesoft.com
 * Description: Bizuno is a powerful ERP/Accounting application adapted as a plugin for WordPress. Bizuno creates a portal running within the WordPress administrator. Once activated, click on the Bizuno menu item to complete the installation and access your Bizuno business.
 * Version:     6.7.8.2
 * Author:      PhreeSoft, Inc.
 * Author URI:  http://www.PhreeSoft.com
 * Text Domain: bizuno
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl.html
 * Domain Path: /locale
 */

defined( 'ABSPATH' ) || exit;

if (!defined('MODULE_BIZUNO_VERSION')) { define('MODULE_BIZUNO_VERSION', '6.7.8.2'); }

class bizuno_accounting
{
    public function __construct()
    {
        // Actions
        add_action ( 'init',                              [ $this, 'init_bizuno'] );
        add_action ( 'admin_init',                        [ $this, 'admin_init_bizuno' ] );
        add_action ( 'admin_menu',                        [ $this, 'admin_menu_bizuno' ] );
        add_action ( 'wp_before_admin_bar_render',        [ $this, 'bizuno_admin_menu_mods'] );
        add_action ( 'phpmailer_init',                    [ $this, 'bizuno_phpmailer_init' ], 10, 1 );
        add_action ( 'template_redirect',                 [ $this, 'bizunoPageRedirect' ] );
        add_action ( 'wp_ajax_bizuno_ajax',               [ $this, 'bizunoAjax' ] );
        add_action ( 'wp_ajax_nopriv_bizuno_ajax',        [ $this, 'bizunoAjax' ] );
        add_action ( 'wp_ajax_bizuno_ajax_fs',            [ $this, 'bizunoAjaxFs' ] );
        add_action ( 'wp_ajax_nopriv_bizuno_ajax_fs',     [ $this, 'bizunoAjaxFs' ] );
//      add_action ( 'wp_ajax_bizuno_api',                [ $this, 'bizuno_api' ] );
//      add_action ( 'wp_ajax_nopriv_bizuno_api',         [ $this, 'bizuno_api' ] );
        add_action ( 'edit_user_profile',                 [ $this, 'bizunoUserEdit'] ); // maybe change to show_user_profile
        add_action ( 'show_user_profile',                 [ $this, 'bizunoUserEdit'] );
        add_action ( 'personal_options_update',           [ $this, 'bizunoUserSave'] );
        add_action ( 'edit_user_profile_update',          [ $this, 'bizunoUserSave'] );
        add_action ( 'profile_update',                    [ $this, 'bizuno_profile_change' ] );
        add_action ( 'wp_logout',                         [ $this, 'bizuno_user_logout' ] );
        add_action ( 'bizuno_daily_event',                [ $this, 'daily_cron' ] );
        add_action ( 'shutdown',                          [ $this, 'bizuno_debug_log_write'] );
        // Filters
        add_filter ( 'xmlrpc_methods',                    function($methods) { unset( $methods['pingback.ping'] ); return $methods; } );
        // Install/Uninstall hooks
        register_activation_hook( __FILE__ ,              [ $this, 'activate'] );
        register_deactivation_hook( __FILE__ ,            [ $this, 'deactivate'] );
        register_uninstall_hook( __FILE__,                'bizuno_uninstall' ); // do not put inside of class
    }

    // Shows the admin menu if the user is logged in and has permission to reach Bizuno Accounting
    public function init_bizuno()
    {
        require_once(__DIR__.'/bizunoCFG.php');
        // the next line needs to be fixed, causes issues when developing sites. Perhaps check for logged in and access to Bizuno enabled.
//        if ( $this->validateUser() ) { add_filter('show_admin_bar', '__return_true', 1000); }
        /********* API *********/
        register_post_status( 'wc-shipped', ['label'=>'Shipped','public'=>true,'exclude_from_search'=>false,'show_in_admin_all_list'=>true,'show_in_admin_status_list'=>true,
            'label_count'=>_n_noop( 'Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>' )] );
    }

    public function admin_init_bizuno()
    {
        add_option( 'bizuno_pro_key', '');
        register_setting( 'bizuno_options_group', 'bizuno_pro_key', 'bizuno_callback' );
    }

    public function admin_menu_bizuno()
    {
        add_menu_page('Bizuno Accounting', 'Bizuno', 'manage_options', 'bizuno', 'bizuno_html', plugins_url( 'view/images/icon_16.png', __FILE__ ), 90);
//      add_options_page('Bizuno Settings','Bizuno', 'manage_options', 'bizuno-accounting', 'bizuno_options_page');
    }

    /**
     * Inserts the Bizuno Accounting menu items to the admin toolbar before the logout selection
     * NOTE: Show admin toolbar needs to be enabled for non-admins to see the menu
     */
    public function bizuno_admin_menu_mods()
    {
        global $wp_admin_bar;
        if ( !$this->validateUser() ) { return; }
        if (empty(get_page_by_path('bizuno'))) { return; }
        $logout = $wp_admin_bar->get_node('logout');
        $wp_admin_bar->remove_node( 'logout' );
        $wp_admin_bar->add_node( ['id'=>'tb-admin-bizBooks', 'title'=>'Bizuno Accounting','href'=>BIZUNO_HOME,   'parent'=>'user-actions', 'meta'=>['target'=>'_blank']] );
//      $wp_admin_bar->add_node( ['id'=>'tb-admin-bizOffice','title'=>'Bizuno Office',    'href'=>BIZOFFICE_HOME,'parent'=>'user-actions', 'meta'=>['target'=>'_blank']] );
        $wp_admin_bar->add_node( $logout );
    }

    public function bizuno_phpmailer_init( $phpmailer )
    {
        if ( get_post_field( 'post_name' ) == 'bizuno' ) { $phpmailer->IsHTML( true ); } // set email format to HTML
    }

    public function bizunoCtlr()
    {
        require_once (BIZBOOKS_ROOT.'controllers/portal/controller.php');
        if ( !$this->validateUser() ) { return; }
        return new \bizuno\portalCtl();
    }

    // Controller for bizOffice Storage home page
    public function bizOfficeCtlr()
    {
//      if (empty(get_user_meta( get_current_user_id(), 'bizoffice_enable', true))) { return; }
//      require_once ('bizOffice.php');
//      $ctl   = new \bizuno\bizOfficeCtl();
//      $ctl->compose();
//      new \bizuno\view($ctl->layout);
//      exit();
    }

    public function bizunoPageRedirect() {
        global $post;
        if ( is_user_logged_in() && !empty($post->post_name) && 'bizuno'==$post->post_name) {
            $ctl = $this->bizunoCtlr();
            if (empty($ctl)) { return; }
            $ctl->compose();
            new \bizuno\view($ctl->layout);
            exit();
        }
//      if ( !empty($post->post_name) && 'bizuno-office' == $post->post_name) { $this->bizOfficeCtlr(); }
    }

    // Launch method for all bizOffice AJAX transactions
    public function bizOfficeAjax()
    {
//      if ( is_user_logged_in() ) { $this->bizOfficeCtlr(); }
    }

    // Launch method for all Bizuno AJAX transactions
    public function bizunoAjax()
    {
        if ( is_user_logged_in() ) {
            $ctl = $this->bizunoCtlr();
            if (empty($ctl)) { return; }
            $ctl->compose();
            new \bizuno\view($ctl->layout);
            exit();
        }
    }

    // for the file system restricted to bizuno upload folder and below ONLY!
    public function bizunoAjaxFs()
    {
        require_once ("bizunoFS.php");
        wp_die();
    }

    public function bizunoUserEdit( $user )
    {
        if (!is_admin()) { return; }
        require_once (BIZBOOKS_ROOT .'model/portal.php');
        $html  = '<h3 class="heading">Bizuno Accounting User Permissions</h3>';
        $html .= '<table class="form-table">';
        // Try to put all of these into a serialized string, before biz related
        $html .= '  <tr><th><label for="bizuno_wallet_id">[e-Store Side Only] Bizuno Wallet ID - To link accounts (e-mail addresses must match)</label></th>';
        $html .= '      <td><input type="text" name="bizuno_wallet_id" id="bizuno_wallet_id" value="'.get_user_meta( $user->ID, 'bizuno_wallet_id', true).'" /></td></tr>';
        foreach ($GLOBALS['bizPortal'] as $biz) {
            $enabled = get_user_meta( $user->ID, 'bizbooks_enable_'.$biz['id'], true);
            $checked = !empty($enabled) ? ' checked' : '';
            $roleID  = get_user_meta( $user->ID, 'bizbooks_role_'  .$biz['id'], true);
            $dbTmp   = new wpdb($biz['user'], $biz['pass'], $biz['name'], $biz['host']);
            if (empty($dbTmp)) { continue; }
            if ( $dbTmp->get_var( "SHOW TABLES LIKE '{$biz['prefix']}configuration'" ) == $biz['prefix'].'configuration' ) {
                $values  = $dbTmp->get_row("SELECT * FROM `{$biz['prefix']}configuration` WHERE config_key='bizuno'");
                $setting = $values ? json_decode($values->config_value, true) : [];
                $title   = !empty($setting['settings']['company']['primary_name']) ? $setting['settings']['company']['primary_name'] : $biz['title'];
                $roles   = $dbTmp->get_results("SELECT id, title FROM `{$biz['prefix']}roles` WHERE inactive='0'");
            } else {
                $title   = $biz['title'];
                $roles   = [(object)['id'=>1, 'title'=>'Admin']];
            }
            $html .= '<tr><th><label for="contact">Allow access to: '.$title.'</label></th>';
            $html .= '  <td><input type="checkbox" class="input-checkbox form-control" id="bizbooks_enable_'.$biz['id'].'" name="bizbooks_enable_'.$biz['id'].'"'.$checked.' /></td></tr>';
            // get the role pull down for each business
            if (!empty($roles)) { // error of some kind, maybe not initialized, or no entries which is bad
                $html .= '<tr><th>Role:</th><td>';
                $html .= '<select name="bizbooks_role_'.$biz['id'].'" id="bizbooks_role_'.$biz['id'].'">';
                foreach ($roles as $role) { $html .= '<option value="'.$role->id.'"'.($roleID==$role->id?' selected':'').'>'.$role->title.'</option>'; }
                $html .= '</select>';
            } else { // might not be initialized, set to 1 which is the default admin
                $html .= '<input type="hidden" name="bizbooks_role_'.$biz['id'].'" id="bizbooks_role_'.$biz['id'].'" value="1" />';
            }
            $html .= '</td></tr>';
        }
        $html .= '</table>';
        echo $html;
    }

    public function bizunoUserSave( $user_id )
    {
        if (!is_admin()) { return; }
        $this->bizunoCtlr(); // load Bizuno functions
        $user = get_userdata( $user_id );
        if (empty($user)) { return; }
        update_user_meta( $user_id, 'bizuno_wallet_id', !empty($_POST['bizuno_wallet_id']) ? $_POST['bizuno_wallet_id'] : '' );
        foreach ($GLOBALS['bizPortal'] as $biz) {
            $enabled = !empty( $_POST['bizbooks_enable_'.$biz['id']] ) ? true : false;
            if ($enabled) { \bizuno\bizSetUser($user, $biz['id']); }
            else          { \bizuno\bizDelUser($user, $biz['id']); }
        }
    }
    // Changes the email address in the Bizuno users table when changed through the WordPress profile page
    // Note: This assumes the new email address is valid, If not it will be changed again when a different email is entered in WordPress.
    public function bizuno_profile_change( $user_id )
    {
        if (!is_admin()) { return; }
        $user     = get_userdata( $user_id );
        $old_email= $user->user_email;
        $new_meta = get_user_meta( $user_id, '_new_email', true );
        $new_email= !empty( $new_meta['newemail'] ) ? $new_meta['newemail'] : '';
        if ( empty( $new_email ) || empty( $old_email ) || $old_email==$new_email ) { return; }
        $this->bizunoCtlr(); // load Bizuno functions
        foreach ($GLOBALS['bizPortal'] as $biz) { bizuno\bizChangeEmail($biz, $old_email, $new_email); }
    }
    public function bizuno_user_logout()
    {
        $redirect_url = site_url();
        wp_safe_redirect( $redirect_url );
        exit;
    }

    public function daily_cron()
    {
        require_once ("controllers/portal/controller.php");
        require_once (BIZBOOKS_ROOT ."controllers/phreebooks/functions.php");
        \bizuno\periodAutoUpdate(false);
    }

    public static function activate()
    {
        global $wpdb;
        require_once(__DIR__.'/bizunoCFG.php');
/*      $oPage = get_page_by_path('bizuno-office'); // need to create page placeholder for office
        if (empty($oPage)) {
            $my_post = ['post_title'=>'Bizuno Office', 'post_status'=>'publish', 'post_type'=>'page',
                'post_content'  => '<p>This is a placeholder page to display Bizuno Office on your site. Any content here will not be displayed.'];
            wp_insert_post( $my_post );
        }
        update_user_meta( get_current_user_id(), 'bizoffice_enable', 1 ); */
        /********* Accounting *********/
        $aPage = get_page_by_path('bizuno'); // need to create page placeholder for accounting
        if (empty($aPage)) {
            $my_post = ['post_title'=>'Bizuno', 'post_status'=>'publish', 'post_type'=>'page', 'post_name'=>'bizuno',
                'post_content'=>"This page is reserved for authorized users of Bizuno Accounting/ERP.
To access Bizuno, please <a href=\"/wp-login.php\">click here</a> to log into your WordPress site and select Bizuno Accounting from the setting menu in the upper right corner of the screen.
If Bizuno Accounting is not an option, see your administrator to gain permission.</p>
<p>Administrators: To authorize a user, navigate to the WordPress administration page -> Users -> search  username/eMail.
Edit the user and check the \'Allow access to: <My Business>\' box along with a role and click Save.</p>"];
            wp_insert_post( $my_post );
        }
        foreach ($GLOBALS['bizPortal'] as $biz) { update_user_meta( get_current_user_id(), 'bizbooks_enable_'.$biz['id'], 1 ); }
        if (!wp_next_scheduled('bizuno_daily_event')) { wp_schedule_event(time(), 'daily', 'bizuno_daily_event'); }
        /********* API *********/
        if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
            // set all existing orders to downloaded to hide Download button for past orders
            $orders = $wpdb->get_results( "SELECT `ID` FROM `{$wpdb->prefix}posts` WHERE `post_type` LIKE 'shop_order'", ARRAY_A);
            foreach ($orders as $order) { update_post_meta($order['ID'], 'bizuno_order_exported', 1); }
        }
    }

    public static function deactivate()
    {
        /********* Accounting *********/
        delete_option('bizuno_active', false);
        wp_clear_scheduled_hook('bizuno_daily_event');
        /********* API *********/
//        update_option('bizuno_api_active', false);
    }
    public function bizuno_debug_log_write() {
        if (!empty($GLOBALS['bizDebug'])) {
            require_once ('controllers/portal/controller.php');
            new \bizuno\portalCtl();
            \bizuno\msgTrap ( );
            \bizuno\msgDebug("\nCaptured from WordPress Plugin: \n".print_r(implode("\n",$GLOBALS['bizDebug']), true));
            \bizuno\msgDebugWrite();
            unset($GLOBALS['bizDebug']);
        }
    }
    /**************** Private Methods *****************/
    private function validateUser()
    {
        $access = false;
        if (!empty($_GET['bizRt']) && 'myPortal'==substr($_GET['bizRt'], 0, 8)) { return true; }
        if ( !is_user_logged_in() ) { return false; }
        $userID = get_current_user_id();
        foreach ($GLOBALS['bizPortal'] as $biz) {
            if ( !empty(get_user_meta( $userID, 'bizbooks_enable_'.$biz['id'], true) ) ) { $access = true; break; }
        }
        return $access;
    }
}
new bizuno_accounting();

/**
 * Uninstall should remove all documents and settings, essentially a clean wipe.
 * WARNING: Needs to be outside of class or WordPress errors
 */
function bizuno_uninstall() {
    global $wpdb;
    $upload_dir = wp_upload_dir();
//    $options = ['bizuno_active','bizuno_api_user','bizuno_api_pw','bizuno_api_url','bizuno_api_prefix','bizuno_api_prefix_cust','bizuno_api_autodownload','bizuno_api_active'];
//    foreach ($options as $option) { delete_option($option); delete_site_option($option); }
    /********* Office *********/
//  $result = $wpdb->get_results("SELECT user_id FROM `".$wpdb->perfix."usermeta` WHERE meta_key='bizoffice_enable'");
//  if (!empty($result)) { foreach ($result as $user) { delete_user_meta( $user->user_id, 'bizoffice_enable' ); } }
    /********* Accounting *********/
    $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}bizuno_%'", ARRAY_A);
    foreach ($tables as $row) { $table = array_shift($row); $wpdb->query("DROP TABLE IF EXISTS $table"); } // drop the Bizuno tables
    bizunoRmdir($upload_dir['basedir'].'/bizuno');
    /********* API *********/
    $wpdb->get_results("DELETE FROM `{$wpdb->prefix}postmeta` WHERE meta_key='bizuno_order_exported'");
}

/**************************************************************************************/
//                  Support functions
/**************************************************************************************/
//Recursive support function to remove Bizuno files from the uploads folder
function bizunoRmdir($dir) {
    if (!is_dir($dir)) { return; }
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object == "." || $object == "..") { continue; }
        if (is_dir($dir."/".$object)) { bizunoRmdir($dir."/".$object); } else { unlink($dir."/".$object);  }
    }
    rmdir($dir);
}

/**
 * DEPRECATED - Function call commented out
 */
function bizuno_options_page()
{
    echo '<div><h1>Bizuno Settings</h1><form method="post" action="options.php">'.settings_fields( 'bizuno_options_group' );
    echo '<h3>Go PRO! Purchase the Bizuno-Pro plugin from PhreeSoft and max out your business system.</h3>';
    echo '<p><a href="https://www.phreesoft.com">GO PRO!</a></p>';
    echo '<table><tr valign="top"><th scope="row"><label for="bizuno_pro_key">Pro Key</label></th>';
    echo '<td><input type="text" id="bizuno_pro_key" name="bizuno_pro_key" value="'.get_option('bizuno_pro_key').'" /></td></tr></table>';
    submit_button();
    echo '</form></div>';
}

function bizuno_html() {
    echo "<p>Please Note: This access method to Bizuno Accounting has deprecated!</p>";
    echo "<p>Bizuno Accounting now only runs in full screen mode to allow all users to use the app in a consistent environment.</p>";
    echo "<p>Permission must be granted by checking the 'Enable Bizuno Accounting' checkbox in each individual users profile. Once access has been granted, Bizuno Accounting can be accessed from the Users profile drop down menu after the user has logged into your site.</p>";
}
