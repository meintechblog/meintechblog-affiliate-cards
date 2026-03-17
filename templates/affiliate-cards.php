<?php

declare(strict_types=1);

if (empty($itemsForTemplate) || ! is_array($itemsForTemplate)) {
    return;
}

echo '<!-- mtb-aff-card markup rendered below -->';
echo $renderer->render_cards($itemsForTemplate, [
    'badge_label' => $badgeLabelForTemplate,
    'cta_label' => $ctaLabelForTemplate,
]);
