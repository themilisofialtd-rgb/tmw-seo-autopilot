<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Core {
    const TAG = '[TMW-SEO-GEN]';
    const POST_TYPE = 'model';

    public static function generate_and_write(int $post_id, array $args = []): array {
        $args = wp_parse_args($args, [
            'strategy' => 'template',
            'insert_content' => true,
            'dry_run' => false,
        ]);

        $post = get_post($post_id);
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return ['ok' => false, 'message' => 'Invalid post or type'];
        }

        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $tax_candidates = ['post_tag', 'models', 'category'];
        $terms = [];
        foreach ($tax_candidates as $tax) {
            if (taxonomy_exists($tax)) {
                $t = wp_get_post_terms($post_id, $tax, ['fields' => 'names']);
                if (!is_wp_error($t)) $terms = array_merge($terms, $t);
            }
        }
        $primary_tag = $terms ? $terms[0] : 'live chat';
        $looks = array_slice($terms, 0, 3);

        $provider = ($args['strategy'] === 'openai' && Providers\OpenAI::is_enabled())
            ? new Providers\OpenAI()
            : new Providers\Template();

        $payload = $provider->generate([
            'name' => $post->post_title,
            'site' => $site,
            'primary' => $primary_tag,
            'looks' => $looks,
        ]);

        if (!is_array($payload) || empty($payload['title'])) {
            return ['ok' => false, 'message' => 'Generator returned empty payload'];
        }

        $prev = [
            'rank_math_title' => get_post_meta($post_id, 'rank_math_title', true),
            'rank_math_description' => get_post_meta($post_id, 'rank_math_description', true),
            'rank_math_focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'post_content' => $post->post_content,
        ];
        update_post_meta($post_id, '_tmwseo_prev', $prev);

        if (!$args['dry_run']) {
            update_post_meta($post_id, 'rank_math_title', $payload['title']);
            update_post_meta($post_id, 'rank_math_description', $payload['meta']);
            update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $payload['focus']));

            if (!empty($args['insert_content']) && !empty($payload['content'])) {
                $marker_start = "\n<!-- TMWSEO:START -->\n";
                $marker_end   = "\n<!-- TMWSEO:END -->\n";
                $new = $post->post_content;

                $new = preg_replace('/\n<!-- TMWSEO:START -->(.*?)<!-- TMWSEO:END -->\n/s', "\n", $new);

                $new .= $marker_start . $payload['content'] . $marker_end;
                wp_update_post(['ID' => $post_id, 'post_content' => $new]);
            }
        }

        error_log(self::TAG . " wrote SEO for #$post_id ({$post->post_title})");
        return ['ok' => true, 'payload' => $payload, 'prev' => $prev];
    }

    public static function rollback(int $post_id): array {
        $prev = get_post_meta($post_id, '_tmwseo_prev', true);
        if (!$prev) return ['ok' => false, 'message' => 'No previous values stored'];
        update_post_meta($post_id, 'rank_math_title', $prev['rank_math_title']);
        update_post_meta($post_id, 'rank_math_description', $prev['rank_math_description']);
        update_post_meta($post_id, 'rank_math_focus_keyword', $prev['rank_math_focus_keyword']);

        if (isset($prev['post_content'])) {
            $clean = preg_replace('/\n<!-- TMWSEO:START -->(.*?)<!-- TMWSEO:END -->\n/s', "\n", $prev['post_content']);
            wp_update_post(['ID' => $post_id, 'post_content' => $clean]);
        }
        delete_post_meta($post_id, '_tmwseo_prev');
        error_log(self::TAG . " rollback done for #$post_id");
        return ['ok' => true];
    }
}
