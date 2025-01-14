/*global jQuery:false */
/*global inlineEditPost */
/* jshint strict: true */
jQuery(function ($) {
    'use strict';

    $('#the-list').on('click', '.editinline', function () {

        inlineEditPost.revert();

        var post_id = $(this).closest('tr').attr('id'),
            new_post_id = post_id.replace('post-', ''),
            $wc_inline_data = $('#wc_taxify_inline_' + new_post_id),
            taxify_tax_class = $wc_inline_data.find('.taxify_tax_class').text();

        $('select[name="_taxify_tax_class"] option[value="' + taxify_tax_class + '"]', '.inline-edit-row').attr('selected', 'selected');
    });
});