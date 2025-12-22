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
        $.post(AJAX_URL, {
            action: action,
            nonce: NONCE,
            ...data
        }, cb);
    }

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
                res.zones.forEach(z => {
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
                res.areas.forEach(a => {
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

        ajaxPost('pathao_price_calculation', {
            recipient_city: city,
            recipient_zone: zone,
            item_weight: weight,
            delivery_type: deliveryType,
            item_type: itemType
        }, function (res) {
            console.log('Price response:', res);

            if (res.success && res.data) {
                $('#ptc-collectable').val(res.data.final_price);
            } else {
                $('#ptc-collectable').val('—');
            }
        });
    }

    $(document).on(
        'change',
        '#ptc-city, #ptc-zone, #ptc-weight, #ptc-delivery-type, #ptc-item-type',
        calculatePathaoPrice
    );

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

        if (!confirm('Send selected orders to Pathao?')) return;

        ajaxPost('send_bulk_orders_to_pathao', { order_ids: orders }, function (res) {
            if (res.success) {
                alert('Orders sent to Pathao successfully');
                location.reload();
            } else {
                alert(res.data?.message || 'Failed to send orders');
            }
        });
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
            alert(res.data?.message || 'Sync completed');
            location.reload();
        });
    });

});
