# HP React Widgets - Developer Guide

This document provides comprehensive guidance for AI developers (like Lovable, Cursor, etc.) and human developers to add new React-based shortcodes to the **HP React Widgets** WordPress plugin.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Technology Stack](#2-technology-stack)
3. [File Structure](#3-file-structure)
4. [Dark Theme & CSS Variables](#4-dark-theme--css-variables)
5. [Fighting Theme CSS Conflicts](#5-fighting-theme-css-conflicts)
6. [TypeScript Interfaces](#6-typescript-interfaces)
7. [React Component Patterns](#7-react-component-patterns)
8. [REST API Endpoints](#8-rest-api-endpoints)
9. [WooCommerce & ThemeHigh Integration](#9-woocommerce--themehigh-integration)
10. [Adding a New Widget - Step by Step](#10-adding-a-new-widget---step-by-step)
11. [Build Process & Versioning](#11-build-process--versioning)
12. [Testing & Debugging](#12-testing--debugging)

---

## 1. Architecture Overview

The plugin uses a **hybrid PHP + React architecture**:

```
┌─────────────────────────────────────────────────────────────┐
│  WordPress/WooCommerce (PHP)                                │
│  ┌───────────────────┐  ┌─────────────────────────────────┐ │
│  │ Shortcode Handler │  │ REST API (AddressApi.php)       │ │
│  │ (Hydrator Class)  │  │ /hp-rw/v1/address/delete        │ │
│  │                   │  │ /hp-rw/v1/address/set-default   │ │
│  │ Outputs:          │  │ /hp-rw/v1/address/copy          │ │
│  │ <div              │  │ /hp-rw/v1/address/update        │ │
│  │   data-hp-widget  │  └─────────────────────────────────┘ │
│  │   data-component  │                                      │
│  │   data-props>     │                                      │
│  └───────────────────┘                                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  React Bundle (main.tsx)                                    │
│  ┌───────────────────┐  ┌─────────────────────────────────┐ │
│  │ Widget Registry   │  │ Component                       │ │
│  │ window.hpReact-   │──│ AddressCardPicker               │ │
│  │ Widgets = {       │  │ - Local state management        │ │
│  │   AddressCard...  │  │ - REST API calls (apiFetch)     │ │
│  │   MyAccountHeader │  │ - Event-based communication     │ │
│  │ }                 │  └─────────────────────────────────┘ │
│  └───────────────────┘                                      │
└─────────────────────────────────────────────────────────────┘
```

### Key Concepts

1. **Hydrator Classes** (PHP): Fetch data from WooCommerce/WordPress and serialize it as JSON props
2. **Widget Registry** (JS): Maps component names to React components
3. **Generic Mounting** (`main.tsx`): Finds all `[data-hp-widget="1"]` elements and mounts the appropriate React component
4. **REST API**: Handles mutations (create, update, delete) from the React frontend

---

## 2. Technology Stack

| Layer | Technology | Notes |
|-------|------------|-------|
| **Frontend** | React 18 | With StrictMode enabled |
| **Language** | TypeScript | Strict mode, path aliases (`@/`) |
| **Styling** | Tailwind CSS 3 | With custom dark theme |
| **UI Components** | shadcn/ui | Pre-built accessible components |
| **Build** | Vite | Fast HMR, optimized production builds |
| **Backend** | PHP 7.4+ | WordPress plugin standards |
| **API** | WP REST API | Namespaced under `hp-rw/v1` |

### Path Aliases

TypeScript is configured with path aliases in `tsconfig.json`:

```json
{
  "compilerOptions": {
    "paths": {
      "@/*": ["./src/*"]
    }
  }
}
```

Usage:
```typescript
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Address } from '@/types/address';
```

---

## 3. File Structure

```
HP-React-Widgets/
├── hp-react-widgets.php          # Main plugin file (version here!)
├── includes/
│   ├── Plugin.php                # Core plugin, registers default shortcodes
│   ├── AssetLoader.php           # Enqueues React bundle
│   ├── AddressApi.php            # REST API endpoints
│   ├── SettingsPage.php          # Admin settings UI
│   ├── ShortcodeRegistry.php     # Shortcode management
│   └── Shortcodes/
│       ├── AddressCardPickerShortcode.php
│       ├── MultiAddressShortcode.php
│       └── MyAccountHeaderShortcode.php
├── src/
│   ├── main.tsx                  # Entry point, widget mounting
│   ├── index.css                 # Tailwind + custom theme
│   ├── components/
│   │   ├── AddressCard.tsx       # Individual address card
│   │   ├── AddressCardPicker.tsx # Main slider component
│   │   ├── EditAddressModal.tsx  # Edit form modal
│   │   └── ui/                   # shadcn/ui components
│   │       ├── button.tsx
│   │       ├── dialog.tsx
│   │       ├── input.tsx
│   │       ├── tooltip.tsx
│   │       └── ... (50+ components)
│   ├── hooks/
│   │   └── use-toast.ts
│   ├── lib/
│   │   └── utils.ts              # cn() helper for classnames
│   └── types/
│       └── address.ts            # TypeScript interfaces
└── dist/                         # Built assets (committed)
    └── assets/
        ├── index-[hash].js
        └── index-[hash].css
```

### Naming Conventions

| Item | Convention | Example |
|------|------------|---------|
| Shortcode tag | `hp_snake_case` | `hp_address_card_picker` |
| React component | `PascalCase` | `AddressCardPicker` |
| Component file | `PascalCase.tsx` | `AddressCardPicker.tsx` |
| Hydrator class | `PascalCaseShortcode` | `AddressCardPickerShortcode` |
| Root DOM ID | `hp-kebab-case-root` | `hp-address-card-picker-root` |
| REST endpoint | `/hp-rw/v1/resource/action` | `/hp-rw/v1/address/delete` |

---

## 4. Dark Theme & CSS Variables

The plugin uses a **HolisticPeople dark theme** defined in `src/index.css`. All colors use HSL CSS variables for consistency.

### CSS Variables (defined in `:root`)

```css
:root {
    --background: 0 0% 7%;           /* Near black */
    --foreground: 0 0% 95%;          /* Off white */
    --card: 0 0% 10%;                /* Dark gray cards */
    --card-foreground: 0 0% 95%;
    --primary: 270 100% 64%;         /* Purple accent */
    --primary-foreground: 0 0% 100%;
    --secondary: 0 0% 15%;
    --muted: 0 0% 20%;
    --muted-foreground: 0 0% 65%;
    --border: 270 50% 40%;           /* Purple-tinted border */
    --destructive: 0 70% 50%;        /* Red for delete actions */
    --radius: 0.5rem;
}
```

### Using Theme Colors in Components

```tsx
// Use Tailwind classes that reference CSS variables:
<div className="bg-card text-foreground border-border">
  <button className="bg-primary text-primary-foreground hover:bg-primary/90">
    Click me
  </button>
</div>
```

### The `cn()` Helper

Use the `cn()` utility from `@/lib/utils` to merge classnames conditionally:

```tsx
import { cn } from '@/lib/utils';

<div className={cn(
  'base-class',
  isActive && 'active-class',
  variant === 'danger' && 'text-destructive'
)}>
```

---

## 5. Fighting Theme CSS Conflicts

**CRITICAL**: The WordPress theme (and Elementor) may inject aggressive CSS that overrides your styles. Here's how to win:

### Problem Example

Your Tailwind class `mb-1` (4px margin) gets overridden by theme CSS setting `margin-bottom: 45px`.

### Solution Hierarchy (weakest to strongest)

1. **Tailwind classes** (often overridden)
   ```tsx
   <div className="mb-1">  // May not work
   ```

2. **Tailwind `!important`** (use `!` prefix)
   ```tsx
   <div className="!mb-1">  // Compiles to margin-bottom: 0.25rem !important
   ```

3. **Inline styles** (strongest, always use for critical layout)
   ```tsx
   <div style={{ marginBottom: '4px' }}>  // Guaranteed to work
   ```

### Recommended Pattern

For any spacing/layout that must be exact, use inline styles:

```tsx
{/* Header with guaranteed tight spacing */}
<div 
  className="flex items-center justify-between" 
  style={{ marginBottom: '4px' }}
>
  <h3 
    className="text-lg font-semibold text-foreground"
    style={{ lineHeight: 1, margin: 0 }}
  >
    {title}
  </h3>
</div>
```

### CSS Reset for Custom Elements

For elements that need complete isolation from theme styles:

```css
.my-custom-element {
    all: unset;
    display: block;
    /* Now define everything explicitly */
}
```

---

## 6. TypeScript Interfaces

Define your data structures in `src/types/`. Here's the Address example:

### `src/types/address.ts`

```typescript
export interface Address {
  id: string;              // 'billing_primary', 'shipping_primary', 'th_billing_0', etc.
  firstName: string;
  lastName: string;
  company?: string;
  address1: string;
  address2?: string;
  city: string;
  state: string;
  postcode: string;
  country: string;
  phone?: string;
  email?: string;          // Only for billing
  isDefault: boolean;
  label?: string;          // Optional display label like "#1", "#2"
}

export type AddressType = 'billing' | 'shipping';

export interface AddressCardPickerProps {
  addresses: Address[];
  type: AddressType;
  selectedId?: string;
  showActions?: boolean;
  title?: string;
  editUrl?: string;
}
```

### Props Interface Convention

Always define a `Props` interface for your component:

```typescript
interface MyWidgetProps {
  // Required props (no ?)
  userId: number;
  items: Item[];
  
  // Optional props (with ?)
  title?: string;
  onSelect?: (item: Item) => void;
}

export const MyWidget = ({ userId, items, title, onSelect }: MyWidgetProps) => {
  // ...
};
```

---

## 7. React Component Patterns

### 7.1 Custom SVG Icons

**DO NOT USE `lucide-react`** - theme CSS often breaks these icons.

Instead, define inline SVG icons with explicit stroke/fill:

```tsx
// ✅ CORRECT: Custom inline SVG
const HpEditIcon = () => (
  <svg className="hp-icon" viewBox="0 0 24 24" aria-hidden="true">
    <path
      d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
    <path
      d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

// ❌ WRONG: lucide-react (often broken by themes)
import { Pencil } from 'lucide-react';
```

CSS for custom icons:

```css
.hp-icon {
    all: unset;
    display: inline-block !important;
    width: 1.125rem !important;
    height: 1.125rem !important;
    color: currentColor !important;
}

.hp-icon * {
    stroke: currentColor !important;
}
```

### 7.2 Tooltips

All widgets are wrapped in `<TooltipProvider>` by `main.tsx`, so you can use tooltips directly:

```tsx
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip';

<Tooltip>
  <TooltipTrigger asChild>
    <button className="action-btn">
      <HpEditIcon />
    </button>
  </TooltipTrigger>
  <TooltipContent className="tooltip-content">
    <p>Edit address</p>
  </TooltipContent>
</Tooltip>
```

### 7.3 Modals/Dialogs

Use shadcn/ui Dialog component:

```tsx
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';

const [isOpen, setIsOpen] = useState(false);

<Dialog open={isOpen} onOpenChange={setIsOpen}>
  <DialogContent className="bg-card border-border">
    <DialogHeader>
      <DialogTitle>Edit Address</DialogTitle>
    </DialogHeader>
    {/* Form content */}
  </DialogContent>
</Dialog>
```

### 7.4 REST API Calls with `apiFetch`

Use WordPress's built-in `apiFetch` for REST calls:

```tsx
declare const wp: {
  apiFetch: <T>(options: {
    path: string;
    method?: string;
    data?: Record<string, unknown>;
  }) => Promise<T>;
};

interface ApiResponse {
  success: boolean;
  addresses: Address[];
  selectedId?: string;
}

const handleDelete = async (address: Address) => {
  try {
    const response = await wp.apiFetch<ApiResponse>({
      path: '/hp-rw/v1/address/delete',
      method: 'POST',
      data: { type, id: address.id },
    });
    
    if (response.success) {
      setItems(response.addresses);
      setActiveId(response.selectedId || null);
    }
  } catch (error) {
    console.error('Delete failed:', error);
  }
};
```

### 7.5 Event-Based Cross-Component Communication

When one widget needs to notify another (e.g., copy from billing to shipping):

```tsx
// Emitting component (AddressApi response handler)
window.dispatchEvent(
  new CustomEvent('hpRWAddressCopied', {
    detail: {
      fromType: 'billing',
      toType: 'shipping',
      addresses: response.addresses,
      selectedId: response.selectedId,
    },
  })
);

// Receiving component (in useEffect)
useEffect(() => {
  const handleAddressCopied = (e: CustomEvent<AddressCopiedEventDetail>) => {
    if (e.detail.toType === type) {
      setItems(e.detail.addresses);
      setActiveId(e.detail.selectedId || null);
    }
  };

  window.addEventListener('hpRWAddressCopied', handleAddressCopied as EventListener);
  return () => {
    window.removeEventListener('hpRWAddressCopied', handleAddressCopied as EventListener);
  };
}, [type]);
```

---

## 8. REST API Endpoints

### Namespace: `hp-rw/v1`

All endpoints require user authentication (`is_user_logged_in()`).

### Available Endpoints

| Endpoint | Method | Parameters | Description |
|----------|--------|------------|-------------|
| `/address/delete` | POST | `type`, `id` | Delete a ThemeHigh address |
| `/address/set-default` | POST | `type`, `id` | Promote address to WooCommerce default |
| `/address/copy` | POST | `type`, `id` | Copy address to opposite type (creates new TH entry) |
| `/address/update` | POST | `type`, `id`, `address` | Update address fields |

### Response Format

All endpoints return:

```typescript
interface ApiResponse {
  success: boolean;
  type: 'billing' | 'shipping';
  addresses: Address[];      // Full refreshed list
  selectedId?: string;       // Currently selected/default ID
  message?: string;          // Error message if success=false
}
```

### Creating New Endpoints

```php
// In includes/AddressApi.php or a new API class

public function register_routes(): void
{
    register_rest_route(
        'hp-rw/v1',
        '/my-resource/action',
        [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle_action'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'param1' => [
                    'required'          => true,
                    'validate_callback' => function ($value): bool {
                        return !empty($value);
                    },
                ],
            ],
        ]
    );
}

public function handle_action(WP_REST_Request $request)
{
    $param1 = sanitize_text_field($request->get_param('param1'));
    
    // Do work...
    
    return rest_ensure_response([
        'success' => true,
        'data'    => $result,
    ]);
}
```

---

## 9. WooCommerce & ThemeHigh Integration

### Address ID Conventions

| ID Format | Source | Example |
|-----------|--------|---------|
| `billing_primary` | WooCommerce default billing | — |
| `shipping_primary` | WooCommerce default shipping | — |
| `th_billing_0` | ThemeHigh additional billing #1 | — |
| `th_shipping_2` | ThemeHigh additional shipping #3 | — |

### Reading WooCommerce Addresses

```php
$customer = new \WC_Customer(get_current_user_id());

$billing = [
    'firstName' => $customer->get_billing_first_name(),
    'lastName'  => $customer->get_billing_last_name(),
    'company'   => $customer->get_billing_company(),
    'address1'  => $customer->get_billing_address_1(),
    'address2'  => $customer->get_billing_address_2(),
    'city'      => $customer->get_billing_city(),
    'state'     => $customer->get_billing_state(),
    'postcode'  => $customer->get_billing_postcode(),
    'country'   => $customer->get_billing_country(),
    'phone'     => $customer->get_billing_phone(),
    'email'     => $customer->get_billing_email(),
];

// For shipping, use get_shipping_* methods
// Note: shipping has get_shipping_phone() but no email
```

### Reading ThemeHigh Multi-Address Data

```php
$thwma = get_user_meta($user_id, 'thwma_custom_address', true);

if (is_array($thwma) && isset($thwma[$type])) {
    foreach ($thwma[$type] as $key => $entry) {
        $addresses[] = [
            'id'        => "th_{$type}_{$key}",
            'firstName' => $entry["{$type}_first_name"] ?? '',
            'lastName'  => $entry["{$type}_last_name"] ?? '',
            'company'   => $entry["{$type}_company"] ?? '',
            'address1'  => $entry["{$type}_address_1"] ?? '',
            'address2'  => $entry["{$type}_address_2"] ?? '',
            'city'      => $entry["{$type}_city"] ?? '',
            'state'     => $entry["{$type}_state"] ?? '',
            'postcode'  => $entry["{$type}_postcode"] ?? '',
            'country'   => $entry["{$type}_country"] ?? '',
            'phone'     => $entry["{$type}_phone"] ?? '',
            'email'     => $type === 'billing' ? ($entry['billing_email'] ?? '') : '',
            'isDefault' => false,
            'label'     => '#' . ($counter++),
        ];
    }
}
```

### Writing ThemeHigh Addresses

```php
$thwma = get_user_meta($user_id, 'thwma_custom_address', true);
if (!is_array($thwma)) {
    $thwma = [];
}

// Add new entry
$thwma[$type][] = [
    "{$type}_first_name" => $data['firstName'],
    "{$type}_last_name"  => $data['lastName'],
    // ... all fields with type prefix
];

update_user_meta($user_id, 'thwma_custom_address', $thwma);
```

---

## 10. Adding a New Widget - Step by Step

### Step 1: Define TypeScript Interface

Create `src/types/mywidget.ts`:

```typescript
export interface MyWidgetItem {
  id: string;
  name: string;
  value: number;
}

export interface MyWidgetProps {
  items: MyWidgetItem[];
  title?: string;
  onSelect?: (item: MyWidgetItem) => void;
}
```

### Step 2: Create React Component

Create `src/components/MyWidget.tsx`:

```tsx
import { useState } from 'react';
import { MyWidgetProps, MyWidgetItem } from '@/types/mywidget';
import { cn } from '@/lib/utils';

export const MyWidget = ({ items, title = 'My Widget' }: MyWidgetProps) => {
  const [selected, setSelected] = useState<string | null>(null);

  return (
    <div className="bg-card rounded-lg border border-border p-4">
      <h3 
        className="text-lg font-semibold text-foreground"
        style={{ marginBottom: '8px', lineHeight: 1 }}
      >
        {title}
      </h3>
      
      <div className="space-y-2">
        {items.map((item) => (
          <div
            key={item.id}
            className={cn(
              'p-3 rounded border cursor-pointer transition-colors',
              selected === item.id 
                ? 'border-primary bg-primary/10' 
                : 'border-border hover:border-primary/50'
            )}
            onClick={() => setSelected(item.id)}
          >
            <span className="text-foreground">{item.name}</span>
          </div>
        ))}
      </div>
    </div>
  );
};
```

### Step 3: Register in Widget Registry

Edit `src/main.tsx`:

```tsx
import { MyWidget } from '@/components/MyWidget';

// Add to registry
widgetRegistry.MyWidget = MyWidget;
```

### Step 4: Create PHP Hydrator

Create `includes/Shortcodes/MyWidgetShortcode.php`:

```php
<?php
namespace HP_RW\Shortcodes;

use HP_RW\AssetLoader;

class MyWidgetShortcode
{
    public function render(array $atts = []): string
    {
        wp_enqueue_script(AssetLoader::HANDLE);

        $atts = shortcode_atts([
            'title' => 'My Widget',
        ], $atts);

        // Fetch data from WordPress/WooCommerce
        $items = $this->get_items();

        $props = [
            'items' => $items,
            'title' => $atts['title'],
        ];

        $root_id = 'hp-my-widget-' . uniqid();

        return sprintf(
            '<div id="%s" data-hp-widget="1" data-component="%s" data-props="%s"></div>',
            esc_attr($root_id),
            esc_attr('MyWidget'),
            esc_attr(wp_json_encode($props))
        );
    }

    private function get_items(): array
    {
        // Your data fetching logic here
        return [
            ['id' => '1', 'name' => 'Item 1', 'value' => 100],
            ['id' => '2', 'name' => 'Item 2', 'value' => 200],
        ];
    }
}
```

### Step 5: Register via Admin Settings

1. Go to **Settings → HP React Widgets**
2. Fill out the wizard:
   - **Shortcode tag**: `hp_my_widget`
   - **Label**: `My Widget`
   - **Description**: `Displays a list of items with selection`
   - **React component**: `MyWidget`
   - **Hydrator class**: `MyWidgetShortcode`
3. Click **Register shortcode**

### Step 6: Use the Shortcode

```
[hp_my_widget title="Custom Title"]
```

---

## 11. Build Process & Versioning

### Development Build

```bash
npm run dev    # Start Vite dev server with HMR
```

### Production Build

```bash
npm run build  # Creates optimized dist/assets/
```

### Version Management

**CRITICAL**: Always increment the version in `hp-react-widgets.php` when pushing changes:

```php
/**
 * Plugin Name:       HP React Widgets
 * Version:           0.0.56  // <-- INCREMENT THIS
 */

define('HP_RW_VERSION', '0.0.56');  // <-- AND THIS
```

The version appears in:
1. Plugin header (WordPress reads this)
2. `HP_RW_VERSION` constant (used for cache busting)
3. Admin footer label

### Git Workflow

```bash
# After making changes:
git add -A
git commit -m "v0.0.XX: Brief description of changes"
git push

# The GitHub Action will auto-deploy to Kinsta staging
```

---

## 12. Testing & Debugging

### Browser DevTools Checklist

1. **Console**: Check for React errors, missing components
2. **Network**: Verify REST API calls succeed (200 OK)
3. **Elements**: Inspect computed styles to find CSS conflicts
4. **Application**: Check for hydration data in `data-props`

### Common Issues

| Symptom | Cause | Solution |
|---------|-------|----------|
| Component not rendering | Not in registry | Add to `main.tsx` widgetRegistry |
| "must be used within TooltipProvider" | Missing provider | Already wrapped in `main.tsx`, check for duplicate mounting |
| Icons not showing | Theme CSS hiding SVGs | Use custom inline SVGs with `.hp-icon` class |
| Spacing wrong | Theme CSS override | Use inline `style={{ }}` instead of Tailwind classes |
| REST API 403 | User not logged in | Check `is_user_logged_in()` |
| Data not updating | Stale props | Use local state, update after API calls |

### Debug PHP Hydration

```php
// Temporarily add to your shortcode render():
error_log('MyWidget props: ' . wp_json_encode($props));
```

Check `/wp-content/debug.log` (requires `WP_DEBUG_LOG` enabled).

### Debug React State

```tsx
// Add to your component:
useEffect(() => {
  console.log('[MyWidget] State changed:', { items, selected });
}, [items, selected]);
```

---

## Quick Reference Card

```
┌──────────────────────────────────────────────────────────────┐
│ NAMING                                                       │
│ Shortcode: hp_my_widget                                      │
│ Component: MyWidget                                          │
│ Hydrator:  MyWidgetShortcode                                 │
│ Root ID:   hp-my-widget-root                                 │
├──────────────────────────────────────────────────────────────┤
│ FILES                                                        │
│ React:     src/components/MyWidget.tsx                       │
│ Types:     src/types/mywidget.ts                             │
│ PHP:       includes/Shortcodes/MyWidgetShortcode.php         │
│ Registry:  src/main.tsx (add to widgetRegistry)              │
├──────────────────────────────────────────────────────────────┤
│ CSS OVERRIDE PRIORITY                                        │
│ 1. Tailwind class     → often overridden                     │
│ 2. !important class   → !mb-1                                │
│ 3. Inline style       → style={{ marginBottom: '4px' }}      │
├──────────────────────────────────────────────────────────────┤
│ API RESPONSE FORMAT                                          │
│ { success: bool, data: T, message?: string }                 │
├──────────────────────────────────────────────────────────────┤
│ ALWAYS INCREMENT VERSION IN hp-react-widgets.php             │
└──────────────────────────────────────────────────────────────┘
```

---

*Last updated: December 2024 | Plugin version: 0.0.56*
