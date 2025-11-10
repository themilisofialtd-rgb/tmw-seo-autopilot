<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

class OpenAI {
    public static function is_enabled(): bool {
        return defined('TMW_SEO_OPENAI') || defined('OPENAI_API_KEY');
    }

    private function api_key(): string {
        return defined('TMW_SEO_OPENAI') ? TMW_SEO_OPENAI : (defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '');
    }

    public function generate_video(array $ctx): array {
        $payload = $this->generate('video', $ctx);
        if (!$this->is_valid($payload)) {
            return (new Template())->generate_video($ctx);
        }
        return $payload;
    }

    public function generate_model(array $ctx): array {
        $payload = $this->generate('model', $ctx);
        if (!$this->is_valid($payload)) {
            return (new Template())->generate_model($ctx);
        }
        return $payload;
    }

    private function generate(string $type, array $ctx) {
        if (!$this->api_key()) {
            return null;
        }
        $name = $ctx['name'] ?? '';
        $site = $ctx['site'] ?? '';
        $focus = $ctx['focus'] ?? $name;
        $extras = implode(', ', array_slice((array) ($ctx['extras'] ?? []), 0, 4));
        $looks = implode(', ', array_slice((array) ($ctx['looks'] ?? []), 0, 6));
        $cta_label = sprintf('Join %s live chat', $name);
        $cta_url = $ctx['cta_url'] ?? '';
        if ($type === 'video') {
            $brief = 'Write a ~1000 word video highlight article with sections: Intro, Highlights, Production & Pacing, Next Steps, FAQ. Mention the linked model profile and reinforce the live chat CTA.';
        } else {
            $brief = 'Write a ~1000 word model profile article with sections: Intro, Bio, Schedule & Experience, Support tips, FAQ. Mention the featured video if provided and reinforce the live chat CTA.';
        }
        $prompt = sprintf(
            "%s\nName: %s\nSite: %s\nPrimary focus: %s\nExtra keywords: %s\nLooks/Tags: %s\nCTA Label: %s\nCTA URL: %s\nReturn JSON with keys: title, meta, keywords (array of 5), content (HTML with h2/h3 + paragraphs, include CTA link once).",
            $brief,
            $name,
            $site,
            $focus,
            $extras,
            $looks,
            $cta_label,
            $cta_url
        );

        $res = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key(),
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
            'body' => wp_json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful SEO assistant. Avoid NSFW language and keep content positive.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
            ]),
        ]);

        if (is_wp_error($res)) {
            error_log('[TMW-SEO-GEN] OpenAI error: ' . $res->get_error_message());
            return null;
        }

        $json = json_decode(wp_remote_retrieve_body($res), true);
        $text = $json['choices'][0]['message']['content'] ?? '';
        $payload = json_decode($text, true);
        if (!$this->is_valid($payload)) {
            return null;
        }

        $payload['title'] = sanitize_text_field($payload['title']);
        $payload['meta'] = sanitize_text_field($payload['meta']);
        $payload['keywords'] = array_slice(array_map('sanitize_text_field', (array) $payload['keywords']), 0, 5);
        $payload['content'] = wp_kses_post($payload['content']);
        return $payload;
    }

    private function is_valid($payload): bool {
        return is_array($payload) && !empty($payload['title']) && !empty($payload['content']) && !empty($payload['keywords']);
    }
}
