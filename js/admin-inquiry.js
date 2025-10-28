jQuery(document).ready(function($) {
    // Handle marking as responded
    $('.mark-as-responded-admin').click(function() {
        var inquiryId = $(this).data('id');
        var button = $(this);

        if (confirm('Are you sure you want to mark this inquiry as responded?')) {
            $.ajax({
                type: 'POST',
                url: ajax_object.ajax_url,
                data: {
                    action: 'mark_inquiry_as_responded_admin',
                    inquiry_id: inquiryId,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        button.closest('tr').find('td:eq(7)').text('Responded');
                        button.replaceWith('<button class="mark-as-not-responded-admin" data-id="' + inquiryId + '">Mark as Not Responded</button>');
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

    // Handle marking as not responded
    $(document).on('click', '.mark-as-not-responded-admin', function() {
        var inquiryId = $(this).data('id');
        var button = $(this);

        if (confirm('Are you sure you want to mark this inquiry as not responded?')) {
            $.ajax({
                type: 'POST',
                url: ajax_object.ajax_url,
                data: {
                    action: 'mark_inquiry_as_not_responded_admin',
                    inquiry_id: inquiryId,
                    nonce: ajax_object.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        button.closest('tr').find('td:eq(7)').text('Not Responded');
                        button.replaceWith('<button class="mark-as-responded-admin" data-id="' + inquiryId + '">Mark as Responded</button>');
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
});
