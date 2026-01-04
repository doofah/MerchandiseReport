<?php
/**
 * MitM2_MerchandiseReport Export CSV Controller
 */

namespace MitM2\MerchandiseReport\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use MitM2\MerchandiseReport\Model\ResourceModel\Report\CollectionFactory;

class ExportCsv extends Action implements CsrfAwareActionInterface, HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'MitM2_MerchandiseReport::merchandise_report';

    /** @var FileFactory */
    protected $fileFactory;

    /** @var CollectionFactory */
    protected $collectionFactory;

    public function __construct(
        Context $context,
        FileFactory $fileFactory,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute()
    {
        $fileName = 'merchandise_report_' . date('Ymd_His') . '.csv';

        // Setup logging
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/merchandise_export.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

        // Get request params which include filters from the grid
        $params = $this->getRequest()->getParams();
        $logger->info('===== EXPORT START =====');
        $logger->info('All Params: ' . json_encode($params));
        
        // Extract filters
        $filters = isset($params['filters']) ? $params['filters'] : [];
        $logger->info('Filters: ' . json_encode($filters));
        
        $fromDate = isset($filters['created_at']['from']) ? $filters['created_at']['from'] : null;
        $toDate = isset($filters['created_at']['to']) ? $filters['created_at']['to'] : null;
        $skus = isset($filters['skus']) ? $filters['skus'] : null;
        
        $logger->info('From Date: ' . ($fromDate ?: 'NULL'));
        $logger->info('To Date: ' . ($toDate ?: 'NULL'));
        $logger->info('SKUs: ' . ($skus ?: 'NULL'));

        // Create collection and apply filters
        $collection = $this->collectionFactory->create();
        $logger->info('Collection created');
        
        if ($fromDate && $toDate && $skus) {
            $logger->info('Applying filters...');
            $collection->setDateRange($fromDate, $toDate);
            $collection->setSkus($skus);
            $collection->setPageSize(1000000);
            $collection->setCurPage(1);
            $logger->info('Loading collection...');
            $collection->load();
            $logger->info('Collection loaded. Total items: ' . count($collection));
            
            foreach ($collection as $item) {
                $logger->info('Item: ' . json_encode($item->getData()));
            }
        } else {
            $logger->info('Filters incomplete - skipping data load');
        }

        $logger->info('Generating CSV content...');
        $csvContent = $this->generateCsvContent($collection);
        $logger->info('CSV Content length: ' . strlen($csvContent));
        $logger->info('CSV Content (first 500 chars): ' . substr($csvContent, 0, 500));

        $logger->info('===== EXPORT END =====');

        return $this->fileFactory->create(
            $fileName,
            $csvContent,
            DirectoryList::VAR_DIR,
            'text/csv',
            null
        );
    }

    protected function generateCsvContent($collection)
    {
        $csvData = [];
        $csvData[] = ['SKU', 'Name', 'Sales', 'Refunds', 'Staff Member', 'First Sale Date', 'Last Sale Date'];

        foreach ($collection as $item) {
            $csvData[] = [
                $item->getData('sku'),
                $item->getData('product_name'),
                $item->getData('sales'),
                $item->getData('refunds'),
                $item->getData('staff_member'),
                $item->getData('first_sale_date'),
                $item->getData('last_sale_date'),
            ];
        }

        $output = fopen('php://temp', 'r+');
        fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}

