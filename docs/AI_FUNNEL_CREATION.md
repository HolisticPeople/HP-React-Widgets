# AI Funnel Creation Guide

This document provides comprehensive documentation for AI agents creating and managing HP Funnels.

## Overview

HP Funnels are custom post types (`hp-funnel`) that define complete sales funnels with modular sections, product offers, and integrated checkout. Each funnel is a self-contained landing page with styling, content sections, offers, and a Stripe-powered checkout flow.

## REST API Endpoints

All AI-related endpoints are prefixed with `/wp-json/hp-rw/v1/ai/`.

### System & Schema

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ai/system/explain` | GET | Complete system documentation including sections, offer types, styling |
| `/ai/schema` | GET | JSON schema with AI generation hints |
| `/ai/styling/schema` | GET | Styling-specific schema with theme presets |

### Funnel CRUD

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ai/funnels` | GET | List all funnels with metadata |
| `/ai/funnels` | POST | Create new funnel |
| `/ai/funnels/{slug}` | GET | Get complete funnel JSON |
| `/ai/funnels/{slug}` | PUT | Update funnel |
| `/ai/funnels/{slug}` | DELETE | Delete funnel |
| `/ai/funnels/{slug}/sections` | POST | Update specific sections |
| `/ai/funnels/{slug}/section/{name}` | GET | Get specific section data |

### Version Control

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ai/funnels/{slug}/versions` | GET | List all versions |
| `/ai/funnels/{slug}/versions` | POST | Create backup/version |
| `/ai/funnels/{slug}/versions/{id}` | GET | Get version snapshot |
| `/ai/funnels/{slug}/versions/{id}/restore` | POST | Restore version |
| `/ai/funnels/{slug}/versions/diff` | GET | Compare two versions |

### Products & Economics

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/ai/products` | GET | Search products with filters |
| `/ai/products/{sku}` | GET | Get product details with economics |
| `/ai/products/calculate-supply` | POST | Calculate supply needs for protocol |
| `/ai/products/categories` | GET | Get product categories |
| `/ai/protocols/build-kit` | POST | Build kit from protocol definition |
| `/ai/economics/calculate` | POST | Calculate offer profitability |
| `/ai/economics/validate-offer` | POST | Validate offer against guidelines |
| `/ai/economics/guidelines` | GET/PUT | Get/update economic guidelines |
| `/ai/economics/shipping-strategy` | POST | Get shipping strategy recommendation |

## Workflow Examples

### 1. Creating a New Funnel from Scratch

```
1. GET /ai/system/explain → Understand funnel structure
2. GET /ai/schema → Get schema with AI generation hints
3. GET /ai/products?category=supplements → Find available products
4. POST /ai/economics/validate-offer → Validate pricing before creation
5. POST /ai/funnels → Create the funnel with validated data
```

### 2. Creating a Funnel from a Protocol

```
1. POST /ai/protocols/build-kit with protocol definition:
   {
     "supplements": [
       {"sku": "ILL-LARGE", "servings_per_day": 3},
       {"sku": "SEL-200MCG", "servings_per_day": 1}
     ],
     "duration_days": 90
   }
   
2. Review kit suggestions and economic analysis
3. POST /ai/funnels with complete funnel data including offers
```

### 3. Updating an Existing Funnel

```
1. GET /ai/funnels/{slug} → Get current funnel state
2. POST /ai/funnels/{slug}/versions → Create backup before changes
3. POST /ai/funnels/{slug}/sections → Update specific sections
4. GET /ai/funnels/{slug}/versions/diff?from=v1&to=current → Review changes
```

### 4. Using Reference Funnel

```
1. GET /ai/funnels → List available funnels
2. GET /ai/funnels/illumodine → Get reference funnel as template
3. Modify template for new product
4. POST /ai/funnels → Create new funnel based on template
```

## Decision Points

AI agents should present choices at key decision points rather than making autonomous decisions. This keeps the admin in control while leveraging AI for speed and suggestions.

### Offer Structure Decisions

**Kit Type:**
- `fixed_bundle` - Best for protocol kits where products are pre-determined
- `customizable_kit` - Best for flexible bundles where customer picks products
- `tiered_singles` - Best for simple product offerings at different quantities

**Discount Strategy:**
- `percent_off` - Easier to communicate ("Save 20%")
- `fixed_discount` - Better for high-value items ("$50 off")
- `tiered_pricing` - Volume-based discounts

**Featured Offer:**
- Choose the offer with the best margin that still provides customer value
- Use clear badges like "BEST VALUE" or "MOST POPULAR"

### Content Decisions

**Headline Style:**
- `benefit-focused` - "Transform Your Health Today"
- `problem-focused` - "Tired of Low Energy?"
- `curiosity-driven` - "The Secret to Optimal Thyroid Function"

**Tone:**
- `professional` - Formal, authoritative
- `conversational` - Friendly, approachable
- `scientific` - Data-driven, technical
- `urgent` - Limited time, scarcity

**Sections to Include:**
- `minimal` - header, hero, products, footer
- `standard` - + benefits, testimonials, faq
- `comprehensive` - All sections including science, authority, features

### Styling Decisions

**Theme Direction:**
- `dark` - Premium feel, recommended for health products
- `light` - Clean, professional
- `match_product_branding` - Extract colors from product labels

**Accent Color:**
- Extract from product label for brand consistency
- Use theme presets as starting point
- Ensure sufficient contrast with background

## Economic Guidelines

All offers should meet these configurable guidelines:

```json
{
  "min_margin_percent": 10,
  "min_profit_dollars": 50,
  "pricing_strategy": "value_based",
  "free_shipping_threshold_us": 100
}
```

### Shipping Strategy

**Domestic (US):**
- Free shipping for orders over $100
- Below threshold: Customer pays actual rate

**International:**
- Tiered subsidy based on order profitability:
  - Profit $100+: 75% subsidy
  - Profit $50-99: 50% subsidy
  - Profit $25-49: 25% subsidy
  - Profit <$25: No subsidy

## Content Guidelines

### FDA Compliance

**Safe Language:**
- "Supports healthy [function]"
- "May help with [wellness goal]"
- "Promotes [positive state]"

**Avoid:**
- "Cures [condition]"
- "Treats [disease]"
- "Prevents [illness]"
- "Guaranteed results"

### Required Disclaimer

All supplement funnels must include:
> These statements have not been evaluated by the FDA. This product is not intended to diagnose, treat, cure or prevent any disease.

### Section Content Guidelines

**Hero Title:**
- 3-8 words
- Action-oriented, benefit-focused
- Examples: "Transform Your Health Today", "Unlock Your Natural Energy"

**Benefits:**
- 6-12 items
- 5-15 words each
- Specific outcomes, not generic claims

**Testimonials:**
- 3-6 testimonials
- Must be real (never generate fake testimonials)
- Use placeholders if none available

**FAQ:**
- 4-8 questions
- Address common objections
- Include shipping, returns, usage questions

## Theme Presets

### Dark Gold (Default)
```json
{
  "accent_color": "#eab308",
  "page_bg_color": "#121212",
  "card_bg_color": "#1a1a1a",
  "text_color_basic": "#e5e5e5"
}
```

### Dark Purple
```json
{
  "accent_color": "#7c3aed",
  "page_bg_color": "#0f0f1a",
  "card_bg_color": "#1a1a2e",
  "text_color_basic": "#e5e5e5"
}
```

### Dark Green
```json
{
  "accent_color": "#22c55e",
  "page_bg_color": "#0f1a0f",
  "card_bg_color": "#1a2e1a",
  "text_color_basic": "#e5e5e5"
}
```

### Light Blue
```json
{
  "accent_color": "#3b82f6",
  "page_bg_color": "#f8fafc",
  "card_bg_color": "#ffffff",
  "text_color_basic": "#1e293b"
}
```

## Example Funnel JSON

```json
{
  "$schema": "hp-funnel/v1",
  "funnel": {
    "name": "Illumodine",
    "slug": "illumodine",
    "status": "active"
  },
  "hero": {
    "title": "Transform Your Health",
    "subtitle": "With Nascent Iodine",
    "tagline": "The purest form of iodine for optimal thyroid support",
    "cta_text": "Get Your Special Offer Now"
  },
  "benefits": {
    "title": "Why Choose Illumodine?",
    "items": [
      {"text": "Supports healthy thyroid function", "icon": "check"},
      {"text": "Boosts natural energy levels", "icon": "check"},
      {"text": "100% pure and vegan formula", "icon": "shield"}
    ]
  },
  "offers": [
    {
      "id": "offer-small",
      "name": "Small Bottle (0.5 oz)",
      "type": "single",
      "product_sku": "ILL-SMALL",
      "quantity": 1,
      "discount_type": "none"
    },
    {
      "id": "offer-best-value",
      "name": "90-Day Supply Kit",
      "type": "fixed_bundle",
      "badge": "BEST VALUE",
      "is_featured": true,
      "discount_label": "Save 20%",
      "discount_type": "percent",
      "discount_value": 20,
      "bundle_items": [
        {"sku": "ILL-LARGE", "qty": 2},
        {"sku": "SEL-200MCG", "qty": 1}
      ]
    }
  ],
  "styling": {
    "accent_color": "#eab308",
    "page_bg_color": "#121212",
    "text_color_basic": "#e5e5e5"
  },
  "footer": {
    "disclaimer": "These statements have not been evaluated by the FDA..."
  }
}
```

## Best Practices

### Content
- Keep hero title under 8 words
- Use specific benefit statements, not generic claims
- Include social proof (testimonials) for higher conversion
- Address top 4-6 objections in FAQ
- Never generate fake testimonials

### Offers
- Always have at least 2 offers for comparison effect
- Feature the best-value offer (not necessarily cheapest)
- Use clear discount badges
- Ensure all offers meet minimum profit margins

### Styling
- Match funnel colors to product branding
- Dark themes convert better for premium/health products
- Ensure CTA buttons have high contrast
- Use consistent styling across all sections

### Version Control
- Create backup before any AI modifications
- Use descriptive version notes
- Keep last 10 versions for rollback capability

## Admin UI Integration

### WordPress Admin Pages

1. **HP Funnels List** (`/wp-admin/edit.php?post_type=hp-funnel`)
   - Shows all funnels with Economics, Versions, Last Modified columns
   
2. **Funnel Edit Page**
   - Version History meta box - Quick access to versions
   - Economics Summary meta box - Profit/margin at a glance
   - AI Activity meta box - Recent AI actions on this funnel

3. **AI Activity Log** (`/wp-admin/edit.php?post_type=hp-funnel&page=hp-funnel-ai-activity`)
   - All AI actions across all funnels
   
4. **Economics Dashboard** (`/wp-admin/edit.php?post_type=hp-funnel&page=hp-funnel-economics`)
   - Profitability overview for all funnels
   
5. **AI Settings** (`/wp-admin/admin.php?page=hp-rw-ai-settings`)
   - Configure economic guidelines, shipping rules, version settings

## Troubleshooting

### Common Issues

**"Funnel not found" error:**
- Check that the slug is correct and lowercase
- Verify the funnel is published (not draft)

**"Validation failed" error:**
- Use `/ai/schema` to verify JSON structure
- Ensure required fields (funnel.name, funnel.slug) are present
- Check offer types have required fields

**Economics validation failing:**
- Check current guidelines with `GET /ai/economics/guidelines`
- Verify product costs are set in WooCommerce
- Consider adjusting pricing or guidelines

**Version restore failed:**
- Check that version ID exists
- Ensure backup option is enabled in settings
