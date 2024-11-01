/*global jQuery:false */
/*global taxify_tax_exempt:false */
/*global taxify_tax_exempt_ajax_nonce_sec:false */
/* jshint strict: true */
jQuery(function ($) {
    'use strict';

    $('#taxify-tax-exempt').change(function () {
        var datachecked = {
            action: 'wc_taxify_apply_tax_exempt',
            checked: 'exempt',
            'security': taxify_tax_exempt.taxify_tax_exempt_ajax_nonce_sec
        }, dataunchecked = {
            action: 'wc_taxify_apply_tax_exempt',
            checked: '',
            'security': taxify_tax_exempt.taxify_tax_exempt_ajax_nonce_sec
        };
        if ($('#taxify-tax-exempt').is(':checked')) {
            $.post(taxify_tax_exempt.taxify_tax_exempt_ajax_url, datachecked, function () {
                $('body').trigger('update_checkout');
            });
        } else {
            $.post(taxify_tax_exempt.taxify_tax_exempt_ajax_url, dataunchecked, function () {
                $('body').trigger('update_checkout');
            });
        }
    }).change();
});