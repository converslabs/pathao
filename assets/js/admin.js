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
     * Send Selected Orders to Pathao
     * --------------------------------------------------- */
    $(document).on('click', '#pathao-bulk-send', function (e) {
    e.preventDefault();

    const orders = [];

    $('.pathao-order-checkbox:checked').each(function () {
        orders.push($(this).val());
    });

    if (!orders.length) {
        alert('Please select at least one order');
        return;
    }

    if (!confirm('Send selected orders to Pathao?')) {
        return;
    }

    $.post(
        pathao_vars.ajax_url,
        {
            action: 'send_bulk_orders_to_pathao',
            nonce: pathao_vars.nonce,
            order_ids: orders
        },
        function (res) {

            if (!res || !res.success) {
                alert(res?.data?.message || 'Bulk send failed');
                return;
            }

            let msg = '';

            if (res.data.sent.length) {
                msg += 'Sent: ' + res.data.sent.join(', ') + '\n';
            }

            if (res.data.skipped.length) {
                msg += 'Skipped: ' + res.data.skipped.join(', ') + '\n';
            }

            if (Object.keys(res.data.failed).length) {
                msg += 'Failed:\n';
                for (const id in res.data.failed) {
                    msg += 'Order #' + id + ': ' + res.data.failed[id] + '\n';
                }
            }

            alert(msg || 'Completed');
            location.reload();
        }
    );
});

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


