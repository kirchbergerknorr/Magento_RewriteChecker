<?php
/**
 * Observer model
 *
 * @category    Kirchbergerknorr
 * @package     Kirchbergerknorr_RewriteChecker
 * @author      Benedikt Volkmer <bv@kirchbergerknorr.de>
 * @copyright   Copyright (c) 2016 kirchbergerknorr GmbH (http://www.kirchbergerknorr.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Kirchbergerknorr_RewriteChecker_Model_Observer
{
    /**
     * @var Mage_Core_Model_Resource $read          Mysql connection
     */
    protected $read;

    /**
     * @var Mage_Catalog_Model_Url $rewriteModel    Magento url model
     */
    protected $rewriteModel;

    /**
     * Kirchbergerknorr_RewriteChecker_Model_Observer constructor.
     */
    public function __construct()
    {
        $this->read = Mage::getSingleton( 'core/resource' )->getConnection( 'core_read' );
        $this->rewriteModel = Mage::getModel( 'catalog/url' );
    }


    /**
     * Check rewrites if enabled
     *
     * @return bool
     */
    public function checkRewrites() {

        if (!Mage::getStoreConfig('kirchbergerknorr/rewrite_check/active')) {
            return;
        }

        if (Mage::getStoreConfig('kirchbergerknorr/rewrite_check/check_categories')) {
            $this->checkCategoryRewrites();
        } else {
            $this->log("Category rewrite check is INACTIVE - exit.");
        }

        if (Mage::getStoreConfig('kirchbergerknorr/rewrite_check/check_products')) {
            $this->checkroductRewrites();
        } else {
            $this->log("Product rewrite check is INACTIVE - exit.");
        }

        return true;
    }

    /**
     * Log message if logging is active
     * @param string $message
     */
    public function log($message)
    {
        if (Mage::getStoreConfig('kirchbergerknorr/rewrite_check/log')) {
            Mage::log($message, null, 'kk_rewrite_checker.log');
        }
    }

    /**
     * Check categories rewrites
     */
    public function checkCategoryRewrites()
    {
        $categoryIds = Mage::getModel('catalog/category')->getCollection()->getAllIds();
        $existingRewrites = $this->getUrlRewrites('category');

        // Check array diff to get category ids with no rewrite
        $missingRewrites = $result = array_diff($categoryIds, $existingRewrites);

        $this->log(count($missingRewrites) . " missing category rewrites found.");
    }

    /**
     * Check product rewrites
     */
    public function checkProductRewrites()
    {
        $productIds = Mage::getModel('catalog/product')->getCollection()->getAllIds();
        $existingRewrites = $this->getUrlRewrites('product');

        // Check array diff to get product ids with no rewrite
        $missingRewrites = $result = array_diff($productIds, $existingRewrites);

        $this->log(count($missingRewrites) . " missing product rewrites found.");
    }

    /**
     * Checks url_rewrite table and returns categories or products with existing rewrite
     *
     * @param string $type Defines if category or product should be chacked
     * @return array
     */
    public function getUrlRewrites($type)
    {
        $urlRewriteTable = Mage::getSingleton( 'core/resource' )->getTableName( 'core_url_rewrite' );

        if('product' == $type) {
            $query = "SELECT DISTINCT(category_id) FROM " . $urlRewriteTable . " WHERE product_id IS :product_id AND category_id is :category_id";

            $binds = array(
                'product_id' => "NOT NULL",
                'category_id' => "NULL"
            );
        } else if('category' == $type) {
            $query = "SELECT DISTINCT(category_id) FROM " . $urlRewriteTable . " WHERE category_id IS :category_id AND product_id is :product_id;";

            $binds = array(
                'category_id' => "NOT NULL",
                'product_id' => "NULL"
            );
        } else {
            $this->log("No type to check set - exit.");
            return false;
        }

        try {
            $result = $this->read->query( $query, $binds );
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $result;
    }

    /**
     * Trigger magento url rewrite for category or product id
     * @param string $type  category|product
     * @param int $id       category or product entity_id
     *
     * @return bool
     */
    protected function triggerUrlRewrite($type, $id)
    {
        if('category' == $type) {
            $this->rewriteModel->refreshCategoryRewrite($id, null, false);
        } else if ('product' == $type) {
            $this->rewriteModel->refreshProductRewrite($id);
        } else {
            $this->log("No type to generate set - exit.");
            return false;
        }

        return true;
    }
}