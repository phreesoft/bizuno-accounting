<?php
/*
 * WordPress Plugin - Guest methods and log in verification
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
 * @version    3.x Last Update: 2021-04-02
 * @filesource /controllers/portal/guest.php
 */

namespace bizuno;

/**
 * Handles entry settings, configuration and environmental activities for PhreeSoft hosted users
 * This class varies depending on framework.
 */
class guest
{
    function __construct() { }

    /**
     * Bizuno does not allow password resets, they must be done through the WordPress administration panel
     * @return null
     */
    public function passwordReset()
    {
        msgAdd('Bizuno does not allow password resets through this form. Password resets must be done through the WordPress administration panel', 'caution');
    }

    /**
     * DO not make any changes to the WordPress user table, it needs to be managed through WordPress
     */
    public function portalSaveUser()
    {
        return msgAdd("No changes have been made to the user table in WordPress. Any changes to that table need to be made through WordPress administration!", 'caution');
    }

    /**
     * Clear the WordPress meta for the user to this business and reload, should open the select business page
     * @param type $layout
     */
    public function changeBiz(&$layout=[])
    {
        clearUserCache('profile', 'admin_encrypt');
        $userID = get_current_user_id();
        update_user_meta( $userID, 'bizbooks_bizID', 0 );
        $layout= ['content'=>['action'=>'eval','actionData'=>"sessionStorage.removeItem('bizuno'); hrefClick('');"]];
    }

    /**
     * Pre-install script for this host
     */
    public function installBizunoPre()
    {
        global $io;
        $htaccess = '# secure uploads directory
<Files ~ ".*\..*">
	Order Allow,Deny
	Deny from all
</Files>
<FilesMatch "\.(css|jpg|jpeg|jpe|gif|png|tif|tiff)$">
	Order Deny,Allow
	Allow from all
</FilesMatch>';
        // write the file to the WordPress Bizuno data folder.
        $io->fileWrite($htaccess, '.htaccess', false);
        return true;
    }
}
