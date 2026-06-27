<?php

if (!defined('ABSPATH')) {
    exit;
}

function vms_v2_register_frontend_hooks(): void
{
    add_action('wp_head', 'vms_v2_enqueue_fonts_css', 5);
    add_action('wp_head', 'vms_v2_add_viewport_meta');
    add_filter('the_content', 'vms_v2_apply_content', 9999);
}

function vms_v2_is_current_verstka_html(string $html): bool
{
    return str_contains($html, 'data-vrstk-article') && str_contains($html, 'data-vrstk-article-payload');
}

function vms_v2_enqueue_fonts_css(): void
{
    if (!is_singular(['post', 'page'])) {
        return;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post || empty($post->post_isvms)) {
        return;
    }

    $fonts_css_url = vms_v2_get_fonts_css_url();
    if ($fonts_css_url === '') {
        return;
    }

    printf(
        '<link rel="stylesheet" href="%s" type="text/css" media="all">' . "\n",
        esc_url($fonts_css_url)
    );
}

function vms_v2_add_viewport_meta(): void
{
    if (!is_singular(['post', 'page'])) {
        return;
    }

    $post = get_queried_object();
    if (!$post instanceof WP_Post || empty($post->post_isvms)) {
        return;
    }

    echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
}

function vms_v2_apply_content(string $content): string
{
    $post = get_post();
    if (!$post || empty($post->post_isvms)) {
        return $content;
    }

    if (post_password_required($post)) {
        return $content;
    }

    $article_html = (string) ($post->post_vms_content ?? '');
    if ($article_html === '') {
        return $content;
    }

    $viewer_url = wp_json_encode(vms_v2_get_viewer_script_url());
    $needs_viewer = vms_v2_is_current_verstka_html($article_html);

    $output = $article_html;

    if ($needs_viewer) {
        $output .= sprintf(
            '<script type="module">import(%1$s).then(({ Verstka }) => { Verstka.initArticles(document); }).catch((error) => { console.error("Failed to initialize Verstka articles", error); });</script>',
            $viewer_url
        );
    }

    return $output;
}
