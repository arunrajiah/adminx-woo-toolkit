/* AdminX WooCommerce Toolkit Admin JavaScript */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Initialize components
        AdminXWooToolkit.init();
    });
    
    var AdminXWooToolkit = {
        
        init: function() {
            this.bindEvents();
            this.initBulkEditor();
            this.initActivityLog();
        },
        
        bindEvents: function() {
            // Global AJAX error handling
            $(document).ajaxError(function(event, xhr, settings, error) {
                if (xhr.status !== 200) {
                    AdminXWooToolkit.showNotice('An error occurred: ' + error, 'error');
                }
            });
        },
        
        initBulkEditor: function() {
            var $bulkForm = $('#adminx-bulk-editor-form');
            if ($bulkForm.length === 0) return;
            
            // Handle bulk update form submission
            $bulkForm.on('submit', function(e) {
                e.preventDefault();
                AdminXWooToolkit.processBulkUpdate();
            });
            
            // Handle select all checkbox
            $('#select-all-products').on('change', function() {
                $('.product-checkbox').prop('checked', $(this).is(':checked'));
            });
            
            // Update select all when individual checkboxes change
            $(document).on('change', '.product-checkbox', function() {
                var totalCheckboxes = $('.product-checkbox').length;
                var checkedCheckboxes = $('.product-checkbox:checked').length;
                $('#select-all-products').prop('checked', totalCheckboxes === checkedCheckboxes);
            });
        },
        
        processBulkUpdate: function() {
            var selectedProducts = [];
            $('.product-checkbox:checked').each(function() {
                selectedProducts.push($(this).val());
            });
            
            if (selectedProducts.length === 0) {
                AdminXWooToolkit.showNotice('Please select at least one product.', 'error');
                return;
            }
            
            if (!confirm(adminx_bulk_editor.strings.confirm_update)) {
                return;
            }
            
            var updateData = AdminXWooToolkit.getUpdateData();
            
            AdminXWooToolkit.showLoading(true);
            
            $.ajax({
                url: adminx_bulk_editor.ajax_url,
                type: 'POST',
                data: {
                    action: 'adminx_bulk_update_products',
                    nonce: adminx_bulk_editor.nonce,
                    product_ids: selectedProducts,
                    update_data: updateData
                },
                success: function(response) {
                    AdminXWooToolkit.showLoading(false);
                    
                    if (response.success) {
                        var message = 'Updated ' + response.data.updated_count + ' of ' + response.data.total_count + ' products.';
                        AdminXWooToolkit.showNotice(message, 'success');
                        
                        if (response.data.errors && response.data.errors.length > 0) {
                            AdminXWooToolkit.showNotice('Some errors occurred: ' + response.data.errors.join(', '), 'error');
                        }
                        
                        // Reload the page to show updated data
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        AdminXWooToolkit.showNotice(response.data || adminx_bulk_editor.strings.error, 'error');
                    }
                },
                error: function() {
                    AdminXWooToolkit.showLoading(false);
                    AdminXWooToolkit.showNotice(adminx_bulk_editor.strings.error, 'error');
                }
            });
        },
        
        getUpdateData: function() {
            var data = {};
            
            // Price updates
            var priceValue = $('#bulk-price').val();
            var priceAction = $('#bulk-price-action').val();
            if (priceValue && priceAction) {
                data.price = priceValue;
                data.price_action = priceAction;
            }
            
            // Stock updates
            var stockValue = $('#bulk-stock').val();
            var stockAction = $('#bulk-stock-action').val();
            if (stockValue && stockAction) {
                data.stock = stockValue;
                data.stock_action = stockAction;
            }
            
            // Stock status
            var stockStatus = $('#bulk-stock-status').val();
            if (stockStatus) {
                data.stock_status = stockStatus;
            }
            
            // Categories
            var categories = $('#bulk-categories').val();
            var categoryAction = $('#bulk-category-action').val();
            if (categories && categories.length > 0 && categoryAction) {
                data.categories = categories;
                data.category_action = categoryAction;
            }
            
            // Tags
            var tags = $('#bulk-tags').val();
            var tagAction = $('#bulk-tag-action').val();
            if (tags && tags.length > 0 && tagAction) {
                data.tags = tags;
                data.tag_action = tagAction;
            }
            
            return data;
        },
        
        initActivityLog: function() {
            var $logFilters = $('#adminx-log-filters');
            if ($logFilters.length === 0) return;
            
            // Handle filter form submission
            $logFilters.on('submit', function(e) {
                e.preventDefault();
                AdminXWooToolkit.filterActivityLog();
            });
            
            // Handle export button
            $('#export-activity-log').on('click', function(e) {
                e.preventDefault();
                AdminXWooToolkit.exportActivityLog();
            });
        },
        
        filterActivityLog: function() {
            var filters = {
                action: $('#filter-action').val(),
                object_type: $('#filter-object-type').val(),
                user_id: $('#filter-user').val(),
                date_from: $('#filter-date-from').val(),
                date_to: $('#filter-date-to').val()
            };
            
            // Build query string
            var queryString = $.param(filters);
            
            // Reload page with filters
            window.location.href = window.location.pathname + '?' + queryString;
        },
        
        exportActivityLog: function() {
            var filters = {
                action: $('#filter-action').val(),
                object_type: $('#filter-object-type').val(),
                user_id: $('#filter-user').val(),
                date_from: $('#filter-date-from').val(),
                date_to: $('#filter-date-to').val()
            };
            
            // Create form and submit
            var $form = $('<form>', {
                method: 'POST',
                action: window.location.href
            });
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'export_logs',
                value: '1'
            }));
            
            $.each(filters, function(key, value) {
                if (value) {
                    $form.append($('<input>', {
                        type: 'hidden',
                        name: key,
                        value: value
                    }));
                }
            });
            
            $('body').append($form);
            $form.submit();
            $form.remove();
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            var $notice = $('<div>', {
                class: 'adminx-notice ' + type,
                html: '<p>' + message + '</p>'
            });
            
            $('.adminx-woo-toolkit').prepend($notice);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },
        
        showLoading: function(show) {
            if (show) {
                $('.adminx-woo-toolkit').addClass('adminx-loading');
                $('body').append('<div class="adminx-loading-overlay"><div class="adminx-spinner"></div></div>');
            } else {
                $('.adminx-woo-toolkit').removeClass('adminx-loading');
                $('.adminx-loading-overlay').remove();
            }
        }
    };
    
    // Make AdminXWooToolkit globally available
    window.AdminXWooToolkit = AdminXWooToolkit;
    
})(jQuery);