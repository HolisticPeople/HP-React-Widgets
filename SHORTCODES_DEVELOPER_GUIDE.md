# HP React Widgets - Shortcode Developer Guide

This document explains how external developers can add new React-based shortcodes to the **HP React Widgets** plugin in a safe, repeatable way.

The goals are:

- Minimize chances of breaking the build or PHP.
- Use clear naming and file structure conventions.
- Let the plugin **wizard** handle React mounting and shortcode registration for you.

---

## 1. File structure and naming conventions

- React components live under:
  - `src/components/YourWidgetName.tsx`
- Each component **must** export a React component with the same name:

```ts
// src/components/MyNewWidget.tsx
export const MyNewWidget = () => {
  return <div>My new widget</div>;
};
```

- Shortcode tags must:
  - Start with `hp_`
  - Use lowercase letters, numbers and underscores, e.g. `hp_my_new_widget`.

- The default root DOM ID convention is:
  - `hp-<shortcode_tag>-root` with underscores replaced by hyphens  
  - Example: `hp_my_new_widget` → `hp-hp_my_new_widget-root` (the wizard will suggest / normalize this for you, or you can set a custom ID).

---

## 2. How React widgets are mounted

The plugin uses a **generic mounting mechanism** in `src/main.tsx`:

- On page load, it looks for elements with:
  - `data-hp-widget="1"`
  - `data-component="YourWidgetName"`
  - `data-props="{...}"` (JSON-encoded initial props)

- It keeps a global registry of available components:

```ts
window.hpReactWidgets = {
  MultiAddress,
  MyAccountHeader,
  // MyNewWidget will be added to this in code
};
```

- Whenever a matching `<div>` is found, it:
  - Looks up the React component by `data-component`
  - Parses `data-props`
  - Calls `ReactDOM.createRoot(node).render(<Component {...props} />)`

You **do not** need to manually edit `main.tsx` when adding new shortcodes—the plugin already wires the generic mount path. You only need to follow the conventions described here.

---

## 3. Adding a new shortcode – step by step

### Step 1 – Add your React component

1. Create a file:
   - `src/components/YourWidgetName.tsx`
2. Export a React component whose name matches the filename:

```ts
// src/components/MyNewWidget.tsx
export const MyNewWidget = () => {
  return <div className="p-4 rounded bg-slate-900 text-white">My New Widget</div>;
};
```

3. Run `npm run build` locally to ensure the TypeScript builds without errors (recommended).

### Step 2 – (Optional) Add a hydrator PHP class

For anything beyond a static widget, you will typically want server-side hydration from WordPress / WooCommerce.

1. Create a file:
   - `includes/Shortcodes/MyNewWidgetShortcode.php`
2. Use the namespace `HP_RW\Shortcodes` and implement a `render($atts)` method:

```php
<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

class MyNewWidgetShortcode
{
    /**
     * Render the shortcode output.
     *
     * @param array $atts Shortcode attributes.
     */
    public function render(array $atts = []): string
    {
        // Ensure the React bundle is present.
        wp_enqueue_script(AssetLoader::HANDLE);

        // TODO: Replace this with real WooCommerce / WordPress data.
        $props = [
            'userId' => get_current_user_id(),
        ];

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr('hp-my-new-widget-root'),
            esc_attr('MyNewWidget'),
            esc_attr(wp_json_encode($props))
        );
    }
}
```

> Note: The wizard can also generate a **generic** container for you if you do not provide a hydrator class yet. In that case, `data-props` will default to `{}`.

### Step 3 – Use the Settings wizard to register the shortcode

In the WordPress admin on the target environment:

1. Go to **Settings → HP React Widgets**.
2. Scroll to **“Add a new shortcode (wizard)”**.
3. Fill out the form:
   - **Shortcode tag**: e.g. `hp_my_new_widget`
   - **Label**: human-friendly name, e.g. `My New Widget`
   - **Description**: what this widget does / where to use it
   - **React component name**: `MyNewWidget`
   - **Root DOM ID**:
     - Optional; if empty, a default will be generated.
     - Recommend: `hp-my-new-widget-root`
   - **Hydrator PHP class (optional)**:
     - If you created the class above, enter `MyNewWidgetShortcode`
4. Click **Register shortcode**.

The wizard will validate:

- That the shortcode tag is unique and correctly formatted.
- That `src/components/YourWidgetName.tsx` exists.
- That an optional hydrator file exists (if specified).

On success:

- The shortcode is added to the internal registry.
- It is **auto-enabled** in the Available Shortcodes table.
- A generic React mount path is ready to render your component.

### Step 4 – Use the shortcode in Elementor / content

- Insert the new shortcode where needed, e.g.:

```text
[hp_my_new_widget]
```

- When the page renders:
  - PHP will output the `<div>` container with `data-hp-widget="1"`.
  - The React bundle will mount `MyNewWidget` into that container with the hydrated props.

---

## 4. Updating or extending a shortcode

- To change **server-side data**:
  - Edit the corresponding hydrator class in `includes/Shortcodes/YourWidgetShortcode.php`.
  - Adjust the `$props` structure as needed.

- To change the **React UI**:
  - Edit `src/components/YourWidgetName.tsx`.
  - Run `npm run build` and commit the new dist.

- To change metadata (label / description):
  - Either adjust it in the Settings page or, for custom entries, update the `hp_rw_custom_shortcodes` option (usually via the Settings UI).

---

## 5. Summary of key conventions

- **Namespaces**:
  - PHP hydrators: `HP_RW\Shortcodes\YourWidgetShortcode`
- **Directories**:
  - React components: `src/components/*.tsx`
  - PHP hydrators: `includes/Shortcodes/*.php`
- **Attributes**:
  - `data-hp-widget="1"` — marks a container for React mounting.
  - `data-component="YourWidgetName"` — selects the React component.
  - `data-props="{...}"` — JSON-encoded initial props.

Following these conventions ensures your widgets plug into the HP React Widgets pipeline cleanly and are easy to maintain across environments and developers.


