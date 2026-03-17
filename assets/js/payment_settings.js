jQuery(document).ready(function ($) {
    csPaypalProductTitleSwitch($('#woocommerce_mecom_paypal_product_title_setting').val());
    $('#woocommerce_mecom_paypal_product_title_setting').on('change', function () {
        csPaypalProductTitleSwitch($(this).val());
    })

    function csPaypalProductTitleSwitch(type) {
        if (type === 'user_define') {
            $('#woocommerce_mecom_paypal_user_define_product_title').closest('tr').show();
            $('#woocommerce_mecom_paypal_random_product_title_list').closest('tr').show();
        } else {
            $('#woocommerce_mecom_paypal_user_define_product_title').closest('tr').hide();
            $('#woocommerce_mecom_paypal_random_product_title_list').closest('tr').hide();
        }
    }
    $('#woocommerce_mecom_paypal_sslverify').closest('tr').hide();
    $('#woocommerce_mecom_paypal_custom_card_icon_css').closest('tr').hide();
    $('#pp_advance_setting_toggle').click(function () {
        $('#woocommerce_mecom_paypal_sslverify').closest('tr').toggle();
        $('#woocommerce_mecom_paypal_custom_card_icon_css').closest('tr').toggle();
    })
})
