jQuery(document).ready(function ($) {
    function showAlert(title, text, type) {
        return Swal.fire({
            title: title,
            text: text,
            type: type,
            confirmButtonText: 'OK'
        })
    }

    function showError(text) {
        return showAlert("Error", text, "error");
    }

    function showConfirm(message) {
        return Swal.fire({
            title: 'Are you sure?',
            html: message,
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes!'
        });
    }

    function showSuccess(message) {
        return Swal.fire(
            'Success!',
            message,
            'success'
        )
    }


    function revertPreviousRotationMethod(selectedRotationMethod) {
        if (selectedRotationMethod === 'by_time') {
            $('#rotationByAmount').prop("checked", true);
        } else if (selectedRotationMethod === 'by_amount') {
            $('#rotationByTime').prop("checked", true);
        }
    }

    function addProxy() {
        var rotationMethod = $('input[name="rotationMethod"]:checked').val();
        var newProxyUrl = $('#new-proxy-url').val().replace(/\/+$/, "");
        var newRotationValue = $('#new-rotation-value').val();

        if (!newProxyUrl.trim() || !newRotationValue.trim()) {
            showError('Please fill in all required field!');
            return;
        }

        var data = {
            'action': 'mecom_gateway_paypal_action',
            'command': 'addNewProxy',
            'rotationMethod': rotationMethod,
            'proxyUrl': newProxyUrl,
            'rotationValue': newRotationValue
        };


        // We can also pass the url value separately from ajax url for front end AJAX implementations
        jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
            var dataJson = JSON.parse(response);
            if (!dataJson.success) {
                showError('Failed to add proxy. Please try again!');
                return;
            }

            $('#new-proxy-url').val('');
            $('#new-rotation-value').val('');

            $('.table-proxy > tbody').append(`
                 <tr>
                    <td>
                        <input type="checkbox" class="form-control proxy-id" value="${dataJson.addedProxy.id}">
                    </td>
                    <td>
                        <input type="text" class="form-control proxy-url" value="${newProxyUrl}">
                    </td>
                    <td>
                        <input type="number" class="form-control proxy-rotation-value" value="${newRotationValue}">
                    </td>
                    <td></td>
                </tr>
            `);

            showSuccess('Add proxy successfully!').then(function () {
                location.reload();
            });
        });
    }

    function saveProxies() {
        var proxies = [];
        var hasError = false;
        var rotationMethod = $('input[name="rotationMethod"]:checked').val();
        var rotationMethodName = rotationMethod === 'by_time' ? 'Time' : 'Amount';
        $('.table-proxy tr.proxy').each(function () {
            var proxy = {
                id: $(this).find('.proxy-id').val(),
                url: $(this).find('.proxy-url').val(),
                rotationValue: $(this).find('.proxy-rotation-value').val(),
            };
            if (!proxy.url.trim() || !proxy.rotationValue.trim()) {
                showError('Please fill in all required field!');
                hasError = true;
                return;
            }
            if (proxy.rotationValue <= 0) {
                showError(rotationMethodName + ' must be greater than 0!');
                hasError = true;
                return;
            }
            proxies.push(proxy);
        })
        if (hasError) {
            return;
        }

        var data = {
            'action': 'mecom_gateway_paypal_action',
            'command': 'saveProxies',
            'rotationMethod': rotationMethod,
            'proxies': proxies
        };
        jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
            showSuccess('Save proxies success!').then(function () {
                location.reload();
            });
        });
    }

    function forceActive() {
        var selectedIds = $('.table-proxy').find('.proxy-id:checked').map(function(){
            return this.value;
        }).get();

        if (selectedIds.length !== 1 ) {
            showError('Please select one proxy to activate!');
            return;
        }
        if ($('.table-proxy').find('.proxy-id:checked').closest('tr').hasClass('activated-proxy')) {
            showError('Already activated proxy');
            return;
        }
        showConfirm("The new proxy will be activated and use as main Payment method!").then(function (result) {
            if (!result.value) {
                return;
            }
            var rotationMethod = $('input[name="rotationMethod"]:checked').val();
            var data = {
                'action': 'mecom_gateway_paypal_action',
                'command': 'activateProxy',
                'rotationMethod': rotationMethod,
                'proxyID': selectedIds[0]
            };
            jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
                var responseJson = JSON.parse(response);
                if (responseJson.success) {
                    $('tr.activated-proxy').removeClass('activated-proxy');
                    $('.table-proxy').find('.proxy-id:checked').closest('tr').addClass('activated-proxy');
                }
            });
        });

    }

    function moveToUnused() {
        var selectedIds = $('.table-proxy').find('.proxy-id:checked').map(function(){
            return this.value;
        }).get();

        if (selectedIds.length <= 0 ) {
            showError('Please select at least one proxy!');
            return;
        }

        showConfirm("Selected proxy will be moved to Unused list!").then(function (result) {
            if (!result.value) {
                return;
            }
            var rotationMethod = $('input[name="rotationMethod"]:checked').val();
            var data = {
                'action': 'mecom_gateway_paypal_action',
                'command': 'moveToUnusedProxies',
                'rotationMethod': rotationMethod,
                'proxyIds': selectedIds
            };
            jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
                var responseJson = JSON.parse(response);
                if (responseJson.success) {
                    $('.table-proxy').find('.proxy-id:checked').each(function () {
                       $(this).closest('tr').appendTo('.table-unused > tbody');
                    });
                    location.reload();
                } else {
                    showError(responseJson.error).then(function () {
                        location.reload();
                    });
                }
            });
        });
    }



    $('input[type=radio][name=rotationMethod]').change(function () {
        var rotationMethod = this.value;
        var methodName = rotationMethod === 'by_time' ? 'time' : 'amount';
        showConfirm(`Proxy will be rotated by <b>${methodName}</b>.`).then((result) => {
            if (result.value) {
                var data = {
                    'action': 'mecom_gateway_paypal_action',
                    'command': 'changeRotationMethod',
                    'rotationMethod': rotationMethod
                };
                jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
                    var responseJson = JSON.parse(response);
                    if (responseJson.success === true) {
                        // toggleRotationMethod(rotationMethod);
                        // replaceProxyList(responseJson.proxies);
                        return showSuccess(`Rotation method changed to <b>${methodName}</b>!`).then(function () {
                            location.reload();
                        })
                    } else {
                        showError('Failed to change rotation method. Please try again!');
                        // Revert previous value
                        revertPreviousRotationMethod(rotationMethod);
                    }
                });

            } else {
                // Revert previous value
                revertPreviousRotationMethod(rotationMethod);
            }
        });
    });

    function deleteProxy() {

        var selectedIds = $('.table-unused').find('.proxy-id:checked').map(function(){
            return this.value;
        }).get();

        if (selectedIds.length <= 0 ) {
            showError('Please select at least one proxy!');
            return;
        }

        showConfirm('Selected proxy will be deleted!').then(function (result) {
            if (!result.value) {
                return;
            }
            var data = {
                'action': 'mecom_gateway_paypal_action',
                'command': 'deleteProxy',
                'deleteProxyIds': selectedIds
            };
            jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
                var responseJson = JSON.parse(response);
                if (responseJson.success === true) {
                    $('.table-unused').find('.proxy-id:checked').closest('tr').remove();
                    showSuccess('Selected proxies has been deleted successfully!');
                } else {
                    showError('Failed to delete selected proxies!');
                }
            });
        })

    }

    function moveBackProxy() {
        var selectedIds = $('.table-unused').find('.proxy-id:checked').map(function(){
            return this.value;
        }).get();

        if (selectedIds.length <= 0 ) {
            showError('Please select at least one proxy!');
            return;
        }

        var data = {
            'action': 'mecom_gateway_paypal_action',
            'command': 'moveBackProxies',
            'moveBackProxyIds': selectedIds
        };
        jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
            var responseJson = JSON.parse(response);
            if (responseJson.success === true) {
                $('.table-unused').find('.proxy-id:checked').closest('tr').appendTo('.table-proxy > tbody');
                showSuccess('Selected proxies has been moved back successfully!').then(function (){
                    location.reload();
                });
            } else {
                showError('Failed to move back selected proxies!');
            }

        });

    }

    function toggleSyncTrackingLoading(isOn) {
        $('#sync-spinner').css("display", isOn ? "inline-block" : "none");
        $('#sync-tracking-info-btn').attr("disabled", isOn);
    }

    function syncTrackingInfo() {

        var syncCount = $('#sync-count').val();
        if (parseInt(syncCount) === 0) {
            showError("Don't have unsynced orders");
            return;
        }
        toggleSyncTrackingLoading(true);

        var data = {
            'action': 'mecom_gateway_paypal_action',
            'command': 'syncTrackingInfo',
        };
        jQuery.ajaxSetup({timeout: 100000});
        jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
            var responseJson = JSON.parse(response);
            if (responseJson.success) {
                showSuccess('Sync tracking info successfully!').then(function () {
                    location.reload();
                });
            } else {
                showError(responseJson.error);
            }
        }).fail(function () {
            showError('Error when sync tracking info. Please try again after!');
        }).always(function () {
            toggleSyncTrackingLoading(false);
        });
    }
    
    function saveEndpointSettings() {
        var data = {
            'action': 'mecom_gateway_paypal_action',
            'command': 'saveEndpointSettings',
            'endpointToken': $('input[name="endpointToken"]').val(),
            'endpointSecret': $('input[name="endpointSecret"]').val(),
        };
        jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
            var responseJson = JSON.parse(response);
            if (responseJson.success === true) {
                showSuccess('Save endpoint settings successfully!');
            } else {
                showError('Save endpoint settings failed!');
            }
        });
    }

    $(document).on('click', '#btn-add-proxy', function () {
        addProxy();
    });

    $(document).on('click', '#btn-save', function () {
        saveProxies();
    });

    $(document).on('click', '#btn-force-active', function () {
        forceActive();
    });

    $(document).on('click', '#btn-move-unused', function () {
        moveToUnused();
    });

    $(document).on('click', '#btn-delete', function () {
        deleteProxy();
    });

    $(document).on('click', '#btn-move-back', function () {
        moveBackProxy();
    });

    $(document).on('click', '#sync-tracking-info-btn', function () {
        syncTrackingInfo();
    });
    
    $(document).on('click', '#btn-save-endpoint-settings', function () {
        saveEndpointSettings();
    });
    
    $(document).on('click', '#btn-save-endpoint-cancel', function () {
        window.location.reload();
    });
    var $currentConnectionMode = $('input[type=radio][name=connectionMode]:checked').val();
    if ($currentConnectionMode == 'endpoint_token') {
         jQuery.get('/?pp-get-endpoint-remaining-amount', [], function (response) {
            var responseJson = JSON.parse(response);
            if (responseJson.value) {
                $('#paypal-ep-amount-remain').html(responseJson.value)
            }
        });
    }
    $('input[type=radio][name=connectionMode]').click(function(e) {
        e.preventDefault();
        var radioEl = $(this)
        var connectionMode = this.value;
        if ($currentConnectionMode === connectionMode) {
            return false;
        }
        if (connectionMode === 'shield_domains') {
            var msgConfirmDialog = "Connection mode shield domains will be activated!";
        } else {
            var msgConfirmDialog = "Connection mode endpoint token will be activated!";
        }
        return showConfirm(msgConfirmDialog).then(function (result) {
            if (!result.value) {
                return false;
            }
            var data = {
                'action': 'mecom_gateway_paypal_action',
                'command': 'changeConnectionMode',
                'connectionMode': connectionMode,
            };
            jQuery.post(cs_ajax_object.ajax_url, data, function (response) {
                var responseJson = JSON.parse(response);
                if (responseJson.success) {
                    radioEl.prop('checked', true);
                    $currentConnectionMode = connectionMode;
                    if (connectionMode === 'shield_domains') {
                        $('#connection_mode_shield_domains_area').show();
                        $('#connection_mode_endpoint_token_area').hide();
                        showSuccess("Connection mode shield domains activated!");
                    } else {
                        $('#connection_mode_endpoint_token_area').show();
                        $('#connection_mode_shield_domains_area').hide();
                        showSuccess("Connection mode endpoint token activated!");
                        jQuery.get('/?pp-get-endpoint-remaining-amount', [], function (response) {
                            var responseJson = JSON.parse(response);
                            if (responseJson.value) {
                                $('#paypal-ep-amount-remain').html(responseJson.value)
                            }
                        });
                    }
                } else {
                    showError('Change connection mode failed!');
                }
            });
        });
    });
})
