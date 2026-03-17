# Affiliate Plugin Editor UX Design

**Goal:** Turn the live affiliate-card prototype into a first-party WordPress plugin that feels native inside the normal Gutenberg editor and renders the same compact card style on the frontend.

## Product Direction
- The editor experience must feel like a normal WordPress content block, not like a raw HTML snippet or shortcode box.
- The live card style from `post 21368` is the design reference:
  - compact editorial cards
  - warm meintechblog color system
  - image, title, benefit line, CTA
  - no public price
  - no star row unless Amazon actually returns review data
- Product titles should default to the current short live style, not Amazon’s long SEO titles.

## Block Model
- Plugin name: `meintechblog-affiliate-cards`
- Primary block: `Affiliate Cards`
- The block stores a list of products, not a giant HTML string.
- Each product row contains:
  - `asin`
  - `benefit`
  - optional `titleOverride`
- Block-level settings:
  - badge mode: `auto`, `video`, `setup`
  - CTA label
  - auto-title-shortening enabled/disabled

## Editor UX
- In Gutenberg, the block shows a real preview of the cards.
- The inspector sidebar is used for settings and per-product controls.
- Products should be editable in a repeatable list UI:
  - add/remove product
  - reorder product rows
  - edit ASIN
  - edit benefit line
  - optional short title override
- Preview should already use the compact short title logic, so the editor reflects the frontend closely.

## Rendering Architecture
- Dynamic server-side block for frontend rendering and cache control.
- JavaScript editor block for preview and block controls.
- PHP render callback uses cached Amazon product data and the post context.
- Badge label logic:
  - if the post contains a YouTube embed and badge mode is `auto`: `Im Video verwendet`
  - otherwise `Passend zu diesem Setup`

## Title Strategy
- Hybrid model:
  - first apply automatic shortening rules
  - then allow optional per-ASIN override
- Default target length: roughly the short live titles now used in `post 21368` and around 55 characters.
- Rules should remove:
  - Amazon marketing prefixes
  - long bracketed claims
  - redundant technical tails
- If the automatic result is still ugly, the manual override wins.

## Data + Caching
- Amazon Creators API token cached until expiry.
- Product payload cached per ASIN and marketplace.
- Render path must tolerate temporary Amazon errors by falling back to cached data if available.

## Admin UX
- Plugin settings page for:
  - Creators API credentials
  - default CTA text
  - default marketplace
  - default title-shortening behavior
  - tracking-ID strategy defaults
- Future admin tools:
  - migrate old text links into block format
  - bulk refresh cached product data

## Non-Goals For First Plugin Cut
- No automatic star scraping from non-official sources.
- No full article-wide auto-transformation of arbitrary HTML in version 1.
- No comparison-table builder in the first slice.

## Success Criteria
- The block is inserted in Gutenberg like a normal content block.
- Editors can manage ASINs without touching HTML.
- Frontend output matches the current live prototype style closely.
- Long Amazon titles are shortened automatically to the current “clean” style.
