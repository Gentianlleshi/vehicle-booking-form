<?php

// Add admin menu for viewing bookings
function vehicle_booking_admin_menu()
{
    add_menu_page(
        'Vehicle Bookings', // Page Title
        'Vehicle Bookings', // Menu Title
        'manage_options', // Capability
        'vehicle-bookings', // Menu Slug
        'vehicle_booking_admin_page', // Callback function
        'dashicons-calendar-alt', // Icon
        20 // Position
    );
}
add_action('admin_menu', 'vehicle_booking_admin_menu');



// Admin page content: Display bookings based on selected date and add the remove button
function vehicle_booking_admin_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "vehicle_bookings";

    // Add additional tables if needed
    // Example: $related_table = $wpdb->prefix . "related_table";

    // Check if the admin clicked "Remove All Bookings"
    if (isset($_POST['remove_all_bookings'])) {
        // Delete all bookings from the primary table
        $wpdb->query("DELETE FROM $table_name");

        // WooCommerce: Delete all orders
        if (class_exists('WC_Order')) {
            $order_query = new WC_Order_Query(array(
                'limit' => -1, // No limit, retrieve all orders
                'return' => 'ids', // Return only order IDs
            ));
            $orders = $order_query->get_orders();

            // Loop through and delete each order
            foreach ($orders as $order_id) {
                wc_delete_order($order_id);
            }
        }

        // Show success message
        echo '<div class="updated"><p>All records and WooCommerce orders have been removed.</p></div>';
    }

    // If the admin selects a date, filter the bookings by that date.
    if (isset($_POST['booking_date'])) {
        // Convert the selected date to MySQL format
        $date = date('Y-m-d', strtotime(sanitize_text_field($_POST['booking_date'])));

        // Query to get bookings for the selected date
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE date = %s",
            $date
        ));
    } else {
        // If no date is selected, retrieve all bookings
        $bookings = $wpdb->get_results("SELECT * FROM $table_name");
    }

?>
    <div class="wrap">
        <h1>Vehicle Bookings Calendar</h1>
        <div id="calendar"></div>
    </div>
    <div id="eventModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2 id="modalTitle"></h2>
            <h3 id="modalName"></h3>
            <p id="modalAdults"></p>
            <p id="modalChildren"></p>
            <p id="modalTravelMethod"></p>
            <p id="modalSurfaceArea"></p>
            <p id="modalVehicleType"></p>
            <p id="modalVehicleBrand"></p>
            <p id="modalVehicleModel"></p>
            <p id="modalPlateNumber"></p>
            <p id="modalDescription"></p>
            <p id="modalTotalPrice"></p>
        </div>
    </div>


<?php
}


// Add admin submenu for settings
function vehicle_booking_settings_menu()
{
    add_submenu_page(
        'vehicle-bookings', // Parent slug
        'Vehicle Booking Settings', // Page title
        'Settings', // Menu title
        'manage_options', // Capability
        'vehicle-booking-settings', // Menu slug
        'vehicle_booking_settings_page' // Callback function
    );
}
add_action('admin_menu', 'vehicle_booking_settings_menu');


function vehicle_booking_settings_page()
{
?>
    <div class="wrap">
        <h1>Vehicle Booking Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('vehicle_booking_settings_group');
            do_settings_sections('vehicle_booking_settings_page');
            submit_button();
            ?>
        </form>
    </div>
<?php
}


function vehicle_booking_register_settings()
{
    register_setting(
        'vehicle_booking_settings_group', // Option group
        'vehicle_booking_settings', // Option name
        'vehicle_booking_sanitize_settings' // Sanitize callback
    );

    add_settings_section(
        'vehicle_booking_settings_section', // ID
        'Price Settings', // Title
        null, // Callback
        'vehicle_booking_settings_page' // Page
    );

    // Ferry Capacity
    add_settings_field(
        'ferry_capacity',
        'Ferry Capacity (m²)',
        'vehicle_booking_ferry_capacity_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );

    // Bus Adult Price
    add_settings_field(
        'bus_adult_price', // ID
        'Bus Adult Price', // Title
        'vehicle_booking_bus_adult_price_field', // Callback
        'vehicle_booking_settings_page', // Page
        'vehicle_booking_settings_section' // Section
    );

    // Bus Child Price
    add_settings_field(
        'bus_child_price',
        'Bus Child Price',
        'vehicle_booking_bus_child_price_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );

    // Ferry Adult Price
    add_settings_field(
        'ferry_adult_price',
        'Ferry Adult Price',
        'vehicle_booking_ferry_adult_price_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );

    // Ferry Child Price
    add_settings_field(
        'ferry_child_price',
        'Ferry Child Price',
        'vehicle_booking_ferry_child_price_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );


    // Price per m²
    add_settings_field(
        'm2_price',
        'Price per m²',
        'vehicle_booking_m2_price_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );

    // Bike Price
    add_settings_field(
        'bike_price',
        'Bike Price',
        'vehicle_booking_bike_price_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );

    // Motorcycle Price
    add_settings_field(
        'motorcycle_price',
        'Motorcycle Price',
        'vehicle_booking_motorcycle_price_field',
        'vehicle_booking_settings_page',
        'vehicle_booking_settings_section'
    );
}
add_action('admin_init', 'vehicle_booking_register_settings');


function vehicle_booking_ferry_capacity_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[ferry_capacity]" value="<?php echo isset($options['ferry_capacity']) ? esc_attr($options['ferry_capacity']) : '40'; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_bus_adult_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[bus_adult_price]" value="<?php echo isset($options['bus_adult_price']) ? esc_attr($options['bus_adult_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_bus_child_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[bus_child_price]" value="<?php echo isset($options['bus_child_price']) ? esc_attr($options['bus_child_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_ferry_adult_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[ferry_adult_price]" value="<?php echo isset($options['ferry_adult_price']) ? esc_attr($options['ferry_adult_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_ferry_child_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[ferry_child_price]" value="<?php echo isset($options['ferry_child_price']) ? esc_attr($options['ferry_child_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_m2_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[m2_price]" value="<?php echo isset($options['m2_price']) ? esc_attr($options['m2_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_bike_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[bike_price]" value="<?php echo isset($options['bike_price']) ? esc_attr($options['bike_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_motorcycle_price_field()
{
    $options = get_option('vehicle_booking_settings');
?>
    <input type="number" name="vehicle_booking_settings[motorcycle_price]" value="<?php echo isset($options['motorcycle_price']) ? esc_attr($options['motorcycle_price']) : ''; ?>" step="0.01" min="0">
<?php
}


function vehicle_booking_sanitize_settings($input)
{
    $sanitized = array();

    $sanitized['ferry_capacity'] = isset($input['ferry_capacity']) ? floatval($input['ferry_capacity']) : 40;
    $sanitized['bus_adult_price'] = isset($input['bus_adult_price']) ? floatval($input['bus_adult_price']) : 0;
    $sanitized['bus_child_price'] = isset($input['bus_child_price']) ? floatval($input['bus_child_price']) : 0;
    $sanitized['ferry_adult_price'] = isset($input['ferry_adult_price']) ? floatval($input['ferry_adult_price']) : 0;
    $sanitized['ferry_child_price'] = isset($input['ferry_child_price']) ? floatval($input['ferry_child_price']) : 0;

    // Existing sanitization
    $sanitized['m2_price'] = isset($input['m2_price']) ? floatval($input['m2_price']) : 0;
    $sanitized['bike_price'] = isset($input['bike_price']) ? floatval($input['bike_price']) : 0;
    $sanitized['motorcycle_price'] = isset($input['motorcycle_price']) ? floatval($input['motorcycle_price']) : 0;

    return $sanitized;
}
