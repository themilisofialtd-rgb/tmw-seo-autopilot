<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Core {
    const TAG = '[TMW-SEO-GEN]';
    const MODEL_PT = 'model';
    const VIDEO_PT = 'video';
    const POST_TYPE = self::MODEL_PT;

    /** Defaults via constants (wp-config) or sane fallbacks */
    public static function brand_order(): array {
        $order = defined('TMW_SEO_BRAND_ORDER') ? TMW_SEO_BRAND_ORDER : 'jasmin,myc,lpr,joy,lsa';
        return array_values(array_filter(array_map('trim', explode(',', strtolower($order)))));
    }
    public static function subaff_pattern(): string {
        return defined('TMW_SEO_SUBAFF_PATTERN') ? TMW_SEO_SUBAFF_PATTERN : '{slug}-{brand}-{postId}';
    }
    public static function default_og(): string {
        return defined('TMW_SEO_DEFAULT_OG') ? TMW_SEO_DEFAULT_OG : '';
    }

    /** Public API */
    public static function generate_for_video(int $video_id, array $args = []): array {
        $args = wp_parse_args($args, ['strategy' => 'template', 'dry_run' => false, 'insert_content' => true]);
        $post = get_post($video_id);
        if (!$post || $post->post_type !== self::VIDEO_PT) return ['ok' => false, 'message' => 'Not a video'];

        $name = self::detect_model_name_from_video($post);
        if (!$name) return ['ok' => false, 'message' => 'No model name detected'];

        $model_id = self::ensure_model_exists($name);
        // Build contexts
        $ctx_video = self::build_ctx_video($video_id, $model_id, $name, $args);
        $ctx_model = self::build_ctx_model($model_id, $name, array_merge($args, ['video_id' => $video_id]));

        $provider = self::resolve_provider($args);
        $payload_video = $provider->generate_video($ctx_video);
        $payload_model = $provider->generate_model($ctx_model);

        if (!self::valid_payload($payload_video) || !self::valid_payload($payload_model)) {
            return ['ok' => false, 'message' => 'Payload incomplete'];
        }

        $payload_video = self::ensure_cta($payload_video, $ctx_video);
        $payload_model = self::ensure_cta($payload_model, $ctx_model);

        if (empty($args['dry_run'])) {
            self::write_all($video_id, $payload_video, 'VIDEO', ['insert_content' => !empty($args['insert_content'])]);
            self::write_all($model_id, $payload_model, 'MODEL', ['insert_content' => !empty($args['insert_content'])]);

            self::link_video_to_model($video_id, $model_id);
            self::link_model_to_video($model_id, $video_id);
        }

        error_log(self::TAG . " generated video#$video_id & model#$model_id for {$name}");
        return ['ok' => true, 'video' => $payload_video, 'model' => $payload_model, 'model_id' => $model_id];
    }

    /** Generate for a standalone model (manual tools) */
    public static function generate_for_model(int $model_id, array $args = []): array {
        $args = wp_parse_args($args, ['strategy' => 'template', 'dry_run' => false, 'insert_content' => true]);
        $post = get_post($model_id);
        if (!$post || $post->post_type !== self::MODEL_PT) {
            return ['ok' => false, 'message' => 'Not a model'];
        }

        $ctx_model = self::build_ctx_model($model_id, $post->post_title, $args);
        $provider = self::resolve_provider($args);
        $payload = $provider->generate_model($ctx_model);
        if (!self::valid_payload($payload)) {
            return ['ok' => false, 'message' => 'Payload incomplete'];
        }
        $payload = self::ensure_cta($payload, $ctx_model);

        if (empty($args['dry_run'])) {
            self::write_all($model_id, $payload, 'MODEL', ['insert_content' => !empty($args['insert_content'])]);
        }

        error_log(self::TAG . " generated model#$model_id manual");
        return ['ok' => true, 'model' => $payload];
    }

    /** Backwards compatibility */
    public static function generate_and_write(int $post_id, array $args = []): array {
        $args = wp_parse_args($args, ['strategy' => 'template', 'dry_run' => false]);
        $result = self::generate_for_model($post_id, $args);
        if (!$result['ok']) return $result;
        return ['ok' => true, 'payload' => $result['model']];
    }

    /** Ensure Model exists by exact post_title; returns ID */
    public static function ensure_model_exists(string $name): int {
        $model = get_page_by_title($name, OBJECT, self::MODEL_PT);
        if ($model) return (int) $model->ID;
        $id = wp_insert_post([
            'post_type' => self::MODEL_PT,
            'post_status' => 'publish',
            'post_title' => $name,
            'post_content' => '',
        ]);
        error_log(self::TAG . " created model#$id for {$name}");
        return (int) $id;
    }

    /** Detect model name from meta/tax/title */
    public static function detect_model_name_from_video(\WP_Post $post): string {
        $name = '';
        $name = trim((string) get_post_meta($post->ID, 'awe_model_name', true));
        if (!$name) {
            foreach (['models', 'model'] as $tax) {
                if (taxonomy_exists($tax)) {
                    $names = wp_get_post_terms($post->ID, $tax, ['fields' => 'names']);
                    if (!is_wp_error($names) && !empty($names)) {
                        $name = (string) $names[0];
                        break;
                    }
                }
            }
        }
        if (!$name) {
            $t = wp_strip_all_tags($post->post_title);
            $parts = preg_split('/\s+—\s+|-+/', $t);
            if (!empty($parts[0])) $name = trim($parts[0]);
        }
        return $name;
    }

    /** Build CTA (LiveJasmin first, with fallbacks) */
    public static function affiliate_url(string $name, string $brand = '', int $post_id = 0, string $slug = ''): string {
        $brands = self::brand_order();
        if (!$brand) $brand = $brands[0] ?? 'jasmin';
        $psid = defined('TMW_SEO_PSID') ? TMW_SEO_PSID : 'Topmodels4u';
        $pstool = defined('TMW_SEO_PSTOOL') ? TMW_SEO_PSTOOL : '205_1';
        $prog = defined('TMW_SEO_PSPROGRAM') ? TMW_SEO_PSPROGRAM : 'revs';
        $handle = rawurlencode(preg_replace('/\s+/', '', $name));
        $slug = $slug ?: sanitize_title($name);
        $sub_aff = self::format_subaff($slug, $brand, $post_id);
        $url = add_query_arg([
            'siteId' => $brand,
            'categoryName' => 'girl',
            'pageName' => 'freechat',
            'performerName' => $handle,
            'prm[psid]' => $psid,
            'prm[pstool]' => $pstool,
            'prm[psprogram]' => $prog,
            'prm[campaign_id]' => '',
            'subAffId' => $sub_aff,
        ], 'https://ctwmsg.com/');
        return $url;
    }

    protected static function format_subaff(string $slug, string $brand, int $post_id): string {
        $pattern = self::subaff_pattern();
        $replacements = [
            '{slug}' => $slug ?: 'post',
            '{brand}' => $brand ?: 'brand',
            '{postId}' => $post_id ?: 0,
        ];
        $out = strtr($pattern, $replacements);
        return sanitize_key(str_replace([' ', '|'], '-', strtolower($out)));
    }

    /** Build contexts */
    protected static function build_ctx_video(int $video_id, int $model_id, string $name, array $args): array {
        $looks = self::first_looks($video_id);
        $hook = $looks[0] ?? 'highlights';
        $title = get_the_title($video_id);
        $slug = self::slug_for($video_id);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $brand = self::brand_order()[0] ?? 'jasmin';
        $cta_url = self::affiliate_url($name, $brand, $video_id, $slug);
        $focus = sprintf('%s %s', $name, $hook);
        $extras = self::pick_extras($name, $looks, ['highlights', 'reel', 'live chat']);
        $model_link = get_permalink($model_id) ?: '';
        $model_title = get_the_title($model_id) ?: $name;

        return compact('video_id', 'model_id', 'name', 'title', 'slug', 'site', 'hook', 'looks', 'focus', 'extras', 'cta_url', 'brand', 'model_link', 'model_title');
    }

    protected static function build_ctx_model(int $model_id, string $name, array $args): array {
        $looks = self::first_looks($model_id);
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $slug = self::slug_for($model_id);
        $brand = self::brand_order()[0] ?? 'jasmin';
        $cta_url = self::affiliate_url($name, $brand, $model_id, $slug);
        $focus = $name;
        $extras = self::pick_extras($name, $looks, ['live chat', 'profile', 'schedule']);
        $video_id = isset($args['video_id']) ? (int) $args['video_id'] : 0;
        $video_link = $video_id ? (get_permalink($video_id) ?: '') : '';
        $video_title = $video_id ? (get_the_title($video_id) ?: '') : '';
        return compact('model_id', 'name', 'slug', 'site', 'looks', 'focus', 'extras', 'cta_url', 'brand', 'video_id', 'video_link', 'video_title');
    }

    protected static function slug_for(int $post_id): string {
        $post_name = get_post_field('post_name', $post_id);
        if ($post_name) return $post_name;
        $permalink = get_permalink($post_id);
        if ($permalink) return basename(untrailingslashit($permalink));
        return (string) $post_id;
    }

    public static function first_looks(int $post_id): array {
        $out = [];
        foreach (['video_tag', 'post_tag', 'models', 'category'] as $tax) {
            if (!taxonomy_exists($tax)) continue;
            $names = wp_get_post_terms($post_id, $tax, ['fields' => 'names']);
            if (!is_wp_error($names)) {
                $out = array_merge($out, $names);
            }
        }
        $out = array_map('trim', $out);
        $out = array_filter($out, function ($v) {
            return $v !== '';
        });
        return array_values(array_unique($out));
    }

    protected static function pick_extras(string $name, array $looks, array $defaults): array {
        $choices = array_values(array_unique(array_merge($looks, $defaults)));
        $extras = [];
        foreach ($choices as $c) {
            if (strtolower($c) === strtolower($name)) continue;
            $extras[] = sprintf('%s %s', $name, trim($c));
            if (count($extras) >= 4) break;
        }
        while (count($extras) < 4) {
            $extras[] = $name . ' live chat';
        }
        return $extras;
    }

    protected static function resolve_provider(array $args) {
        if (!empty($args['strategy']) && $args['strategy'] === 'openai' && Providers\OpenAI::is_enabled()) {
            return new Providers\OpenAI();
        }
        return new Providers\Template();
    }

    protected static function valid_payload($payload): bool {
        return is_array($payload) && !empty($payload['title']) && !empty($payload['content']) && !empty($payload['keywords']);
    }

    protected static function ensure_cta(array $payload, array $ctx): array {
        $cta_url = $ctx['cta_url'] ?? '';
        if (!$cta_url) return $payload;
        $label = sprintf('Join %s live chat', $ctx['name']);
        $brand = strtoupper($ctx['brand'] ?? '');
        $cta_html = self::cta_markup($cta_url, $label, $brand);
        if (strpos($payload['content'], 'tmwseo-cta') === false) {
            $payload['content'] .= $cta_html;
        }
        $payload['cta_url'] = $cta_url;
        $payload['cta_label'] = $label;
        $payload['brand'] = $ctx['brand'] ?? '';
        return $payload;
    }

    protected static function cta_markup(string $url, string $label, string $brand): string {
        $label_safe = esc_html($label);
        $brand_safe = $brand ? '<span class="tmwseo-cta-brand">' . esc_html($brand) . '</span>' : '';
        return '\n<div class="tmwseo-cta">' . $brand_safe . '<a class="tmwseo-cta-link" href="' . esc_url($url) . '" target="_blank" rel="sponsored noopener">' . $label_safe . '</a></div>\n';
    }

    /** Write RankMath + content; $type = MODEL|VIDEO */
    protected static function write_all(int $post_id, array $payload, string $type, array $options = []): void {
        $post = get_post($post_id);
        if (!$post || empty($payload['title'])) return;

        $prev = [
            'rank_math_title' => get_post_meta($post_id, 'rank_math_title', true),
            'rank_math_description' => get_post_meta($post_id, 'rank_math_description', true),
            'rank_math_focus_keyword' => get_post_meta($post_id, 'rank_math_focus_keyword', true),
            'post_content' => $post->post_content,
        ];
        update_post_meta($post_id, "_tmwseo_prev_{$type}", $prev);

        $keywords = array_map('sanitize_text_field', (array) $payload['keywords']);
        $keywords = array_slice(array_filter($keywords), 0, 5);

        update_post_meta($post_id, 'rank_math_title', sanitize_text_field($payload['title']));
        update_post_meta($post_id, 'rank_math_description', sanitize_text_field($payload['meta'] ?? ''));
        update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));

        update_post_meta($post_id, 'rank_math_facebook_title', sanitize_text_field($payload['title']));
        update_post_meta($post_id, 'rank_math_facebook_description', sanitize_text_field($payload['meta'] ?? ''));
        update_post_meta($post_id, 'rank_math_twitter_title', sanitize_text_field($payload['title']));
        update_post_meta($post_id, 'rank_math_twitter_description', sanitize_text_field($payload['meta'] ?? ''));
        if (self::default_og()) {
            update_post_meta($post_id, 'rank_math_facebook_image', esc_url_raw(self::default_og()));
            update_post_meta($post_id, 'rank_math_twitter_image', esc_url_raw(self::default_og()));
        }

        $start = "<!-- TMWSEO:{$type}:START -->";
        $end = "<!-- TMWSEO:{$type}:END -->";
        $content = $post->post_content ?: '';
        $content = preg_replace("#{$start}.*?{$end}#s", '', $content);

        $insert = !isset($options['insert_content']) || !empty($options['insert_content']);
        if ($insert) {
            $block_content = self::sanitize_block($payload['content']);
            $block_content = preg_replace('#<h1>#i', '<h2>', $block_content);
            $block_content = preg_replace('#</h1>#i', '</h2>', $block_content);
            $block = "\n{$start}\n" . $block_content . "\n{$end}\n";
            $content .= $block;
        }

        wp_update_post(['ID' => $post_id, 'post_content' => $content]);
    }

    protected static function sanitize_block(string $content): string {
        return wp_kses_post($content);
    }

    /** Cross-links */
    protected static function link_video_to_model(int $video_id, int $model_id): void {
        update_post_meta($video_id, '_tmwseo_model_id', $model_id);
    }
    protected static function link_model_to_video(int $model_id, int $video_id): void {
        update_post_meta($model_id, '_tmwseo_latest_video_id', $video_id);
    }

    public static function rollback(int $post_id): array {
        $post = get_post($post_id);
        if (!$post) return ['ok' => false, 'message' => 'Post not found'];
        $type = strtoupper($post->post_type === self::VIDEO_PT ? 'VIDEO' : 'MODEL');
        $prev = get_post_meta($post_id, "_tmwseo_prev_{$type}", true);
        if (!$prev) return ['ok' => false, 'message' => 'No previous values stored'];

        update_post_meta($post_id, 'rank_math_title', $prev['rank_math_title'] ?? '');
        update_post_meta($post_id, 'rank_math_description', $prev['rank_math_description'] ?? '');
        update_post_meta($post_id, 'rank_math_focus_keyword', $prev['rank_math_focus_keyword'] ?? '');

        if (isset($prev['post_content'])) {
            $start = "<!-- TMWSEO:{$type}:START -->";
            $end = "<!-- TMWSEO:{$type}:END -->";
            $clean = preg_replace("#{$start}.*?{$end}#s", '', $post->post_content);
            wp_update_post(['ID' => $post_id, 'post_content' => $clean]);
        }
        delete_post_meta($post_id, "_tmwseo_prev_{$type}");
        error_log(self::TAG . " rollback done for #$post_id");
        return ['ok' => true];
    }
}
