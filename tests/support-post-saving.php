<?php

declare(strict_types=1);

// WordPress expects slashed input, unslashes before storage, and fires save_post again.
function wp_slash($value) {
    return is_array($value) ? array_map('wp_slash', $value) : (is_string($value) ? addslashes($value) : $value);
}

function wp_unslash($value) {
    return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value);
}

class WP_Error {}
function is_wp_error($value): bool { return $value instanceof WP_Error; }

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    if ($hook !== 'save_post') {
        $GLOBALS['mtb_actions'][$hook][] = $callback;
        return;
    }
    foreach ($GLOBALS['mtb_save_actions'][$priority] ?? [] as $entry) {
        if ($entry[0] === $callback) {
            return;
        }
    }
    $GLOBALS['mtb_save_actions'][$priority][] = [$callback, $accepted_args];
    $GLOBALS['mtb_actions'][$hook] = true;
}

function remove_action(string $hook, $callback, int $priority = 10): void {
    if ($hook === 'save_post') {
        $GLOBALS['mtb_save_actions'][$priority] = array_values(array_filter(
            $GLOBALS['mtb_save_actions'][$priority] ?? [],
            static fn(array $entry): bool => $entry[0] !== $callback
        ));
    }
}

function do_action(string $hook, ...$args): void {
    if ($hook !== 'save_post') {
        return;
    }
    $priorities = array_keys($GLOBALS['mtb_save_actions'] ?? []);
    sort($priorities);
    foreach ($priorities as $priority) {
        foreach ($GLOBALS['mtb_save_actions'][$priority] ?? [] as [$callback, $acceptedArgs]) {
            $callback(...array_slice($args, 0, $acceptedArgs));
        }
    }
}

function wp_update_post(array $postarr, bool $wp_error = false) {
    $GLOBALS['mtb_update_error_requested'] = $wp_error;
    if (! empty($GLOBALS['mtb_update_exception'])) {
        throw new RuntimeException('Simulierter Speicherfehler');
    }
    if (! empty($GLOBALS['mtb_update_error'])) {
        return $wp_error ? new WP_Error() : 0;
    }
    $postarr = wp_unslash($postarr);
    $GLOBALS['mtb_update_posts'][] = $postarr;
    $GLOBALS['mtb_updated_post'] = $postarr;
    $postId = (int) ($postarr['ID'] ?? 0);
    if (! isset($GLOBALS['mtb_posts'][$postId])) {
        return 0;
    }
    $GLOBALS['mtb_posts'][$postId]->post_content = $postarr['post_content'];
    $GLOBALS['mtb_save_depth'] = ($GLOBALS['mtb_save_depth'] ?? 0) + 1;
    if ($GLOBALS['mtb_save_depth'] > 5) {
        throw new RuntimeException('save_post recursion');
    }
    $GLOBALS['mtb_max_save_depth'] = max($GLOBALS['mtb_max_save_depth'] ?? 0, $GLOBALS['mtb_save_depth']);
    try {
        do_action('save_post', $postId, get_post($postId), true);
    } finally {
        --$GLOBALS['mtb_save_depth'];
    }
    return $postId;
}

function get_post(int $postId): ?object {
    return isset($GLOBALS['mtb_posts'][$postId]) ? clone $GLOBALS['mtb_posts'][$postId] : null;
}

function wp_is_post_revision(int $postId): bool { return ! empty($GLOBALS['mtb_revisions'][$postId]); }
function wp_is_post_autosave(int $postId): bool { return ! empty($GLOBALS['mtb_autosaves'][$postId]); }
