<?php

declare(strict_types=1);

final class MTB_Affiliate_Renderer {
    public function render_cards(array $items, array $options = []): string {
        $badgeLabel = $options['badge_label'] ?? 'Passend zu diesem Setup';
        $ctaLabel = $options['cta_label'] ?? 'Preis auf Amazon checken';

        $cards = array_map(
            fn(array $item): string => $this->render_single_card($item, $badgeLabel, $ctaLabel),
            $items
        );

        return $this->style_block() . implode('', $cards);
    }

    private function render_single_card(array $item, string $badgeLabel, string $ctaLabel): string {
        $asin = htmlspecialchars((string) ($item['asin'] ?? ''), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $imageUrl = htmlspecialchars((string) ($item['image_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $detailUrl = htmlspecialchars((string) ($item['detail_url'] ?? ''), ENT_QUOTES, 'UTF-8');
        $benefit = htmlspecialchars((string) ($item['benefit'] ?? ''), ENT_QUOTES, 'UTF-8');
        $badge = htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8');
        $cta = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');

        $benefitHtml = $benefit !== '' ? '<div class="mtb-aff-benefit">' . $benefit . '</div>' : '';

        return '<div class="mtb-aff-card" data-asin="' . $asin . '">'
            . '<div class="mtb-aff-badge">' . $badge . '</div>'
            . '<a class="mtb-aff-img-link" href="' . $detailUrl . '" target="_blank" rel="nofollow noopener sponsored">'
            . '<div class="mtb-aff-img"><img src="' . $imageUrl . '" alt="' . $title . '" loading="lazy"></div>'
            . '</a>'
            . '<div class="mtb-aff-body">'
            . '<div class="mtb-aff-title"><a class="mtb-aff-title-link" href="' . $detailUrl . '" target="_blank" rel="nofollow noopener sponsored">' . $title . '</a></div>'
            . $benefitHtml
            . '<a class="mtb-aff-cta" href="' . $detailUrl . '" target="_blank" rel="nofollow noopener sponsored"><span>' . $cta . '</span><span class="mtb-aff-cta-subline">Affiliate-Link</span></a>'
            . '</div></div>';
    }

    private function style_block(): string {
        return '<style>'
            . '.mtb-aff-card{--mtb-aff-bg:#FFFFFF;--mtb-aff-panel:#F8F2E8;--mtb-aff-border:#E8D7BE;--mtb-aff-text:#111111;--mtb-aff-muted:#6B6258;--mtb-aff-soft:#8F8376;--mtb-aff-accent:#E38A44;--mtb-aff-accent-2:#FCD537;--mtb-aff-badge-bg:#111111;--mtb-aff-badge-text:#FFFFFF;position:relative;display:grid;grid-template-columns:220px minmax(0,1fr);gap:0;margin:20px 0;border:1px solid var(--mtb-aff-border);background:var(--mtb-aff-bg);box-shadow:0 14px 34px rgba(17,17,17,.10);overflow:hidden}'
            . '.mtb-aff-card::before{content:"";position:absolute;inset:0 auto 0 0;width:4px;background:linear-gradient(180deg,#FCD537 0%,#E38A44 100%)}'
            . '.mtb-aff-badge{position:absolute;top:14px;left:16px;z-index:1;display:inline-block;padding:5px 9px;background:var(--mtb-aff-badge-bg);color:var(--mtb-aff-badge-text);font-size:.7rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-radius:999px}'
            . '.mtb-aff-img-link{display:block;text-decoration:none;background:linear-gradient(180deg,#FFF8E8 0%,var(--mtb-aff-panel) 100%)}'
            . '.mtb-aff-img{display:flex;align-items:center;justify-content:center;padding:28px 24px 24px;background:linear-gradient(180deg,#FFF8E8 0%,var(--mtb-aff-panel) 100%);min-height:190px}'
            . '.mtb-aff-img img{display:block;max-width:100%;max-height:180px;width:auto;height:auto;object-fit:contain}'
            . '.mtb-aff-body{display:flex;flex-direction:column;justify-content:center;padding:24px 24px 22px;background:var(--mtb-aff-bg)}'
            . '.mtb-aff-title{font-size:1.45rem;font-weight:700;line-height:1.15;margin-bottom:10px;color:var(--mtb-aff-text)}'
            . '.mtb-aff-title-link{color:inherit;text-decoration:none}'
            . '.mtb-aff-title-link:hover{text-decoration:none;color:var(--mtb-aff-accent)}'
            . '.mtb-aff-benefit{font-size:.97rem;line-height:1.5;color:var(--mtb-aff-muted);margin-bottom:14px}'
            . '.mtb-aff-cta{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;width:100%;padding:13px 16px;border-radius:14px;background:linear-gradient(135deg,#FCD537 0%,#E38A44 100%);color:#111111;text-align:center;text-decoration:none;font-weight:700;letter-spacing:.01em;box-shadow:inset 0 -2px 0 rgba(0,0,0,.08)}'
            . '.mtb-aff-cta-subline{display:block;font-size:.72rem;line-height:1.15;font-weight:600;opacity:.82}'
            . '@media (prefers-color-scheme:dark){.mtb-aff-card{--mtb-aff-bg:#181818;--mtb-aff-panel:#222222;--mtb-aff-border:#333333;--mtb-aff-text:#F6F1E8;--mtb-aff-muted:#D0C5B8;--mtb-aff-soft:#A89B8D;--mtb-aff-badge-bg:#F6F1E8;--mtb-aff-badge-text:#111111}}'
            . 'html[data-theme="dark"] .mtb-aff-card,body[data-theme="dark"] .mtb-aff-card,body.dark-mode .mtb-aff-card,body.is-dark-theme .mtb-aff-card,.dark .mtb-aff-card{--mtb-aff-bg:#181818;--mtb-aff-panel:#222222;--mtb-aff-border:#333333;--mtb-aff-text:#F6F1E8;--mtb-aff-muted:#D0C5B8;--mtb-aff-soft:#A89B8D;--mtb-aff-badge-bg:#F6F1E8;--mtb-aff-badge-text:#111111}'
            . '@media (max-width:760px){.mtb-aff-card{grid-template-columns:1fr}.mtb-aff-img{min-height:160px;padding:24px 20px 18px}.mtb-aff-body{padding:18px}.mtb-aff-title{font-size:1.2rem}.mtb-aff-cta{padding:12px 14px}}'
            . '</style>';
    }
}
