# AI Funnel Creation Guide

This document describes how AI agents can create, modify, and manage sales funnels in the HP-React-Widgets system.

## Overview

The AI Funnel Creation system enables AI agents to:
1. Understand the complete funnel architecture
2. Build product kits from health protocols
3. Generate marketing content from articles
4. Create color palettes from product inspiration
5. Validate offers against economic guidelines
6. Manage funnel versions with backup/restore

## API Endpoints

All endpoints are under the namespace `wp-json/hp-rw/v1/ai/`.

### System Understanding

#### GET `/system/explain`
Returns comprehensive documentation of the funnel CPT structure, sections, offer types, styling, and checkout flow.

```json
{
  "overview": "HP Funnels are custom post types...",
  "cpt_structure": { ... },
  "sections": { ... },
  "offer_types": { ... },
  "styling": { ... },
  "checkout_flow": { ... }
}
```

#### GET `/schema`
Returns the complete funnel JSON schema with AI generation hints.

```json
{
  "version": "hp-funnel/v1",
  "schema": { ... },
  "field_descriptions": { ... },
  "example": { ... },
  "ai_generation_hints": { ... },
  "content_guidelines": { ... }
}
```

#### GET `/styling/schema`
Returns styling schema with color palettes and theme presets.

```json
{
  "css_custom_properties": { ... },
  "color_palette_structure": { ... },
  "theme_presets": { ... },
  "generation_guidelines": { ... }
}
```

### Product Catalog

#### GET `/products`
Search products with filters.

Query parameters:
- `search` - Search term
- `category` - Category slug
- `sku` - Exact SKU match

#### GET `/products/{sku}`
Get detailed product information including servings, costs, and economics.

#### POST `/products/calculate-supply`
Calculate how many bottles needed for X days.

```json
{
  "sku": "ILL-SMALL",
  "days": 90,
  "servings_per_day": 3
}
```

### Protocol Kit Builder

#### POST `/protocols/build-kit`
Build a product kit from a health protocol.

```json
{
  "protocol": {
    "name": "Thyroid Support Protocol",
    "duration_days": 90,
    "supplements": [
      {
        "name": "Nascent Iodine",
        "daily_servings": 3,
        "preferred_sku": "ILL-SMALL"
      },
      {
        "name": "Selenium",
        "daily_servings": 1
      }
    ]
  },
  "economic_constraints": {
    "min_margin_percent": 10,
    "target_price_point": 150
  }
}
```

Response includes decision points for product selection and pricing.

### Economics

#### POST `/economics/calculate`
Calculate profitability for a set of products.

```json
{
  "items": [
    { "sku": "ILL-SMALL", "qty": 3, "discount_percent": 15 }
  ],
  "offer_price": 99.99,
  "shipping_scenario": "domestic"
}
```

#### POST `/economics/validate-offer`
Validate an offer against economic guidelines.

```json
{
  "offer": {
    "type": "fixed_bundle",
    "bundle_items": [
      { "sku": "ILL-SMALL", "qty": 3 }
    ],
    "discount_type": "percent",
    "discount_value": 20
  }
}
```

#### GET `/economics/shipping-strategy`
Get shipping cost and subsidy recommendations.

Query parameters:
- `order_total` - Order total in dollars
- `weight_oz` - Total weight in ounces
- `destination` - `domestic` or `international`

### Funnel CRUD

#### GET `/funnels`
List all funnels with summary data.

#### GET `/funnels/{slug}`
Get complete funnel data by slug.

#### POST `/funnels`
Create a new funnel. Requires admin permission.

```json
{
  "funnel": {
    "name": "New Funnel",
    "slug": "new-funnel"
  },
  "hero": { ... },
  "offers": [ ... ],
  "styling": { ... }
}
```

#### PUT `/funnels/{slug}`
Update an existing funnel.

#### DELETE `/funnels/{slug}`
Delete a funnel.

#### GET `/funnels/{slug}/sections`
Get all section data for a funnel.

#### GET `/funnels/{slug}/section/{section}`
Get a specific section (hero, benefits, offers, etc.).

### Version Control

#### GET `/funnels/{slug}/versions`
List all saved versions of a funnel.

#### POST `/funnels/{slug}/versions`
Create a new version backup.

```json
{
  "description": "Before AI modifications",
  "created_by": "ai_agent"
}
```

#### GET `/funnels/{slug}/versions/{id}`
Get a specific version's data.

#### POST `/funnels/{slug}/versions/{id}/restore`
Restore a funnel to a previous version.

```json
{
  "backup_current": true
}
```

#### GET `/funnels/{slug}/versions/diff`
Compare two versions.

Query parameters:
- `from` - Source version ID
- `to` - Target version ID

## Decision Points

The AI system uses decision points to interact with administrators during funnel creation. Decision points pause the AI workflow and present choices to the user.

### Decision Point Types

1. **single_choice** - Select one option from a list
2. **multiple_choice** - Select multiple options
3. **confirmation** - Approve/reject a proposal
4. **input** - Free-form text input
5. **range** - Numeric slider selection
6. **review** - Review and optionally edit complex data

### Decision Point Structure

```json
{
  "decision_point": true,
  "type": "single_choice",
  "id": "pricing_strategy",
  "title": "Select Pricing Strategy",
  "description": "Choose the pricing approach for this offer:",
  "options": [
    {
      "value": "aggressive",
      "label": "Aggressive (25% off)",
      "details": { "margin": "15%", "profit": "$12" }
    },
    {
      "value": "moderate",
      "label": "Moderate (15% off)",
      "details": { "margin": "25%", "profit": "$20" },
      "recommended": true
    }
  ],
  "recommendation": "moderate",
  "context": {
    "retail_total": 79.99,
    "cost_total": 32.00
  },
  "awaiting_response": true
}
```

### Common Decision Points

1. **Product Selection** - Which products to include in a kit
2. **Pricing Strategy** - Discount level and pricing approach
3. **Color Palette** - Visual theme selection
4. **Offer Type** - Single, bundle, or customizable kit
5. **Supply Duration** - 30, 60, 90, or 180 days
6. **Content Review** - Review AI-generated marketing content
7. **Economics Validation** - Approve offers meeting guidelines
8. **Version Backup** - Confirm backup before modifications

## Workflow Examples

### Example 1: Create Funnel from Protocol

```
User: Create a funnel for Dr. Cousens' thyroid support protocol

AI Agent Steps:
1. GET /system/explain - Understand funnel architecture
2. GET /schema - Get content structure
3. POST /protocols/build-kit - Build kit from protocol
   → Decision Point: Product Selection
   
User: Select products A, B, C

4. POST /economics/validate-offer - Validate economics
   → Decision Point: Pricing Strategy (if margin too low)
   
User: Choose moderate pricing

5. GET /styling/schema - Get color options
   → Decision Point: Color Palette
   
User: Choose Dark Gold theme

6. POST /funnels - Create funnel with all data
7. POST /funnels/{slug}/versions - Create initial backup
```

### Example 2: Derive Content from Article

```
User: Create a funnel based on this article about iodine benefits

AI Agent Steps:
1. Parse article for key information
2. GET /schema - Get AI generation hints
3. Generate content following guidelines:
   - hero.title from article main benefit
   - benefits.items from key points
   - science.sections from research data
   - faq.items from common questions
   
4. → Decision Point: Content Review
   Present generated content for each section

User: Approve hero, edit benefits, regenerate FAQ

5. Apply edits, regenerate as requested
6. POST /funnels - Create with approved content
```

### Example 3: Modify Existing Funnel

```
User: Update the illumodine funnel with a new pricing strategy

AI Agent Steps:
1. GET /funnels/illumodine - Get current funnel
2. POST /funnels/illumodine/versions - Create backup
   → Decision Point: Version Backup Confirmation
   
User: Confirm backup

3. POST /economics/calculate - Calculate new pricing options
   → Decision Point: Pricing Strategy
   
User: Select new pricing

4. PUT /funnels/illumodine - Update funnel
5. Verify changes applied correctly
```

## Economic Guidelines

The system enforces configurable economic guidelines:

| Setting | Default | Description |
|---------|---------|-------------|
| `min_profit_percent` | 10% | Minimum profit margin |
| `min_profit_dollars` | 10 | Minimum absolute profit |
| `apply_rule` | either | Pass if either threshold met |
| `free_shipping_threshold` | 100 | Free domestic shipping above |
| `international_subsidy_percent` | 50 | % of international shipping subsidized |

AI agents should:
1. Calculate economics before finalizing offers
2. Present warnings for offers below thresholds
3. Suggest pricing adjustments to meet guidelines
4. Consider shipping costs in profitability calculations

## Best Practices

### For AI Agents

1. **Always create backups** before modifying existing funnels
2. **Present decision points** for significant choices rather than deciding autonomously
3. **Validate economics** before presenting final offer configurations
4. **Use reference funnels** to understand existing patterns
5. **Follow content guidelines** to avoid compliance issues
6. **Include context** in decision points to help users make informed choices

### Content Guidelines

1. Avoid FDA-prohibited medical claims
2. Use benefit-focused language ("supports" not "cures")
3. Include required disclaimers
4. Follow brand voice guidelines
5. Maintain accessibility standards

### Version Control

1. Create backups before any AI modifications
2. Use descriptive version names
3. Keep versions for at least 30 days
4. Compare versions before restoring

## Error Handling

API responses include error information:

```json
{
  "success": false,
  "error": {
    "code": "invalid_offer",
    "message": "Offer profit margin (5%) is below minimum (10%)",
    "suggestions": [
      {
        "action": "increase_price",
        "message": "Increase price to $85 for 12% margin"
      }
    ]
  }
}
```

AI agents should:
1. Check `success` field in responses
2. Present errors clearly to users
3. Offer suggestions when available
4. Log errors for debugging

## Appendix: Funnel JSON Structure

See `GET /schema` for the complete schema. Key sections:

- `funnel` - Core identity (name, slug, status)
- `header` - Logo, navigation
- `hero` - Main headline, image, CTA
- `benefits` - Benefit list with icons
- `offers` - Product offers (single, bundle, kit)
- `features` - Feature cards
- `authority` - Expert bio and credentials
- `science` - Scientific information
- `testimonials` - Customer reviews
- `faq` - FAQ accordion
- `cta` - Secondary call-to-action
- `checkout` - Checkout configuration
- `thankyou` - Thank you page
- `styling` - Colors and visual theme
- `footer` - Footer content

