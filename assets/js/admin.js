jQuery(document).ready(function ($) {
    $(document).on('click', '.ptc-open-modal-button', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var order_id = $btn.data('order-id');

        console.log('Pathao Send Order Button Clicked', order_id);

        $.ajax({
            url: pathao_admin_obj.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'sdevs_send_order',
                order_id: order_id,
                nonce: pathao_admin_obj.nonce
            },
            beforeSend: function () {
                $btn.prop('disabled', true).text('Sending...');
            },
            success: function (response) {
                console.log(response);

                if (response.success) {
                    alert('Consignment ID: ' + response.data.consignment_id);
                    $btn.replaceWith('<a href="https://merchant.pathao.com/courier/orders/' + response.data.consignment_id + '" target="_blank">' + response.data.consignment_id + '</a>');
                } else {
                    var messages = response.data?.messages ?? response.data?.message ?? 'Unknown error';
                    if (!Array.isArray(messages)) messages = [messages];
                    alert('Error: ' + messages.join(', '));
                    $btn.prop('disabled', false).text('Send with Pathao');
                }
            },
            error: function (err) {
                console.error(err);
                alert('AJAX request failed');
                $btn.prop('disabled', false).text('Send with Pathao');
            }
        });
    });
});
