<?php
/*
 * Language translation for payment extension - method PayPal
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
 * @filesource /locale/en_US/module/payment/methods/paypal/language.php
 */

$lang = [
    'title' => 'PayPal',
    'description'=> 'PayPal interface, covers both PayPal Express and PayPal Pro.',
    'at_paypal' => '@PayPal',
    'user' => 'User ID (provided by PayPal)',
    'pass' => 'Password (provided by PayPal)',
    'signature' => 'Signature (provided by PayPal)',
    'auth_type' => 'Authorization Type',
    'prefix_amex' => 'Prefix to use for American Express credit cards. (These cards are processed and reconciled through American Express)',
    'allow_refund' => 'Allow Void/Refunds? This must be enabled by PayPal Pro for your merchant account or refunds will not be allowed.',
    'msg_address_result' => 'Address verification results: %s',
    'msg_website' => 'This must be done manually at the PayPal website.',
    'msg_capture_manual' => 'The payment was not processed through the PayPal gateway.',
    'msg_delete_manual' =>'The payment was not deleted through the PayPal gateway.',
    'msg_refund_manual' =>'The payment was not refunded through the PayPal gateway.',
    'err_process_decline' => 'Decline Message: %s',
    'err_process_failed' => 'The credit card did not process, the response from PayPal Pro:'];

