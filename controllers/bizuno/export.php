<?php
/*
 * API Export controller
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
 * @version    6.x Last Update: 2024-02-16
 * @filesource /controllers/bizuno/export.php
 */

namespace bizuno;

class bizunoExport
{
    public  $moduleID  = 'bizuno';
    public  $user_email= '';      // will be filled in after access granted
    private $updated   = false;
    private $endCatOnly= false;   // if set to true, will only check the final category of the tree

    function __construct() {
        $this->lang = getLang('bizuno'); // needs to be hardcoded as this is extended by extensions
    }

    /**
     *
     */
    public function apiInventory(&$product=[])
    {
        $rID = clean($product['RecordID'], 'integer');
        if (!$rID) { return msgDebug("\nBad ID passed. Needs to be the inventory field id tag name (RecordID)."); }
        setUserCache('security', 'prices_c', 1);
        setUserCache('security', 'j12_mgr', 1);
        msgDebug("\nRead back user cache for prices_c = ".getUserCache('security', 'prices_c'));
        msgDebug("\nRead back user cache for j12_mgr = ".getUserCache('security', 'j12_mgr'));
        $pDetails['args'] = ['iID'=>$rID];
        compose('inventory', 'prices', 'quote', $pDetails);
        $product['Price'] = $pDetails['content']['price'];
        if (!empty($pDetails['content']['regular_price'])){ $product['RegularPrice']= $pDetails['content']['regular_price']; }
        if (!empty($pDetails['content']['sale_price']))   { $product['SalePrice']   = $pDetails['content']['sale_price']; }
        if ( isset($pDetails['content']['sheets']) && sizeof($pDetails['content']['sheets']) > 0) { $product['PriceLevels'] = $pDetails['content']['sheets']; }
        $product['WeightUOM']   = getModuleCache('inventory', 'settings', 'general', 'weight_uom','LB');
        $product['DimensionUOM']= getModuleCache('inventory', 'settings', 'general', 'dim_uom',   'IN');
        $this->getImage($product);
        $this->getAccessories($product);
    }

    /**
     *
     * @global type $io
     * @param type $product
     * @return type
     */
    private function getImage(&$product)
    {
        global $io;
        $product['Images'] = [];
        $product['ProductImageData'] = $product['ProductImageDirectory'] = $product['ProductImageFilename'] = '';
        if (!empty($product['Image'])) { // primary image file
            $info = pathinfo($product['Image']);
            $data = $io->fileRead("images/{$product['Image']}");
            $product['ProductImageData']     = $data ? base64_encode($data['data']) : '';
            $product['ProductImageDirectory']= $info['dirname']."/";
            $product['ProductImageFilename'] = $info['basename'];
        }
        if (empty($product['invImages'])) { return; }
        $images = json_decode($product['invImages'], true);
        foreach ($images as $image) { // invImages extension list
            $info = pathinfo($image);
            $data = $io->fileRead("images/$image");
            $product['Images'][] = ['Title'=>$info['basename'], 'Path'=>$info['dirname']."/", 'Filename'=>$info['basename'], 'Data'=>$data ? base64_encode($data['data']) : ''];
        }
    }

    private function getAccessories(&$product)
    {
        if (isset($product['invAccessory'])) {
            $vals = json_decode($product['invAccessory'], true);
            if (!is_array($vals)) { return; }
            unset($product['invAccessory']);
            foreach ($vals as $rID) {
                $product['invAccessory'][] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
            }
        }
    }

    /**
     *
     * @param type $layout
     */
    public function apiSync(&$layout=[])
    {
        $skus  = [];
        if (!isset($layout['data']['syncSkus'])) { $layout['data']['syncSkus'] = []; }
        $layout['data']['syncDelete'] = clean('syncDelete', 'integer', 'get');
        $field = !empty($layout['data']['syncTag']) ? $layout['data']['syncTag'] : 'cart_sync';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "`$field`='1'"); //  AND inactive='0'
        foreach ($result as $row) { $skus[] = $row['sku']; }
        $layout['data']['syncSkus'] = json_encode($skus);
        msgDebug("\nSending aipSync content = ".print_r($layout, true));
    }

    /**
     * Takes package information in and returns the Bizuno enabled shipping rates and services
     * @param array $layout - Structure coming in ($layout['pkg'] is where the information is)
     */
    public function shippingRates(&$layout=[])
    {
        msgDebug("\nEntering export/shippingRates with layout = ".print_r($layout, true));
        if ( !\is_plugin_active ( 'bizuno-pro/bizuno-pro.php' ) ) { return; }
        $pkg = [
            'destination' => [
                'address1'   => $layout['pkg']['destination']['address'],
                'address2'   => $layout['pkg']['destination']['address_1'],
                'city'       => $layout['pkg']['destination']['city'],
                'state'      => $layout['pkg']['destination']['state'],
                'postal_code'=> $layout['pkg']['destination']['postcode'],
                'country'    => clean($layout['pkg']['destination']['country'], ['format'=>'country','option'=>'ISO2'])],
            'settings' => [
                'ship_date'  => date('Y-m-d'),
                'order_total'=> !empty($layout['pkg']['cart_subtotal']) ? $layout['pkg']['cart_subtotal'] : 0,
                'weight'     => !empty($layout['pkg']['destination']['totalWeight']) ? $layout['pkg']['destination']['totalWeight'] : 1,
//              'length'     => clean('length',     'float',  'post'),
//              'width'      => clean('width',      'float',  'post'),
//              'height'     => clean('height',     'float',  'post'),
                'num_boxes'  => 1, // Assume 1 for now as dims may not be entered
                'ltl_class'  => 60, // clean('ltl_class',  'text',   'post'),
                'residential'=> 1,  // clean('residential','boolean','post'),
                'verify_add' => true]];
//        $pkg['ship']['country2'] = $layout['pkg']['destination']['country'];
//        $pkg['ship']['country3'] = $pkg['ship']['country'];
        bizAutoLoad(BIZBOOKS_EXT.'controllers/proLgstc/rate.php', 'proLgstcShip');
        $quote = new proLgstcRate();
        $quote->fieldsAddress($pkg, ['suffix'=>'_o','cID'=>0]); // origin
        $quote->fieldsAddress($pkg, ['suffix'=>'_s','cID'=>0]); // shipper
        $quote->fieldsAddress($pkg, ['suffix'=>'_p','cID'=>0]); // payor
//        $quote->fieldsAddress($pkg, ['suffix'=>'_s','src'=>'post']); // destination
        msgDebug("\ncalling rateAPI with pkg = ".print_r($pkg, true));
        $layout['rates'] = $quote->rateAPI($pkg);
    }

    /**
     * Install common fields into inventory db table shared amongst all the interfaces
     */
    protected function installStoreFields()
    {
        $id = validateTab($module_id='inventory', 'inventory', lang('estore'), 80);
        if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'msrp'))             { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD msrp DOUBLE NOT NULL DEFAULT '0' COMMENT 'label:Mfg Suggested Retail Price;tag:MSRPrice;tab:$id;order:42'"); }
        // All of these fields have been moved to attributes or removed. Used for customization only.
//      if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'description_long')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD description_long TEXT COMMENT 'type:textarea;label:Long Description;tag:DescriptionLong;tab:$id;order:10'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'manufacturer'))     { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD manufacturer VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'label:Manufacturer;tag:Manufacturer;tab:$id;order:40'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'model'))            { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD model VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'label:Model;tag:Model;tab:$id;order:41'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'meta_keywords'))    { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD meta_keywords VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'label:Meta Keywords;tag:MetaKeywords;tab:$id;o rder:90;group:General'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'meta_description')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD meta_description VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'label:Meta Description;tag:MetaDescription;tab:$id;order:91;group:General'"); }
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/admin.php', 'inventoryAdmin');
        $inv = new inventoryAdmin();
        $inv->installPhysicalFields();
    }
}
