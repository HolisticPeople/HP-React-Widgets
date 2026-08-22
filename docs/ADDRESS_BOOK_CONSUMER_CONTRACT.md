# HP React Widgets Address-Book Consumer Contract

## Ownership

HP Core owns additional-address persistence, normalization, and mutations.
HP React Widgets consumes `HP_Core\Plugin::get_service('address')` and owns only
its rendering, REST compatibility surface, and browser selection event.

The storage key is intentionally opaque to this repository. Runtime PHP must
not read or write it directly or depend on the retired ThemeHigh or
HP-Multi-Address plugins.

## Stable public behavior

- Native WooCommerce billing and shipping addresses remain available.
- Additional-address IDs retain the established `th_{type}_{key}` shape.
- The `hpAddressSelected` browser event and payload remain unchanged.
- Existing `hp-rw/v1/address/*` endpoints remain compatibility surfaces.
- If HP Core is absent, readers show native WooCommerce data only and
  additional-address mutations return `hp_rw_address_service_unavailable`
  with HTTP status `503`.

## Historical references

`wpcode-snippets/thwma-shortcode-display-type.php` and its README section are
deprecated, non-loaded migration evidence. They are not registered by the
plugin bootstrap and are not part of the address runtime. Their later archive
or deletion belongs to the repository-retirement cleanup, not this contract
change.

The static contract test rejects legacy storage-key or HP-Multi-Address
constant usage in the active address runtime files.
