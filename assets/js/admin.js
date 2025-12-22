jQuery(document).ready(function ($) {

    if (typeof pathao_vars === 'undefined') {
        console.error('pathao_vars is missing');
        return;
    }

    const AJAX_URL = pathao_vars.ajax_url;
    const NONCE = pathao_vars.nonce;

    /* =====================================================
     * BULK SEND ORDERS
     * ===================================================== */
    $(document).on('click', '#pathao-bulk-send', function (e) {
        e.preventDefault();

        let orderIds = [];

        $('.pathao-order-checkbox:checked').each(function () {
            orderIds.push($(this).val());
        });

        if (orderIds.length === 0) {
            alert('Please select at least one order');
            return;
        }

        if (!confirm('Send selected orders to Pathao?')) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: AJAX_URL,
            dataType: 'json',
            data: {
                action: 'send_bulk_orders_to_pathao',
                nonce: NONCE,
                order_ids: orderIds
            }
        })
        .done(function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data?.message || 'Bulk order failed');
            }
        })
        .fail(function (xhr) {
            console.error(xhr.responseText);
            alert('AJAX request failed');
        });
    });

    /* =====================================================
     * SINGLE ORDER (MODAL BUTTON)
     * ===================================================== */
    $(document).on('click', '.ptc-open-modal-button', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const orderId = $(this).data('order-id');
        console.log('Opening Pathao modal for order:', orderId);

        // popup.js handles the modal logic
        $(document).trigger('pathao:openModal', [orderId]);
    });

    /* =====================================================
    * SYNC ORDER STATUS (MANUAL)
    * ===================================================== */
    $(document).on('click', '#pathao-sync-status', function (e) {
        e.preventDefault();

        if (!confirm('Sync Pathao order statuses now?')) {
            return;
        }

        $.ajax({
            type: 'POST',
            url: AJAX_URL,
            dataType: 'json',
            data: {
                action: 'pathao_sync_order_status',
                nonce: NONCE
            }
        })
        .done(function (res) {
            if (res.success) {
                alert(res.data.message);
                location.reload();
            } else {
                alert(res.data?.message || 'Sync failed');
            }
        })
        .fail(function () {
            alert('AJAX request failed');
        });
    });


});



$(document).on('change', '#ptc-city', function () {
    const cityId = $(this).val();

    if (!cityId) return;

    $.post(AJAX_URL, {
        action: 'get_city_zones',
        nonce: NONCE,
        city: cityId
    }, function (res) {
        const $zone = $('#ptc-zone');
        $zone.empty().append('<option value="">Select Zone</option>');

        if (res.zones) {
            res.zones.forEach(z => {
                $zone.append(`<option value="${z.id}">${z.name}</option>`);
            });
        }
    });
});


$(document).on('change', '#ptc-zone', function () {
    const zoneId = $(this).val();

    if (!zoneId) return;

    $.post(AJAX_URL, {
        action: 'get_zone_areas',
        nonce: NONCE,
        zone: zoneId
    }, function (res) {
        const $area = $('#ptc-area');
        $area.empty().append('<option value="">Select Area</option>');

        if (res.areas) {
            res.areas.forEach(a => {
                $area.append(`<option value="${a.id}">${a.name}</option>`);
            });
        }
    });
});
