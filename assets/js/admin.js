jQuery(document).ready(function ($) {

const AJAX_URL = pathao_vars.ajax_url;
const NONCE = pathao_vars.nonce;

function calculatePathaoPrice() {
    const cityId = $('#ptc-city').val();
    const zoneId = $('#ptc-zone').val();
    const weight = $('#ptc-weight').val();
    const deliveryType = $('#ptc-delivery-type').val();
    const itemType = $('#ptc-item-type').val();

    if (!cityId || !zoneId || !weight) return;

    $.post(AJAX_URL, {
        action: 'pathao_price_calculation',
        nonce: NONCE,
        city_id: cityId,
        zone_id: zoneId,
        item_weight: weight,
        delivery_type: deliveryType,
        item_type: itemType
    }, function (res) {
        console.log('Price response:', res);
        if (res.success) {
            $('#ptc-collectable').val(res.data.final_price);
        }
    });
}

$(document).on(
    'change',
    '#ptc-city, #ptc-zone, #ptc-weight, #ptc-delivery-type, #ptc-item-type',
    calculatePathaoPrice
);

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


function calculatePathaoPrice() {
    const cityId = $('#ptc-city').val();
    const zoneId = $('#ptc-zone').val();
    const weight = $('#ptc-weight').val();
    const deliveryType = $('#ptc-delivery-type').val();
    const itemType = $('#ptc-item-type').val();

    if (!cityId || !zoneId || !weight) return;

    $.post(AJAX_URL, {
        action: 'pathao_price_calculation',
        nonce: NONCE,
        city_id: cityId,
        zone_id: zoneId,
        item_weight: weight,
        delivery_type: deliveryType,
        item_type: itemType
    }, function (res) {
         console.log(res); // 👈 ADD THIS
        if (res.success) {
            $('#ptc-collectable').val(res.data.final_price);
        }
    });
}

$(document).on('change', '#ptc-city, #ptc-zone, #ptc-weight, #ptc-delivery-type, #ptc-item-type', calculatePathaoPrice);
