# Modular Funnel Shortcodes

This document describes the modular shortcode architecture for building sales funnels using React components embedded in WordPress via Elementor.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Elementor Template                                │
├─────────────────────────────────────────────────────────────────────┤
│  [hp_funnel_header funnel="illumodine"]                             │
│  [hp_funnel_hero_section funnel="illumodine"]                       │
│  [hp_funnel_benefits funnel="illumodine"]                           │
│  [hp_funnel_products funnel="illumodine"]                           │
│  [hp_funnel_features funnel="illumodine"]                           │
│  [hp_funnel_authority funnel="illumodine"]                          │
│  [hp_funnel_testimonials funnel="illumodine"]                       │
│  [hp_funnel_faq funnel="illumodine"]                                │
│  [hp_funnel_cta funnel="illumodine"]                                │
│  [hp_funnel_footer funnel="illumodine"]                             │
└─────────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    hp-funnel CPT (ACF)                              │
│  - All section data stored in one place                             │
│  - Configurable via WordPress admin                                  │
│  - Import/Export via JSON                                           │
└─────────────────────────────────────────────────────────────────────┘
```

## Available Shortcodes

### Core Page Structure

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[hp_funnel_header]` | Logo and navigation | `sticky`, `transparent` |
| `[hp_funnel_hero_section]` | Headline, image, CTA | `image_position`, `text_align`, `min_height` |
| `[hp_funnel_footer]` | Disclaimer, copyright, links | `show_copyright` |

### Content Sections

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[hp_funnel_benefits]` | Benefits grid with icons | `columns`, `show_cards`, `default_icon` |
| `[hp_funnel_products]` | Product showcase | `layout`, `show_prices`, `show_features` |
| `[hp_funnel_features]` | Feature cards/list | `columns`, `layout` |
| `[hp_funnel_authority]` | Expert bio and quotes | `layout` (side-by-side, centered, card) |
| `[hp_funnel_testimonials]` | Customer reviews | `columns`, `show_ratings`, `layout` |
| `[hp_funnel_faq]` | FAQ accordion | `allow_multiple` |
| `[hp_funnel_cta]` | Secondary CTA | `alignment`, `background` |

## Usage

### Basic Usage

All shortcodes require the `funnel` attribute (slug) or `id` attribute (post ID):

```
[hp_funnel_hero_section funnel="illumodine"]
```

Or by ID:

```
[hp_funnel_hero_section id="125153"]
```

### Override Attributes

Most shortcodes allow overriding default values from the CPT:

```
[hp_funnel_benefits funnel="illumodine" title="Custom Title" columns="2"]
```

### Layout Options

**Products Layout:**
- `grid` (default) - Grid of product cards
- `horizontal` - Single column list

**Features Layout:**
- `cards` (default) - Card grid
- `list` - Vertical list
- `grid` - Compact icon grid

**Authority Layout:**
- `side-by-side` (default) - Image left, content right
- `centered` - Centered layout
- `card` - Card container

**Testimonials Layout:**
- `cards` (default) - Card grid
- `carousel` - Horizontal scroll
- `simple` - Clean list

## Elementor Integration

### Creating a Funnel Template

1. Create a new Elementor template or page
2. Add Shortcode widgets for each section
3. Configure each shortcode with the funnel slug

Example structure:

```
Elementor Section (Full Width)
└── Shortcode: [hp_funnel_header funnel="my-funnel" sticky="true"]

Elementor Section (Full Width)
└── Shortcode: [hp_funnel_hero_section funnel="my-funnel"]

Elementor Section (Full Width)
└── Shortcode: [hp_funnel_benefits funnel="my-funnel"]

Elementor Section (Full Width)
└── Shortcode: [hp_funnel_products funnel="my-funnel"]

... additional sections ...

Elementor Section (Full Width)
└── Shortcode: [hp_funnel_footer funnel="my-funnel"]
```

### Mixing with Elementor Widgets

You can mix React shortcode sections with native Elementor widgets:

```
[hp_funnel_header funnel="my-funnel"]
[hp_funnel_hero_section funnel="my-funnel"]

<!-- Native Elementor section for custom content -->
<Elementor Testimonial Carousel Widget>

[hp_funnel_products funnel="my-funnel"]
[hp_funnel_cta funnel="my-funnel"]
[hp_funnel_footer funnel="my-funnel"]
```

## ACF Field Structure

The funnel CPT includes these ACF tabs:

| Tab | Fields |
|-----|--------|
| General | `funnel_slug`, `funnel_status`, `stripe_mode` |
| Header | `header_logo`, `header_logo_link`, `header_nav_items`, `header_sticky`, `header_transparent` |
| Hero | `hero_title`, `hero_subtitle`, `hero_tagline`, `hero_description`, `hero_image`, `hero_cta_text` |
| Benefits | `hero_benefits_title`, `hero_benefits` (repeater) |
| Products | `funnel_products` (repeater with SKU, name, price, features, etc.) |
| Features | `features_title`, `features_subtitle`, `features_list` (repeater) |
| Authority | `authority_title`, `authority_name`, `authority_credentials`, `authority_image`, `authority_bio`, `authority_quotes` |
| Testimonials | `testimonials_title`, `testimonials_list` (repeater) |
| FAQ | `faq_title`, `faq_list` (repeater) |
| CTA | `cta_title`, `cta_subtitle`, `cta_button_text`, `cta_button_url` |
| Checkout | `checkout_url`, `free_shipping_countries`, `global_discount_percent`, etc. |
| Thank You | `thankyou_url`, `thankyou_headline`, `thankyou_message`, upsell config |
| Styling | `accent_color`, `background_type`, `background_color`, `background_image`, `custom_css` |
| Footer | `footer_text`, `footer_disclaimer`, `footer_links` (repeater) |

## Complete Example: Illumodine Funnel Page

```html
<!-- In Elementor or as page content -->

[hp_funnel_header funnel="illumodine" sticky="true" transparent="true"]

[hp_funnel_hero_section funnel="illumodine" min_height="700px"]

[hp_funnel_benefits funnel="illumodine" columns="3"]

[hp_funnel_products funnel="illumodine" layout="grid"]

[hp_funnel_features funnel="illumodine" columns="3" layout="cards"]

[hp_funnel_authority funnel="illumodine" layout="side-by-side"]

[hp_funnel_testimonials funnel="illumodine" columns="3" show_ratings="true"]

[hp_funnel_faq funnel="illumodine"]

[hp_funnel_cta funnel="illumodine" background="gradient"]

[hp_funnel_footer funnel="illumodine"]
```

## Creating a New Funnel

1. **Create the CPT Entry:**
   - Go to HP Funnels → Add New
   - Fill in the funnel configuration in each tab
   - Set a unique slug (e.g., `my-new-funnel`)
   - Publish

2. **Create the Elementor Template:**
   - Create a new page
   - Add shortcode widgets for each section
   - Use `funnel="my-new-funnel"` in each shortcode

3. **Test and Iterate:**
   - Preview the page
   - Adjust ACF fields or shortcode attributes as needed

## Import/Export

Funnels can be exported and imported via the Export/Import tool:

- **Export:** HP Funnels → Export/Import → Select funnel → Export JSON
- **Import:** HP Funnels → Export/Import → Upload JSON → Import

This allows:
- Duplicating funnels across environments
- Version control of funnel configurations
- Quick setup of new funnels based on templates

## Shortcode Reference

### hp_funnel_header

```
[hp_funnel_header 
  funnel="slug" 
  sticky="true|false" 
  transparent="true|false"
]
```

### hp_funnel_hero_section

```
[hp_funnel_hero_section 
  funnel="slug" 
  image_position="right|left|background" 
  text_align="left|center|right" 
  min_height="600px"
]
```

### hp_funnel_benefits

```
[hp_funnel_benefits 
  funnel="slug" 
  columns="2|3|4" 
  show_cards="true|false" 
  default_icon="check|star|shield|heart"
  title="Override Title"
]
```

### hp_funnel_products

```
[hp_funnel_products 
  funnel="slug" 
  layout="grid|horizontal" 
  show_prices="true|false" 
  show_features="true|false"
  cta_text="Select"
]
```

### hp_funnel_features

```
[hp_funnel_features 
  funnel="slug" 
  columns="2|3|4" 
  layout="cards|list|grid"
]
```

### hp_funnel_authority

```
[hp_funnel_authority 
  funnel="slug" 
  layout="side-by-side|centered|card"
]
```

### hp_funnel_testimonials

```
[hp_funnel_testimonials 
  funnel="slug" 
  columns="2|3" 
  show_ratings="true|false" 
  layout="cards|carousel|simple"
]
```

### hp_funnel_faq

```
[hp_funnel_faq 
  funnel="slug" 
  allow_multiple="true|false"
]
```

### hp_funnel_cta

```
[hp_funnel_cta 
  funnel="slug" 
  alignment="center|left" 
  background="gradient|solid|transparent"
  button_text="Override Button Text"
]
```

### hp_funnel_footer

```
[hp_funnel_footer 
  funnel="slug" 
  show_copyright="true|false"
  disclaimer="Override disclaimer text"
]
```

