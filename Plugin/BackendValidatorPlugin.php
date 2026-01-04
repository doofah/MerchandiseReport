<?php
/**
 * MitM2_MerchandiseReport Backend Validator Plugin
 *
 * @category  MitM2
 * @package   MitM2_MerchandiseReport
 * @author    Maybury IT
 * @copyright Copyright (c) 2025 Maybury IT
 */

namespace MitM2\MerchandiseReport\Plugin;

use Magento\Backend\App\Request\BackendValidator;
use Magento\Framework\App\RequestInterface;

/**
 * Class BackendValidatorPlugin
 *
 * Plugin to skip secret key validation for export controller
 * This controller implements CsrfAwareActionInterface for its own validation
 */
class BackendValidatorPlugin
{
    /**
     * Actions that should skip backend secret URL validation
     *
     * @var array
     */
    protected $exemptActions = [
        'mitm2_merchandisereport/report/exportcsv',
    ];

    /**
     * Skip validation for exempt actions
     *
     * @param BackendValidator $subject
     * @param \Closure $proceed
     * @param RequestInterface $request
     * @param \Magento\Framework\App\ActionInterface $action
     * @return void
     */
    public function aroundValidate(
        BackendValidator $subject,
        \Closure $proceed,
        RequestInterface $request,
        \Magento\Framework\App\ActionInterface $action
    ) {
        // Get the action path (e.g., "mitm2_merchandisereport/report/exportcsv")
        $actionPath = strtolower(
            $request->getModuleName() . '/' .
            $request->getControllerName() . '/' .
            $request->getActionName()
        );

        // Skip validation for exempt actions
        if (in_array($actionPath, $this->exemptActions)) {
            return; // Don't validate
        }

        // Call original validation for all other actions
        return $proceed($request, $action);
    }
}
