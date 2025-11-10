<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

if (defined('WP_CLI') && WP_CLI) {
    class CLI {
        public static function boot() {
            \WP_CLI::add_command('tmw-seo generate', [__CLASS__, 'generate']);
            \WP_CLI::add_command('tmw-seo rollback', [__CLASS__, 'rollback']);
        }
        public static function generate($args, $assoc) {
            $pt = $assoc['post_type'] ?? Core::MODEL_PT;
            $limit = isset($assoc['limit']) ? (int) $assoc['limit'] : 100;
            $dry = !empty($assoc['dry-run']);
            $strategy = isset($assoc['strategy']) ? sanitize_text_field($assoc['strategy']) : 'template';
            $q = new \WP_Query(['post_type' => $pt, 'posts_per_page' => $limit, 'post_status' => 'publish']);
            $done = 0;
            while ($q->have_posts()) {
                $q->the_post();
                $id = get_the_ID();
                if ($pt === Core::VIDEO_PT) {
                    $r = Core::generate_for_video($id, ['dry_run' => $dry, 'strategy' => $strategy]);
                } else {
                    $r = Core::generate_for_model($id, ['dry_run' => $dry, 'strategy' => $strategy, 'insert_content' => true]);
                }
                if (!empty($r['ok'])) $done++;
            }
            wp_reset_postdata();
            \WP_CLI::success("Generated SEO for $done posts.");
        }
        public static function rollback($args, $assoc) {
            $id = (int)($assoc['post_id'] ?? 0);
            if (!$id) { \WP_CLI::error('Provide --post_id=ID'); return; }
            $r = Core::rollback($id);
            $r['ok'] ? \WP_CLI::success('Rollback complete') : \WP_CLI::error('Nothing to rollback');
        }
    }
    CLI::boot();
}
