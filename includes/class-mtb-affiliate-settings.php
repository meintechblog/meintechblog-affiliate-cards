<?php

declare(strict_types=1);

final class MTB_Affiliate_Settings {
    public function defaults(): array {
        return [
            'cta_label' => 'Preis auf Amazon checken',
            'badge_mode' => 'auto',
            'auto_shorten_titles' => true,
            'marketplace' => 'www.amazon.de',
        ];
    }
}
