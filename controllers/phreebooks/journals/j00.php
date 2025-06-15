<?php
/*
 * PhreeBooks journal class for all Journals, typically searching
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
 * @version    6.x Last Update: 2020-10-15
 * @filesource /controllers/phreebooks/journals/j00.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journals/common.php", 'jCommon');

class j00 extends jCommon
{
    public $journalID = 0;

    function __construct($main=[], $item=[])
    {
        parent::__construct();
        $this->main = $main;
        $this->item = $item;
    }

}
