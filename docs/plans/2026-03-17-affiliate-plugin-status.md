# Affiliate Plugin Status

- Plugin scaffold created under `wordpress-plugin/meintechblog-affiliate-cards/`
- Native Gutenberg block metadata and editor shell added
- Frontend renderer uses the current live card style baseline
- Core services implemented:
  - title shortener
  - badge resolver
  - token scanner for standalone ASIN text blocks
- Local verification completed:
  - `php tests/test-core.php`
  - `php tests/test-block-files.php`
  - `php tests/test-token-scanner.php`
  - `php -l` across all plugin PHP files

## Still Open Before Live Use
- Real WordPress settings persistence and form handling
- Creators API service in PHP
- Post-save hook that scans standalone ASIN blocks and updates block content
- Migration path from existing text-link posts into native block data
- Packaging and installation on the live WordPress instance
