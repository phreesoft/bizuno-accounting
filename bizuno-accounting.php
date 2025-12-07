<?php
/**
 * Plugin Name: Bizuno Accounting/ERP/CRM
 * Plugin URI: https://www.phreesoft.com
 * Description: Bizuno is a powerful ERP/Accounting application adapted as a plugin for WordPress. Bizuno creates a portal running within the WordPress administrator. Once activated, click on the Bizuno menu item to complete the installation and access your Bizuno business.
 * Version: 7.3.4
 * Requires at least: 6.5
 * Tested up to: 6.8.3
 * Requires PHP: 8.2
 * Requires Plugins: bizuno:https://bizuno.com/downloads/wordpress/plugins/bizuno-wp-library
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
    private $bizLib    = "bizuno-wp";
    private $bizLibURL = "https://bizuno.com/downloads/latest/bizuno.zip";
    private $bizExists = false;
    
    public function __construct()
    {
        add_action ( 'init',                      [ $this, 'initializeBizuno' ] );
        add_action ( 'admin_init',                [ $this, 'initializeBizunoAdmin'], 5 );
        add_action ( 'admin_menu',                [ $this, 'admin_menu_bizuno' ] );
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

    public function initializeBizuno()
    {
        if ( ! is_plugin_active( "$this->bizLib/$this->bizLib.php" ) ) {
            add_action( 'admin_notices', function() {
                echo '<div class="notice notice-warning"><p>The Bizuno Accounting plugin now requires the Bizuno library plugin available from the Bizuno project website. Click <a href="https://dspind.com/wp-admin/admin.php?page=get-bizuno">HERE</a> to download the plugin!</p></div>';
            });
            return;
        } else { $this->bizExists = true; }
        global $msgStack, $cleaner, $db, $io, $wpdb; // , $html5, $portal
        require_once ( plugin_dir_path( __FILE__ ) . 'portalCFG.php' ); // Set Bizuno environment
        $msgStack = new \bizuno\messageStack();
        $cleaner  = new \bizuno\cleaner();
        $io       = new \bizuno\io();
        $db       = new \bizuno\db(BIZUNO_DB_CREDS);
        $this->verifyDbInstalled();
    }

    public function initializeBizunoAdmin()
    {
    }

    public function admin_menu_bizuno()
    {
        if ( $this->bizExists ) {
            add_menu_page( 'Bizuno', 'Bizuno', 'manage_options', 'bizuno', 'bizuno_html', 
                plugins_url( 'icon_16.png', WP_PLUGIN_DIR . "/$this->bizLib/$this->bizLib.php" ), 90);            
        } else {
            add_menu_page( 'GET BIZUNO', 'GET BIZUNO', 'manage_options', 'get-bizuno', 'get_bizuno_html',
                plugins_url( 'icon_16.png', WP_PLUGIN_DIR . "/bizuno-accounting/bizuno-accounting.php" ), 1);
        }
    }

    /**
     * Inserts the Bizuno Accounting menu items to the admin toolbar before the logout selection
     * NOTE: Show admin toolbar needs to be enabled for non-admins to see the menu
     */
    public function bizuno_admin_menu_mods()
    {
        global $wp_admin_bar;
        if ( !in_array ( "$this->bizLib/$this->bizLib.php", apply_filters( 'active_plugins', get_option ( 'active_plugins' ) ) ) ) {
            return;
        }        
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
        if ( is_user_logged_in() && !empty($post->post_name) && $this->bizSlug==$post->post_name && $this->bizExists) {
            new \bizuno\portalCtl();
            exit();
        }
    }

    public function bizunoAjax()
    {
        if ( !$this->bizExists ) { return; }
        new \bizuno\portalCtl();
        exit();
    }

    public function daily_cron()
    {
        if ( !$this->bizExists ) { return; }
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

    public static function deactivate()
    {
        wp_clear_scheduled_hook('bizuno_daily_event');
    }
}
new bizuno_accounting();

/******************************* Operations outside of class ***************************/
register_activation_hook( __FILE__, 'bizuno_schedule_library_install' );
function bizuno_schedule_library_install()
{
//    set_transient( 'bizuno_install_library', true, 12 * HOUR_IN_SECONDS ); // Just schedule the real work for later — this runs safely
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

function bizuno_html() {
    echo "<p>Please Note: This access method to Bizuno Accounting has deprecated!</p>";
    echo "<p>Bizuno Accounting now only runs in full screen mode to allow all users to use the app in a consistent environment.</p>";
    echo "<p>Permission must be granted by checking the 'Enable Bizuno Accounting' checkbox in each individual users profile. Once access has been granted, Bizuno Accounting can be accessed from the Users profile drop down menu after the user has logged into your site.</p>";
}

function get_bizuno_html()
{
    if (!current_user_can('manage_options')) { wp_die('Insufficient permissions'); }
    echo '<div class="wrap">
        <h1>Get Bizuno (Latest version from the Bizuno.com website)</h1>';
        if (isset($_POST['bizuno_install_private'])) {
            check_admin_referer('bizuno_install_private');
            if (bizuno_install_and_activate_project_plugin()) {
                // redirect to plugin main page
            }
        }
        echo '<form method="post">';
        wp_nonce_field('bizuno_install_private');
        echo '<p>This will download and install the full Bizuno plugin from bizuno.com.</p>
            <p><strong>No license key required</strong> – it’s now publicly available.</p>';
        submit_button('Get Bizuno Now', 'primary', 'bizuno_install_private');
        echo '</form></div>';
}

function bizuno_install_and_activate_project_plugin() {
    if ( is_plugin_active('bizuno/bizuno.php' ) ) {
        echo '<div class="updated"><p>Bizuno ERP is already installed and active!</p></div>';
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    $download_url = 'https://bizuno.com/downloads/latest/bizuno-wp.zip';
    $tmp_file = download_url($download_url);
    if (is_wp_error($tmp_file)) {
        echo '<div class="error"><p>Failed to download Bizuno: ' . esc_html($tmp_file->get_error_message()) . '</p></div>';
        return;
    }
    $upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
    $installed = $upgrader->install($tmp_file, ['overwrite_package' => true]);
    @unlink($tmp_file); // clean up temp file
    if (!$installed || is_wp_error($installed)) {
        echo '<div class="error"><p>Installation failed.</p></div>';
        return;
    }
    $plugin_path = '/bizuno-wp/bizuno-wp.php';
    if ( file_exists( WP_PLUGIN_DIR . $plugin_path ) ) { 
        $activated = activate_plugin( $plugin_path, '', false, true );
        if (is_wp_error($activated)) {
            echo '<div class="error"><p>Installed but failed to activate: ' . esc_html($activated->get_error_message()) . '</p></div>';
        } else {
            echo '<div class="updated"><p><strong>Bizuno ERP has been successfully installed and activated!</strong></p>';
            echo '<p><a href="' . admin_url('admin.php?page=bizuno') . '" class="button button-primary">Go to Bizuno Dashboard →</a></p></div>';
        }
        return true;
    }
    echo '<div class="error"><p>Failed to activate Bizuno!</p></div>';
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
