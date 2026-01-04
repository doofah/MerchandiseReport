<?php
/**
 * MitM2_MerchandiseReport Resource Model
 *
 * @category  MitM2
 * @package   MitM2_MerchandiseReport
 * @author    Maybury IT
 * @copyright Copyright (c) 2026 Maybury IT
 */
namespace MitM2\MerchandiseReport\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Report extends AbstractDb
{
    protected function _construct()
    {
        // This is a custom report, no real table
        $this->_init('sales_order', 'entity_id');
    }
}
