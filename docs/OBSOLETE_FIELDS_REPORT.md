# Obsolete Database Fields Report

This report specifies the `wp_postmeta` fields that have become obsolete or redundant following the Funnel CPT refactoring. These fields can be cleaned up from the database once the new ACF structure is verified.

## 1. Redundant Consolidated Fields
These fields were previously in separate ACF field groups and have been consolidated into `group_hp_funnel_config`. While the field *names* are mostly the same, their ACF field key associations in `wp_postmeta` (keys starting with `_`) will change.

| Field Name | Previous Group | New Group | Status |
|------------|----------------|-----------|--------|
| `accent_color` | `group_funnel_styling_colors` | `group_hp_funnel_config` | Redundant Key |
| `background_type` | `group_funnel_styling_colors` | `group_hp_funnel_config` | Redundant Key |
| `hero_title_size` | `group_hero_title_size` | `group_hp_funnel_config` | Redundant Key |
| `seo_focus_keyword` | `group_hp_funnel_seo` | `group_hp_funnel_config` | Redundant Key |
| `seo_meta_title` | `group_hp_funnel_seo` | `group_hp_funnel_config` | Redundant Key |
| `seo_meta_description` | `group_hp_funnel_seo` | `group_hp_funnel_config` | Redundant Key |
| `seo_cornerstone_content` | `group_hp_funnel_seo` | `group_hp_funnel_config` | Redundant Key |
| `testimonials_subtitle` | `group_testimonials_display_settings` | `group_hp_funnel_config` | Redundant Key |
| `testimonials_display_mode`| `group_testimonials_display_settings` | `group_hp_funnel_config` | Redundant Key |
| `testimonials_columns` | `group_testimonials_display_settings` | `group_hp_funnel_config` | Redundant Key |
| `authority_subtitle` | `group_hp_funnel_v23_fields` | `group_hp_funnel_config` | Redundant Key |
| `science_title` | `group_hp_funnel_v23_fields` | `group_hp_funnel_config` | Redundant Key |
| `science_subtitle` | `group_hp_funnel_v23_fields` | `group_hp_funnel_config` | Redundant Key |
| `features_subtitle` | `group_hp_funnel_v23_fields` | `group_hp_funnel_config` | Redundant Key |
| `cta_subtitle` | `group_hp_funnel_v23_fields` | `group_hp_funnel_config` | Redundant Key |

## 2. Truly Obsolete Internal Meta
These fields were used for internal caching or legacy logic and are no longer required in their current form.

| Meta Key | Description | Recommendation |
|----------|-------------|----------------|
| `_hp_funnel_min_price` | Prefixed price cache | Delete (Replaced by `funnel_min_price`) |
| `_hp_funnel_max_price` | Prefixed price cache | Delete (Replaced by `funnel_max_price`) |
| `funnel_brand` | Cached brand name | Delete (Will be re-calculated on save) |
| `funnel_availability` | Cached stock status | Delete (Will be re-calculated on save) |

## 3. Legacy Product Fields (Pre-v2.0)
If any funnels still have these fields from very old versions, they should be removed.

- `product_sku`
- `product_qty`
- `product_id`
- `product_discount_type`
- `product_discount_value`

## 4. Testimonials Legacy Structure
Old fields from before the `testimonials_list` repeater was introduced.

- `testimonial_1_name`
- `testimonial_1_quote`
- `testimonial_2_name`
- `testimonial_2_quote`
- (and so on...)

## Cleanup Strategy
Run the following SQL queries (after backup!) to identify and remove obsolete rows:

```sql
-- Identify rows with old field keys (e.g., from deleted groups)
-- Replace 'field_old_key' with actual keys found in wp_postmeta
SELECT * FROM wp_postmeta WHERE meta_key LIKE '_field_677%' AND post_id IN (SELECT ID FROM wp_posts WHERE post_type = 'hp-funnel');

-- Delete prefixed price cache
DELETE FROM wp_postmeta WHERE meta_key IN ('_hp_funnel_min_price', '_hp_funnel_max_price');
```

