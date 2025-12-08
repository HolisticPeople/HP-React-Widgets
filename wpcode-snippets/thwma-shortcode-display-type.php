<?php
/**
 * ThemeHigh Multiple Addresses - Shortcode Display Type Extension
 * 
 * Adds a third "Shortcode" option to the THWMA display type settings,
 * allowing admins to use custom shortcodes (like HP Address Card Picker)
 * instead of the built-in popup or dropdown.
 * 
 * WPCode Snippet - Add to your site via WPCode plugin
 * 
 * @package HP_THWMA_Extension
 * @version 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class HP_THWMA_Shortcode_Display
 * 
 * Extends ThemeHigh Multiple Addresses with shortcode display option
 */
class HP_THWMA_Shortcode_Display {

    /**
     * Option key for our custom settings
     */
    const OPTION_KEY = 'hp_thwma_shortcode_settings';
    
    /**
     * THWMA's option key
     */
    const THWMA_OPTION_KEY = 'th_multiple_addresses_pro_settings';

    /**
     * Initialize the extension
     */
    public function __construct() {
        // Hook BEFORE ThemeHigh saves to capture our custom values
        add_action( 'admin_init', array( $this, 'intercept_save_before_thwma' ), 1 );
        
        // Hook AFTER ThemeHigh saves to inject our shortcode_display value back
        add_action( 'admin_init', array( $this, 'inject_shortcode_display_after_save' ), 999 );
        
        // Hook into admin settings page to add our fields
        add_action( 'admin_footer', array( $this, 'inject_admin_js' ) );
        
        // Frontend hooks - intercept address display rendering
        add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'maybe_render_billing_shortcode' ), 5 );
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'maybe_render_billing_shortcode_below' ), 5 );
        add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'maybe_render_shipping_shortcode' ), 5 );
        add_action( 'woocommerce_after_checkout_shipping_form', array( $this, 'maybe_render_shipping_shortcode_below' ), 5 );
        
        // Remove default THWMA rendering when shortcode is active
        add_action( 'wp', array( $this, 'maybe_remove_thwma_hooks' ) );
    }

    /**
     * Intercept the save BEFORE ThemeHigh processes it
     * Store our custom values that ThemeHigh will discard
     */
    public function intercept_save_before_thwma() {
        if ( ! isset( $_POST['save_settings'] ) ) {
            return;
        }

        // Store our custom display type values before ThemeHigh overwrites them
        $our_settings = array(
            'billing_display'    => isset( $_POST['i_billing_display'] ) ? sanitize_text_field( $_POST['i_billing_display'] ) : '',
            'shipping_display'   => isset( $_POST['i_shipping_display'] ) ? sanitize_text_field( $_POST['i_shipping_display'] ) : '',
            'billing_shortcode'  => isset( $_POST['hp_billing_shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_billing_shortcode'] ) ) : '[hp_address_card_picker type="billing" show_actions="true"]',
            'shipping_shortcode' => isset( $_POST['hp_shipping_shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_shipping_shortcode'] ) ) : '[hp_address_card_picker type="shipping" show_actions="true"]',
        );

        // Store temporarily
        update_option( self::OPTION_KEY, $our_settings );
    }

    /**
     * After ThemeHigh saves, inject our shortcode_display value back into their settings
     */
    public function inject_shortcode_display_after_save() {
        if ( ! isset( $_POST['save_settings'] ) ) {
            return;
        }

        // Get our stored settings
        $our_settings = get_option( self::OPTION_KEY, array() );
        
        // Get THWMA settings (after they saved)
        $thwma_settings = get_option( self::THWMA_OPTION_KEY, array() );
        
        if ( empty( $thwma_settings ) ) {
            return;
        }

        $modified = false;

        // Inject billing shortcode_display if that's what was selected
        if ( isset( $our_settings['billing_display'] ) && $our_settings['billing_display'] === 'shortcode_display' ) {
            if ( isset( $thwma_settings['settings_billing'] ) ) {
                $thwma_settings['settings_billing']['billing_display'] = 'shortcode_display';
                $modified = true;
            }
        }

        // Inject shipping shortcode_display if that's what was selected
        if ( isset( $our_settings['shipping_display'] ) && $our_settings['shipping_display'] === 'shortcode_display' ) {
            if ( isset( $thwma_settings['settings_shipping'] ) ) {
                $thwma_settings['settings_shipping']['shipping_display'] = 'shortcode_display';
                $modified = true;
            }
        }

        // Save the modified settings back
        if ( $modified ) {
            update_option( self::THWMA_OPTION_KEY, $thwma_settings );
        }
    }

    /**
     * Get our custom settings
     */
    public function get_settings() {
        return get_option( self::OPTION_KEY, array(
            'billing_shortcode'  => '[hp_address_card_picker type="billing" show_actions="true"]',
            'shipping_shortcode' => '[hp_address_card_picker type="shipping" show_actions="true"]',
        ) );
    }

    /**
     * Get THWMA setting value (compatible with their structure)
     */
    private function get_thwma_setting( $section, $key = null ) {
        $settings = get_option( self::THWMA_OPTION_KEY );
        
        if ( ! $settings || ! isset( $settings[ $section ] ) ) {
            return null;
        }
        
        if ( $key === null ) {
            return $settings[ $section ];
        }
        
        return isset( $settings[ $section ][ $key ] ) ? $settings[ $section ][ $key ] : null;
    }

    /**
     * Check if shortcode display is enabled for a type
     */
    public function is_shortcode_display( $type = 'billing' ) {
        $display_key = $type . '_display';
        $display_value = $this->get_thwma_setting( 'settings_' . $type, $display_key );
        
        return $display_value === 'shortcode_display';
    }

    /**
     * Check if multiple addresses are enabled for a type
     */
    private function is_enabled( $type = 'billing' ) {
        $enabled = $this->get_thwma_setting( 'settings_' . $type, 'enable_' . $type );
        return $enabled === 'yes';
    }

    /**
     * Get display position for a type
     */
    private function get_display_position( $type = 'billing' ) {
        return $this->get_thwma_setting( 'settings_' . $type, $type . '_display_position' );
    }

    /**
     * Remove default THWMA hooks when shortcode display is active
     */
    public function maybe_remove_thwma_hooks() {
        if ( ! is_checkout() ) {
            return;
        }

        // Check billing
        if ( $this->is_shortcode_display( 'billing' ) && $this->is_enabled( 'billing' ) ) {
            $this->remove_thwma_billing_hooks();
        }

        // Check shipping
        if ( $this->is_shortcode_display( 'shipping' ) && $this->is_enabled( 'shipping' ) ) {
            $this->remove_thwma_shipping_hooks();
        }
    }

    /**
     * Remove THWMA billing display hooks
     */
    private function remove_thwma_billing_hooks() {
        global $wp_filter;
        
        $hooks_to_check = array(
            'woocommerce_before_checkout_billing_form' => 'address_above_billing_form',
            'woocommerce_after_checkout_billing_form'  => 'address_below_billing_form',
        );

        foreach ( $hooks_to_check as $hook => $method ) {
            if ( ! isset( $wp_filter[ $hook ] ) ) {
                continue;
            }
            
            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
                        $class_name = get_class( $callback['function'][0] );
                        if ( strpos( $class_name, 'THWMA_Public_billing' ) !== false && $callback['function'][1] === $method ) {
                            remove_action( $hook, $callback['function'], $priority );
                        }
                    }
                }
            }
        }
    }

    /**
     * Remove THWMA shipping display hooks
     */
    private function remove_thwma_shipping_hooks() {
        global $wp_filter;
        
        $hooks_to_check = array(
            'woocommerce_before_checkout_shipping_form' => 'address_above_shipping_form',
            'woocommerce_after_checkout_shipping_form'  => 'address_below_shipping_form',
        );

        foreach ( $hooks_to_check as $hook => $method ) {
            if ( ! isset( $wp_filter[ $hook ] ) ) {
                continue;
            }
            
            foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $key => $callback ) {
                    if ( is_array( $callback['function'] ) && is_object( $callback['function'][0] ) ) {
                        $class_name = get_class( $callback['function'][0] );
                        if ( strpos( $class_name, 'THWMA_Public_shipping' ) !== false && $callback['function'][1] === $method ) {
                            remove_action( $hook, $callback['function'], $priority );
                        }
                    }
                }
            }
        }
    }

    /**
     * Render billing shortcode (above position)
     */
    public function maybe_render_billing_shortcode() {
        if ( ! is_user_logged_in() || ! $this->is_enabled( 'billing' ) || ! $this->is_shortcode_display( 'billing' ) ) {
            return;
        }
        if ( $this->get_display_position( 'billing' ) !== 'above' ) {
            return;
        }
        $this->render_shortcode( 'billing' );
    }

    /**
     * Render billing shortcode (below position)
     */
    public function maybe_render_billing_shortcode_below() {
        if ( ! is_user_logged_in() || ! $this->is_enabled( 'billing' ) || ! $this->is_shortcode_display( 'billing' ) ) {
            return;
        }
        if ( $this->get_display_position( 'billing' ) !== 'below' ) {
            return;
        }
        $this->render_shortcode( 'billing' );
    }

    /**
     * Render shipping shortcode (above position)
     */
    public function maybe_render_shipping_shortcode() {
        if ( ! is_user_logged_in() || ! $this->is_enabled( 'shipping' ) || ! $this->is_shortcode_display( 'shipping' ) ) {
            return;
        }
        if ( $this->get_display_position( 'shipping' ) !== 'above' ) {
            return;
        }
        $this->render_shortcode( 'shipping' );
    }

    /**
     * Render shipping shortcode (below position)
     */
    public function maybe_render_shipping_shortcode_below() {
        if ( ! is_user_logged_in() || ! $this->is_enabled( 'shipping' ) || ! $this->is_shortcode_display( 'shipping' ) ) {
            return;
        }
        if ( $this->get_display_position( 'shipping' ) !== 'below' ) {
            return;
        }
        $this->render_shortcode( 'shipping' );
    }

    /**
     * Render the configured shortcode
     */
    private function render_shortcode( $type = 'billing' ) {
        $settings = $this->get_settings();
        $shortcode_key = $type . '_shortcode';
        $shortcode = isset( $settings[ $shortcode_key ] ) ? $settings[ $shortcode_key ] : '';

        if ( empty( $shortcode ) ) {
            $shortcode = sprintf( '[hp_address_card_picker type="%s" show_actions="true"]', esc_attr( $type ) );
        }

        echo '<div class="hp-thwma-shortcode-container hp-thwma-shortcode-' . esc_attr( $type ) . '">';
        echo do_shortcode( $shortcode );
        echo '</div>';
    }

    /**
     * Inject JavaScript into THWMA admin settings page
     */
    public function inject_admin_js() {
        $screen = get_current_screen();
        
        if ( ! $screen || strpos( $screen->id, 'th_multiple_addresses_pro' ) === false ) {
            return;
        }

        $settings = $this->get_settings();
        $billing_shortcode = esc_attr( isset( $settings['billing_shortcode'] ) ? $settings['billing_shortcode'] : '[hp_address_card_picker type="billing" show_actions="true"]' );
        $shipping_shortcode = esc_attr( isset( $settings['shipping_shortcode'] ) ? $settings['shipping_shortcode'] : '[hp_address_card_picker type="shipping" show_actions="true"]' );
        
        // Check current saved display values
        $billing_display = $this->get_thwma_setting( 'settings_billing', 'billing_display' );
        $shipping_display = $this->get_thwma_setting( 'settings_shipping', 'shipping_display' );
        ?>
        <style>
            .hp-thwma-shortcode-field {
                margin-top: 10px;
            }
            .hp-thwma-shortcode-field input {
                width: 100%;
                max-width: 400px;
                padding: 8px;
                font-family: monospace;
            }
            .hp-thwma-shortcode-field label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .hp-thwma-shortcode-field .description {
                margin-top: 5px;
                color: #666;
                font-style: italic;
            }
            .hp-thwma-shortcode-row {
                display: none;
            }
            .hp-thwma-shortcode-row.visible {
                display: table-row;
            }
        </style>
        <script>
        jQuery(document).ready(function($) {
            // Current saved values
            var savedBillingDisplay = '<?php echo esc_js( $billing_display ); ?>';
            var savedShippingDisplay = '<?php echo esc_js( $shipping_display ); ?>';
            
            // Add "Shortcode" option to display type dropdowns
            var $billingDisplay = $('select[name="i_billing_display"]');
            var $shippingDisplay = $('select[name="i_shipping_display"]');
            
            // Add shortcode option to billing dropdown
            if ($billingDisplay.length && !$billingDisplay.find('option[value="shortcode_display"]').length) {
                $billingDisplay.append('<option value="shortcode_display">Shortcode</option>');
                // Set the saved value if it was shortcode_display
                if (savedBillingDisplay === 'shortcode_display') {
                    $billingDisplay.val('shortcode_display');
                }
            }
            
            // Add shortcode option to shipping dropdown
            if ($shippingDisplay.length && !$shippingDisplay.find('option[value="shortcode_display"]').length) {
                $shippingDisplay.append('<option value="shortcode_display">Shortcode</option>');
                // Set the saved value if it was shortcode_display
                if (savedShippingDisplay === 'shortcode_display') {
                    $shippingDisplay.val('shortcode_display');
                }
            }

            // Add shortcode input field for billing
            var billingShortcodeRow = `
                <tr class="hp-thwma-shortcode-row hp-billing-shortcode-row">
                    <td>Billing shortcode</td>
                    <td class="thwma-tooltip"></td>
                    <td>
                        <div class="hp-thwma-shortcode-field">
                            <input type="text" 
                                   name="hp_billing_shortcode" 
                                   value="<?php echo $billing_shortcode; ?>"
                                   placeholder='[hp_address_card_picker type="billing"]' />
                            <p class="description">Enter your address picker shortcode with parameters</p>
                        </div>
                    </td>
                </tr>
            `;
            
            // Add shortcode input field for shipping
            var shippingShortcodeRow = `
                <tr class="hp-thwma-shortcode-row hp-shipping-shortcode-row">
                    <td>Shipping shortcode</td>
                    <td class="thwma-tooltip"></td>
                    <td>
                        <div class="hp-thwma-shortcode-field">
                            <input type="text" 
                                   name="hp_shipping_shortcode" 
                                   value="<?php echo $shipping_shortcode; ?>"
                                   placeholder='[hp_address_card_picker type="shipping"]' />
                            <p class="description">Enter your address picker shortcode with parameters</p>
                        </div>
                    </td>
                </tr>
            `;

            // Insert rows after the display dropdowns
            var $billingDisplayRow = $billingDisplay.closest('tr');
            if ($billingDisplayRow.length && !$('.hp-billing-shortcode-row').length) {
                $billingDisplayRow.after(billingShortcodeRow);
            }

            var $shippingDisplayRow = $shippingDisplay.closest('tr');
            if ($shippingDisplayRow.length && !$('.hp-shipping-shortcode-row').length) {
                $shippingDisplayRow.after(shippingShortcodeRow);
            }

            // Toggle shortcode field visibility
            function toggleShortcodeField(selectElement, rowClass) {
                var $row = $('.' + rowClass);
                if ($(selectElement).val() === 'shortcode_display') {
                    $row.addClass('visible');
                } else {
                    $row.removeClass('visible');
                }
            }

            // Initial state
            toggleShortcodeField($billingDisplay, 'hp-billing-shortcode-row');
            toggleShortcodeField($shippingDisplay, 'hp-shipping-shortcode-row');

            // On change
            $billingDisplay.on('change', function() {
                toggleShortcodeField(this, 'hp-billing-shortcode-row');
            });

            $shippingDisplay.on('change', function() {
                toggleShortcodeField(this, 'hp-shipping-shortcode-row');
            });
        });
        </script>
        <?php
    }
}

// Initialize the extension
new HP_THWMA_Shortcode_Display();

/**
 * Class HP_THWMA_Checkout_Integration
 * 
 * Handles syncing HP Address Card Picker selections with WooCommerce checkout fields
 */
class HP_THWMA_Checkout_Integration {

    public function __construct() {
        add_action( 'wp_footer', array( $this, 'output_checkout_integration_script' ) );
    }

    /**
     * Output JavaScript that syncs address card selections with checkout fields
     */
    public function output_checkout_integration_script() {
        if ( ! is_checkout() ) {
            return;
        }
        ?>
        <script>
        (function() {
            'use strict';

            /**
             * Map of HP address fields to WooCommerce checkout field IDs
             */
            const fieldMappings = {
                billing: {
                    firstName: 'billing_first_name',
                    lastName: 'billing_last_name',
                    company: 'billing_company',
                    address1: 'billing_address_1',
                    address2: 'billing_address_2',
                    city: 'billing_city',
                    state: 'billing_state',
                    postcode: 'billing_postcode',
                    country: 'billing_country',
                    phone: 'billing_phone',
                    email: 'billing_email'
                },
                shipping: {
                    firstName: 'shipping_first_name',
                    lastName: 'shipping_last_name',
                    company: 'shipping_company',
                    address1: 'shipping_address_1',
                    address2: 'shipping_address_2',
                    city: 'shipping_city',
                    state: 'shipping_state',
                    postcode: 'shipping_postcode',
                    country: 'shipping_country',
                    phone: 'shipping_phone'
                }
            };

            /**
             * Country name to code mapping (common countries)
             */
            const countryNameToCode = {
                'United States': 'US',
                'United Kingdom': 'GB',
                'Canada': 'CA',
                'Australia': 'AU',
                'Germany': 'DE',
                'France': 'FR',
                'Italy': 'IT',
                'Spain': 'ES',
                'Netherlands': 'NL',
                'Belgium': 'BE',
                'Ireland': 'IE',
                'New Zealand': 'NZ',
                'Israel': 'IL',
            };

            /**
             * Get country code from country name
             */
            function getCountryCode(countryName) {
                if (countryName && countryName.length === 2) {
                    return countryName;
                }
                return countryNameToCode[countryName] || countryName;
            }

            /**
             * Fill checkout form fields with address data
             */
            function fillCheckoutFields(address, type) {
                const mapping = fieldMappings[type];
                if (!mapping || !address) return;

                console.log('[HP-THWMA] Filling ' + type + ' fields with:', address);

                // Handle country first (needed for state dropdown population)
                if (address.country) {
                    const countryCode = getCountryCode(address.country);
                    const countryField = document.getElementById(mapping.country);
                    if (countryField) {
                        countryField.value = countryCode;
                        jQuery(countryField).trigger('change');
                        
                        setTimeout(function() {
                            fillRemainingFields(address, mapping);
                        }, 300);
                        return;
                    }
                }

                fillRemainingFields(address, mapping);
            }

            /**
             * Fill remaining fields after country is set
             */
            function fillRemainingFields(address, mapping) {
                Object.keys(mapping).forEach(function(hpField) {
                    if (hpField === 'country') return;
                    
                    const wcFieldId = mapping[hpField];
                    const value = address[hpField] || '';
                    const field = document.getElementById(wcFieldId);
                    
                    if (field) {
                        field.value = value;
                        jQuery(field).trigger('change').trigger('input');
                    }
                });

                jQuery(document.body).trigger('update_checkout');
            }

            /**
             * Store selected address ID for ThemeHigh plugin compatibility
             */
            function storeSelectedAddressId(addressId, type) {
                const hiddenFieldName = 'thwma_hidden_field_' + type;
                let hiddenField = document.querySelector('input[name="' + hiddenFieldName + '"]');
                
                if (!hiddenField) {
                    hiddenField = document.createElement('input');
                    hiddenField.type = 'hidden';
                    hiddenField.name = hiddenFieldName;
                    const form = document.querySelector('form.checkout');
                    if (form) {
                        form.appendChild(hiddenField);
                    }
                }

                // Convert HP address ID format to THWMA format
                let thwmaKey = addressId;
                if (addressId.endsWith('_primary')) {
                    thwmaKey = 'selected_address';
                } else if (addressId.startsWith('th_')) {
                    const match = addressId.match(/th_(?:billing|shipping)_(\d+)/);
                    if (match) {
                        thwmaKey = 'address_' + match[1];
                    }
                }

                hiddenField.value = thwmaKey;
                console.log('[HP-THWMA] Stored address ID:', thwmaKey, 'for', type);
            }

            /**
             * Listen for HP Address Card Picker selection events
             */
            window.addEventListener('hpAddressSelected', function(e) {
                if (e.detail && e.detail.address && e.detail.type) {
                    fillCheckoutFields(e.detail.address, e.detail.type);
                    storeSelectedAddressId(e.detail.addressId || e.detail.address.id, e.detail.type);
                }
            });

            // Also listen for the hpRWAddressCopied event
            window.addEventListener('hpRWAddressCopied', function(e) {
                if (e.detail && e.detail.addresses && e.detail.toType) {
                    const selectedId = e.detail.selectedId;
                    const address = e.detail.addresses.find(function(addr) {
                        return addr.id === selectedId;
                    });
                    if (address) {
                        fillCheckoutFields(address, e.detail.toType);
                        storeSelectedAddressId(selectedId, e.detail.toType);
                    }
                }
            });

        })();
        </script>
        <?php
    }
}

// Initialize checkout integration
new HP_THWMA_Checkout_Integration();
