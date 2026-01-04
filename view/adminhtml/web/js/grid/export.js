/**
 * Custom Export CSV Button for MitM2_MerchandiseReport
 * Builds URL with filters as query params (GET) to pass to export controller.
 */
define([
    'Magento_Ui/js/grid/export',
    'uiRegistry'
], function (Export, registry) {
    'use strict';

    return Export.extend({
        defaults: {
            template: 'ui/grid/exportButton'
        },

        initialize: function () {
            this._super();
            return this;
        },

        applyOption: function (option) {
            if (!option || option.value === 'csv' || !option.value) {
                var filters = this.getFilters();
                var url = this.buildExportUrl(filters);
                console.log('Export URL:', url);
                window.location.href = url;
                return this;
            }

            return this._super(option);
        },

        getParams: function () {
            return {};
        },

        getFilters: function () {
            var provider = registry.get(this.provider);
            var filters = {};

            if (provider && provider.get('params') && provider.get('params').filters) {
                filters = provider.get('params').filters;
            }

            console.log('Captured filters:', filters);
            return filters;
        },

        buildExportUrl: function (filters) {
            var baseUrl = this.getBaseUrl();
            var params = [];


            // Handle date range filter (created_at)
            if (filters.created_at) {
                if (filters.created_at.from) {
                    params.push('filters[created_at][from]=' + encodeURIComponent(filters.created_at.from));
                }
                if (filters.created_at.to) {
                    params.push('filters[created_at][to]=' + encodeURIComponent(filters.created_at.to));
                }
            }

            // Handle SKUs filter
            if (filters.skus) {
                params.push('filters[skus]=' + encodeURIComponent(filters.skus));
            }

            // Add any other filters generically
            for (var key in filters) {
                if (!filters.hasOwnProperty(key)) {
                    continue;
                }
                if (key === 'created_at' || key === 'skus' || key === 'placeholder') {
                    continue;
                }
                if (typeof filters[key] === 'object') {
                    for (var subKey in filters[key]) {
                        if (filters[key].hasOwnProperty(subKey)) {
                            params.push('filters[' + key + '][' + subKey + ']=' + encodeURIComponent(filters[key][subKey]));
                        }
                    }
                } else {
                    params.push('filters[' + key + ']=' + encodeURIComponent(filters[key]));
                }
            }

            console.log('URL params:', params);
            return baseUrl + (params.length ? '?' + params.join('&') : '');
        },

        getBaseUrl: function () {
            // Preserve current admin URL and swap /report/index with /report/exportcsv
            var href = window.location.href.split('?')[0];
            var path = href.replace(/\/report\/index(\/|$)/, '/report/exportcsv$1');
            console.log('Base URL:', path);
            return path;
        }
    });
});
