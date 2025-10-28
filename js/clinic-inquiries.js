jQuery(document).ready(function($) {
    $('.mark-as-responded').click(function() {
        var inquiryId = $(this).data('id');
        var button = $(this);

        console.log('Marking as responded. Inquiry ID:', inquiryId);

        if (confirm('Are you sure you want to mark this inquiry as responded?')) {
            $.ajax({
                type: 'POST',
                url: ajax_object.ajax_url,
                data: {
                    action: 'mark_inquiry_as_responded',
                    inquiry_id: inquiryId,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    console.log('Response:', response);
                    if (response.success) {
                        alert(response.data);
                        button.closest('.inquiry-card').find('p:contains("Status")').text('Status: Responded');
                        button.remove();
                    } else {
                        alert('Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred. Please try again.');
                }
            });
        }
    });

    // Handle filter changes
    $('#response_filter, #date_order').change(function() {
        var responseFilter = $('#response_filter').val();
        var dateOrder = $('#date_order').val();

        var queryParams = new URLSearchParams(window.location.search);
        queryParams.set('response_filter', responseFilter);
        queryParams.set('date_order', dateOrder);

        window.location.search = queryParams.toString();
    });
});
