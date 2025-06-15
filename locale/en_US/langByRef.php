<?php

/*
 * Locale langage file - English US (en_US)
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
 * @version    6.x Last Update: 2020-05-05
 * @filesource /local/en_US/locale.php
 */

namespace bizuno;

$langByRef = [
    'address_book_telephone1' => $langCore['telephone'],
    'bizuno_admin' => $langCore['settings'],
    'bizuno_tools' => $langCore['tools'],
    'bizuno_users' => $langCore['users'],
    'contacts_gl_type_account' => $langCore['gl_account'],
    'contacts_type_c_mgr' => sprintf($langCore['tbd_manager'], $langCore['contacts_type_c']),
    'contacts_type_e_mgr' => sprintf($langCore['tbd_manager'], $langCore['contacts_type_e']),
    'contacts_type_i_mgr' => sprintf($langCore['tbd_manager'], $langCore['contacts_type_i']),
    'contacts_type_v_mgr' => sprintf($langCore['tbd_manager'], $langCore['contacts_type_v']),
    'contacts_type_c_prc' => sprintf($langCore['tbd_prices'], $langCore['contacts_type_c']),
    'contacts_type_v_prc' => sprintf($langCore['tbd_prices'], $langCore['contacts_type_v']),
    'users_admin_id' => $langCore['id'],
    'journal_main_gl_acct_id' => $langCore['gl_account'],
    'gl_acct_type_4_mgr' => sprintf($langCore['tbd_manager'], $langCore['gl_acct_type_4']),
    'journal_main_journal_id_6_mgr' => sprintf($langCore['tbd_manager'], $langCore['journal_main_journal_id_6']),
    'journal_main_journal_id_12_mgr' => sprintf($langCore['tbd_manager'], $langCore['journal_main_journal_id_12']),
    'journal_main_invoice_num_12' => $langCore['journal_main_invoice_num_6'],
    'journal_main_invoice_num_18' => $langCore['journal_main_invoice_num_17'],
    'journal_main_invoice_num_22' => $langCore['journal_main_invoice_num_20'],
    'journal_main_purch_order_id_9' => $langCore['journal_main_invoice_num_4'],
    'journal_main_purch_order_id_10' => $langCore['journal_main_invoice_num_4'],
    'journal_main_store_id' => $langCore['contacts_store_id'],
    'journal_main_primary_name_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_primary_name']),
    'journal_main_contact_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_contact']),
    'journal_main_address1_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_address1']),
    'journal_main_address2_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_address2']),
    'journal_main_city_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_city']),
    'journal_main_state_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_state']),
    'journal_main_postal_code_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_postal_code']),
    'journal_main_country_b' => sprintf($langCore['tbd_bill'], $langCore['address_book_country']),
    'journal_main_telephone1_b' => sprintf($langCore['tbd_bill'], $langCore['telephone']),
    'journal_main_email_b' => sprintf($langCore['tbd_bill'], $langCore['email']),
    'journal_main_primary_name_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_primary_name']),
    'journal_main_contact_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_contact']),
    'journal_main_address1_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_address1']),
    'journal_main_address2_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_address2']),
    'journal_main_city_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_city']),
    'journal_main_state_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_state']),
    'journal_main_postal_code_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_postal_code']),
    'journal_main_country_s' => sprintf($langCore['tbd_ship'], $langCore['address_book_country']),
    'journal_main_telephone1_s' => sprintf($langCore['tbd_ship'], $langCore['telephone']),
    'journal_main_email_s' => sprintf($langCore['tbd_ship'], $langCore['email']),
    'journal_main_tax_rate_id_c' => $langCore['sales_tax'],
    'journal_main_tax_rate_id_v' => $langCore['purchase_tax'],
    'journal_main_terminal_date_9' => $langCore['journal_main_terminal_date_3'],
    'journal_main_terminal_date_10' => $langCore['ship_date'],
    'journal_main_terminal_date_12' => $langCore['ship_date'],
    ];
