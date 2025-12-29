# AI Agent Instructions for WC Plugins Workspace

## Funnel Creation - USE THE RIGHT SYSTEM

When asked to create, build, or modify sales funnels, you MUST use:

### ✅ CORRECT: HP-React-Widgets AI Funnel API

**Location**: `HP-React-Widgets/` plugin

**REST API Base**: `/wp-json/hp-rw/v1/ai/`

**Key Endpoints**:
| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/ai/system/explain` | GET | Complete system documentation |
| `/ai/schema` | GET | JSON schema with AI generation hints |
| `/ai/funnels` | GET | List existing funnels for reference |
| `/ai/funnels` | POST | Create new funnel |
| `/ai/funnels/{slug}` | GET | Get complete funnel as template |
| `/ai/products` | GET | Search products for offers |
| `/ai/protocols/build-kit` | POST | Build product kit from protocol |
| `/ai/economics/validate-offer` | POST | Validate offer profitability |

**Documentation**: `HP-React-Widgets/docs/AI_FUNNEL_CREATION.md`

**MCP Servers**: 
- `woocommerce_mcp_staging` (staging environment)
- `woocommerce_mcp_production` (production - use carefully)

### ❌ WRONG: HP-Funnel-Bridge

**DO NOT USE** HP-Funnel-Bridge for creating new funnels. It's a bridge for ONE existing satellite funnel (illumodine-boost-funnel).

### ❌ WRONG: illumodine-boost-funnel

**DO NOT MODIFY** this for new funnels. It's a Lovable React app for a single product.

---

## Workflow for Building a Funnel from Protocol

### 1. Get System Understanding
```
GET /wp-json/hp-rw/v1/ai/system/explain
```

### 2. Get a Reference Funnel
```
GET /wp-json/hp-rw/v1/ai/funnels
GET /wp-json/hp-rw/v1/ai/funnels/illumodine
```

### 3. Search Products
```
GET /wp-json/hp-rw/v1/ai/products?category=supplements
```

### 4. Build Kit from Protocol
```json
POST /wp-json/hp-rw/v1/ai/protocols/build-kit
{
  "supplements": [
    {"sku": "PRODUCT-SKU", "servings_per_day": 3}
  ],
  "duration_days": 90
}
```

### 5. Validate Economics
```json
POST /wp-json/hp-rw/v1/ai/economics/validate-offer
{
  "type": "fixed_bundle",
  "price": 149.99,
  "bundle_items": [...]
}
```

### 6. Create Funnel
```json
POST /wp-json/hp-rw/v1/ai/funnels
{
  "$schema": "hp-funnel/v1",
  "funnel": { "name": "...", "slug": "..." },
  "hero": { ... },
  "offers": [ ... ],
  ...
}
```

---

## Decision Points - Ask the Admin

Present choices rather than making autonomous decisions for:
- **Offer structure**: fixed_bundle vs customizable_kit
- **Discount strategy**: percent_off vs fixed_discount  
- **Headline style**: benefit-focused vs problem-focused
- **Theme**: dark vs light, accent color
- **Sections**: minimal, standard, or comprehensive

---

## Plugin Overview

| Plugin | Purpose | Use for Funnel Creation? |
|--------|---------|--------------------------|
| `HP-React-Widgets` | Native WP funnels with AI API | ✅ YES |
| `HP-Funnel-Bridge` | Bridge for satellite funnel | ❌ NO |
| `illumodine-boost-funnel` | One specific Lovable funnel | ❌ NO |
| `HP-Abilities` | WC Abilities for AI agents | Helper tool |
| `HP-Dev-Config` | Dev environment config | Not for funnels |
| `HP-enhanced-admin-order` | Order admin enhancements | Not for funnels |
| `hp-shipstation-rates` | Shipping rate calculation | Not for funnels |
| `products-manager` | Product management | Not for funnels |
| `slick-address-slider` | Address UI component | Not for funnels |
| `HP-Multi-Address` | Multi-address component | Not for funnels |

