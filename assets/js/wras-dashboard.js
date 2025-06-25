(function($) {
    'use strict';

    $(document).ready(function() {

        // Function to calculate and update prices for a product row
        function updatePriceDisplay(row) {
            const discountedBasePrice = parseFloat(row.data('discounted-base-price'));
            if (isNaN(discountedBasePrice)) {
                // console.error('Invalid discounted base price for product ID:', row.data('product-id'));
                return;
            }

            const additionalProfitInput = row.find('.wras-additional-profit');
            const resellerDiscountPercentInput = row.find('.wras-reseller-discount-percent');

            let additionalProfit = parseFloat(additionalProfitInput.val());
            let resellerDiscountPercent = parseFloat(resellerDiscountPercentInput.val());

            // Validate inputs and provide defaults
            if (isNaN(additionalProfit) || additionalProfit < 0) {
                additionalProfit = 0;
            }
            if (additionalProfit > 800) {
                additionalProfit = 800; // Cap at 800
                additionalProfitInput.val(800);
            }


            if (isNaN(resellerDiscountPercent) || resellerDiscountPercent < 0) {
                resellerDiscountPercent = 0;
            }
            if (resellerDiscountPercent > 100) {
                resellerDiscountPercent = 100; // Cap at 100
                resellerDiscountPercentInput.val(100);
            }

            // Calculate reseller's discount amount from the discounted base price
            const resellerDiscountAmount = (discountedBasePrice * resellerDiscountPercent) / 100;

            // Adjusted profit for the reseller
            // This is their "Additional Profit" minus the discount they are offering to the customer
            const adjustedProfit = additionalProfit - resellerDiscountAmount;

            // Customer final price
            // This is the discounted base price + reseller's additional profit - reseller's discount for customer
            // Or, more simply: discounted_base_price + adjusted_profit
            let customerFinalPrice = discountedBasePrice + adjustedProfit;

            if (customerFinalPrice < 0) {
                customerFinalPrice = 0; // Price cannot be negative
                // Potentially alert the reseller or adjust inputs?
                // For now, just cap at 0. The adjusted profit might be negative in this case.
            }

            // Update display fields
            row.find('.wras-adjusted-profit-display').text(formatPrice(adjustedProfit));
            row.find('.wras-customer-final-price-display').text(formatPrice(customerFinalPrice));
        }

        // Helper function to format price (basic, replace with WooCommerce's if available via JS)
        function formatPrice(price) {
            // This is a simplified version. Ideally, use WC's formatting if passed via wp_localize_script
            // For now, assuming currency symbol is handled by server-side `wc_price` initially
            // and JS updates just the number part or a simple prefix.
            // Let's assume wras_dashboard_params.currency_symbol is available.
            const symbol = (typeof wras_dashboard_params !== 'undefined' && wras_dashboard_params.currency_symbol) ? wras_dashboard_params.currency_symbol : '';
            const decimals = (typeof wras_dashboard_params !== 'undefined' && wras_dashboard_params.price_decimals) ? parseInt(wras_dashboard_params.price_decimals) : 2;

            // Handle potential negative adjusted profit for display
            const isNegative = price < 0;
            const absolutePrice = Math.abs(price);
            let formatted = symbol + absolutePrice.toFixed(decimals);
            if (isNegative) {
                formatted = '-' + formatted;
            }
            return formatted;
        }

        // Event listener for input changes
        $('.wras-product-list-table').on('input change', '.wras-additional-profit, .wras-reseller-discount-percent', function() {
            const row = $(this).closest('.wras-product-row');
            updatePriceDisplay(row);
        });

        // Initialize prices on page load for all rows
        $('.wras-product-row').each(function() {
            updatePriceDisplay($(this));
        });

        // Handle "Generate Link" button click
        $('.wras-product-list-table').on('click', '.wras-generate-link-button', function() {
            const button = $(this);
            const row = button.closest('.wras-product-row');
            const productId = button.data('product-id');
            const additionalProfit = parseFloat(row.find('.wras-additional-profit').val());
            const resellerDiscountPercent = parseFloat(row.find('.wras-reseller-discount-percent').val());

            const linkDisplay = row.find('.wras-generated-link-display');
            const urlInput = row.find('.wras-affiliate-url');
            const spinner = row.find('.wras-link-generation-spinner');
            const errorDiv = row.find('.wras-link-generation-error');

            // Validate before sending
            if (isNaN(additionalProfit) || additionalProfit < 0 || additionalProfit > 800) {
                errorDiv.text(wras_dashboard_params.i18n_invalid_profit_val).show();
                linkDisplay.hide();
                return;
            }
            if (isNaN(resellerDiscountPercent) || resellerDiscountPercent < 0 || resellerDiscountPercent > 100) {
                errorDiv.text(wras_dashboard_params.i18n_invalid_discount_val).show();
                linkDisplay.hide();
                return;
            }

            spinner.text(wras_dashboard_params.i18n_generating_link).show(); // Use localized string for spinner
            button.prop('disabled', true);
            errorDiv.hide().empty();
            linkDisplay.hide();

            $.ajax({
                url: wras_dashboard_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'wras_generate_affiliate_link',
                    nonce: wras_dashboard_params.nonce,
                    product_id: productId,
                    additional_profit: additionalProfit,
                    reseller_discount_percent: resellerDiscountPercent
                },
                success: function(response) {
                    if (response.success) {
                        urlInput.val(response.data.url);
                        linkDisplay.show();
                        errorDiv.hide().empty();
                    } else {
                        errorDiv.text(response.data.message || wras_dashboard_params.i18n_error_generating_link).show();
                        linkDisplay.hide();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    // console.error('AJAX error:', textStatus, errorThrown);
                    errorDiv.text(wras_dashboard_params.i18n_ajax_error + ' ' + textStatus).show();
                    linkDisplay.hide();
                },
                complete: function() {
                    spinner.hide();
                    button.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);
