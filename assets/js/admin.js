jQuery(document).ready(function ($) {

    if (typeof pathao_vars === 'undefined') {
        console.error('pathao_vars is missing');
        return;
    }

    const AJAX_URL = pathao_vars.ajax_url;
    const NONCE = pathao_vars.nonce;

    /* ---------------------------------------------------
     * Helpers
     * --------------------------------------------------- */
    function ajaxPost(action, data, cb) {
        $.post(
            AJAX_URL,
            {
                action: action,
                nonce: NONCE,
                ...data,
            },
            cb
        );
    }

    /* ---------------------------------------------------
     * Pathao Setup: Generate Token (Sandbox / Live)
     * --------------------------------------------------- */
    $('#pathao-setup').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $spinner = $('.pathao-setup-spinner');
        const $notice = $('.pathao-notice');

        $notice.empty();
        $spinner.addClass('is-active');

        const payload = {
            _wp_setup_nonce: $form.find('input[name="_wp_setup_nonce"]').val(),
            client_id: $('#pathao_client_id').val(),
            client_secret: $('#pathao_client_secret').val(),
            username: $('#pathao_client_username').val(),
            password: $('#pathao_client_password').val(),
            sandbox_mode: $('#pathao_sandbox_mode').is(':checked') ? 1 : 0,
        };

        $.post(
            AJAX_URL,
            {
                action: 'pathao_setup_generate_token',
                ...payload,
            },
            function (res) {
                $spinner.removeClass('is-active');

                if (!res || !res.success) {
                    const msg = res && res.data && res.data.message ? res.data.message : 'Failed to generate token';
                    $notice.html('<div class="notice notice-error"><p>' + msg + '</p></div>');
                    return;
                }

                const data = res.data || {};

                if (data.access_token) {
                    $('#pathao_access_token').val(data.access_token);
                }
                if (data.refresh_token) {
                    $('#pathao_refresh_token').val(data.refresh_token);
                }

                $notice.html('<div class="notice notice-success"><p>' + (data.message || 'Token generated successfully') + '</p></div>');
            }
        );
    });

    /* ---------------------------------------------------
     * City → Zone
     * --------------------------------------------------- */
    $(document).on('change', '#ptc-city', function () {
        const cityId = $(this).val();
        if (!cityId) return;

        $('#ptc-zone').html('<option>Loading…</option>');
        $('#ptc-area').html('<option value="">Select Area</option>');

        ajaxPost('get_city_zones', { city: cityId }, function (res) {
            const $zone = $('#ptc-zone');
            $zone.empty().append('<option value="">Select Zone</option>');

            if (res.zones) {
                res.zones.forEach((z) => {
                    $zone.append(`<option value="${z.id}">${z.name}</option>`);
                });
            }
        });
    });

    /* ---------------------------------------------------
     * Zone → Area
     * --------------------------------------------------- */
    $(document).on('change', '#ptc-zone', function () {
        const zoneId = $(this).val();
        if (!zoneId) return;

        $('#ptc-area').html('<option>Loading…</option>');

        ajaxPost('get_zone_areas', { zone: zoneId }, function (res) {
            const $area = $('#ptc-area');
            $area.empty().append('<option value="">Select Area</option>');

            if (res.areas) {
                res.areas.forEach((a) => {
                    $area.append(`<option value="${a.id}">${a.name}</option>`);
                });
            }
        });
    });

    /* ---------------------------------------------------
     * Price Calculation
     * --------------------------------------------------- */
    function calculatePathaoPrice() {
        const city = $('#ptc-city').val();
        const zone = $('#ptc-zone').val();
        const weight = $('#ptc-weight').val() || 0.5;
        const deliveryType = $('#ptc-delivery-type').val() || 48;
        const itemType = $('#ptc-item-type').val() || 2;

        if (!city || !zone) return;

        $('#ptc-collectable').val('Calculating…');
        ajaxPost(
            'pathao_price_calculation',
            {
                recipient_city: city,
                recipient_zone: zone,
                item_weight: weight,
                delivery_type: deliveryType,
                item_type: itemType,
            },
            function (res) {
                if (res.success && res.data) {
                    if (payment_status === 'Unpaid') {
                        $('#ptc-collectable').val(order_total);
                    } else {
                        $('#ptc-collectable').val(0);
                    }

                } else {
                    $('#ptc-collectable').val('—');
                }
            }
        );
    }

    $(document).on('change', '#ptc-city, #ptc-zone, #ptc-weight, #ptc-delivery-type, #ptc-item-type', calculatePathaoPrice);

    /* ---------------------------------------------------
     * Bulk Order Send - Open Modal
     * --------------------------------------------------- */
    $(document).on('click', '#pathao-bulk-send', function (e) {
        e.preventDefault();

        const orderIds = [];
        $('.pathao-order-checkbox:checked').each(function () {
            orderIds.push($(this).val());
        });

        if (!orderIds.length) {
            alert('Please select at least one order');
            return;
        }

        // Open modal and show loading
        $('#pathao-bulk-modal').fadeIn();
        $('#pathao-bulk-rows').html('<tr><td colspan="10" style="text-align:center;padding:20px;">Loading orders...</td></tr>');

        // Load order data from server
        $.post(AJAX_URL, {
            action: 'get_bulk_orders_data',
            nonce: NONCE,
            order_ids: orderIds
        }, function (res) {
            if (!res || !res.success) {
                $('#pathao-bulk-rows').html('<tr><td colspan="10" style="text-align:center;padding:20px;color:red;">Error: ' + (res?.data?.message || 'Failed to load orders') + '</td></tr>');
                return;
            }

            $('#pathao-bulk-rows').html(res.data.html);

            // Load cities for all order rows
            loadCitiesForBulkOrders();

            // Initialize city/zone/area change handlers for bulk orders
            initializeBulkOrderDropdowns();
        }).fail(function() {
            $('#pathao-bulk-rows').html('<tr><td colspan="10" style="text-align:center;padding:20px;color:red;">Network error. Please try again.</td></tr>');
        });
    });

    /* ---------------------------------------------------
     * Bulk Order Form Submit
     * --------------------------------------------------- */
    $('#pathao-bulk-form').on('submit', function (e) {
        e.preventDefault();

        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const originalText = $submitBtn.text();

        // Disable submit button
        $submitBtn.prop('disabled', true).text('Sending...');

        // Serialize form data - WordPress expects orders[ORDER_ID][field_name] format
        const formData = $form.serialize();

        $.post(AJAX_URL, {
            action: 'send_bulk_orders_to_pathao',
            nonce: NONCE,
            form_data: formData  // Send as form_data, backend will parse it
        }, function (res) {
            $submitBtn.prop('disabled', false).text(originalText);

            if (!res || !res.success) {
                alert('Error: ' + (res?.data?.message || 'Bulk send failed'));
                return;
            }

            // Build summary message
            let msg = '';
            const data = res.data || {};

            if (data.sent && data.sent.length > 0) {
                msg += '✅ Sent: #' + data.sent.join(', #') + '\n\n';
            }

            if (data.skipped && data.skipped.length > 0) {
                msg += '⏭️  Skipped (already sent): #' + data.skipped.join(', #') + '\n\n';
            }

            if (data.failed && Object.keys(data.failed).length > 0) {
                msg += '❌ Failed:\n';
                for (const orderId in data.failed) {
                    msg += 'Order #' + orderId + ': ' + data.failed[orderId] + '\n';
                }
            }

            alert(msg || 'No orders processed');
            $('#pathao-bulk-modal').fadeOut();
            location.reload();
        }).fail(function() {
            $submitBtn.prop('disabled', false).text(originalText);
            alert('Network error. Please try again.');
        });
    });

    /* ---------------------------------------------------
     * Close Bulk Modal
     * --------------------------------------------------- */
    $(document).on('click', '#pathao-bulk-modal .pathao-bulk-close, #pathao-bulk-modal .pathao-bulk-overlay', function (e) {
        if ($(e.target).hasClass('pathao-bulk-overlay') || $(e.target).hasClass('pathao-bulk-close')) {
            $('#pathao-bulk-modal').fadeOut();
        }
    });

    /* ---------------------------------------------------
     * Load Cities for Bulk Orders
     * --------------------------------------------------- */
    function loadCitiesForBulkOrders() {
        $.post(AJAX_URL, {
            action: 'get_cities',
            nonce: NONCE
        }, function (res) {
            if (res && res.success && res.data && res.data.cities && res.data.cities.length > 0) {
                $('.pathao-bulk-city').each(function() {
                    const $select = $(this);
                    const selectedCity = $select.data('selected-city');
                    $select.empty().append('<option value="">Select City</option>');
                    res.data.cities.forEach(function(city) {
                        $select.append('<option value="' + city.id + '" ' + (selectedCity == city.id ? 'selected' : '') + '>' + city.name + '</option>');
                    });
                    // Trigger change if city was pre-selected
                    if (selectedCity) {
                        $select.trigger('change');
                    }
                });
            } else {
                const errorMsg = (res && res.data && res.data.message) ? res.data.message : 'Failed to load cities';
                console.error('Pathao Cities Error:', errorMsg, res);
                $('.pathao-bulk-city').html('<option value="">Error: ' + errorMsg + '</option>');
            }
        }).fail(function(xhr, status, error) {
            console.error('AJAX error loading cities:', xhr, status, error);
            $('.pathao-bulk-city').html('<option value="">Error loading cities</option>');
        });
    }

    /* ---------------------------------------------------
     * Initialize City/Zone/Area Dropdowns for Bulk Orders
     * --------------------------------------------------- */
    function initializeBulkOrderDropdowns() {
        // City change -> Load zones
        $(document).off('change', '.pathao-bulk-city').on('change', '.pathao-bulk-city', function() {
            const cityId = $(this).val();
            const $row = $(this).closest('tr');
            const $zoneSelect = $row.find('.pathao-bulk-zone');
            const $areaSelect = $row.find('.pathao-bulk-area');
            const selectedZone = $zoneSelect.data('selected-zone');

            $zoneSelect.prop('disabled', true).html('<option value="">Loading zones...</option>');
            $areaSelect.html('<option value="">Select Area</option>');

            if (!cityId) {
                $zoneSelect.prop('disabled', false).html('<option value="">Select Zone</option>');
                return;
            }

            $.post(AJAX_URL, {
                action: 'get_city_zones',
                city: cityId,
                nonce: NONCE
            }, function (res) {
                $zoneSelect.prop('disabled', false);
                if (res && res.success && res.data && res.data.zones && res.data.zones.length > 0) {
                    $zoneSelect.empty().append('<option value="">Select Zone</option>');
                    res.data.zones.forEach(function(zone) {
                        const selected = (selectedZone && selectedZone == zone.id) ? 'selected' : '';
                        $zoneSelect.append('<option value="' + zone.id + '" ' + selected + '>' + zone.name + '</option>');
                    });
                    // Trigger change if zone was pre-selected
                    if (selectedZone) {
                        $zoneSelect.trigger('change');
                    }
                } else if (res && res.success && res.data && res.data.zones) {
                    // Empty zones array
                    $zoneSelect.html('<option value="">No zones found</option>');
                } else {
                    const errorMsg = (res && res.data && res.data.message) ? res.data.message : 'Failed to load zones';
                    console.error('Pathao Zones Error:', errorMsg, res);
                    $zoneSelect.html('<option value="">Error: ' + errorMsg + '</option>');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error loading zones:', xhr, status, error);
                $zoneSelect.prop('disabled', false).html('<option value="">Error loading zones</option>');
            });
        });

        // Zone change -> Load areas
        $(document).off('change', '.pathao-bulk-zone').on('change', '.pathao-bulk-zone', function() {
            const zoneId = $(this).val();
            const $row = $(this).closest('tr');
            const $areaSelect = $row.find('.pathao-bulk-area');
            const selectedArea = $areaSelect.data('selected-area');

            $areaSelect.prop('disabled', true).html('<option value="">Loading areas...</option>');

            if (!zoneId) {
                $areaSelect.prop('disabled', false).html('<option value="">Select Area</option>');
                return;
            }

            $.post(AJAX_URL, {
                action: 'get_zone_areas',
                zone: zoneId,
                nonce: NONCE
            }, function (res) {
                $areaSelect.prop('disabled', false);
                if (res && res.success && res.data && res.data.areas && res.data.areas.length > 0) {
                    $areaSelect.empty().append('<option value="">Select Area (optional)</option>');
                    res.data.areas.forEach(function(area) {
                        const selected = (selectedArea && selectedArea == area.id) ? 'selected' : '';
                        $areaSelect.append('<option value="' + area.id + '" ' + selected + '>' + area.name + '</option>');
                    });
                } else if (res && res.success && res.data && res.data.areas) {
                    // Empty areas array
                    $areaSelect.html('<option value="">No areas found</option>');
                } else {
                    const errorMsg = (res && res.data && res.data.message) ? res.data.message : 'Failed to load areas';
                    console.error('Pathao Areas Error:', errorMsg, res);
                    $areaSelect.html('<option value="">Error: ' + errorMsg + '</option>');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX error loading areas:', xhr, status, error);
                $areaSelect.prop('disabled', false).html('<option value="">Error loading areas</option>');
            });
        });
    }

    /* ---------------------------------------------------
     * Sync Order Status
     * --------------------------------------------------- */
    $(document).on('click', '#pathao-sync-status', function (e) {
        e.preventDefault();

        const btn = $(this);
        btn.prop('disabled', true).text('Syncing…');

        ajaxPost('pathao_sync_order_status', {}, function (res) {
            btn.prop('disabled', false).text('🔄 Sync Order Status');
            alert((res.data && res.data.message) || 'Sync completed');
            location.reload();
        });
    });
});
