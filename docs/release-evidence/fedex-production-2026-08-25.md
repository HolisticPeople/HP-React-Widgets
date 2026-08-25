# FedEx production release evidence

- Production release: 2026-08-25
- Release PR: #45
- Production main SHA: `323a72af1f602ccfc076415ec716a50e7445df78`
- Production workflow run: `32812850955` (success)
- Runtime posture: HP React Widgets remains intentionally inactive; HP Checkout and HP UI Widgets own the supported customer checkout surface.
- Supported checkout: `/hp-checkout/`; native WooCommerce checkout is compatibility/rollback only.
- Shipping quote contract: exact positive converted package weights must never be rounded or clamped.

This evidence note gives the otherwise tree-identical main-to-dev alignment a durable reviewable change.
