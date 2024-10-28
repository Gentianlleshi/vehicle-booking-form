<?php
/*
Plugin Name: Vehicle Booking Form
Description: A custom form for vehicle booking through a ferry service.
Version: 1.0
Author: Gentian Lleshi
*/


// Include the admin functions
if (is_admin()) {
    include_once plugin_dir_path(__FILE__) . 'admin/vehicle-bookings-admin.php';
}


// Enqueue custom styles and scripts
function vehicle_form_styles()
{
    wp_enqueue_style('vehicle-booking-form-style', plugin_dir_url(__FILE__) . 'css/style.css');
}
add_action('wp_enqueue_scripts', 'vehicle_form_styles');


function enqueue_vehicle_booking_calendar_assets()
{
    // Use the FullCalendar CDN URL from cdnjs for version 6.1.15
    wp_enqueue_style('fullcalendar-css', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.css');
    wp_enqueue_script('fullcalendar-js', 'https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/6.1.15/index.global.min.js', array('jquery'), null, true);

    // Enqueue your custom script to initialize the calendar (depends on FullCalendar JS)
    wp_enqueue_script('vehicle-booking-calendar', plugin_dir_url(__FILE__) . 'js/vehicle-booking-calendar.js', array('fullcalendar-js'), null, true);
    // Enqueue your custom css for the calendar
    wp_enqueue_style('vehicle-booking-calendar-style', plugin_dir_url(__FILE__) . 'css/vehicle-booking-calendar.css');

    // Prepare bookings data
    global $wpdb;
    $table_name = $wpdb->prefix . "vehicle_bookings";
    $bookings = $wpdb->get_results("SELECT * FROM $table_name");

    $bookings_data = array_map(function ($booking) {
        return array(
            'title' => $booking->route,
            'start' => $booking->date,
            'name' => $booking->first_name . ' ' . $booking->last_name,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'travel_method' => $booking->travel_method,
            'total_price' => $booking->total_price,
            'surface_area' => $booking->surface_area,
            'vehicle_type' => $booking->vehicle_type,
            'vehicle_brand' => $booking->vehicle_brand,
            'vehicle_model' => $booking->vehicle_model,
            'plate_number' => $booking->plate_number,
            // 'description' => 'Travel Method: ' . $booking->travel_method . ', Adults: ' . $booking->adults . ', Children: ' . $booking->children . ', Total Price: ' . $booking->total_price . 'Surface Area (m²)' . $booking->surface_area . ', Vehicle Type: ' . $booking->vehicle_type . ', Vehicle Brand: ' . $booking->vehicle_brand . ', Vehicle Model: ' . $booking->vehicle_model . ', Plate Number: ' . $booking->plate_number,
        );
    }, $bookings);


    // Pass bookings data to JS
    wp_localize_script('vehicle-booking-calendar', 'vehicleBookingsData', array('bookings' => $bookings_data));
}
add_action('admin_enqueue_scripts', 'enqueue_vehicle_booking_calendar_assets');



define('VEHICLE_BOOKING_PLUGIN_VERSION', '1.3');

function vehicle_booking_check_plugin_version()
{
    if (get_option('vehicle_booking_plugin_version') != VEHICLE_BOOKING_PLUGIN_VERSION) {
        vehicle_booking_create_table();
        update_option('vehicle_booking_plugin_version', VEHICLE_BOOKING_PLUGIN_VERSION);
    }
}
add_action('plugins_loaded', 'vehicle_booking_check_plugin_version');

















// Create table for bookings on plugin activation
function vehicle_booking_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . "vehicle_bookings";

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        first_name varchar(255) NOT NULL,
        last_name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        phone varchar(255) NOT NULL,
        adults int NOT NULL,
        children int NOT NULL,
        date date NOT NULL,
        route varchar(50) NOT NULL,
        travel_method varchar(10) NOT NULL,
        vehicle_type varchar(255) NOT NULL,
        vehicle_brand varchar(255) NOT NULL,
        vehicle_model varchar(255) NOT NULL,
        plate_number varchar(255) NOT NULL,
        surface_area float NOT NULL,
        total_price float NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'vehicle_booking_create_table');


// Handle the booking form submission and save data to the database
function handle_vehicle_booking_submission()
{
    // ob_start();
    global $wpdb;

    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        if (isset($_POST['submit_vehicle_booking'])) {
            error_log('Vehicle booking form submitted.');
            // Retrieve and sanitize form data for customer details
            $first_name = sanitize_text_field($_POST['first_name']);
            $last_name = sanitize_text_field($_POST['last_name']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $adults = intval($_POST['adults']);
            $children = intval($_POST['children']);
            $date = sanitize_text_field($_POST['date']);
            // Retrieve and sanitize the selected route
            $route = sanitize_text_field($_POST['route']);
            $travel_method = sanitize_text_field($_POST['travel_method']);
            $ferry_capacity = isset($options['ferry_capacity']) ? floatval($options['ferry_capacity']) : 40;


            // Get prices from settings
            $options = get_option('vehicle_booking_settings');
            // Get ferry capacity from settings
            $ferry_capacity = isset($options['ferry_capacity']) ? floatval($options['ferry_capacity']) : 40;
            $bus_adult_price = isset($options['bus_adult_price']) ? $options['bus_adult_price'] : 0;
            $bus_child_price = isset($options['bus_child_price']) ? $options['bus_child_price'] : 0;
            $ferry_adult_price = isset($options['ferry_adult_price']) ? $options['ferry_adult_price'] : 0;
            $ferry_child_price = isset($options['ferry_child_price']) ? $options['ferry_child_price'] : 0;
            $m2_price = isset($options['m2_price']) ? $options['m2_price'] : 0;
            $bike_price = isset($options['bike_price']) ? $options['bike_price'] : 0;
            $motorcycle_price = isset($options['motorcycle_price']) ? $options['motorcycle_price'] : 0;

            // Set adult and child prices based on travel method
            if ($travel_method === 'Bus') {
                $adult_price = $bus_adult_price;
                $child_price = $bus_child_price;
            } else { // Assume Ferry
                $adult_price = $ferry_adult_price;
                $child_price = $ferry_child_price;
            }

            // Initialize variables to aggregate vehicle data
            $vehicle_types = [];
            $vehicle_brands = [];
            $vehicle_models = [];
            $plate_numbers = [];
            $total_surface_area = 0;

            $total_price = 0;

            // Calculate price for adults and children
            $adults_total = $adults * $adult_price;
            $children_total = $children * $child_price;
            $total_price += $adults_total + $children_total;



            // Loop through each vehicle entry and aggregate data
            foreach ($_POST['vehicle_type'] as $index => $vehicle_type) {
                if (!empty($vehicle_type)) {
                    $vehicle_types[] = sanitize_text_field($vehicle_type);
                    $vehicle_brands[] = sanitize_text_field($_POST['vehicle_brand'][$index]);
                    $vehicle_models[] = sanitize_text_field($_POST['vehicle_model'][$index]);
                    $plate_numbers[] = sanitize_text_field($_POST['plate_number'][$index]);

                    // Calculate price for each vehicle
                    if ($vehicle_type === 'Bike') {
                        $vehicle_price = $bike_price;
                    } elseif ($vehicle_type === 'Motorcycle') {
                        $vehicle_price = $motorcycle_price;
                    } else {
                        // Calculate surface area for each vehicle
                        $width = floatval($_POST['vehicle_width'][$index]);
                        $length = floatval($_POST['vehicle_length'][$index]);
                        $surface_area = $width * $length;
                        $total_surface_area += $surface_area;

                        $vehicle_price = $surface_area * $m2_price;
                    }

                    $total_price += $vehicle_price;
                }
            }

            // Get already booked surface area for the selected date and route
            $booked_surface_area = $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(surface_area) FROM {$wpdb->prefix}vehicle_bookings WHERE date = %s AND route = %s",
                $date,
                $route
            ));


            if (is_null($booked_surface_area)) {
                $booked_surface_area = 0;
            }

            // Calculate new total surface area if this booking is added
            $new_total_surface_area = $booked_surface_area + $total_surface_area;

            // Check if capacity is exceeded
            if ($new_total_surface_area > $ferry_capacity) {
                // Capacity exceeded, show error message and do not proceed
                wc_add_notice('Please select another date for travel. On the current selected date, the ferry has no more space available.', 'error');
                return;
            }

            // Concatenate vehicle details into a single string
            $vehicle_type_str = implode(' + ', $vehicle_types);
            $vehicle_brand_str = implode(' + ', $vehicle_brands);
            $vehicle_model_str = implode(' + ', $vehicle_models);
            $plate_number_str = implode(' + ', $plate_numbers);

            // Insert concatenated data into the database
            $table_name = $wpdb->prefix . 'vehicle_bookings';
            $wpdb->insert($table_name, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'adults' => $adults,
                'children' => $children,
                'date' => $date,
                'route'         => $route,
                'travel_method' => $travel_method,
                'vehicle_type' => $vehicle_type_str,
                'vehicle_brand' => $vehicle_brand_str,
                'vehicle_model' => $vehicle_model_str,
                'plate_number' => $plate_number_str,
                'surface_area' => $total_surface_area, // Total surface area
                'total_price'    => $total_price,
            ]);

            // Store total price in session for use in WooCommerce cart
            WC()->session->set('vehicle_booking_custom_price', $total_price);

            // Add product to WooCommerce cart with custom price and redirect to checkout
            $product_id = 22; // Replace with your WooCommerce product ID for booking
            $quantity = 1; // Default quantity is 1

            // Add custom price as cart item data
            $cart_item_data = array(
                'custom_price' => $total_price,
                'route'        => $route,
                'travel_method' => $travel_method,
            );

            WC()->cart->empty_cart(); // Empty the cart before adding new item
            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
            error_log('Cart item key: ' . $cart_item_key);

            if (!$cart_item_key) {
                // Log an error if the product couldn't be added
                error_log('Failed to add product to cart.');
            }

            // Save the cart
            WC()->cart->calculate_totals();
            WC()->cart->maybe_set_cart_cookies();

            // Redirect to checkout page
            // wp_safe_redirect(wc_get_cart_url());
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    } else {
        // echo 'WooCommerce is not active.';
    }
}
add_action('template_redirect', 'handle_vehicle_booking_submission');

function vehicle_booking_add_order_item_meta($item, $cart_item_key, $values, $order)
{
    if (isset($values['route'])) {
        $item->add_meta_data('Route', $values['route'], true);
    }
    if (isset($values['travel_method'])) {
        $item->add_meta_data('Travel Method', $values['travel_method'], true);
    }
}

add_action('woocommerce_checkout_create_order_line_item', 'vehicle_booking_add_order_item_meta', 10, 4);



// Shortcode to display the booking form
function vehicle_booking_form_shortcode()
{
    // Get the prices from the settings
    $options = get_option('vehicle_booking_settings');

    $bus_adult_price = isset($options['bus_adult_price']) ? $options['bus_adult_price'] : 0;
    $bus_child_price = isset($options['bus_child_price']) ? $options['bus_child_price'] : 0;
    $ferry_adult_price = isset($options['ferry_adult_price']) ? $options['ferry_adult_price'] : 0;
    $ferry_child_price = isset($options['ferry_child_price']) ? $options['ferry_child_price'] : 0;
    $m2_price = isset($options['m2_price']) ? $options['m2_price'] : 0;
    $bike_price = isset($options['bike_price']) ? $options['bike_price'] : 0;
    $motorcycle_price = isset($options['motorcycle_price']) ? $options['motorcycle_price'] : 0;

    // **Add the code here to retrieve vehicle data from POST**
    $vehicle_types_post = isset($_POST['vehicle_type']) ? $_POST['vehicle_type'] : array();
    $vehicle_brands_post = isset($_POST['vehicle_brand']) ? $_POST['vehicle_brand'] : array();
    $vehicle_models_post = isset($_POST['vehicle_model']) ? $_POST['vehicle_model'] : array();
    $plate_numbers_post = isset($_POST['plate_number']) ? $_POST['plate_number'] : array();
    $vehicle_widths_post = isset($_POST['vehicle_width']) ? $_POST['vehicle_width'] : array();
    $vehicle_lengths_post = isset($_POST['vehicle_length']) ? $_POST['vehicle_length'] : array();


    ob_start();
    // Display WooCommerce notices
    if (function_exists('wc_print_notices')) {
        wc_print_notices();
    }
?>
    <div id="booking-form-wrapper">
        <div id="form-section">
            <form id="vehicle-booking-form" method="post" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">

                <h4>Booking information</h4>

                <!-- Add this section above the "Customer Details" -->
                <label for="route">Select Route</label>
                <select name="route" id="route" required>
                    <option value="">-- Select Route --</option>
                    <option value="KOMAN – FIERZE" <?php selected(isset($_POST['route']) ? $_POST['route'] : '', 'KOMAN – FIERZE'); ?>>KOMAN – FIERZE</option>
                    <option value="FIERZE – KOMAN" <?php selected(isset($_POST['route']) ? $_POST['route'] : '', 'FIERZE – KOMAN'); ?>>FIERZE – KOMAN</option>
                </select>


                <!-- Travel Method Selection -->
                <label for="travel_method">Select Travel Method</label>
                <select name="travel_method" id="travel_method" required>
                    <option value="">-- Select Travel Method --</option>
                    <option value="Ferry" <?php selected(isset($_POST['travel_method']) ? $_POST['travel_method'] : '', 'Ferry'); ?>>Ferry</option>
                    <option value="Bus" <?php selected(isset($_POST['travel_method']) ? $_POST['travel_method'] : '', 'Bus'); ?>>Bus</option>
                </select>


                <!-- Customer Details -->
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>" required>


                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>" required>

                <label for="email">Email</label>
                <input type="email" name="email" value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" required>

                <label for="phone">Phone</label>
                <input type="text" name="phone" value="<?php echo isset($_POST['phone']) ? esc_attr($_POST['phone']) : ''; ?>" required>

                <label for="adults">N. Adults</label>
                <input type="number" name="adults" min="1" value="<?php echo isset($_POST['adults']) ? esc_attr($_POST['adults']) : ''; ?>" required>

                <label for="children">N. Children (0-6 years)</label>
                <input type="number" name="children" min="0" value="<?php echo isset($_POST['children']) ? esc_attr($_POST['children']) : ''; ?>">

                <label for="date">Select a Date</label>
                <input type="date" name="date" required>

                <!-- Show Vehicle Information Button -->
                <button type="button" id="show-vehicle-info-btn">Do you have a car?</button>

                <!-- Vehicle Details Section -->
                <!-- Vehicle Details Section -->
                <div id="vehicles-section" style="<?php echo !empty($vehicle_types_post) ? 'display: block;' : 'display: none;'; ?>">
                    <div id="vehicles-container">
                        <?php
                        if (!empty($vehicle_types_post)) {
                            foreach ($vehicle_types_post as $index => $vehicle_type_post) {
                        ?>
                                <div class="vehicle-group">
                                    <label for="vehicle_type[]">Type of vehicle</label>
                                    <select name="vehicle_type[]" class="vehicle-type">
                                        <option value="">Select vehicle...</option>
                                        <option value="Car" <?php selected($vehicle_type_post, 'Car'); ?>>Car</option>
                                        <option value="Jeep" <?php selected($vehicle_type_post, 'Jeep'); ?>>Jeep</option>
                                        <option value="Minivan" <?php selected($vehicle_type_post, 'Minivan'); ?>>Minivan</option>
                                        <option value="Camper" <?php selected($vehicle_type_post, 'Camper'); ?>>Camper</option>
                                        <option value="Bike" <?php selected($vehicle_type_post, 'Bike'); ?>>Bike</option>
                                        <option value="Motorcycle" <?php selected($vehicle_type_post, 'Motorcycle'); ?>>Motorcycle</option>
                                    </select>

                                    <!-- Other vehicle fields, conditionally displayed -->
                                    <div class="vehicle-details" style="<?php echo in_array($vehicle_type_post, array('Bike', 'Motorcycle', '')) ? 'display: none;' : 'display: block;'; ?>">
                                        <div class="vehicle-dimensions">
                                            <label for="vehicle_width[]">Width (m)</label>
                                            <input type="number" name="vehicle_width[]" step="0.01" min="0" value="<?php echo isset($vehicle_widths_post[$index]) ? esc_attr($vehicle_widths_post[$index]) : ''; ?>">

                                            <label for="vehicle_length[]">Length (m)</label>
                                            <input type="number" name="vehicle_length[]" step="0.01" min="0" value="<?php echo isset($vehicle_lengths_post[$index]) ? esc_attr($vehicle_lengths_post[$index]) : ''; ?>">
                                        </div>

                                        <div class="vehicle-brand-model">
                                            <label for="vehicle_brand[]">Brand</label>
                                            <input type="text" name="vehicle_brand[]" value="<?php echo isset($vehicle_brands_post[$index]) ? esc_attr($vehicle_brands_post[$index]) : ''; ?>">

                                            <label for="vehicle_model[]">Model</label>
                                            <input type="text" name="vehicle_model[]" value="<?php echo isset($vehicle_models_post[$index]) ? esc_attr($vehicle_models_post[$index]) : ''; ?>">

                                            <label for="plate_number[]">Plate Number</label>
                                            <input type="text" name="plate_number[]" value="<?php echo isset($plate_numbers_post[$index]) ? esc_attr($plate_numbers_post[$index]) : ''; ?>">
                                        </div>
                                    </div>

                                    <!-- Remove Vehicle Button -->
                                    <button type="button" class="remove-vehicle-btn">Remove Vehicle</button>
                                </div>
                            <?php
                            }
                        } else {
                            // If no vehicles were submitted, display a default empty vehicle group
                            ?>
                            <div class="vehicle-group">
                                <label for="vehicle_type[]">Type of vehicle</label>
                                <select name="vehicle_type[]" class="vehicle-type">
                                    <option value="">Select vehicle...</option>
                                    <option value="Car">Car</option>
                                    <option value="Jeep">Jeep</option>
                                    <option value="Minivan">Minivan</option>
                                    <option value="Camper">Camper</option>
                                    <option value="Bike">Bike</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                </select>

                                <!-- Other vehicle fields, initially hidden -->
                                <div class="vehicle-details" style="display: none;">
                                    <div class="vehicle-dimensions">
                                        <label for="vehicle_width[]">Width (m)</label>
                                        <input type="number" name="vehicle_width[]" step="0.01" min="0">

                                        <label for="vehicle_length[]">Length (m)</label>
                                        <input type="number" name="vehicle_length[]" step="0.01" min="0">
                                    </div>

                                    <div class="vehicle-brand-model">
                                        <label for="vehicle_brand[]">Brand</label>
                                        <input type="text" name="vehicle_brand[]">

                                        <label for="vehicle_model[]">Model</label>
                                        <input type="text" name="vehicle_model[]">

                                        <label for="plate_number[]">Plate Number</label>
                                        <input type="text" name="plate_number[]">
                                    </div>
                                </div>

                                <!-- Remove Vehicle Button -->
                                <button type="button" class="remove-vehicle-btn">Remove Vehicle</button>
                            </div>
                        <?php
                        }
                        ?>
                    </div>
                    <!-- Add Another Vehicle Button -->
                    <button type="button" id="add-vehicle-btn">Add Another Vehicle</button>
                </div>


                <!-- Submit Button -->
                <button type="submit" name="submit_vehicle_booking">Proceed to Checkout</button>
            </form>
            <div id="summary-section">
                <h4>Booking Summary</h4>
                <table id="booking-summary">
                    <!-- Summary rows will be populated dynamically -->
                </table>
                <p id="total-price">Total to pay now: $0</p>
            </div>
        </div>

        <script>
            document.addEventListener("DOMContentLoaded", function() {
                var vehicleBookingPrices = {
                    busAdultPrice: <?php echo json_encode($bus_adult_price); ?>,
                    busChildPrice: <?php echo json_encode($bus_child_price); ?>,
                    ferryAdultPrice: <?php echo json_encode($ferry_adult_price); ?>,
                    ferryChildPrice: <?php echo json_encode($ferry_child_price); ?>,
                    m2Price: <?php echo json_encode($m2_price); ?>,
                    bikePrice: <?php echo json_encode($bike_price); ?>,
                    motorcyclePrice: <?php echo json_encode($motorcycle_price); ?>
                };

                var showVehicleInfoBtn = document.getElementById("show-vehicle-info-btn");
                var vehiclesSection = document.getElementById("vehicles-section");
                var vehiclesContainer = document.getElementById("vehicles-container");
                var addVehicleBtn = document.getElementById("add-vehicle-btn");

                // Show vehicle type when user clicks "Do you have a car?"
                showVehicleInfoBtn.addEventListener("click", function() {
                    vehiclesSection.style.display = "block";
                    showVehicleInfoBtn.style.display = "none"; // Hide the button after clicking

                    // Hide all vehicle details initially
                    var vehicleGroups = vehiclesContainer.querySelectorAll(".vehicle-group");
                    vehicleGroups.forEach(function(vehicleGroup) {
                        var vehicleDetails = vehicleGroup.querySelector(".vehicle-details");
                        vehicleDetails.style.display = "none";
                    });
                });

                // Function to update visibility of fields based on vehicle type
                function updateFieldsVisibility(vehicleGroup) {
                    var vehicleTypeSelect = vehicleGroup.querySelector(".vehicle-type");
                    var vehicleDetails = vehicleGroup.querySelector(".vehicle-details");
                    var vehicleBrandModel = vehicleGroup.querySelector(".vehicle-brand-model");
                    var dimensionsDiv = vehicleGroup.querySelector(".vehicle-dimensions");

                    var selectedValue = vehicleTypeSelect.value;

                    if (selectedValue === "") {
                        // Hide all details if no type is selected
                        vehicleDetails.style.display = "none";
                    } else {
                        // Show vehicle details
                        vehicleDetails.style.display = "block";

                        if (selectedValue === "Bike" || selectedValue === "Motorcycle") {
                            // Hide Brand, Model, Plate Number for Bike and Motorcycle
                            vehicleBrandModel.style.display = "none";
                            dimensionsDiv.style.display = "none";
                        } else {
                            // Show Brand, Model, Plate Number
                            vehicleBrandModel.style.display = "block";
                            dimensionsDiv.style.display = "block";
                        }
                    }
                }

                // Add remove functionality to each vehicle group
                function addRemoveVehicleFunctionality(vehicleGroup) {
                    var removeBtn = vehicleGroup.querySelector(".remove-vehicle-btn");
                    removeBtn.addEventListener("click", function() {
                        vehicleGroup.remove(); // Remove the entire vehicle group
                        updateSummary(); // Update the summary when a vehicle is removed
                    });
                }

                // Add another vehicle group
                addVehicleBtn.addEventListener("click", function() {
                    var vehicleGroup = document.querySelector(".vehicle-group").cloneNode(true);
                    vehicleGroup.querySelectorAll("input").forEach(function(input) {
                        input.value = ""; // Clear input values
                    });
                    vehicleGroup.querySelector(".vehicle-type").value = ""; // Reset select
                    vehicleGroup.querySelector(".vehicle-details").style.display = "none"; // Hide details
                    vehiclesContainer.appendChild(vehicleGroup);

                    // Add event listener for vehicle type change
                    vehicleGroup.querySelector(".vehicle-type").addEventListener("change", function() {
                        updateFieldsVisibility(vehicleGroup);
                        updateSummary(); // Update summary when vehicle type changes
                    });

                    // Add event listeners for dimensions input
                    var widthInput = vehicleGroup.querySelector('input[name="vehicle_width[]"]');
                    var lengthInput = vehicleGroup.querySelector('input[name="vehicle_length[]"]');
                    widthInput.addEventListener('input', updateSummary);
                    lengthInput.addEventListener('input', updateSummary);

                    addRemoveVehicleFunctionality(vehicleGroup);
                });

                // Update fields visibility when vehicle type changes
                vehiclesContainer.addEventListener("change", function(event) {
                    if (event.target.classList.contains("vehicle-type")) {
                        var vehicleGroup = event.target.closest(".vehicle-group");
                        updateFieldsVisibility(vehicleGroup);
                        updateSummary();
                    }
                });

                // Add event listeners to form fields to update the summary
                var adultsInput = document.querySelector('input[name="adults"]');
                var childrenInput = document.querySelector('input[name="children"]');
                var dateInput = document.querySelector('input[name="date"]');

                adultsInput.addEventListener('input', updateSummary);
                childrenInput.addEventListener('input', updateSummary);
                dateInput.addEventListener('change', updateSummary);

                // Initialize visibility and event listeners for the first vehicle
                document.querySelectorAll(".vehicle-group").forEach(function(vehicleGroup) {
                    // Hide details initially
                    vehicleGroup.querySelector(".vehicle-details").style.display = "none";

                    // Add event listener for vehicle type change
                    vehicleGroup.querySelector(".vehicle-type").addEventListener("change", function() {
                        updateFieldsVisibility(vehicleGroup);
                        updateSummary();
                    });

                    // Add event listeners for dimensions input
                    var widthInput = vehicleGroup.querySelector('input[name="vehicle_width[]"]');
                    var lengthInput = vehicleGroup.querySelector('input[name="vehicle_length[]"]');
                    if (widthInput && lengthInput) {
                        widthInput.addEventListener('input', updateSummary);
                        lengthInput.addEventListener('input', updateSummary);
                    }

                    // Add remove button functionality
                    addRemoveVehicleFunctionality(vehicleGroup);
                });

                // Initialize the summary
                updateSummary();

                // Add event listener for route selection
                var routeSelect = document.getElementById('route');
                routeSelect.addEventListener('change', updateSummary);

                // Add event listener for travel method selection
                var travelMethodSelect = document.getElementById('travel_method');
                travelMethodSelect.addEventListener('change', updateSummary);

                function updateSummary() {
                    var summaryTable = document.getElementById('booking-summary');
                    summaryTable.innerHTML = ''; // Clear the table

                    var totalPrice = 0;

                    // Get selected route
                    var routeSelect = document.getElementById('route');
                    var selectedRoute = routeSelect.value;
                    // var selectedRoute = routeSelect.options[routeSelect.selectedIndex].text;

                    // Get selected travel method
                    var travelMethodSelect = document.getElementById('travel_method');
                    var selectedTravelMethod = travelMethodSelect.value;

                    // Route selected
                    var routeRow = document.createElement('tr');
                    var routeCell = document.createElement('td');
                    routeCell.colSpan = 3;
                    routeCell.textContent = 'Route selected: ' + selectedRoute;
                    routeRow.appendChild(routeCell);
                    summaryTable.appendChild(routeRow);

                    // Travel method selected
                    var travelMethodRow = document.createElement('tr');
                    var travelMethodCell = document.createElement('td');
                    travelMethodCell.colSpan = 3;
                    travelMethodCell.textContent = 'Travel method: ' + selectedTravelMethod;
                    travelMethodRow.appendChild(travelMethodCell);
                    summaryTable.appendChild(travelMethodRow);

                    // Number of adults
                    var adults = parseInt(adultsInput.value) || 0;
                    var adultPrice = 0;
                    if (selectedTravelMethod === 'Bus') {
                        adultPrice = parseFloat(vehicleBookingPrices.busAdultPrice) || 0;
                    } else if (selectedTravelMethod === 'Ferry') {
                        adultPrice = parseFloat(vehicleBookingPrices.ferryAdultPrice) || 0;
                    }
                    var adultsTotal = adults * adultPrice;
                    totalPrice += adultsTotal;

                    var adultsRow = document.createElement('tr');
                    adultsRow.innerHTML = '<td>Adults (' + adults + ' x $' + adultPrice + ')</td><td></td><td>$' + adultsTotal.toFixed(2) + '</td>';
                    summaryTable.appendChild(adultsRow);

                    // Number of children
                    var children = parseInt(childrenInput.value) || 0;
                    var childPrice = 0;
                    if (selectedTravelMethod === 'Bus') {
                        childPrice = parseFloat(vehicleBookingPrices.busChildPrice) || 0;
                    } else if (selectedTravelMethod === 'Ferry') {
                        childPrice = parseFloat(vehicleBookingPrices.ferryChildPrice) || 0;
                    }
                    var childrenTotal = children * childPrice;
                    totalPrice += childrenTotal;

                    var childrenRow = document.createElement('tr');
                    childrenRow.innerHTML = '<td>Children (' + children + ' x $' + childPrice + ')</td><td></td><td>$' + childrenTotal.toFixed(2) + '</td>';
                    summaryTable.appendChild(childrenRow);

                    // Vehicles
                    var vehicleGroups = vehiclesContainer.querySelectorAll('.vehicle-group');
                    vehicleGroups.forEach(function(vehicleGroup, index) {
                        var vehicleTypeSelect = vehicleGroup.querySelector('.vehicle-type');
                        var vehicleType = vehicleTypeSelect.value;

                        if (vehicleType) {
                            var vehicleRow = document.createElement('tr');
                            var vehiclePrice = 0;
                            var vehicleDescription = 'Vehicle ' + (index + 1) + ' (' + vehicleType + ')';

                            if (vehicleType === 'Bike') {
                                vehiclePrice = parseFloat(vehicleBookingPrices.bikePrice) || 0;
                            } else if (vehicleType === 'Motorcycle') {
                                vehiclePrice = parseFloat(vehicleBookingPrices.motorcyclePrice) || 0;
                            } else {
                                // Other vehicles: calculate price based on surface area
                                var widthInput = vehicleGroup.querySelector('input[name="vehicle_width[]"]');
                                var lengthInput = vehicleGroup.querySelector('input[name="vehicle_length[]"]');

                                var width = parseFloat(widthInput.value) || 0;
                                var length = parseFloat(lengthInput.value) || 0;
                                var surfaceArea = width * length;
                                vehiclePrice = surfaceArea * parseFloat(vehicleBookingPrices.m2Price || 0);
                                vehicleDescription += ' (' + surfaceArea.toFixed(2) + ' m²)';
                            }

                            totalPrice += vehiclePrice;

                            vehicleRow.innerHTML = '<td>' + vehicleDescription + '</td><td></td><td>$' + vehiclePrice.toFixed(2) + '</td>';
                            summaryTable.appendChild(vehicleRow);
                        }
                    });

                    // Update total price
                    var totalPriceElement = document.getElementById('total-price');
                    totalPriceElement.textContent = 'Total to pay now: $' + totalPrice.toFixed(2);
                }
            });
        </script>
    </div>

<?php
    return ob_get_clean();
}
add_shortcode('vehicle_booking_form', 'vehicle_booking_form_shortcode');



// Handle the custom price and add to WooCommerce cart
function vehicle_booking_custom_price($cart_object)
{
    foreach ($cart_object->get_cart() as $hash => $value) {
        if (isset($value['custom_price'])) {
            $value['data']->set_price($value['custom_price']);
        }
        if (isset($value['route'])) {
            $value['data']->set_meta_data(array('Route' => $value['route']));
        }
    }
}
add_action('woocommerce_before_calculate_totals', 'vehicle_booking_custom_price', 10, 1);



function vehicle_booking_get_cart_item_from_session($cart_item, $values, $key)
{
    if (isset($values['custom_price'])) {
        $cart_item['custom_price'] = $values['custom_price'];
        $cart_item['data']->set_price($values['custom_price']);
    }
    return $cart_item;
}
add_filter('woocommerce_get_cart_item_from_session', 'vehicle_booking_get_cart_item_from_session', 20, 3);
