<?php
/*
 * Language translation for Inventory module
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
 * @version    6.x Last Update: 2024-03-28
 * @filesource /locale/en_US/module/inventory/language.php
 */

$lang = [
    'title' => 'Inventory',
    'description' => 'The inventory module manages all products and services used in the PhreeSoft Business Toolkit. <b>NOTE: This is a core module and cannot be removed!</b>',
    'inventory_merge' => 'Merge SKUs',
    // Settings
    'weight_uom_lbl' => 'Weight UOM',
    'dim_uom_lbl' => 'Dimension UOM',
    'tax_rate_id_c_lbl' => 'Sales Tax - Customers',
    'tax_rate_id_v_lbl' => 'Purchase Tax - Vendors',
    'auto_add_lbl' => 'Auto Add SKU',
    'auto_cost_lbl' => 'Update Cost',
    'allow_neg_stock_lbl'=> 'Allow No Stock Sales',
    'stock_usage_lbl'    => 'Stock Usage',
    'average_usage'      => 'Average Usage',
    'annual_usage'       => 'Annual Usage',
    'weight_uom_tip'     => 'Default weight unit of measure.',
    'dim_uom_tip'        => 'Default dimension unit of measure.',
    'tax_rate_id_c_tip'  => 'Default sales tax rate to use for all inventory items for Sales.',
    'tax_rate_id_v_tip'  => 'Default purchase tax rate to use for all inventory items for Purchases.',
    'auto_add_tip'       => 'Yes - Enable auto-add new items to inventory, default type will be Stock Item. No - SKUs must exist in inventory database to post to general ledger.',
    'auto_cost_tip'      => 'If set, will set the latest cost in the inventory database when (PO) Purchase Orders are posted, (PR) Inventory items are received. This feature helps to keep your Unit Cost up to date with the latest costing.',
    'allow_neg_stock_tip'=> 'Yes - Allow sales of items that are not in stock. No - require adequate stock to post to general journal.',
    'stock_usage_tip'    => 'This feature will calculate the average and median item usage over the last 12 months and displays the results in a popup when the inventory item is edited.',
    'inc_assemblies_lbl' => 'Include Assemblies',
    'inc_assemblies_tip' => 'Include assemblies in the stock calculations when determining the available inventory quantities to display and send to your store to fill orders.',
    'inc_committed_lbl'  => 'Subtract Committed Quantities',
    'inc_committed_tip'  => 'Subtract committed quantities from sales orders and work orders when determining the quantity of inventory items are available to sell.',
    'inv_sales_lbl' => 'Sales GL Account: ',
    'inv_inv_lbl' => 'Inventory GL Account: ',
    'inv_cogs_lbl' => 'COGS GL Account: ',
    'inv_meth_lbl' => 'Costing Method: ',
    'inv_sales_' => 'Default GL Account for Sales/Income activities for inventory type: ',
    'inv_inv_'   => 'Default GL Account for Inventory/Wage activities for inventory type: ',
    'inv_cogs_'  => 'Default GL Account for Cost of Sales (COGS) for inventory type: ',
    'inv_meth_'  => 'Default Costing Method to use for inventory type: ',
    'price_sheet_to_override' => 'Select price sheet to override:',
    // Labels
    'store_stock' => 'Store Stock',
    '01month' => '1 Months Average',
    '03month' => '3 Months Average',
    '06month' => '6 Months Average',
    '12month' => '12 Months Average',
    'adj_value' => 'Adj Value',
    'rnd_value' => 'Rnd Value',
    'inv_hst_qty' => 'Qty Stock',
    'inv_hst_val' => 'Inv Value',
    'inv_hst_cst' => 'Average Cost',
    // Messages
    'msg_sku_entry_copy' => 'Enter a SKU to be created from this record:',
    'msg_sku_entry_rename' => 'Enter a new SKU for this record:',
    'msg_inventory_merge_src' => 'Select the SKU that will be merged. <b>WARNING!</b> This SKU will be removed from the inventory table after the merge along with all of the associated properties.',
    'msg_inventory_merge_dest' => 'Select the SKU to merge into. This SKU will remain after the merge.',
    'msg_inventory_merge_note' => '<b>DISCLAIMER:</b>THIS PROCESS IS NOT REVERSABLE. Please make a backup before proceeding! This tool will not change the prices or GL accounts for any posted transactions. If the source and destination SKUs are posted to different GL accounts you could un-balance your journal. If this happens please run the Journal Tools to verify your books are in balance in Settings -> PhreeBooks -> Journal Tools.',
    'msg_inventory_sku_usage' => 'This SKU is part of the following assemblies:',
    'msg_inventory_assy_cost' => 'The current cost to assemble this sku is: %s',
    'msg_inv_assy_stock_good' => 'There are enough parts to build this sku!',
    'msg_no_price_sheets' => '<b>Note:</b> No price sheets have been selected to create/edit. Press the New icon on the Manager toolbar to create a new price sheet or edit an existing price sheet.',
    'msg_inv_qty_min' => 'Inventory stock levels need adjusting. New minimum stock = %s',
    'msg_inv_median' => 'Check monthly usage, median value (%s) is out of range to average sales (%s).',
    // Error Messages
    'err_inv_sku_blank' => 'The SKU Field is required!',
    'err_inv_delete_assy' => 'The inventory item cannot be deleted since it is part of an assembly. This SKU must be removed from the assembly before it can be deleted.',
    'err_inv_delete_gl_entry' => 'The inventory item cannot be deleted if there are journal entries assigned to it. Either repost the entries assigned to this item or set the item inactive.',
    'err_inv_assy_error' => 'Either this is not an assembly or no there are no parts in this assembly!',
    'err_inv_assy_low_stock' => 'There are not enough parts to build %s of this SKU!',
    'err_inv_assy_low_list' => '(%s) %s: Stock: %s, %s are needed.',
    // Tools
    'inv_tools_val_inv' => 'Validate Inventory Displayed Stock',
    'inv_tools_val_inv_desc' => 'This operation tests to make sure your inventory quantities listed in the inventory database and displayed in the inventory screens are the same as the quantities in the inventory history database as calculated by PhreeBooks when inventory movements occur. The only items tested are the ones that are tracked in the cost of goods sold calculation. Repairing inventory balances will correct the quantity in stock and leave the inventory history data alone.',
    'inv_tools_repair_test' => 'Test Inventory Balances with COGS History',
    'inv_tools_repair_fix' => 'Repair Inventory Balances with COGS History',
    'inv_sku_drill_title' => 'SKU Journal Activity',
    'inv_sku_drill_desc' => 'This tool does a deep dive on a particular SKU to follow the journal activity. It is best used when SKUs in inventory or stores don\'t match physical stock. Select a SKU and a start date, then Go.',
    'inv_sku_recalc_title' => 'Fix/Repair History Table',
    'inv_sku_recalc_desc' => 'This tool compares the balances in the inventory history table with the journal entries and repairs any discrepencies. WARNING: It takes a long time to run if there are a lot of products!',
    'inv_tools_qty_alloc' => 'Inventory Allocation',
    'inv_tools_qty_alloc_desc' => 'This tool will sync inventory quantity on allocation values will journal entries from open activities.',
    'inv_tools_qty_alloc_label' => 'Sync Inventory Quantites on Allocation',
    'inv_tools_btn_test' => 'Verify Stock Balances',
    'inv_tools_btn_repair' => 'Sync Qty in Stock',
    'inv_tools_out_of_balance' => 'SKU: %s -> stock indicates %s on hand but COGS history list %s available',
    'inv_tools_in_balance' => 'Your inventory balances are OK.',
    'inv_tools_stock_rounding_error' => 'SKU: %s -> Stock indicates %s on hand but is less than your precision. Please repair your inventory balances, the stock on hand will be rounded to %s.',
    'inv_tools_balance_corrected' => 'SKU: %s -> The inventory stock on hand has been changed to %s.',
    'inv_tools_validate_so_po_desc' => 'This operation tests to make sure your inventory quantity on Purchase Order and quantity of Sales Order match with the journal entries. The calculated values from the journal entries override the value in the inventory table.',
    'inv_tools_repair_so_po' => 'Test and Repair Inventory Quantity on Order Values',
    'inv_tools_btn_so_po_fix' => 'Begin Test and Repair',
    'inv_tools_so_po_result' => 'Finished processing Inventory on order quantities with no errors.',
    'inv_tools_price_assy' => 'Recalculate Costs of Assemblies',
    'inv_tools_price_assy_desc' => 'Recalculate the cost of all assemblies and serialized assembies based on the bill of materials and their current item cost. Iterates through all items and updates the database of the assembly item.',
    // API and Import/Export
    'invapi_desc' => 'The inventory API currently supports the base inventory table for both inserts and updates. Extra custom fields are supported. To import an inventory file:<br>1. Download the inventory template which lists the field headers and descriptions.<br>2. Add your data to your .csv file.<br>3. Select the file and press the import icon.<br>The results will be displayed after the script completes. Any errors will also be displayed.',
    'invapi_template' => 'Step 1: Download the inventory template => ',
    'invapi_skips' => 'Bizuno expects the first row to be the indexes. If there are additional rows, select the nmber of rows to ignore before importing the file.',
    'invapi_import' => 'Step 2: Add your inventory to the template, browse to select the file and press Import. ',
    'invapi_export' => 'OPTIONAL: Export your inventory database table in .csv format for backup => ',
    // Install notes
    'note_inventory_install_1' => 'PRIORITY MEDIUM: Set default general ledger accounts for inventory types, after loading GL accounts (My Business -> Settings -> Bizuno Tab -> Inventory (Settings)',
    ];
