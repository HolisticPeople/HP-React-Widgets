# Plan - Fix Privacy Policy Popup Title

## Goal
Fix the broken title in the privacy policy popup where HTML entities (like `&#8211;`) are displayed instead of decoded characters.

## Context
- File: `c:\DEV\WC Plugins\My Plugins\HP-React-Widgets\src\components\checkout-app\LegalPopup.tsx`
- The WordPress REST API returns `data.title.rendered` with HTML entities.
- React does not automatically decode entities when setting them as text in an element.

## Tasks
1. Create a helper function `decodeHtmlEntities` in `LegalPopup.tsx`.
2. Apply the helper function to the `setTitle` call in `fetchContent`.
3. Increment the plugin version in `hp-react-widgets.php`.
4. Run build for `HP-React-Widgets`.
5. Commit and push changes to `dev`.

## Acceptance criteria
- Privacy policy popup title displays "HolisticPeople – Privacy Policy" (with a real dash) instead of "HolisticPeople &#8211; Privacy Policy".
- Plugin version incremented.
- Changes pushed to staging.
