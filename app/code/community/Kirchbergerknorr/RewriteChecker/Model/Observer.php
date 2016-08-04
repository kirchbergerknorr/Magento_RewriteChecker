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
     * @var int $fixedCount                         Counter of fixed entries
     */
    protected $fixedCount;

    /**
     * @var int $maxFix                             Counter of max ids to fix
     */
    protected $maxFix;



    /**
     * Kirchbergerknorr_RewriteChecker_Model_Observer constructor.
     */
    public function __construct()
    {
        $this->read = Mage::getSingleton( 'core/resource' )->getConnection( 'core_read' );
        $this->rewriteModel = Mage::getModel( 'catalog/url' );
        $this->maxFix = Mage::getStoreConfig('kirchbergerknorr/rewrite_check/max_fix');
    }


    /**
     * Check rewrites if enabled
     *
     * @return bool
     */
    public function checkRewrites() {

        if (!Mage::getStoreConfig('kirchbergerknorr/rewrite_check/active')) {
            $this->log("Rewrite Checker disabled - exit");
            return;
        }

        if (Mage::getStoreConfig('kirchbergerknorr/rewrite_check/check_categories')) {
            $this->checkCategoryRewrites();
        } else {
            $this->log("Category rewrite check is INACTIVE - exit.");
        }

        if (Mage::getStoreConfig('kirchbergerknorr/rewrite_check/check_products')) {
            $this->checkProductRewrites();
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
        // Get all active category ids
        $categoryIds = Mage::getModel('catalog/category')
            ->getCollection()
            ->addAttributeToFilter('is_active', 1)
            ->getAllIds();

        // Get existing rewrites from table
        $existingRewrites = $this->getUrlRewrites('category');

        // Get category ids which should be ignored
        $ignoreIds = explode(',', Mage::getStoreConfig('kirchbergerknorr/rewrite_check/ignore_categories'));

        // Check array diff to get category ids with no rewrite
        $missingRewrites = $result = array_diff($categoryIds, $existingRewrites, $ignoreIds);

        $this->log(count($missingRewrites) . " missing category rewrites found.");

        if(Mage::getStoreConfig('kirchbergerknorr/rewrite_check/fix_categories')) {
            $this->fixedCount = 0;
            foreach ($missingRewrites as $id) {
                $this->triggerUrlRewrite('category', $id);

                if($this->fixedCount++ >= $this->maxFix) {
                    break;
                }
            }
        } else {
            $this->log("Category URL fixing disabled - skip.");
        }
    }

    /**
     * Check product rewrites
     */
    public function checkProductRewrites()
    {
        // Get all active product ids
        $productIds = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('is_active', 1)
            ->getAllIds();

        // Get existing rewrites from table
        $existingRewrites = $this->getUrlRewrites('product');

        // Get product ids which should be ignored
        $ignoreIds = explode(',', Mage::getStoreConfig('kirchbergerknorr/rewrite_check/ignore_products'));

        // Check array diff to get product ids with no rewrite
        $missingRewrites = $result = array_diff($productIds, $existingRewrites, $ignoreIds);

        $this->log(count($missingRewrites) . " missing product rewrites found.");

        if(Mage::getStoreConfig('kirchbergerknorr/rewrite_check/fix_products')) {
            $this->fixedCount = 0;
            foreach ($missingRewrites as $id) {
                $this->triggerUrlRewrite('product', $id);

                if($this->fixedCount++ >= $this->maxFix) {
                    break;
                }
            }
        } else {
            $this->log("Product URL fixing disabled - skip.");
        }
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
            $query = "SELECT DISTINCT(product_id) FROM " . $urlRewriteTable . " WHERE product_id IS NOT NULL AND category_id is NULL";
        } else if('category' == $type) {
            $query = "SELECT DISTINCT(category_id) FROM " . $urlRewriteTable . " WHERE category_id IS NOT NULL AND product_id IS NULL";
        } else {
            $this->log("No type to check set - exit.");
            return false;
        }

        try {
            $result = $this->read->fetchCol( $query );
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
            $this->log("Triggered rewrite for category $id");
            $this->rewriteModel->refreshCategoryRewrite($id, null, false);
        } else if ('product' == $type) {
            $this->log("Triggered rewrite for product $id");
            $this->rewriteModel->refreshProductRewrite($id);
        } else {
            $this->log("No type to generate set - exit.");
            return false;
        }

        return true;
    }
}