# Funnel Data Update Guide via SSH/WP-CLI

## SSH Connection

```bash
ssh -i C:\Users\user\.ssh\kinsta_staging_key -p 12872 holisticpeoplecom@35.236.219.140 "cd public && <command>"
```

## Key Concepts

### ACF Repeater Field Structure

ACF repeaters store data in **3 parts**:

| Part | Example Key | Value |
|------|-------------|-------|
| Count | `hero_benefits` | `12` (number of rows) |
| Value | `hero_benefits_0_text` | `"Benefit text here"` |
| Field Ref | `_hero_benefits_0_text` | `field_benefit_text` (ACF field key) |

**Important**: All 3 parts must exist for ACF to display the data correctly in the admin UI.

### Finding Field Keys

To find the correct ACF field key for a subfield:
```bash
wp db query "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = POST_ID AND meta_key LIKE '_%' AND meta_value LIKE 'field_%'" 
```

---

## Common Operations

### Get All Funnel Posts
```bash
wp post list --post_type=hp-funnel --fields=ID,post_title,post_status
```

### Get All Meta for a Funnel
```bash
wp db query "SELECT meta_key, LEFT(meta_value, 50) FROM wp_postmeta WHERE post_id = POST_ID ORDER BY meta_key"
```

### Update Simple Field
```bash
wp post meta update POST_ID field_name 'value'
```

### Update Repeater Row (SINGLE COMMAND PATTERN - RECOMMENDED)

Run each update **separately** to avoid silent failures:

```bash
# Step 1: Update the count
wp post meta update 125153 hero_benefits 12

# Step 2: Add the value
wp post meta update 125153 hero_benefits_11_text 'New benefit text'

# Step 3: Add the field reference  
wp post meta update 125153 _hero_benefits_11_text field_benefit_text

# Step 4: Verify
wp post meta get 125153 hero_benefits_11_text
```

### Delete a Repeater Row

```bash
# Delete value and field ref
wp post meta delete POST_ID hero_benefits_N_text
wp post meta delete POST_ID _hero_benefits_N_text

# Update count
wp post meta update POST_ID hero_benefits NEW_COUNT
```

---

## Cache Clearing

**Always clear caches after updates:**

```bash
# WordPress transients
wp transient delete --all

# WordPress object cache
wp cache flush

# Kinsta cache (if available)
wp kinsta cache purge --all

# Plugin-specific cache
wp transient delete hp_rw_funnel_config_slug_FUNNEL_SLUG
```

---

## Troubleshooting

### Field Shows Empty in Admin

1. **Check the value exists:**
   ```bash
   wp db query "SELECT meta_value FROM wp_postmeta WHERE post_id = POST_ID AND meta_key = 'field_name'"
   ```

2. **Check the field reference exists:**
   ```bash
   wp db query "SELECT meta_value FROM wp_postmeta WHERE post_id = POST_ID AND meta_key = '_field_name'"
   ```

3. **If field ref is missing, add it:**
   ```bash
   wp post meta update POST_ID _field_name field_key_from_acf
   ```

4. **Clear all caches and hard refresh browser (Ctrl+Shift+R)**

### Multiple Commands Failing Silently

**Don't chain many commands with `&&`**. Run them one at a time:

```bash
# BAD - may fail silently
wp post meta update ... && wp post meta update ... && wp post meta update ...

# GOOD - run separately
wp post meta update ...
wp post meta update ...
wp post meta update ...
```

---

## Reference: Illumodine Funnel (ID: 125153)

### Key Fields

| Field | Meta Key |
|-------|----------|
| Funnel Slug | `funnel_slug` |
| Status | `funnel_status` |
| Hero Title | `hero_title` |
| Benefits Count | `hero_benefits` |
| Benefit N Text | `hero_benefits_N_text` |
| Products Count | `funnel_products` |
| Product N SKU | `funnel_products_N_sku` |

### ACF Field Keys for Subfields

| Subfield | Field Key |
|----------|-----------|
| Benefit text | `field_benefit_text` |
| Product SKU | `field_product_sku` |
| Product name | `field_product_display_name` |
| Feature text | `field_feature_text` |

---

## Full Example: Add a New Benefit

```bash
# Current count is 12, adding benefit #13 (index 12)
SSH="ssh -i C:\Users\user\.ssh\kinsta_staging_key -p 12872 holisticpeoplecom@35.236.219.140"

# Update count to 13
$SSH "cd public && wp post meta update 125153 hero_benefits 13"

# Add the benefit text
$SSH "cd public && wp post meta update 125153 hero_benefits_12_text 'New amazing benefit'"

# Add the field reference
$SSH "cd public && wp post meta update 125153 _hero_benefits_12_text field_benefit_text"

# Clear caches
$SSH "cd public && wp transient delete --all && wp cache flush"

# Verify
$SSH "cd public && wp post meta get 125153 hero_benefits_12_text"
```

