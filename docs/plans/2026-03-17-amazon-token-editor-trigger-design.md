# Amazon Token Editor Trigger Design

**Goal:** Replace the fragile save-time ASIN scanning flow with an explicit Gutenberg editor trigger that turns `amazon:ASIN` paragraph input into a native affiliate block immediately inside the editor.

## Product Direction
- The plugin must stop doing surprising "magic on save" for ASIN marker paragraphs.
- Editors should see the transformation happen directly in Gutenberg, not after a page reload.
- The trigger syntax is now exact and explicit:
  - `amazon:B0CK3L9WD3`
- We do not support raw ASIN-only markers in this flow anymore.

## Trigger Behavior
- The trigger only works when a paragraph block contains exactly one token matching:
  - `amazon:ASIN`
- The transformation is triggered from the editor, not from `save_post`.
- Primary trigger moment:
  - when the editor commits the block content, especially on `Enter`
- Safety fallback:
  - when the paragraph block loses focus or is otherwise committed by Gutenberg state changes

## Editor UX
- Input:
  - user creates a normal paragraph block
  - user types `amazon:B0CK3L9WD3`
  - user presses `Enter`
- Result:
  - the paragraph block is replaced in-place with one native `meintechblog/affiliate-cards` block
  - that block contains exactly one product item
- Each token becomes its own block so blocks can be reordered freely later.

## Duplicate Handling
- If the same ASIN already exists in another affiliate block in the same post:
  - do not create a second block silently
  - remove the token paragraph
  - surface a lightweight editor notice that the product already exists in the post
- We prefer preventing confusing duplicate blocks over blindly creating them.

## Data Resolution
- The editor trigger should reuse the existing Amazon data resolution path where possible.
- On successful resolution, the created block should already contain:
  - `asin`
  - short title
  - image URL
  - detail URL with derived tracking tag
  - benefit line if available or derivable
- If Amazon data resolution fails:
  - still create the block with the ASIN only
  - allow server render to fill the rest later

## Save Behavior
- `save_post` should no longer mutate paragraph content based on ASIN token scanning.
- Existing affiliate blocks remain editable and render exactly as before.
- Save-time logic can remain for enrichment or validation later, but not for token-to-block transformation.

## Technical Direction
- Gutenberg editor script watches paragraph blocks for exact `amazon:ASIN` tokens.
- On match, editor code dispatches block replacement using WordPress block-editor data APIs.
- PHP remains responsible for frontend rendering and Amazon fallback resolution.
- This is a native editor transform workflow, not a shortcode or HTML injection path.

## Success Criteria
- Typing `amazon:B0CK3L9WD3` in a paragraph and pressing `Enter` replaces that paragraph with an affiliate block immediately.
- No page save or reload is required to see the result.
- Existing blocks are never accidentally removed or overwritten by this flow.
- Duplicate ASINs in the same post do not create confusing duplicate blocks.
