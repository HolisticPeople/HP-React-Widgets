# HP React Widgets - WPCode Snippets

This folder contains WPCode snippets that extend functionality of HP React Widgets with other plugins.

---

## ThemeHigh Multiple Addresses - Shortcode Display Type

**File:** `thwma-shortcode-display-type.php`

> ⚠️ **DEPRECATED**: This snippet is no longer needed if you use the **HP Multi-Address** plugin.
> The HP Multi-Address plugin provides all this functionality built-in, plus additional features.
> If you're switching from ThemeHigh to HP Multi-Address, simply:
> 1. Activate the HP Multi-Address plugin
> 2. Deactivate the ThemeHigh Multiple Addresses plugin
> 3. Remove this WPCode snippet
> 4. Your existing address data will continue to work (no migration needed)

### Description

This snippet adds a third "Shortcode" option to the ThemeHigh Multiple Addresses Pro display type settings. Instead of using the built-in popup or dropdown for address selection during checkout, this allows you to use the HP Address Card Picker (or any other shortcode) for a more modern, React-based address selection experience.

### Features

- ✅ Adds "Shortcode" option to Display type dropdown (for both Billing and Shipping)
- ✅ Shows a shortcode input field when "Shortcode" is selected
- ✅ Automatically removes ThemeHigh's default rendering when shortcode is active
- ✅ Respects "Display position" setting (above/below the form)
- ✅ Default shortcode pre-configured for HP Address Card Picker

### Installation

1. **Go to WPCode** in your WordPress admin (`Code Snippets` → `+ Add Snippet`)
2. Click **"Create Your Own"** (blank snippet)
3. **Name:** `THWMA Shortcode Display Type`
4. **Code Type:** PHP Snippet
5. **Paste** the entire contents of `thwma-shortcode-display-type.php`
6. **Location:** Run Everywhere (or Frontend + Admin)
7. **Activate** the snippet

### Usage

1. After activating the snippet, go to **WooCommerce → Manage Address → General Settings**

2. In the **Billing Address Properties** section:
   - Change **Display type** from "Pop Up" or "Drop Down" to **"Shortcode"**
   - A new input field will appear: **"Billing shortcode"**
   - Enter your shortcode (default is already set)

3. Similarly for **Shipping Address Properties**:
   - Change **Display type** to **"Shortcode"**
   - Configure the **"Shipping shortcode"** field

4. Click **Save changes**

### Default Shortcodes

The snippet comes pre-configured with HP Address Card Picker shortcodes:

```
[hp_address_card_picker type="billing" show_actions="true"]
[hp_address_card_picker type="shipping" show_actions="true"]
```

### Customizing the Shortcode

You can customize the shortcode with additional parameters:

```
[hp_address_card_picker type="billing" show_actions="true" title="Select Billing Address"]
```

**Available Parameters:**

| Parameter | Default | Description |
|-----------|---------|-------------|
| `type` | `billing` | Address type: `billing` or `shipping` |
| `show_actions` | `true` | Show edit/delete/copy actions on cards |
| `title` | Auto (based on type) | Custom title for the address picker |

### How It Works

1. **Admin Side:** JavaScript injects the "Shortcode" option into ThemeHigh's display type dropdown and adds a text field for the shortcode value.

2. **Settings Storage:** Shortcode values are stored in a separate WordPress option (`hp_thwma_shortcode_settings`) to avoid conflicts with ThemeHigh's settings.

3. **Frontend:** When checkout loads:
   - The snippet checks if "shortcode_display" is selected for billing/shipping
   - If yes, it removes ThemeHigh's default rendering hooks
   - It then renders your configured shortcode at the correct position (above/below the form)

### Screenshots

**Admin Settings with Shortcode Option:**

The Display type dropdown will show three options:
- Drop Down
- Pop Up  
- **Shortcode** ← New!

When "Shortcode" is selected, an input field appears below where you can enter your custom shortcode.

### Checkout Integration

The snippet includes JavaScript that automatically syncs address selections with WooCommerce checkout fields. When you click on an address card:

1. The HP Address Card Picker dispatches an `hpAddressSelected` event
2. The integration script receives this event
3. All checkout form fields are populated with the selected address data
4. WooCommerce's checkout update is triggered
5. A hidden field stores the selected address ID for ThemeHigh compatibility

**Note:** After updating the AddressCardPicker component, you need to rebuild the React bundle:

```bash
cd HP-React-Widgets
npm run build
```

### Troubleshooting

**Q: The shortcode field doesn't appear?**
A: Make sure you're on the General Settings tab and the Display type dropdown shows "Shortcode" as selected. Clear browser cache if needed.

**Q: Both ThemeHigh's popup AND my shortcode appear?**
A: Clear your browser cache and any WordPress caching plugins. The snippet removes ThemeHigh's hooks, but aggressive caching might show old content.

**Q: Address selection doesn't populate the checkout fields?**
A: 
1. Make sure the HP React Widgets plugin is updated with the latest build (includes `hpAddressSelected` event)
2. Check browser console for errors
3. Verify the country name mapping includes your target countries (edit `countryNameToCode` in the snippet if needed)

**Q: State field doesn't populate correctly?**
A: The script waits 300ms after setting the country for WooCommerce to populate the state dropdown. You can increase this timeout in the snippet if needed.

**Q: Need to add more countries to the mapping?**
A: Find the `countryNameToCode` object in the integration script and add your country mappings:

```javascript
const countryNameToCode = {
    'United States': 'US',
    'Your Country Name': 'XX', // Add your mapping
    // ...
};
```

### Compatibility

- ✅ ThemeHigh Multiple Addresses Pro (tested with v2.x)
- ✅ HP React Widgets (requires the address card picker shortcode)
- ✅ WooCommerce 6.x, 7.x, 8.x, 9.x
- ✅ WordPress 6.x

### Changelog

**v1.0.0**
- Initial release
- Added Shortcode display type option
- Admin JS injection for settings page
- Frontend rendering with proper hook management

---

## Contributing

If you find bugs or want to suggest improvements, please contact the development team.

