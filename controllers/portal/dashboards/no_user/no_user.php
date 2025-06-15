<?php
/*
 * Bizuno dashboard - WordPress Portal - No Bizuno Account found
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
 * @version    3.x Last Update: 2020-10-23
 * @filesource /portal/dashboards/no_user/no_user.php
 */

namespace bizuno;

class no_user
{
    public $moduleID  = 'portal';
    public $methodDir = 'dashboards';
    public $code      = 'no_user';
    public $noSettings= true;
    public $noCollapse= true;
    public $noClose   = true;

    function __construct()
    {
        $this->security= 1;
        $this->hidden  = true;
        $this->lang = ['title' => 'Oh Snap!',
        'description'=> 'Displays when trying to acccess Bizuno when a WordPress account exists and a Bizuno account does not.',
        'instructions'=>'<h3>You do not have a Bizuno user account!</h3><p>It appears that you have a WordPress Account but not a user account set up in Bizuno. Please see your administrator and ask them to set up your Bizuno user profile and role in WordPress Admin -> Bizuno -> My Business -> Users.</p><p>Remind them that Bizuno uses email addresses to identify user accounts and your WordPress user account email address and the Bizuno account email address must be the same.</p>'];
    }

    public function settingsStructure()
    {
    }

    public function render()
    {
        return "<p>&nbsp;</p><div><p>{$this->lang['instructions']}</p><p>&nbsp;</p></div>";
    }

}
