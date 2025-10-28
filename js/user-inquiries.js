jQuery(document).ready(function($) {
    // Handle filter changes for user inquiries
    $('#user_date_order').change(function() {
        var dateOrder = $('#user_date_order').val();

        var queryParams = new URLSearchParams(window.location.search);
        queryParams.set('user_date_order', dateOrder);

        window.location.search = queryParams.toString();
    });
});
