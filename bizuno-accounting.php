<?php
/**
 * Plugin Name: Bizuno Accounting/ERP/CRM
 * Plugin URI:  https://www.phreesoft.com
 * Description: Bizuno is a powerful ERP/Accounting application adapted as a plugin for WordPress. Bizuno creates a portal running within the WordPress administrator. Once activated, click on the Bizuno menu item to complete the installation and access your Bizuno business.
 * Version:     7.0
 * Author:      PhreeSoft, Inc.
 * Author URI:  http://www.PhreeSoft.com
 * Text Domain: bizuno
 * License:     Affero GPL 3.0
 * License URI: https://www.gnu.org/licenses/agpl-3.0.txt
 * Domain Path: /locale
 */

defined( 'ABSPATH' ) || exit;

class bizuno_accounting
{
    public function __construct()
    {
        // Actions
        add_action ( 'init',                      [ $this, 'init_bizuno'] );
        add_action ( 'admin_menu',                [ $this, 'admin_menu_bizuno' ] );
        add_action ( 'wp_before_admin_bar_render',[ $this, 'bizuno_admin_menu_mods'] );
        add_action ( 'edit_user_profile',         [ $this, 'bizunoUserEdit'] ); // maybe change to show_user_profile
        add_action ( 'show_user_profile',         [ $this, 'bizunoUserEdit'] );
        add_action ( 'personal_options_update',   [ $this, 'bizunoUserSave'] );
        add_action ( 'edit_user_profile_update',  [ $this, 'bizunoUserSave'] );
        // Filters
        add_filter ( 'xmlrpc_methods', function($methods) { unset( $methods['pingback.ping'] ); return $methods; } );
        // Install/Uninstall hooks
        register_activation_hook( __FILE__ ,      [ $this, 'activate'] );
    }

    public function init_bizuno()
    {
        if ( !in_array ( 'bizuno-wp-lib/bizuno-wp-lib.php', apply_filters( 'active_plugins', get_option ( 'active_plugins' ) ) ) ) {
            // deactivate this plugin
            require_once ( ABSPATH . 'wp-admin/includes/plugin.php' );
            $plugin_path = 'bizuno-accounting/bizuno-accounting.php';
            if ( is_plugin_active( $plugin_path ) ) {
                deactivate_plugins( $plugin_path );
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p>The plugin bizuno-accounting has been deactivated because the Library plugin cannot be found!</p>';
                echo '</div>';
            }
        }
    }

    public function admin_menu_bizuno()
    {
        add_menu_page('Bizuno', 'Bizuno', 'manage_options', 'bizuno', 'bizuno_html', plugins_url( 'view/images/icon_16.png', __FILE__ ), 90);
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
        $wp_admin_bar->add_node( $logout );
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
    public static function activate()
    {
        $aPage = get_page_by_path('bizuno'); // need to create page placeholder for accounting
        if (empty($aPage)) {
            $my_post = ['post_title'=>'Bizuno', 'post_status'=>'publish', 'post_type'=>'page', 'post_name'=>'bizuno',
                'post_content'=>"This page is reserved for authorized users of Bizuno Accounting/ERP.
To access Bizuno, please <a href=\"/wp-login.php\">click here</a> to log into your WordPress site and select Bizuno Accounting from the profile menu in the upper right corner of the screen.
If Bizuno Accounting is not an option, see your administrator to gain permission.</p>
<p>Administrators: To authorize a user, navigate to the WordPress administration page -> Users -> search  username/eMail.
Edit the user and check the \'Allow access to: <My Business>\' box along with a role and click Save.</p>"];
            wp_insert_post( $my_post );
        }
    }

    /**************** Private Methods *****************/
    private function validateUser()
    {
        $access = false;
        if (!empty($_GET['bizRt']) && 'api'==substr($_GET['bizRt'], 0, 3)) { return true; }
        if ( !is_user_logged_in() ) { return false; }
        $userID = get_current_user_id();
        foreach ($GLOBALS['bizPortal'] as $biz) {
            if ( !empty(get_user_meta( $userID, 'bizbooks_enable_'.$biz['id'], true) ) ) { $access = true; break; }
        }
        return $access;
    }
}
new bizuno_accounting();
