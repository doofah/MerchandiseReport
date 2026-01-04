<?php
/**
 * MitM2_MerchandiseReport Data Provider
 *
 * @category  MitM2
 * @package   MitM2_MerchandiseReport
 * @author    Maybury IT
 * @copyright Copyright (c) 2026 Maybury IT
 */

namespace MitM2\MerchandiseReport\Ui\Component;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as AbstractDataProvider;
use MitM2\MerchandiseReport\Model\ResourceModel\Report\CollectionFactory;

/**
 * Class DataProvider
 *
 * Data provider for Merchandise Report grid
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var array
     */
    protected $appliedFilters = [];

    /**
     * @var \MitM2\MerchandiseReport\Model\ResourceModel\Report\Collection
     */
    protected $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
        $this->collectionFactory = $collectionFactory;
        $this->request = $request;
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        // Process filters from search criteria
        $searchCriteria = $this->getSearchCriteria();
        if ($searchCriteria) {
            foreach ($searchCriteria->getFilterGroups() as $filterGroup) {
                foreach ($filterGroup->getFilters() as $filter) {
                    $this->addFilter($filter);
                }
            }
        }

        try {
            $collection = $this->getCollection();

            // Apply pagination
            $pagingParams = $this->request->getParam('paging', []);
            if (is_array($pagingParams)) {
                $pageSize = isset($pagingParams['pageSize']) ? (int)$pagingParams['pageSize'] : 20;
                $currentPage = isset($pagingParams['current']) ? (int)$pagingParams['current'] : 1;
            } else {
                $pageSize = 20;
                $currentPage = 1;
            }

            $collection->setPageSize($pageSize);
            $collection->setCurPage($currentPage);

            $totalRecords = $collection->getSize();

            if (!$collection->isLoaded()) {
                $collection->load();
            }

            $items = [];
            $rowId = 1;
            foreach ($collection as $item) {
                $items[] = [
                    'row_id' => $rowId++,
                    'sku' => $item->getData('sku'),
                    'product_name' => $item->getData('product_name'),
                    'sales' => (int)$item->getData('sales'),
                    'refunds' => (int)$item->getData('refunds'),
                    'staff_member' => $item->getData('staff_member'),
                    'first_sale_date' => $item->getData('first_sale_date'),
                    'last_sale_date' => $item->getData('last_sale_date'),
                ];
            }

            return [
                'totalRecords' => $totalRecords,
                'items' => $items,
            ];
        } catch (\Exception $e) {
            return [
                'totalRecords' => 0,
                'items' => [],
            ];
        }
    }

    /**
     * Get collection
     *
     * @return \MitM2\MerchandiseReport\Model\ResourceModel\Report\Collection
     */
    public function getCollection()
    {
        if (!$this->collection) {
            $this->collection = $this->collectionFactory->create();

            $fromDate = null;
            $toDate = null;
            $skus = null;
            $staffMember = null;

            // Check if filters were applied via addFilter()
            if (isset($this->appliedFilters['created_at']) && is_array($this->appliedFilters['created_at'])) {
                $fromDate = isset($this->appliedFilters['created_at']['from']) ? $this->appliedFilters['created_at']['from'] : null;
                $toDate = isset($this->appliedFilters['created_at']['to']) ? $this->appliedFilters['created_at']['to'] : null;
            }
            
            if (isset($this->appliedFilters['skus'])) {
                $skus = $this->appliedFilters['skus'];
            }

            if (isset($this->appliedFilters['staff_member'])) {
                $staffMember = $this->appliedFilters['staff_member'];
            }

            // Fallback: check request params
            if (!$fromDate || !$toDate || !$skus || !$staffMember) {
                $params = $this->request->getParams();
                $filters = isset($params['filters']) ? $params['filters'] : [];

                if (!$fromDate || !$toDate) {
                    if (isset($filters['created_at']) && is_array($filters['created_at'])) {
                        $fromDate = $fromDate ?: (isset($filters['created_at']['from']) ? $filters['created_at']['from'] : null);
                        $toDate = $toDate ?: (isset($filters['created_at']['to']) ? $filters['created_at']['to'] : null);
                    }
                }

                if (!$skus && isset($filters['skus'])) {
                    $skus = $filters['skus'];
                }

                if (!$staffMember && isset($filters['staff_member'])) {
                    $staffMember = $filters['staff_member'];
                }
            }

            // Set filters on collection
            $this->collection->setDateRange($fromDate, $toDate);
            $this->collection->setSkus($skus);
            $this->collection->setStaffMember($staffMember);
        }

        return $this->collection;
    }

    /**
     * Add filter
     *
     * @param \Magento\Framework\Api\Filter $filter
     * @return void
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
        $field = $filter->getField();
        $value = $filter->getValue();

        // Call parent to ensure filter is registered
        parent::addFilter($filter);

        if ($field === 'created_at') {
            // Store date range filter
            $this->appliedFilters[$field] = $value;
            $this->collection = null;
            return;
        }

        if ($field === 'skus') {
            // Store SKUs filter
            $this->appliedFilters[$field] = $value;
            $this->collection = null;
            return;
        }

        if ($field === 'staff_member') {
            // Store staff member filter
            $this->appliedFilters[$field] = $value;
            $this->collection = null;
            return;
        }

        // For other filters
        $this->appliedFilters[$field] = $value;
    }
}
