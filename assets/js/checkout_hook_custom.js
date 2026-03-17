jQuery(document).ready(function ($) {
    var OPT_CS_PAYPAL_SETTING_CHECKOUT = 'PAYPAL_CHECKOUT';

    if (window.addEventListener) {
        window.addEventListener("message", listenerPaypalCustom);
    } else {
        window.attachEvent("onmessage", listenerPaypalCustom);
    }
    
    if ($('#mecom-paypal-button-setting-custom').data('value') === OPT_CS_PAYPAL_SETTING_CHECKOUT && 
        ['product_page', 'carts_page'].includes($('#mecom-paypal-button-setting-context').data('value')) &&
        $('#mecom_express_paypal_current_proxy_url').data('value')) {
        fetch($('#mecom_express_paypal_current_proxy_url').data('value') + "/?rest_route=/cs/paypal-check-account-status").then(response => response.json())
            .then(res => {
                if (res.status !== 'active') {
                    $.ajax({url: '/?mecom-paypal-force-rotate-shield=1', method: 'POST'})
                }
            })
            .catch(err => {
                $.ajax({url: '/?mecom-paypal-force-rotate-shield=1', method: 'POST'})
            });
    }

    function listenerPaypalCustom(event) {
        if (event.data === "mecom-paypalOpenCreditForm-custom") {
            $('#payment-paypal-area-custom').attr('height', 400);
            if ($('#mecom-paypal-button-setting-context').data('value') === 'product_page') {
                window.mecom_paypal_custom_checkout_purchase_units = undefined;
                var hasAddonPass = true;
				if (isProductPageAndHasAddons()) {
					if (isProductAddonsValidated()) {
					} else {
					    hasAddonPass = false;
                    }
				}
				if (isProductPageAndHasAddons2()) {
					if (isProductAddonsValidated2()) {
					} else {
					    hasAddonPass = false;
                    }
				}
				if (isProductPageAndHasVariations3()) {
					if (isProductAddonsValidated3()) {
					} else {
					    hasAddonPass = false;
                    }
				}
				if (isProductPageAndHasAddons4()) {
					if (isProductAddonsValidated4()) {
					} else {
					    hasAddonPass = false;
                    }
				} 
                if(hasAddonPass) {
                    handleAddtoCartAndGetPurchaseUnits();
                } else {
                    resetCartAndGetPurchaseUnits();
				}
            } else {
                window.mecom_paypal_custom_checkout_purchase_units = undefined;
                var order_id = null;
                if($('#cs_pay_for_order_page').length) {
                    order_id = $('#cs_pay_for_order_page').data('value')
                }
                $.ajax({
                    url: '/',
                    method: 'POST',
                    data: {
                        'mecom-paypal-button-calculate-to-get-purchase-units': 1,
                        'order_id': order_id 
                    },
                    success: function (res) {
                        window.mecom_paypal_custom_checkout_purchase_units = JSON.parse(res)
                    }
                })
            }
        }
        if (event.data === "mecom-paypalCreateOrderFail") {
            $.ajax({url: '/?mecom-paypal-force-rotate-shield=1', method: 'POST'})
        }
        if (event.data === "mecom-paypalRequestFromBlacklist") {
            $('#payment-paypal-area-custom').remove();
            $('.cs_pp_element').remove();
        }
        if (event.data === "mecom-paypalOpenCreditFormReject-custom") {
            $('form.checkout').submit();
        }
        if (event.data === "mecom-paypalCloseCreditForm-custom") {
            $('#payment-paypal-area-custom').attr('height', 120);
        }
        if (event.data === "mecom-paypalMakeFullIframeCreditForm-custom") {
            $('#payment-paypal-area-custom').addClass('full_screen_iframe_paypal_checkout')
        }
        if (event.data === "mecom-paypalMakeIframeCreditFormNormal-custom") {
            $('#payment-paypal-area-custom').removeClass('full_screen_iframe_paypal_checkout')
        }
        if ((typeof event.data === 'object') && event.data.name === 'mecom-paypalBodyResizeCreditForm-custom') {
            if (event.data.value >= 20) {
                $('#payment-paypal-area-custom').attr('height',
                    event.data.value +
                    ($('#mecom-paypal-button-setting-context').data('value') === 'express_checkout_page' ? 40 : 20));
            }
        }
        if ((typeof event.data === 'object') && event.data.name === 'mecom-paypalOpenCreditFormError-custom') {
            $.ajax({
                url: '/?mecom-paypal-button-create-order=1',
                method: 'POST',
                data: {
                    'cs_order': event.data.value,
                    'current_proxy_id': $('#mecom_express_paypal_current_proxy_id').data('value'),
                    'current_proxy_url': $('#mecom_express_paypal_current_proxy_url').data('value'),
                }
            })
        }
        if ((typeof event.data === 'object') && event.data.name === 'mecom-paypalApprovedOrder-custom') {
            $('.blockUI').hide();
            $('#cs-pp-loader-credit-custom').show();
            setTimeout((function () {
                $('#cs-pp-loader-credit-custom').hide();
            }), 30000);
            var order_id = null;
            if($('#cs_pay_for_order_page').length) {
                order_id = $('#cs_pay_for_order_page').data('value')
            }
            $.ajax({
                url: '/',
                method: 'POST',
                data: {
                    'pp_order_id': event.data.value.order_id,
                    'order_id': order_id,
                    'mecom-paypal-button-create-woo-order': 1,
                    'current_proxy_id': $('#mecom_express_paypal_current_proxy_id').data('value'),
                    'current_proxy_url': $('#mecom_express_paypal_current_proxy_url').data('value')
                },
                success: function (res) {
                    res = JSON.parse(res)
                    if (res && res.result === 'success') {
                        window.location.replace(res.redirect)
                    } else {
                        checkout_error_wc(res.message)
                        $('#cs-pp-loader-credit-custom').hide();
                    }
                },
                error: function () {
                    checkout_error_wc('We cannot process your PayPal payment now, please try again with another method.')
                    $('#cs-pp-loader-credit-custom').hide();
                }
            })
        }
    }

    if ($('#mecom_enable_paypal_card_payment').length) {
        setInterval(function () {
            if ($('#mecom-paypal-button-setting-custom').data('value') === OPT_CS_PAYPAL_SETTING_CHECKOUT && $('#payment-paypal-area-custom')[0]) {
                var merchantSite = $('#mecom_merchant_site_url').data('value');
                if (merchantSite.endsWith("/")) {
                    merchantSite = merchantSite.slice(0, -1);
                }
                $('#payment-paypal-area-custom')[0].contentWindow.postMessage({
                    name: 'mecom-paypalSendOrderInfo-custom',
                    value: {
                        whitelist_obj: {
                            merchant_site: Sha1.hash(merchantSite),
                        },
                        merchant_token: $('#mecom_merchant_site_encode').data('value'),
                        purchase_units: window.mecom_paypal_custom_checkout_purchase_units,
                        orderIntent: $('#mecom-paypal-order-intent-custom').data('value'),
                        shipping_preference: $('#mecom_express_paypal_shipping_preference').data('value'),
                    }
                }, '*')
            }
            var isAddonPass = true;
            if (isProductPageAndHasVariations()) {
                if (getVariations().length) {
                } else {
                    isAddonPass = false;
                }
            }
			if (isProductPageAndHasAddons()) {
                if (isProductAddonsValidated()) {
                } else {
                    isAddonPass = false;
                }
            }
			if (isProductPageAndHasAddons2()) {
                if (isProductAddonsValidated2()) {
                } else {
                    isAddonPass = false;
                }
            }
			if (isProductPageAndHasAddons4()) {
                if (isProductAddonsValidated4()) {
                } else {
                    isAddonPass = false;
                }
            }
			if (isAddonPass) {
                $('#mecom-paypal-credit-form-container-custom').show();
            } else {
                $('#mecom-paypal-credit-form-container-custom').hide();
            }
        }, 100);
    }

    function getVariations() {
        var variations = []
        var dataSelections = $('.variations_form').find('[name^="attribute_"]');
        if (dataSelections) {
            dataSelections.each(function () {
                if ($(this).val() && $(this).val().toString().length) {
                    variations.push({
                        name: $(this).attr('name'),
                        value: $(this).val(),
                    })
                } else {
                    variations = [];
                    return false;
                }
            })
        }
        return variations;
    }
	
	function isProductAddonsValidated() {
        var isValidated = true;
        var dataSelections = $('.cart').find('.wc-pao-addon-field');
        if (dataSelections) {
            dataSelections.each(function () {
                if ($(this).val() && $(this).val().toString().length) {
                } else {
                    isValidated = false;
                    return false;
                }
            })
        }
        return isValidated;
    }

    function isProductPageAndHasVariations() {
        return $('#mecom-paypal-product-page-has-variations') &&
            $('#mecom-paypal-product-page-has-variations').data('value') === 'yes';
    }
	
	function isProductPageAndHasAddons() {
		return $('.cart').find('.wc-pao-addon-field').length;
	}
	
	function isProductPageAndHasAddons2() {
        var isHasAddons2 = false;
		var dataSelections = $('.cart').find('.wcpa_form_item');
        if (dataSelections) {
            dataSelections.each(function () {
                if ($(this).attr('id')) {
                  	var inputName = $(this).attr('id').replace(/\wcpa-/, '');
                    var inputEl = $(this).find('*[name="' + inputName + '"]');
                    if (inputEl) {
                        isHasAddons2 = true;
                    }
                }
            })
        }
        return isHasAddons2;
	}
        
    function isProductPageAndHasAddons4() {
		return $('.cart').find('.wcpa_field').length;
	}

    function isProductAddonsValidated4() {
        var isValidated = true;
        var dataSelections = $('.cart').find('.wcpa_type_radio-group');
        if (dataSelections) {
            dataSelections.each(function () {
                if ($(this).attr('id')) {
                    if ($(this).find('.wcpa_selected').length === 0) {
                        isValidated = false;
                        return false;
                    }
                }
            })
        } else {
            dataSelections = $('.cart').find('.wcpa_field');
            if (dataSelections) {
                dataSelections.each(function () {
                    if ($(this).prop('required')) {
                        if ($(this).attr('type') === 'radio') {
                            if ($(this).is(':checked') != true) {
                                isValidated = false;
                                return false;
                            }
                        } else {
                            if (!$(this).val() || !$(this).val().toString().length) {
                                isValidated = false;
                                return false;
                            }
                        }
                    }
                })
            }
        }
        return isValidated;
    }
    
    function isProductPageAndHasVariations3() {
		return $('.cart').find('.woo-variation-raw-select').length;
	}
    
	function isProductAddonsValidated2() {
        var isValidated = true;
        var dataSelections = $('.cart').find('.wcpa_form_item');
        if (dataSelections) {
            dataSelections.each(function () {
                if ($(this).attr('id')) {
                  	var inputName = $(this).attr('id').replace(/\wcpa-/, '');
                    var inputEl = $(this).find('*[name="' + inputName + '"]');
                    if (inputEl.attr('type') === 'radio') {
                        if(inputEl.is(':checked') != true) {
                           isValidated = false;
                           return false;
                        }
                    }
                    if(inputEl.is('select')) {
                        if (!inputEl.val() || !inputEl.val().toString().length) {
                            isValidated = false;
                            return false;
                        }
                    }  
                }
            })
        }
        return isValidated;
    }
    
    function isProductAddonsValidated3() {
        var isValidated = true;
        var dataSelections = $('.cart').find('.woo-variation-raw-select');
        if (dataSelections) {
            dataSelections.each(function () {
                if(!$(this).val() || $(this).val() === '') {
                    isValidated = false;
                }
            })
        }
        return isValidated;
    }

    function checkout_error_wc(msg) {
        var container = $('.woocommerce-notices-wrapper').first();
        if (container) {
            $('.woocommerce-error').remove();
            container.append('<ul class="woocommerce-error" role="alert">' +
                '<li>' +
                msg +
                '</li>' +
                '</ul>')
            $([document.documentElement, document.body]).animate({
                scrollTop: container.offset().top - 100
            }, 1000);
        }
    }
	
	function handleAddtoCartAndGetPurchaseUnits() {
		$.ajax({
			url: '/',
			method: 'POST',
			data: {
				'mecom-paypal-button-reset-carts': 1,
			},
			success: function (res) {
				$('.cart').append(
					$("<input type='hidden'>").attr( {
						name: $('.cart').find('button[type="submit"]').attr('name'), 
						value: $('.cart').find('button[type="submit"]').attr('value') })
				);
				$.ajax({
					url : $('.cart').attr('action') || window.location.pathname,
					type: $('.cart').attr('method'),
					data: $('.cart').serialize(),
					success: function (data) {
						$.ajax({
							url: '/',
							method: 'POST',
							data: {
								'mecom-paypal-button-calculate-to-get-purchase-units': 1,
							},
							success: function (res) {
								window.mecom_paypal_custom_checkout_purchase_units = JSON.parse(res)
							}
						})
					},
					error: function (jXHR, textStatus, errorThrown) {
						alert(errorThrown);
					}
				});
			}
		})
	}
    
    function resetCartAndGetPurchaseUnits() {
        $.ajax({
            url: '/',
            method: 'POST',
            data: {
                'mecom-paypal-button-reset-carts-and-get-purchase-units': 1,
                'product_id': $('#mecom-paypal-product-page-current-id').data('value'),
                'quantity': $('input[name="quantity"]').val(),
                'variations': getVariations()
            },
            success: function (res) {
                window.mecom_paypal_custom_checkout_purchase_units = JSON.parse(res)
            }
        })	
    }
});

