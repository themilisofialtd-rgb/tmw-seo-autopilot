<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

class Template {
    public function generate(array $ctx): array {
        $name = $ctx['name'];
        $site = $ctx['site'];
        $primary = $ctx['primary'];
        $looks = $ctx['looks'];
        $looks_str = $looks ? implode(', ', $looks) : strtolower($primary);

        $title = sprintf('%s — Live Chat & %s', $name, ucfirst($primary));
        $meta  = sprintf('Meet %s. Explore %s looks and join live chat on %s. Bio, photos, and links updated regularly.', $name, strtolower($primary), $site);
        $focus = array_unique(array_filter([
            "$name live chat",
            $primary,
            $site,
        ]));

        // Optional internal link to the first available term
        $link_html = '';
        $taxes = ['post_tag','models','category'];
        foreach ($taxes as $tax) {
            if (!empty($looks[0]) && taxonomy_exists($tax)) {
                $term = get_term_by('name', $looks[0], $tax);
                if ($term && !is_wp_error($term)) {
                    $url = get_term_link($term);
                    if (!is_wp_error($url)) {
                        $link_html = '<p>Browse more: <a href="' . esc_url($url) . '">' . esc_html($term->name) . '</a></p>';
                        break;
                    }
                }
            }
        }

        $intro = sprintf(
            '%s brings confident, friendly energy to %s. Known for polished visuals and relaxed chat, they blend editorial styling with interactive live sessions. Favorite looks: %s.',
            esc_html($name), esc_html($site), esc_html($looks_str)
        );

        $bio = implode("\n\n", [
            "$name’s profile favors clean compositions, rich color styling, and a smooth pace that makes chat feel personal.",
            "Between sessions, expect short teasers and simple updates so you always know when something new is coming. Fans mention the consistent vibe and the way regulars get remembered.",
            "Want to catch $name live? Evenings are often best, with weekend pops when the schedule allows. Bookmark the profile, enable notifications, and drop a hello when you arrive.",
        ]);

        $content  = '<h2>Intro</h2><p>' . esc_html($intro) . '</p>';
        if ($link_html) $content .= $link_html;
        $content .= '<h2>Bio</h2>' . wpautop(esc_html($bio));
        $content .= '<h2>FAQ</h2>';
        $faq = [
            ['When is ' . $name . ' usually online?', 'Most evenings with occasional weekend sessions; check the banner note on the profile.'],
            ['What content is on the page?', 'Teasers, favorite looks, schedule notes, and live chat links.'],
            ['How do I support ' . $name . '?', 'Join live chat, bookmark the profile, and share favorite posts.'],
        ];
        foreach ($faq as [$q,$a]) {
            $content .= '<h3>' . esc_html($q) . '</h3><p>' . esc_html($a) . '</p>';
        }

        return [
            'title' => sanitize_text_field($title),
            'meta' => sanitize_text_field($meta),
            'focus' => array_map('sanitize_text_field', $focus),
            'content' => wp_kses_post($content),
        ];
    }
}
