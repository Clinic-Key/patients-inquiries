jQuery(document).ready(function($) {

    // Target the form on the page, outside the popup
    $('.patients-inquirys-form').submit(function(event) {
        event.preventDefault(); // Prevent the form from submitting normally

        var formData = $(this).serialize();
        
        $.ajax({
            url: ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            dataType: 'json', // Specify JSON data type for the response
            success: function(response) {
                if (response.success) {
                    // Display success message
                    $('.response-messages').text(response.data).css('color', 'green');
                    $('.patients-inquirys-form')[0].reset(); // Reset the form
                } else {
                    // Display error message
                    $('.response-messages').text(response.data).css('color', 'red');
                }
            },
            error: function() {
                // Handle AJAX request failures
                $('.response-messages').text('An error occurred. Please try again.').css('color', 'red');
            }
        });
    });

});
