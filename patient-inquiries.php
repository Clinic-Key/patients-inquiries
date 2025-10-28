<?php
/*
 * Plugin Name:       Clinic patients inquiries
 * Description:       A custom plugin made for allowing inquiries between patients and clinics
 * Version:           1.0.4
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Kazmi Webwhiz
 * Author URI:        https://kazmiwebwhiz.com/
 * Text Domain:       clinic-patients-inquiries
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Create the database table on plugin activation
function create_patient_inquiries_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name tinytext NOT NULL,
        email varchar(100) NOT NULL,
        phone varchar(20) NOT NULL,
        query text NOT NULL,
        clinic_id varchar(255) DEFAULT 'admin' NOT NULL,
        user_id mediumint(9) NOT NULL,
        clinic_title varchar(255) DEFAULT '' NOT NULL,
        clinic_owner_response varchar(20) DEFAULT 'not responded' NOT NULL,
        submission_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'create_patient_inquiries_table');

// Shortcode for the patient inquiry form
function patient_inquiry_form_shortcode() {
    ob_start();
    $clinic_id = is_singular('clinic') ? get_the_ID() : 'admin'; 
    $user_id = get_current_user_id();
    ?>
    <form class="patients-inquirys-form" method="post" action="">
        <div class="form-group">
            <label for="name"><?php esc_html_e('Name', 'clinic-patients-inquiries'); ?>:</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email"><?php esc_html_e('Email', 'clinic-patients-inquiries'); ?>:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="phone"><?php esc_html_e('Phone', 'clinic-patients-inquiries'); ?>:</label>
            <input type="text" id="phone" name="phone" required>
        </div>
        <div class="form-group">
            <label for="query"><?php esc_html_e('Query', 'clinic-patients-inquiries'); ?>:</label>
            <textarea id="query" name="query" required></textarea>
        </div>
        <input type="hidden" name="clinic_id" value="<?php echo esc_attr($clinic_id); ?>">
        <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
        <input type="hidden" name="clinic_title" value="<?php echo is_singular('clinic') ? get_the_title() : 'Admin'; ?>">
        <input type="hidden" name="action" value="submit_patient_inquiry">
        <?php wp_nonce_field('patient_inquiry_nonce', 'patient_inquiry_nonce_field'); ?>
        <input type="submit" name="submit_inquiry" value="<?php esc_html_e('Submit', 'clinic-patients-inquiries'); ?>">
    </form>
    <div class="response-messages"></div>
    <div class="response-message"></div>
    <?php
    return ob_get_clean();
}
add_shortcode('patient_inquiry_form', 'patient_inquiry_form_shortcode');

// Handle form submissions via AJAX
function handle_patient_inquiry_form_submission() {
    check_ajax_referer('patient_inquiry_nonce', 'patient_inquiry_nonce_field');

    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    $name = sanitize_text_field($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $query = sanitize_textarea_field($_POST['query']);
    $clinic_id = sanitize_text_field($_POST['clinic_id']);
    $user_id = intval($_POST['user_id']);
    $clinic_title = sanitize_text_field($_POST['clinic_title']);

    $result = $wpdb->insert(
        $table_name,
        array(
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'query' => $query,
            'clinic_id' => $clinic_id,
            'user_id' => $user_id,
            'clinic_title' => $clinic_title,
            'clinic_owner_response' => 'not responded',
        )
    );

    if ($result) {
        // Send email notification
        $admin_email = get_option('admin_email');
        $to = $clinic_title === 'Admin' ? $admin_email : get_the_author_meta('user_email', get_post_field('post_author', $clinic_id));
        $subject = 'New Patient Inquiry';
        $message = "Name: $name\nEmail: $email\nPhone: $phone\nQuery: $query\nClinic: $clinic_title";
        wp_mail($to, $subject, $message);

        wp_send_json_success('Inquiry submitted successfully!');
    } else {
        wp_send_json_error('Failed to submit inquiry. Please try again.');
    }
}
add_action('wp_ajax_submit_patient_inquiry', 'handle_patient_inquiry_form_submission');
add_action('wp_ajax_nopriv_submit_patient_inquiry', 'handle_patient_inquiry_form_submission');

// Enqueue scripts and styles
function enqueue_patient_inquiry_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('patient-inquiry-ajax', plugin_dir_url(__FILE__) . 'js/patient-inquiry.js', array('jquery'), null, true);
    wp_localize_script('patient-inquiry-ajax', 'ajax_object', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('patient_inquiry_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'enqueue_patient_inquiry_scripts');

// Function to handle marking an inquiry as responded
function mark_inquiry_as_responded() {
    check_ajax_referer('patient_inquiry_nonce', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error('You must be logged in to perform this action.');
    }

    $inquiry_id = intval($_POST['inquiry_id']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    $result = $wpdb->update(
        $table_name,
        array('clinic_owner_response' => 'responded'),
        array('id' => $inquiry_id)
    );

    if ($result !== false) {
        wp_send_json_success('Inquiry marked as responded.');
    } else {
        wp_send_json_error('Failed to update inquiry. Please try again.');
    }
}
add_action('wp_ajax_mark_inquiry_as_responded', 'mark_inquiry_as_responded');

// Shortcode to display inquiries for the logged-in clinic post author with sorting and filtering
function display_clinic_inquiries_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You need to be logged in to view inquiries.</p>';
    }

    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    // Get sorting and filtering parameters
    $response_filter = isset($_GET['response_filter']) ? sanitize_text_field($_GET['response_filter']) : 'all';
    $date_order = isset($_GET['date_order']) ? sanitize_text_field($_GET['date_order']) : 'desc';

    // Build query with filters
    $query = "SELECT id, name, email, phone, query, clinic_owner_response, submission_date 
              FROM $table_name 
              WHERE clinic_id IN (SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type = 'clinic')";

    if ($response_filter !== 'all') {
        $query .= $wpdb->prepare(" AND clinic_owner_response = %s", $response_filter);
    }

    $query .= " ORDER BY submission_date " . ($date_order === 'asc' ? 'ASC' : 'DESC');

    // Fetch inquiries based on the query
    $inquiries = $wpdb->get_results($wpdb->prepare($query, $current_user_id));

    if (empty($inquiries)) {
        return '<p>No inquiries found for your clinics.</p>';
    }

    // Output filter controls
    $output = '<div class="inquiry-filters">';
    $output .= '<label for="response_filter">Filter by Status:</label>';
    $output .= '<select id="response_filter">';
    $output .= '<option value="all"' . selected($response_filter, 'all', false) . '>All</option>';
    $output .= '<option value="not responded"' . selected($response_filter, 'not responded', false) . '>Not Responded</option>';
    $output .= '<option value="responded"' . selected($response_filter, 'responded', false) . '>Responded</option>';
    $output .= '</select>';

    $output .= '<label for="date_order">Sort by Date:</label>';
    $output .= '<select id="date_order">';
    $output .= '<option value="desc"' . selected($date_order, 'desc', false) . '>Descending</option>';
    $output .= '<option value="asc"' . selected($date_order, 'asc', false) . '>Ascending</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Display inquiries in a nicely designed card format
    $output .= '<div class="clinic-inquiries">';
    foreach ($inquiries as $inquiry) {
        $response_status = $inquiry->clinic_owner_response === 'responded' ? 'Responded' : 'Not Responded';
        $response_button = $inquiry->clinic_owner_response === 'not responded' ? '<button class="mark-as-responded" data-id="' . esc_attr($inquiry->id) . '">Mark as Responded</button>' : '';

        $output .= '<div class="inquiry-card">';
        $output .= '<h3>' . esc_html($inquiry->name) . '</h3>';
        $output .= '<p><strong>Email:</strong> ' . esc_html($inquiry->email) . '</p>';
        $output .= '<p><strong>Phone:</strong> ' . esc_html($inquiry->phone) . '</p>';
        $output .= '<p><strong>Query:</strong> ' . esc_html($inquiry->query) . '</p>';
        $output .= '<p><strong>Status:</strong> ' . esc_html($response_status) . '</p>';
        $output .= '<p><strong>Date:</strong> ' . esc_html(date('Y-m-d H:i:s', strtotime($inquiry->submission_date))) . '</p>';
        $output .= $response_button;
        $output .= '</div>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('clinic_inquiries', 'display_clinic_inquiries_shortcode');

// Enqueue styles and scripts for the inquiries cards
function enqueue_clinic_inquiries_assets() {
    wp_enqueue_style('clinic-inquiries-styles', plugin_dir_url(__FILE__) . 'css/clinic-inquiries.css');
    wp_enqueue_script('clinic-inquiries-script', plugin_dir_url(__FILE__) . 'js/clinic-inquiries.js', array('jquery'), null, true);
    wp_localize_script('clinic-inquiries-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('patient_inquiry_nonce')));
}
add_action('wp_enqueue_scripts', 'enqueue_clinic_inquiries_assets');

// Create admin menu for viewing inquiries
function patient_inquiries_menu() {
    add_menu_page(
        'Patient Inquiries',
        'Patient Inquiries',
        'manage_options',
        'patient-inquiries',
        'patient_inquiries_page',
        'dashicons-admin-users',
        6
    );
}
add_action('admin_menu', 'patient_inquiries_menu');

// Function to display the patient inquiries page in the admin area
function patient_inquiries_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    // Get filtering parameters
    $filter_to = isset($_GET['filter_to']) ? sanitize_text_field($_GET['filter_to']) : 'all';
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';

    // Build the query with filters
    $query = "SELECT * FROM $table_name WHERE 1=1";
    
    if ($filter_to !== 'all') {
        if ($filter_to === 'admin') {
            $query .= " AND clinic_id = 'admin'";
        } else {
            $query .= " AND clinic_id != 'admin'";
        }
    }
    
    if ($filter_status !== 'all') {
        $query .= $wpdb->prepare(" AND clinic_owner_response = %s", $filter_status);
    }
    
    $query .= " ORDER BY submission_date DESC";
    
    $inquiries = $wpdb->get_results($query);

    echo '<div class="wrap">';
    echo '<h1>Patient Inquiries</h1>';

    // Display filter controls
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="patient-inquiries">';
    echo '<label for="filter_to">Filter by To: </label>';
    echo '<select name="filter_to" id="filter_to">';
    echo '<option value="all"' . selected($filter_to, 'all', false) . '>All</option>';
    echo '<option value="admin"' . selected($filter_to, 'admin', false) . '>Admin</option>';
    echo '<option value="clinics"' . selected($filter_to, 'clinics', false) . '>Clinics</option>';
    echo '</select>';

    echo '<label for="filter_status">Filter by Status: </label>';
    echo '<select name="filter_status" id="filter_status">';
    echo '<option value="all"' . selected($filter_status, 'all', false) . '>All</option>';
    echo '<option value="not responded"' . selected($filter_status, 'not responded', false) . '>Not Responded</option>';
    echo '<option value="responded"' . selected($filter_status, 'responded', false) . '>Responded</option>';
    echo '</select>';
    
    echo '<input type="submit" value="Filter">';
    echo '</form>';

    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Query</th><th>To</th><th>Clinic ID</th><th>User ID</th><th>Status</th><th>Submission Date</th><th>Action</th></tr></thead>';
    echo '<tbody>';
    foreach ($inquiries as $inquiry) {
        $response_status = $inquiry->clinic_owner_response === 'responded' ? 'Responded' : 'Not Responded';
        $status_action = $inquiry->clinic_owner_response === 'not responded' ? '<button class="mark-as-responded-admin" data-id="' . esc_attr($inquiry->id) . '">Mark as Responded</button>' : '<button class="mark-as-not-responded-admin" data-id="' . esc_attr($inquiry->id) . '">Mark as Not Responded</button>';
        
        echo '<tr>';
        echo '<td>' . esc_html($inquiry->name) . '</td>';
        echo '<td>' . esc_html($inquiry->email) . '</td>';
        echo '<td>' . esc_html($inquiry->phone) . '</td>';
        echo '<td>' . esc_html($inquiry->query) . '</td>';
        echo '<td>' . esc_html($inquiry->clinic_title) . '</td>';
        echo '<td>' . esc_html($inquiry->clinic_id) . '</td>';
        echo '<td>' . esc_html($inquiry->user_id) . '</td>';
        echo '<td>' . esc_html($response_status) . '</td>';
        echo '<td>' . esc_html($inquiry->submission_date) . '</td>';
        echo '<td>' . $status_action . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

// Function to handle marking an inquiry as responded by the admin
function mark_inquiry_as_responded_admin() {
    check_ajax_referer('patient_inquiry_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $inquiry_id = intval($_POST['inquiry_id']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    $result = $wpdb->update(
        $table_name,
        array('clinic_owner_response' => 'responded'),
        array('id' => $inquiry_id)
    );

    if ($result !== false) {
        wp_send_json_success('Inquiry marked as responded.');
    } else {
        wp_send_json_error('Failed to update inquiry. Please try again.');
    }
}
add_action('wp_ajax_mark_inquiry_as_responded_admin', 'mark_inquiry_as_responded_admin');

// Function to handle marking an inquiry as not responded by the admin
function mark_inquiry_as_not_responded_admin() {
    check_ajax_referer('patient_inquiry_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    $inquiry_id = intval($_POST['inquiry_id']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    $result = $wpdb->update(
        $table_name,
        array('clinic_owner_response' => 'not responded'),
        array('id' => $inquiry_id)
    );

    if ($result !== false) {
        wp_send_json_success('Inquiry marked as not responded.');
    } else {
        wp_send_json_error('Failed to update inquiry. Please try again.');
    }
}
add_action('wp_ajax_mark_inquiry_as_not_responded_admin', 'mark_inquiry_as_not_responded_admin');

// Enqueue admin scripts for handling status changes
function enqueue_admin_inquiry_scripts($hook) {
    if ($hook !== 'toplevel_page_patient-inquiries') {
        return;
    }

    wp_enqueue_script('admin-inquiry-script', plugin_dir_url(__FILE__) . 'js/admin-inquiry.js', array('jquery'), null, true);
    wp_localize_script('admin-inquiry-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('patient_inquiry_nonce')));
}
add_action('admin_enqueue_scripts', 'enqueue_admin_inquiry_scripts');

// Shortcode to display inquiries submitted by the current user with sorting by date
function display_user_inquiries_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You need to be logged in to view your inquiries.</p>';
    }

    $current_user_id = get_current_user_id();
    global $wpdb;
    $table_name = $wpdb->prefix . 'patient_inquiries';

    // Get sorting parameter
    $date_order = isset($_GET['user_date_order']) ? sanitize_text_field($_GET['user_date_order']) : 'desc';

    // Build query to fetch inquiries by the current user
    $query = $wpdb->prepare(
        "SELECT id, query, clinic_title, clinic_id, submission_date 
         FROM $table_name 
         WHERE user_id = %d 
         ORDER BY submission_date " . ($date_order === 'asc' ? 'ASC' : 'DESC'),
        $current_user_id
    );

    // Fetch inquiries based on the query
    $inquiries = $wpdb->get_results($query);

    if (empty($inquiries)) {
        return '<p>No inquiries found for your account.</p>';
    }

    // Output filter controls
    $output = '<div class="user-inquiry-filters">';
    $output .= '<label for="user_date_order">Sort by Date:</label>';
    $output .= '<select id="user_date_order">';
    $output .= '<option value="desc"' . selected($date_order, 'desc', false) . '>Descending</option>';
    $output .= '<option value="asc"' . selected($date_order, 'asc', false) . '>Ascending</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Display inquiries in a nicely designed card format
    $output .= '<div class="user-inquiries">';
    foreach ($inquiries as $inquiry) {
        // Create a link to the clinic post if clinic_id is not 'admin'
        $clinic_link = $inquiry->clinic_id !== 'admin' ? get_permalink($inquiry->clinic_id) : '#';
        $clinic_name = $inquiry->clinic_id !== 'admin' ? '<a href="' . esc_url($clinic_link) . '" target="_blank">' . esc_html($inquiry->clinic_title) . '</a>' : esc_html($inquiry->clinic_title);

        $output .= '<div class="inquiry-card">';
        $output .= '<p><strong>My Query:</strong> ' . esc_html($inquiry->query) . '</p>';
        $output .= '<p><strong>To:</strong> ' . $clinic_name . '</p>';
        $output .= '<p><strong>Date:</strong> ' . esc_html(date('Y-m-d H:i:s', strtotime($inquiry->submission_date))) . '</p>';
        $output .= '</div>';
    }
    $output .= '</div>';

    return $output;
}
add_shortcode('user_inquiries', 'display_user_inquiries_shortcode');

// Enqueue styles and scripts for the user inquiries
function enqueue_user_inquiries_assets() {
    wp_enqueue_style('user-inquiries-styles', plugin_dir_url(__FILE__) . 'css/user-inquiries.css');
    wp_enqueue_script('user-inquiries-script', plugin_dir_url(__FILE__) . 'js/user-inquiries.js', array('jquery'), null, true);
    wp_localize_script('user-inquiries-script', 'ajax_object', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('patient_inquiry_nonce')));
}
add_action('wp_enqueue_scripts', 'enqueue_user_inquiries_assets');
