# MitM2_MerchandiseReport

Magento 2 module for reporting on merchandise sales by SKU with comprehensive filtering and CSV export functionality.

## Features

- **Sales Report Grid** displaying:
  - SKU
  - Product Name
  - Total Sales (quantity)
  - Total Refunds (quantity)
  - Staff Member(s) who processed orders
  - First Sale Date
  - Last Sale Date

- **Advanced Filtering**:
  - Date Range filter (from/to)
  - SKUs filter (comma-separated, supports partial matching)
  - Staff Member filter (partial name matching)

- **CSV Export** with applied filters

## Installation

1. Upload the module to `app/code/MitM2/MerchandiseReport/`

2. **Enable the module and run setup:**
   ```bash
   php bin/magento module:enable MitM2_MerchandiseReport
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   ```

3. **Deploy static content (CRITICAL):**
   ```bash
   # Clear existing cache and generated files
   rm -rf var/cache/* var/page_cache/* var/view_preprocessed/* generated/code/* generated/metadata/*
   
   # Deploy static content for admin area
   php bin/magento setup:static-content:deploy -f en_GB en_US --area adminhtml
   
   # Clear and flush all caches
   php bin/magento cache:clean
   php bin/magento cache:flush
   ```

4. **Set proper permissions:**
   ```bash
   chmod -R 775 var/ pub/static/ pub/media/ generated/
   ```

5. **Configure admin permissions:**
   - Navigate to **System → User Roles**
   - Edit the appropriate role
   - Ensure **NESS Modules → Reports → Merchandise Items by Sales** is enabled

6. **Clear browser cache** and hard refresh (Ctrl+Shift+F5)

## Usage

1. Navigate to **NESS Modules → Reports → Merchandise Items by Sales**

2. Apply filters:
   - **Date Range**: Select start and end dates for order placement
   - **SKUs**: Enter one or more SKUs (comma-separated, e.g., `SKU-123, SKU-456`)
   - **Staff Member**: Filter by staff member name (partial matching)

3. Click **Apply Filters** to view results

4. Click **Export → CSV** to download filtered results

## Technical Notes

### Database Queries
The module queries the following tables:
- `sales_order` - Order data and date filtering
- `sales_order_item` - Item quantities and SKU information
- `catalog_product_entity_varchar` - Product names

Orders are filtered by status: `complete`, `processing`, or `closed`

### Staff Member Field
The module uses the `pos_staff_name` field from the `sales_order` table. Multiple staff members per SKU are displayed comma-separated.

### Export Functionality
The export uses a custom JavaScript component (`js/grid/export.js`) and BackendValidator plugin to bypass secret key validation while maintaining CSRF protection through `CsrfAwareActionInterface`.

### Dependencies
- `MitM2_Core` module (parent menu structure)
- Magento 2.x UI Component framework

## Troubleshooting

### Export button not appearing
1. Clear cache and deploy static content (see Installation step 3)
2. Clear browser cache (Ctrl+Shift+F5)
3. Log out and back into admin panel
4. Check browser console (F12) for JavaScript errors
5. Verify admin role has proper permissions

### No data showing in grid
- Ensure all three filters are applied (Date Range and SKUs are required)
- Check that orders exist in the date range with the specified SKUs
- Verify orders have status `complete`, `processing`, or `closed`

### Export downloads empty CSV
- Verify filters are applied before clicking export
- Check `var/log/system.log` for errors
- Ensure `di.xml` and `BackendValidatorPlugin.php` are properly deployed

## File Structure

```
MerchandiseReport/
├── Controller/Adminhtml/Report/
│   ├── Index.php                      # Grid page controller
│   └── ExportCsv.php                  # CSV export controller
├── Model/ResourceModel/Report/
│   └── Collection.php                 # Custom collection with SQL queries
├── Plugin/
│   └── BackendValidatorPlugin.php    # Bypass secret key validation for export
├── Ui/Component/
│   └── DataProvider.php               # Grid data provider
├── view/adminhtml/
│   ├── layout/
│   │   └── mitm2_merchandisereport_report_index.xml
│   ├── ui_component/
│   │   └── mitm2_merchandisereport_listing.xml
│   ├── web/js/grid/
│   │   └── export.js                  # Custom export component
│   └── requirejs-config.js
├── etc/
│   ├── acl.xml                        # Admin permissions
│   ├── di.xml                         # Dependency injection config
│   ├── module.xml                     # Module declaration
│   └── adminhtml/
│       ├── menu.xml                   # Admin menu entry
│       └── routes.xml                 # Admin routing
├── registration.php
└── README.md

```

## License

Copyright (c) 2026 Maybury IT

## Support

For issues or questions, contact Maybury IT.
