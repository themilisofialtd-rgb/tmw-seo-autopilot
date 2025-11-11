<?php
namespace TMW_SEO;
if (!defined('ABSPATH')) exit;

class Admin {
    const TAG = '[TMW-SEO-UI]';
    public static function boot() {
        add_action('add_meta_boxes', [__CLASS__, 'meta_box']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('wp_ajax_tmw_seo_generate', [__CLASS__, 'ajax_generate']);
        add_action('wp_ajax_tmw_seo_rollback', [__CLASS__, 'ajax_rollback']);
        add_filter('bulk_actions-edit-model', [__CLASS__, 'bulk_action']);
        add_filter('handle_bulk_actions-edit-model', [__CLASS__, 'handle_bulk'], 10, 3);
        add_action('admin_menu', [__CLASS__, 'tools_page']);
        add_action('add_meta_boxes', [__CLASS__, 'add_video_metabox']);
        add_action('save_post_video', [__CLASS__, 'save_video_metabox'], 10, 2);
    }

    public static function assets($hook) {
        if (strpos($hook, 'tmw-seo-autopilot') !== false) {
            wp_enqueue_style('tmw-seo-admin', TMW_SEO_URL . 'assets/admin.css', [], '1.0.0');
        }
    }

    public static function meta_box() {
        add_meta_box('tmw-seo-box', 'TMW SEO Autopilot', [__CLASS__, 'render_box'], Core::MODEL_PT, 'side', 'high');
    }

    public static function render_box($post) {
        wp_nonce_field('tmw_seo_box', 'tmw_seo_nonce');
        echo '<p>Generate RankMath fields + intro/bio/FAQ for this model.</p>';
        echo '<p><label><input type="checkbox" id="tmw_seo_insert" checked> Insert content block</label></p>';
        echo '<p>Strategy: <select id="tmw_seo_strategy"><option value="template">Template</option><option value="openai">OpenAI (if configured)</option></select></p>';
        echo '<p><button type="button" class="button button-primary" id="tmw_seo_generate_btn">Generate</button> <button type="button" class="button" id="tmw_seo_rollback_btn">Rollback</button></p>';
        ?>
        <script>
        (function($){
            $('#tmw_seo_generate_btn').on('click', function(){
                var data = {
                    action: 'tmw_seo_generate',
                    nonce: '<?php echo wp_create_nonce('tmw_seo_nonce'); ?>',
                    post_id: <?php echo (int)$post->ID; ?>,
                    insert: $('#tmw_seo_insert').is(':checked') ? 1 : 0,
                    strategy: $('#tmw_seo_strategy').val()
                };
                $(this).prop('disabled', true).text('Generating…');
                $.post(ajaxurl, data, function(resp){
                    alert(resp.data && resp.data.message ? resp.data.message : (resp.success ? 'Done' : 'Failed'));
                    location.reload();
                });
            });
            $('#tmw_seo_rollback_btn').on('click', function(){
                var data = {
                    action: 'tmw_seo_rollback',
                    nonce: '<?php echo wp_create_nonce('tmw_seo_nonce'); ?>',
                    post_id: <?php echo (int)$post->ID; ?>
                };
                $.post(ajaxurl, data, function(resp){
                    alert(resp.success ? 'Rollback complete' : 'Nothing to rollback');
                    location.reload();
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public static function ajax_generate() {
        check_ajax_referer('tmw_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(['message' => 'No permission']);
        $post_id = (int)($_POST['post_id'] ?? 0);
        $strategy = sanitize_text_field($_POST['strategy'] ?? 'template');
        $insert = !empty($_POST['insert']);
        $res = Core::generate_and_write($post_id, ['strategy' => $strategy, 'insert_content' => $insert]);
        if ($res['ok']) wp_send_json_success(['message' => 'SEO generated']);
        wp_send_json_error(['message' => $res['message'] ?? 'Error']);
    }

    public static function ajax_rollback() {
        check_ajax_referer('tmw_seo_nonce', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error();
        $post_id = (int)($_POST['post_id'] ?? 0);
        $res = Core::rollback($post_id);
        $res['ok'] ? wp_send_json_success() : wp_send_json_error();
    }

    public static function bulk_action($actions) {
        $actions['tmw_seo_generate_bulk'] = 'Generate SEO (TMW)';
        return $actions;
    }

    public static function handle_bulk($redirect, $doaction, $ids) {
        if ($doaction !== 'tmw_seo_generate_bulk') return $redirect;
        $count = 0;
        foreach ($ids as $id) {
            $r = Core::generate_and_write((int)$id, ['strategy' => 'template', 'insert_content' => true]);
            if (!empty($r['ok'])) $count++;
        }
        return add_query_arg('tmw_seo_bulk_done', $count, $redirect);
    }

    public static function tools_page() {
        add_submenu_page('tools.php', 'TMW SEO Autopilot', 'TMW SEO Autopilot', 'manage_options', 'tmw-seo-autopilot', [__CLASS__, 'render_tools']);
    }

    public static function render_tools() {
        if (!current_user_can('manage_options')) return;
        if (!empty($_POST['tmw_seo_run'])) {
            check_admin_referer('tmw_seo_tools');
            $limit = max(1, (int)$_POST['limit']);
            $q = new \WP_Query(['post_type' => Core::MODEL_PT, 'posts_per_page' => $limit, 'post_status' => 'publish']);
            $done = 0;
            while ($q->have_posts()) { $q->the_post();
                $r = Core::generate_and_write(get_the_ID(), ['strategy' => 'template', 'insert_content' => true]);
                if (!empty($r['ok'])) $done++;
            } wp_reset_postdata();
            echo '<div class="updated"><p>Generated for ' . (int)$done . ' models.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>TMW SEO Autopilot</h1>
            <form method="post">
                <?php wp_nonce_field('tmw_seo_tools'); ?>
                <p>Run a quick backfill using Template provider.</p>
                <p><label>Limit <input type="number" name="limit" value="25" min="1" max="500"></label></p>
                <p><button class="button button-primary" name="tmw_seo_run" value="1">Run Now</button></p>
                <hr>
                <p>Optional OpenAI provider: define <code>OPENAI_API_KEY</code> in wp-config.php or set constant <code>TMW_SEO_OPENAI</code> with your key to enable.</p>
            </form>
            <?php
            echo '<hr><h2>Integration Settings (read-only)</h2><table class="widefat"><tbody>';
            echo '<tr><th>Brand order</th><td>' . esc_html(implode(' → ', \TMW_SEO\Core::brand_order())) . '</td></tr>';
            echo '<tr><th>SUBAFF pattern</th><td>' . esc_html(\TMW_SEO\Core::subaff_pattern()) . '</td></tr>';
            echo '<tr><th>Default OG image</th><td>' . (\TMW_SEO\Core::default_og() ? '<code>' . esc_url(\TMW_SEO\Core::default_og()) . '</code>' : '<em>none</em>') . '</td></tr>';
            echo '</tbody></table>';
            ?>
        </div>
        <?php
    }

    public static function add_video_metabox() {
        add_meta_box(
            'tmwseo_video_box',
            __('TMW SEO Autopilot', 'tmwseo'),
            [__CLASS__, 'render_video_box'],
            \TMW_SEO\Core::VIDEO_PT,
            'side',
            'high'
        );
    }

    public static function render_video_box($post) {
        wp_nonce_field('tmwseo_video_box', 'tmwseo_video_box_nonce');
        $val = get_post_meta($post->ID, 'tmwseo_model_name', true);
        echo '<p><label for="tmwseo_model_name"><strong>'.esc_html__('Model Name (override)','tmwseo').'</strong></label></p>';
        echo '<input type="text" id="tmwseo_model_name" name="tmwseo_model_name" value="'.esc_attr($val).'" class="widefat" placeholder="e.g., Abby Murray" />';
        echo '<p class="description">'.esc_html__('Only needed if detection from the title/taxonomies is wrong.','tmwseo').'</p>';
    }

    public static function save_video_metabox($post_id, $post) {
        if (!isset($_POST['tmwseo_video_box_nonce']) || !wp_verify_nonce($_POST['tmwseo_video_box_nonce'], 'tmwseo_video_box')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if ($post->post_type !== \TMW_SEO\Core::VIDEO_PT) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $val = isset($_POST['tmwseo_model_name']) ? sanitize_text_field(wp_unslash($_POST['tmwseo_model_name'])) : '';
        if ($val !== '') {
            update_post_meta($post_id, 'tmwseo_model_name', $val);
        } else {
            delete_post_meta($post_id, 'tmwseo_model_name');
        }
    }
}
