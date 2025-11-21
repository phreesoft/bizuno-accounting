<?php
/**
 * Plugin Name: Bizuno Accounting/ERP/CRM
 * Plugin URI: https://www.phreesoft.com
 * Description: Bizuno is a powerful ERP/Accounting application adapted as a plugin for WordPress. Bizuno creates a portal running within the WordPress administrator. Once activated, click on the Bizuno menu item to complete the installation and access your Bizuno business.
 * Version: 7.0
 * Requires at least: 6.5
 * Tested up to: 6.8.3
 * Requires PHP: 8.2
 * Requires Plugins: bizuno-wp-library:https://bizuno.com/downloads/wordpress/plugins/bizuno-wp-library
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
    private $bizSlug   = 'bizuno';
    private $bizLib    = "bizuno-wp-library";
    private $bizLibURL = "https://bizuno.com/downloads/wordpress/plugins/bizuno-wp-library/bizuno-wp-library.latest-stable.zip";
    
    public function __construct()
    {
        add_action ( 'init',                      [ $this, 'initializeBizuno' ] );
        add_action ( 'admin_init',                [ $this, 'initializeBizunoAdmin'], 5 );
        add_action ( 'admin_menu',                [ $this, 'admin_menu_bizuno' ] );
        add_action ( 'admin_notices',             [ $this, 'admin_notices_bizuno' ] );
        add_action ( 'wp_before_admin_bar_render',[ $this, 'bizuno_admin_menu_mods' ] );
        add_action ( 'phpmailer_init',            [ $this, 'bizuno_phpmailer_init' ], 10, 1 );
        add_action ( 'template_redirect',         [ $this, 'bizunoPageRedirect' ] );
        add_action ( 'wp_ajax_bizuno_ajax',       [ $this, 'bizunoAjax' ] );
        add_action ( 'wp_ajax_nopriv_bizuno_ajax',[ $this, 'bizunoAjax' ] );
        add_action ( 'bizuno_daily_event',        [ $this, 'daily_cron' ] );
        // Filters
        add_filter ( 'plugin_requirements',       [ $this, 'bizunoLibTest' ], 10, 2 );
        add_filter ( 'xmlrpc_methods', function( $methods ) { unset( $methods[ 'pingback.ping' ] ); return $methods; } );
        // Install/Uninstall hooks
        register_deactivation_hook ( __FILE__ ,   [ $this, 'deactivate' ] );
        register_uninstall_hook ( __FILE__,       'bizunoUninstall' ); // do not put inside of class
    }

    /**
     * Initializes the Bizuno library
     */
    public function initializeBizuno()
    {
        global $msgStack, $cleaner, $db, $io, $wpdb; // , $html5, $portal
        require_once ( plugin_dir_path( __FILE__ ) . 'portalCFG.php' ); // Set Bizuno environment
        if ( !in_array ( "$this->bizLib/$this->bizLib.php", apply_filters( 'active_plugins', get_option ( 'active_plugins' ) ) ) ) {
            return $this->deactivateBizuno();
        }        
        // Instantiate the Bizuno classes
        $msgStack = new \bizuno\messageStack();
        $cleaner  = new \bizuno\cleaner();
        $io       = new \bizuno\io();
        $db       = new \bizuno\db(BIZUNO_DB_CREDS);
        $this->verifyDbInstalled();
    }

    public function initializeBizunoAdmin() {
        if ( ! get_transient( 'bizuno_install_library' ) ) { return; }
        delete_transient( 'bizuno_install_library' );
        if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) { // Show a nice notice instead of dying
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-error"><p>Bizuno Accounting requires permission to install the required library plugin.</p></div>';
            } );
            return;
        }
        $this->bizuno_auto_install_library();
    }

    public function admin_menu_bizuno()
    {
        $library_main_file = WP_PLUGIN_DIR . "/$this->bizLib/$this->bizLib.php";
        add_menu_page('Bizuno', 'Bizuno', 'manage_options', 'bizuno', 'bizuno_html', plugins_url( 'view/images/icon_16.png', $library_main_file ), 90);
    }

    public function admin_notices_bizuno() {
        if ( get_transient( 'BIZUNO_FS_LIBRARY_auto_installed' ) ) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '    <p>Bizuno Accounting: The required bizuno-wp-library was automatically downloaded and activated.</p>';
            echo '</div>';
            delete_transient( 'BIZUNO_FS_LIBRARY_auto_installed' );
        }
    }

    /**
     * Inserts the Bizuno Accounting menu items to the admin toolbar before the logout selection
     * NOTE: Show admin toolbar needs to be enabled for non-admins to see the menu
     */
    public function bizuno_admin_menu_mods()
    {
        global $wp_admin_bar;
        if (empty(get_page_by_path($this->bizSlug))) { return; }
        $logout = $wp_admin_bar->get_node('logout');
        $wp_admin_bar->remove_node( 'logout' );
        $wp_admin_bar->add_node( ['id'=>'tb-admin-bizBooks', 'title'=>'Bizuno Accounting', 'href'=>BIZUNO_URL_PORTAL, 'parent'=>'user-actions', 'meta'=>['target'=>'_blank']] );
        $wp_admin_bar->add_node( $logout );
    }

    public function bizuno_phpmailer_init( $phpmailer )
    {
        if ( get_post_field( 'post_name' ) == 'bizuno' ) { $phpmailer->IsHTML( true ); } // set email format to HTML
    }

    public function bizunoPageRedirect() {
        global $post;
        if ( is_user_logged_in() && !empty($post->post_name) && $this->bizSlug==$post->post_name) {
            new \bizuno\portalCtl();
            exit();
        }
    }

    public function bizunoAjax()
    {
        new \bizuno\portalCtl();
        exit();
    }

    public function daily_cron()
    {
        \bizuno\periodAutoUpdate(false); // since function has been loaded
    }

    public function bizunoLibTest( $requirements, $plugin_file )
    {
        if ( plugin_basename( __FILE__ ) !== $plugin_file || defined('BIZUNO_FS_LIBRARY' )) { return $requirements; }
        if ( !in_array ( "$this->bizLib/$this->bizLib.php", apply_filters( 'active_plugins', get_option ( 'active_plugins' ) ) ) ) {
            $required["$this->bizLib/$this->bizLib.php"] = $this->bizLibURL;
        }
        return $required;
    }

    private function verifyDbInstalled()
    {
        if ( !is_admin() ) { return; }
        if ( \bizuno\dbTableExists( BIZUNO_DB_PREFIX.'configuration' ) ) { return true; }
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p>You\'re just about ready to get started managing your business! '
        . 'Click <a href="'.home_url("/$this->bizSlug").'" target="_blank">HERE</a> to open a new page at your website to run the database installer script. '
        . 'Remember to bookmark this page so you can quickly access your Bizuno business in the future.</p>';
        echo '</div>';
    }

    private function bizuno_auto_install_library() {
        $library_slug = "$this->bizLib/$this->bizLib.php";
        $library_dir  = WP_PLUGIN_DIR . "/$this->bizLib";
        if ( is_plugin_active( $library_slug ) ) { return; } // Already good?
        
        if ( file_exists( $library_dir . "/$this->bizLib.php" ) ) { // Installed but inactive?
            activate_plugin( $library_slug, '', false, true ); // silent
            return;
        }
        // Download + install silently
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
        $skin     = new class extends WP_Upgrader_Skin {
            public function feedback($string, ...$args) {}
            public function header() {}
            public function footer() {}
        };
        $upgrader = new Plugin_Upgrader( $skin );
        $upgrader->skin->plugin_info = [ 'Slug' => $this->bizLib ]; // This forces the correct folder name
        $tmp = download_url( $this->bizLibURL );
        if ( is_wp_error( $tmp ) ) {
            error_log( 'Bizuno library download failed: ' . $tmp->get_error_message() );
            return;
        }
        $result = $upgrader->install( $tmp, [ 'clear_destination' => true, 'clear_working' => true ] );
        @unlink( $tmp );
        if ( $result !== true ) {
            error_log( 'Bizuno library install failed' );
            return;
        }
        if ( ! is_dir( $library_dir ) ) { // Final safety net (almost never needed)
            $dirs = glob( WP_PLUGIN_DIR . "/{$this->bizLib}*", GLOB_ONLYDIR );
            foreach ( $dirs as $dir ) {
                if ( basename( $dir ) !== $this->bizLib ) {
                    rename( $dir, $library_dir );
                    break;
                }
            }
        }
        if ( file_exists( $library_dir . "/$this->bizLib.php" ) ) { activate_plugin( $library_slug, '', false, true ); }
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('bizuno_daily_event');
    }
    
    /**************** Private Methods *****************/
    /*
     * Deactivates this plugin since the library cannot be found
     */
    private function deactivateBizuno()
    {
        require_once ( ABSPATH . 'wp-admin/includes/plugin.php' );
        $plugin_path = 'bizuno-accounting/bizuno-accounting.php';
        if ( is_plugin_active( $plugin_path ) ) {
            deactivate_plugins( $plugin_path );
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>The plugin bizuno-accounting has been deactivated because the Library plugin cannot be found! '
            . 'Click <a href="#" target="_blank">HERE</a> to open a new page at the Bizuno.com website to download the Bizuno library. '
            . 'Once downloaded, it must be manually installed the first time. '
            . 'Henceforth, the standard WordPress upgrade process may be used to perform upgrades.</p>';
            echo '</div>';
        }
    }
}
new bizuno_accounting();

/******************************* Operations outside of class ***************************/
function bizuno_html() {
    echo "<p>Please Note: This access method to Bizuno Accounting has deprecated!</p>";
    echo "<p>Bizuno Accounting now only runs in full screen mode to allow all users to use the app in a consistent environment.</p>";
    echo "<p>Permission must be granted by checking the 'Enable Bizuno Accounting' checkbox in each individual users profile. Once access has been granted, Bizuno Accounting can be accessed from the Users profile drop down menu after the user has logged into your site.</p>";
}

/**
 * Uninstall should remove all documents and settings, essentially a clean wipe.
 * WARNING: Needs to be outside of class or WordPress errors
 */
function bizunoUninstall() {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bizuno_%'" );
    $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}bizuno_%'", ARRAY_A);
    foreach ($tables as $row) { $table = array_shift($row); $wpdb->query("DROP TABLE IF EXISTS $table"); } // drop the Bizuno tables
    $upload_dir = wp_upload_dir();
    bizunoRmdir($upload_dir['basedir'].'/bizuno');
}

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

register_activation_hook( __FILE__, 'bizuno_schedule_library_install' );
function bizuno_schedule_library_install()
{
    set_transient( 'bizuno_install_library', true, 12 * HOUR_IN_SECONDS ); // Just schedule the real work for later — this runs safely
    if ( ! wp_next_scheduled( 'bizuno_daily_event' ) ) { wp_schedule_event( time(), 'daily', 'bizuno_daily_event' ); }
    // Create the placeholder page (this is safe during activation)
    if ( ! get_page_by_path( 'bizuno' ) ) {
        wp_insert_post( [ 'post_title' => 'Bizuno', 'post_name' => 'bizuno', 'post_status' => 'publish', 'post_type' => 'page',
            'post_content' => "This page is reserved for authorized users of Bizuno Accounting/ERP.
To access Bizuno, please <a href=\"/wp-login.php\">click here</a> to log into your WordPress site and select Bizuno Accounting from the profile menu in the upper right corner of the screen.
If Bizuno Accounting is not an option, see your administrator to gain permission.</p>
<p>Administrators: To authorize a user, navigate to the WordPress administration page -> Users -> search  username/eMail.
Edit the user and check the \'Allow access to: <My Business>\' box along with a role and click Save.</p>"] );
    }
}