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
 * @version 1.2.0
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
     * THWMA's option key (from THWMA_Utils::OPTION_KEY_THWMA_SETTINGS)
     */
    const THWMA_OPTION_KEY = 'thwma_general_settings';

    /**
     * Temporary storage for display values during save
     */
    private static $pending_display_values = array();

    /**
     * Initialize the extension
     */
    public function __construct() {
        // Capture our values from POST before anything else
        add_action( 'admin_init', array( $this, 'capture_posted_values' ), 1 );
        
        // Hook into the option update to inject our values
        add_filter( 'pre_update_option_' . self::THWMA_OPTION_KEY, array( $this, 'inject_shortcode_display_on_save' ), 10, 3 );
        
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
     * Capture posted values early in admin_init
     */
    public function capture_posted_values() {
        if ( ! isset( $_POST['save_settings'] ) ) {
            return;
        }

        // Capture the display values from POST
        self::$pending_display_values = array(
            'billing_display'    => isset( $_POST['i_billing_display'] ) ? sanitize_text_field( $_POST['i_billing_display'] ) : '',
            'shipping_display'   => isset( $_POST['i_shipping_display'] ) ? sanitize_text_field( $_POST['i_shipping_display'] ) : '',
            'billing_shortcode'  => isset( $_POST['hp_billing_shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_billing_shortcode'] ) ) : '',
            'shipping_shortcode' => isset( $_POST['hp_shipping_shortcode'] ) ? sanitize_text_field( wp_unslash( $_POST['hp_shipping_shortcode'] ) ) : '',
        );

        // Save our shortcode settings
        $our_settings = $this->get_settings();
        if ( ! empty( self::$pending_display_values['billing_shortcode'] ) ) {
            $our_settings['billing_shortcode'] = self::$pending_display_values['billing_shortcode'];
        }
        if ( ! empty( self::$pending_display_values['shipping_shortcode'] ) ) {
            $our_settings['shipping_shortcode'] = self::$pending_display_values['shipping_shortcode'];
        }
        // Also store display type in our settings as backup
        $our_settings['billing_display'] = self::$pending_display_values['billing_display'];
        $our_settings['shipping_display'] = self::$pending_display_values['shipping_display'];
        
        update_option( self::OPTION_KEY, $our_settings );
    }

    /**
     * Inject shortcode_display value when THWMA option is being saved
     * This filter runs right before the option is written to the database
     */
    public function inject_shortcode_display_on_save( $value, $old_value, $option ) {
        // Check if we have pending values to inject
        if ( empty( self::$pending_display_values ) ) {
            // No pending values from current save, check our stored settings
            $our_settings = $this->get_settings();
            if ( isset( $our_settings['billing_display'] ) && $our_settings['billing_display'] === 'shortcode_display' ) {
                if ( isset( $value['settings_billing'] ) ) {
                    $value['settings_billing']['billing_display'] = 'shortcode_display';
                }
            }
            if ( isset( $our_settings['shipping_display'] ) && $our_settings['shipping_display'] === 'shortcode_display' ) {
                if ( isset( $value['settings_shipping'] ) ) {
                    $value['settings_shipping']['shipping_display'] = 'shortcode_display';
                }
            }
            return $value;
        }

        // Inject billing shortcode_display if that's what was selected
        if ( self::$pending_display_values['billing_display'] === 'shortcode_display' ) {
            if ( isset( $value['settings_billing'] ) ) {
                $value['settings_billing']['billing_display'] = 'shortcode_display';
            }
        }

        // Inject shipping shortcode_display if that's what was selected
        if ( self::$pending_display_values['shipping_display'] === 'shortcode_display' ) {
            if ( isset( $value['settings_shipping'] ) ) {
                $value['settings_shipping']['shipping_display'] = 'shortcode_display';
            }
        }

        return $value;
    }

    /**
     * Get our custom settings
     */
    public function get_settings() {
        return get_option( self::OPTION_KEY, array(
            'billing_shortcode'  => '[hp_address_card_picker type="billing" show_actions="true"]',
            'shipping_shortcode' => '[hp_address_card_picker type="shipping" show_actions="true"]',
            'billing_display'    => '',
            'shipping_display'   => '',
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
        // First check THWMA settings
        $display_key = $type . '_display';
        $display_value = $this->get_thwma_setting( 'settings_' . $type, $display_key );
        
        if ( $display_value === 'shortcode_display' ) {
            return true;
        }
        
        // Fallback: check our own settings (backup)
        $our_settings = $this->get_settings();
        return isset( $our_settings[ $display_key ] ) && $our_settings[ $display_key ] === 'shortcode_display';
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

        if ( $this->is_shortcode_display( 'billing' ) && $this->is_enabled( 'billing' ) ) {
            $this->remove_thwma_billing_hooks();
        }

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

        // Get the display text from THWMA settings
        $display_text = $this->get_thwma_setting( 'settings_' . $type, $type . '_display_text' );
        if ( empty( $display_text ) ) {
            $display_text = $type === 'billing' ? 'Select a different billing address' : 'Select a different shipping address';
        }

        // Get button style settings
        $styles_settings = $this->get_thwma_setting( 'settings_styles' );
        $button_style = '';
        if ( ! empty( $styles_settings ) && isset( $styles_settings['enable_button_styles'] ) && $styles_settings['enable_button_styles'] === 'yes' ) {
            $bg_color = isset( $styles_settings['button_background_color'] ) ? $styles_settings['button_background_color'] : '#333';
            $text_color = isset( $styles_settings['button_text_color'] ) ? $styles_settings['button_text_color'] : '#fff';
            $padding = isset( $styles_settings['button_padding'] ) ? $styles_settings['button_padding'] : '10px 20px';
            $button_style = sprintf( 'background:%s;color:%s;padding:%s;', esc_attr( $bg_color ), esc_attr( $text_color ), esc_attr( $padding ) );
        }

        $container_id = 'hp-thwma-' . esc_attr( $type ) . '-' . uniqid();
        ?>
        <div class="hp-thwma-wrapper hp-thwma-wrapper-<?php echo esc_attr( $type ); ?>" style="margin: 15px 0;">
            <!-- Toggle Button -->
            <button type="button" 
                    class="hp-thwma-toggle-btn" 
                    data-target="<?php echo esc_attr( $container_id ); ?>"
                    style="<?php echo $button_style; ?>border:1px solid #555;border-radius:4px;cursor:pointer;display:inline-flex;align-items:center;gap:8px;<?php echo empty($button_style) ? 'padding:10px 20px;background:#333;color:#fff;' : ''; ?>">
                <span class="hp-thwma-toggle-icon">▼</span>
                <?php echo esc_html( $display_text ); ?>
            </button>
            
            <!-- Collapsible Address Picker Container -->
            <div id="<?php echo esc_attr( $container_id ); ?>" 
                 class="hp-thwma-shortcode-container hp-thwma-shortcode-<?php echo esc_attr( $type ); ?>" 
                 style="display:none; margin-top:15px; padding:15px; border:1px solid #444; border-radius:8px; background:rgba(0,0,0,0.2);">
                <?php
                $output = do_shortcode( $shortcode );
                
                if ( empty( trim( $output ) ) || $output === $shortcode ) {
                    echo '<div style="padding: 15px; background: #2a2a2a; border: 1px solid #444; border-radius: 8px; color: #ccc;">';
                    echo '<strong>Address Picker:</strong> The shortcode <code>' . esc_html( $shortcode ) . '</code> is not available. ';
                    echo 'Make sure the HP React Widgets plugin is active.';
                    echo '</div>';
                } else {
                    echo $output;
                }
                ?>
            </div>
        </div>
        <?php
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
        
        // Check current saved display values from THWMA settings
        $billing_display = $this->get_thwma_setting( 'settings_billing', 'billing_display' );
        $shipping_display = $this->get_thwma_setting( 'settings_shipping', 'shipping_display' );
        
        // Also check our backup settings
        if ( $billing_display !== 'shortcode_display' && isset( $settings['billing_display'] ) ) {
            $billing_display = $settings['billing_display'];
        }
        if ( $shipping_display !== 'shortcode_display' && isset( $settings['shipping_display'] ) ) {
            $shipping_display = $settings['shipping_display'];
        }
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
            
            console.log('[HP-THWMA] Saved billing display:', savedBillingDisplay);
            console.log('[HP-THWMA] Saved shipping display:', savedShippingDisplay);
            
            // Add "Shortcode" option to display type dropdowns
            var $billingDisplay = $('select[name="i_billing_display"]');
            var $shippingDisplay = $('select[name="i_shipping_display"]');
            
            // Add shortcode option to billing dropdown
            if ($billingDisplay.length && !$billingDisplay.find('option[value="shortcode_display"]').length) {
                $billingDisplay.append('<option value="shortcode_display">Shortcode</option>');
            }
            // Set the saved value if it was shortcode_display
            if (savedBillingDisplay === 'shortcode_display') {
                $billingDisplay.val('shortcode_display');
            }
            
            // Add shortcode option to shipping dropdown
            if ($shippingDisplay.length && !$shippingDisplay.find('option[value="shortcode_display"]').length) {
                $shippingDisplay.append('<option value="shortcode_display">Shortcode</option>');
            }
            // Set the saved value if it was shortcode_display
            if (savedShippingDisplay === 'shortcode_display') {
                $shippingDisplay.val('shortcode_display');
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
             * Extract country code from various formats:
             * - "US" (already a code)
             * - "United States" (full name)
             * - "United States (US)" (name with code in parentheses)
             */
            function getCountryCode(countryValue) {
                if (!countryValue) return '';
                
                // If already a 2-letter code, return as-is
                if (countryValue.length === 2 && countryValue === countryValue.toUpperCase()) {
                    return countryValue;
                }
                
                // Check for format "Country Name (XX)" - extract code from parentheses
                const parenMatch = countryValue.match(/\(([A-Z]{2})\)$/);
                if (parenMatch) {
                    return parenMatch[1];
                }
                
                // Fallback: lookup table for common country names
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
                    'Mexico': 'MX',
                    'Brazil': 'BR',
                    'Japan': 'JP',
                    'China': 'CN',
                    'India': 'IN',
                    'South Korea': 'KR',
                    'Singapore': 'SG',
                    'Switzerland': 'CH',
                    'Austria': 'AT',
                    'Sweden': 'SE',
                    'Norway': 'NO',
                    'Denmark': 'DK',
                    'Finland': 'FI',
                    'Poland': 'PL',
                    'Portugal': 'PT',
                    'Greece': 'GR',
                };
                
                return countryNameToCode[countryValue] || countryValue;
            }

            function fillCheckoutFields(address, type) {
                const mapping = fieldMappings[type];
                if (!mapping || !address) return;

                if (address.country) {
                    const countryCode = getCountryCode(address.country);
                    const countryField = document.getElementById(mapping.country);
                    if (countryField) {
                        countryField.value = countryCode;
                        jQuery(countryField).trigger('change').trigger('input');
                        
                        // Wait for WooCommerce to update state dropdown, then fill remaining fields
                        setTimeout(function() {
                            fillRemainingFields(address, mapping);
                        }, 500);
                        return;
                    }
                }

                fillRemainingFields(address, mapping);
            }

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

                // Trigger WooCommerce checkout update
                jQuery(document.body).trigger('update_checkout');
            }

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

            window.addEventListener('hpAddressSelected', function(e) {
                if (e.detail && e.detail.address && e.detail.type) {
                    fillCheckoutFields(e.detail.address, e.detail.type);
                    storeSelectedAddressId(e.detail.addressId || e.detail.address.id, e.detail.type);
                }
            });

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

/**
 * Add frontend CSS and JS for the collapsible address picker and modal z-index fix
 */
add_action( 'wp_footer', function() {
    if ( ! is_checkout() ) {
        return;
    }
    ?>
    <style>
    /* Fix modal z-index - ensure HP React Widgets modals appear above everything */
    [data-radix-portal],
    .hp-edit-address-modal,
    [role="dialog"],
    .fixed.inset-0 {
        z-index: 999999 !important;
    }
    
    /* Modal backdrop - DARK overlay */
    [data-radix-portal] > div:first-child,
    .fixed.inset-0.bg-black\/80,
    [data-radix-portal] [data-state="open"] + div,
    [role="dialog"] ~ div[aria-hidden="true"] {
        z-index: 999998 !important;
        background-color: rgba(0, 0, 0, 0.92) !important;
        backdrop-filter: blur(4px) !important;
    }
    
    /* Force dark backdrop on any overlay/backdrop elements */
    .bg-black\/80,
    [class*="bg-black"],
    [class*="backdrop"] {
        background-color: rgba(0, 0, 0, 0.92) !important;
    }
    
    /* Modal content */
    [data-radix-portal] [role="dialog"],
    .hp-edit-address-modal [role="dialog"] {
        z-index: 999999 !important;
    }
    
    /* Toggle button hover effect */
    .hp-thwma-toggle-btn:hover {
        opacity: 0.9;
    }
    
    /* Toggle icon rotation when expanded */
    .hp-thwma-toggle-btn.expanded .hp-thwma-toggle-icon {
        transform: rotate(180deg);
    }
    
    .hp-thwma-toggle-icon {
        transition: transform 0.2s ease;
        font-size: 10px;
    }
    
    /* Smooth expand/collapse animation */
    .hp-thwma-shortcode-container {
        transition: all 0.3s ease;
    }
    </style>
    
    <script>
    (function() {
        'use strict';
        
        // Toggle functionality for address picker containers
        document.addEventListener('click', function(e) {
            const toggleBtn = e.target.closest('.hp-thwma-toggle-btn');
            if (!toggleBtn) return;
            
            e.preventDefault();
            
            const targetId = toggleBtn.getAttribute('data-target');
            const container = document.getElementById(targetId);
            
            if (!container) return;
            
            const isHidden = container.style.display === 'none';
            
            if (isHidden) {
                container.style.display = 'block';
                toggleBtn.classList.add('expanded');
            } else {
                container.style.display = 'none';
                toggleBtn.classList.remove('expanded');
            }
        });
        
        // Ensure modals have proper z-index when they open
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        // Check for portal/modal containers
                        if (node.hasAttribute && node.hasAttribute('data-radix-portal')) {
                            node.style.zIndex = '999999';
                        }
                        // Check for dialog elements
                        const dialogs = node.querySelectorAll ? node.querySelectorAll('[role="dialog"]') : [];
                        dialogs.forEach(function(dialog) {
                            dialog.style.zIndex = '999999';
                        });
                    }
                });
            });
        });
        
        observer.observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php
}, 100 );
