<?php
/**
 * MitM2_MerchandiseReport Index Controller
 *
 * @category  MitM2
 * @package   MitM2_MerchandiseReport
 * @author    Maybury IT
 * @copyright Copyright (c) 2026 Maybury IT
 */

namespace MitM2\MerchandiseReport\Controller\Adminhtml\Report;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Index
 *
 * Display the Merchandise Items by Sales Report grid
 */
class Index extends Action
{
    /**
     * Authorization level of a basic admin session
     */
    const ADMIN_RESOURCE = 'MitM2_MerchandiseReport::merchandise_report';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Index action
     *
     * @return \Magento\Backend\Model\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MitM2_MerchandiseReport::merchandise_report');
        $resultPage->getConfig()->getTitle()->prepend(__('Merchandise Items by Sales'));

        return $resultPage;
    }
}
