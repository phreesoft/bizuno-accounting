<?php
/*
 * Language translation for payment extension - method Elavon/Converge
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
 * @version    6.x Last Update: 2024-02-17
 * @filesource /locale/en_US/module/payment/methods/payfabric/language.php
 */

$lang = [
    'title'       => 'EVO PayFabric',
    'description' => 'Accept credit card payments through the PayFabric payment gateway.',
    'at_payfabric'=> '@PayFabric',
    'setup_id'    => 'Setup ID (provided by PhreeSoft)',
    'device_id'   => 'Device ID (provided by PhreeSoft)',
    'device_pw'   => 'Device Password (provided by PhreeSoft)',
    'auth_type'   => 'Authorization Type',
    'prefix_amex' => 'Prefix to use for American Express credit cards. (These cards are processed and reconciled through American Express)',
    'allow_refund'=> 'Allow Void/Refunds? This must be enabled by PayFabric for your merchant account or refunds will not be allowed.',
    'msg_website'         => 'This must be done manually at the PayFabric website.',
    'msg_capture_manual'  => 'The payment was not processed through the PayFabric gateway.',
    'msg_void_success'    => 'The VOID at PayFabric was Successful - PayFabric Response: %s - Approval code: %s',
    'msg_refund_success'  => 'The REFUND at PayFabric was Successful - PayFabric Response: %s - Approval code: %s',
    'msg_address_result'  => 'Address verification results: %s',
    'err_process_decline' => 'Decline Code #%s: %s',
    'err_process_failed'  => 'The credit card did not process, the response from PayFabric:',
    // new AVS responses
    'AVS_Match' => 'AVS Match',
    'AVS_NoMatch' => 'No match on address (street) or postal code.',
    'AVS_NotVerified' => 'PayFabric retured status Not Verified',
    'CVV_NotSet' => 'CVV Code Not Set',
   ];
