<?php
/**
 * MitM2_MerchandiseReport Collection
 *
 * @category  MitM2
 * @package   MitM2_MerchandiseReport
 * @author    Maybury IT
 * @copyright Copyright (c) 2026 Maybury IT
 */
namespace MitM2\MerchandiseReport\Model\ResourceModel\Report;

use Magento\Framework\Data\Collection as DataCollection;
use Magento\Framework\App\ResourceConnection;
use Magento\Eav\Model\Config as EavConfig;

class Collection extends DataCollection
{
    protected $resourceConnection;
    protected $eavConfig;
    protected $fromDate;
    protected $toDate;
    protected $skus;
    protected $staffMember;
    protected $pageSize = 20;
    protected $curPage = 1;

    public function __construct(
        \Magento\Framework\Data\Collection\EntityFactoryInterface $entityFactory,
        ResourceConnection $resourceConnection,
        EavConfig $eavConfig
    ) {
        parent::__construct($entityFactory);
        $this->resourceConnection = $resourceConnection;
        $this->eavConfig = $eavConfig;
    }

    public function setPageSize($size)
    {
        $this->pageSize = $size;
        return $this;
    }

    public function setCurPage($page)
    {
        $this->curPage = $page;
        return $this;
    }

    public function getSize()
    {
        if ($this->_totalRecords === null) {
            if (!$this->fromDate || !$this->toDate || empty($this->skus)) {
                $this->_totalRecords = 0;
            } else {
                $data = $this->getReportData();
                $this->_totalRecords = count($data);
            }
        }
        return $this->_totalRecords;
    }

    public function setDateRange($from, $to)
    {
        $this->fromDate = $from;
        $this->toDate = $to;
        return $this;
    }

    public function setSkus($skus)
    {
        // Normalize incoming SKUs (search filter sends values like "%ABC%")
        $sanitize = function ($value) {
            $value = trim($value);
            // Strip leading/trailing wildcards injected by the text filter
            $value = ltrim($value, '%');
            $value = rtrim($value, '%');
            return $value;
        };

        if (is_string($skus)) {
            $skus = array_map($sanitize, explode(',', $skus));
            $skus = array_filter($skus);
        } elseif (is_array($skus)) {
            $skus = array_map($sanitize, $skus);
            $skus = array_filter($skus);
        } else {
            $skus = [];
        }

        $this->skus = $skus;
        return $this;
    }

    public function setStaffMember($staffMember)
    {
        $this->staffMember = $staffMember ? trim($staffMember) : null;
        return $this;
    }

    public function loadData($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }

        $data = $this->getReportData();

        $offset = ($this->curPage - 1) * $this->pageSize;
        $data = array_slice($data, $offset, $this->pageSize);

        foreach ($data as $item) {
            $this->addItem(new \Magento\Framework\DataObject($item));
        }

        $this->_setIsLoaded(true);
        return $this;
    }

    protected function getReportData()
    {
        if (!$this->fromDate || !$this->toDate || empty($this->skus)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        // Convert date format from D/M/Y to Y-m-d if needed
        $fromDateFormatted = $this->formatDate($this->fromDate);
        $toDateFormatted = $this->formatDate($this->toDate);

        $toDateAdjusted = $toDateFormatted . ' 23:59:59';
        $fromDateAdjusted = $fromDateFormatted . ' 00:00:00';

        try {
            $nameAttributeId = $this->getAttributeId('name');
        } catch (\Exception $e) {
            return [];
        }

        $catalogProductEntityVarchar = $connection->getTableName('catalog_product_entity_varchar');

        // Build main sales query
        $select = $connection->select()
            ->from(['soi' => $connection->getTableName('sales_order_item')], [
                'sku' => 'soi.sku',
                'sales' => new \Zend_Db_Expr('SUM(CASE WHEN so.status IN ("complete", "processing", "closed") THEN soi.qty_ordered ELSE 0 END)'),
                'refunds' => new \Zend_Db_Expr('SUM(CASE WHEN so.status = "closed" AND soi.qty_refunded > 0 THEN soi.qty_refunded ELSE 0 END)'),
                'first_sale_date' => new \Zend_Db_Expr('MIN(so.created_at)'),
                'last_sale_date' => new \Zend_Db_Expr('MAX(so.created_at)'),
                'staff_member' => new \Zend_Db_Expr('GROUP_CONCAT(DISTINCT so.pos_staff_name SEPARATOR ", ")'),
            ])
            ->joinInner(
                ['so' => $connection->getTableName('sales_order')],
                'so.entity_id = soi.order_id',
                []
            )
            ->joinLeft(
                ['cpev_name' => $catalogProductEntityVarchar],
                $connection->quoteInto(
                    'cpev_name.entity_id = soi.product_id AND cpev_name.attribute_id = ? AND cpev_name.store_id = 0',
                    $nameAttributeId
                ),
                ['product_name' => new \Zend_Db_Expr('MAX(cpev_name.value)')]
            )
            ->where('soi.parent_item_id IS NULL')
            ->where('so.status IN (?)', ['complete', 'processing', 'closed'])
            ->where('so.created_at >= ?', $fromDateAdjusted)
            ->where('so.created_at <= ?', $toDateAdjusted)
            ->where('soi.sku IN (?)', $this->skus);

        // Apply staff member filter if provided
        if ($this->staffMember) {
            $select->where('so.pos_staff_name LIKE ?', '%' . $this->staffMember . '%');
        }

        $select->group('soi.sku')
            ->order('soi.sku ASC');

        $results = $connection->fetchAll($select);

        // Ensure all requested SKUs are in the results (even with 0 sales)
        $resultSkus = array_column($results, 'sku');
        $missingSkus = array_diff($this->skus, $resultSkus);

        foreach ($missingSkus as $missingSku) {
            // Get product name for missing SKUs
            $productName = $this->getProductNameBySku($missingSku, $nameAttributeId);
            
            $results[] = [
                'sku' => $missingSku,
                'product_name' => $productName,
                'sales' => 0,
                'refunds' => 0,
                'staff_member' => '',
                'first_sale_date' => null,
                'last_sale_date' => null,
            ];
        }

        return $results;
    }

    protected function getProductNameBySku($sku, $nameAttributeId)
    {
        $connection = $this->resourceConnection->getConnection();
        
        $select = $connection->select()
            ->from(['cpe' => $connection->getTableName('catalog_product_entity')], [])
            ->joinLeft(
                ['cpev' => $connection->getTableName('catalog_product_entity_varchar')],
                $connection->quoteInto(
                    'cpev.entity_id = cpe.entity_id AND cpev.attribute_id = ? AND cpev.store_id = 0',
                    $nameAttributeId
                ),
                ['product_name' => 'value']
            )
            ->where('cpe.sku = ?', $sku)
            ->limit(1);

        $result = $connection->fetchOne($select);
        return $result ?: '';
    }

    protected function getAttributeId($attributeCode)
    {
        $attribute = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        if (!$attribute || !$attribute->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Attribute with code "%1" not found.', $attributeCode)
            );
        }
        return (int)$attribute->getId();
    }

    protected function formatDate($date)
    {
        if (empty($date)) {
            return $date;
        }

        // If date contains '/' parse as DD/MM/YYYY format (UK locale)
        if (strpos($date, '/') !== false) {
            try {
                $dateObj = \DateTime::createFromFormat('d/m/Y', $date);
                if ($dateObj) {
                    return $dateObj->format('Y-m-d');
                }
                // Fallback to generic parser
                $dateObj = new \DateTime($date);
                return $dateObj->format('Y-m-d');
            } catch (\Exception $e) {
                return $date;
            }
        }

        // If already in Y-m-d format or Y-m-d H:i:s, extract just the date part
        if (strpos($date, ' ') !== false) {
            return substr($date, 0, 10);
        }

        return $date;
    }

    public function load($printQuery = false, $logQuery = false)
    {
        return $this->loadData($printQuery, $logQuery);
    }
}
