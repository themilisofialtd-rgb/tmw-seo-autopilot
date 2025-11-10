<?php
namespace TMW_SEO\Providers;
if (!defined('ABSPATH')) exit;

class Template {
    /** VIDEO: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_video(array $c): array {
        $name = $c['name'];
        $hook = $c['hook'];
        $site = $c['site'];
        $model_link = $c['model_link'] ?? '';
        $model_title = $c['model_title'] ?? $name;
        $keywords = array_merge([$c['focus']], array_slice((array) ($c['extras'] ?? []), 0, 4));

        $title = sprintf('%s — 7 Signature %s Highlights & Live Chat Preview', $name, ucwords($hook));
        $meta = sprintf('%s curates a %s showcase with seven signature beats, pace notes, and a direct jump into live chat on %s. Follow the cues, explore the model profile, and join the session instantly.', $name, strtolower($hook), $site);

        $intro = sprintf('%s stacks seven crisp chapters into a %s reel designed to feel like a guided tour of their live chat.', $name, strtolower($hook));
        $overview = sprintf('The cut moves from warm-up smiles to confident feature shots, always leaving space for %s to look straight down the lens and invite real-time conversation.', $name);
        $segment_one = sprintf('Opening beats keep the tempo low: gentle camera moves, consistent lighting, and space for %s to reset body language between poses so the transitions never feel rushed.', $name);
        $segment_two = sprintf('By the midpoint the reel leans on color blocking—cool blues against warm studio ambers—mirroring how %s plays with lighting cues on live chat to signal when a new game or topic is about to start.', $name);
        $segment_three = sprintf('The final minutes push in closer, highlighting expressive eye contact and small gestures that regulars know as pre-show signals. It’s a deliberate teaser for the premium portions of %s’s schedule.', $name);
        $production = 'Footage is captured in 4K but mastered for fast streaming, so the file loads instantly on mobile without losing clarity on desktop. Cuts land on the beat, giving the whole video an easy rewatch rhythm.';
        $audio = 'Background audio stays instrumental and light, fading under the voice track so viewers can focus on breathing, outfit textures, and subtle ASMR moments that hint at interactive segments.';
        $engagement = sprintf('Every chapter overlays clear lower-thirds that echo the live chat CTA wording—“Join %s now” and “Queue your requests”—so new viewers know exactly what to do next.', $name);
        $workflow = 'Transitions are mapped against a beat grid, so editors can drop in fresh clips each week without rebuilding the entire timeline. That keeps the reel evergreen and aligned with current promotions.';
        $interaction = sprintf('Cutaway shots focus on hand gestures, keyboard taps, and chat screenshot overlays to show how %s responds to fan prompts in real time. It reinforces that this isn’t a static gallery—it’s a doorway to live conversation.', $name);
        $model_prompt = $model_link
            ? sprintf('Need more context? Visit the <a href="%s">%s model profile</a> to read bio notes, skim the photo sets, and bookmark upcoming appearances.', esc_url($model_link), esc_html($model_title))
            : '';
        $cta_prompt = sprintf('When the highlight montage ends, use the “Join %s live chat” button to hop onto LiveJasmin. The reel is timed so the CTA appears right when anticipation peaks.', $name);
        $support = sprintf('Returning fans can treat this page as a trailer hub: drop comments with timestamp shout-outs, share the link in group chats, and keep the autoplay loop running while waiting for %s to go live.', $name);
        $faq = [
            [sprintf('When is %s usually live?', $name), 'Most weekdays after 19:00 local time with bonus brunch shows on Sundays. The banner note updates if the schedule shifts.'],
            ['What gear was used for this reel?', 'A dual-camera rig with a soft key light, fill light, and a color-controlled backdrop so tones stay flattering on any screen.'],
            ['How do I join the live chat instantly?', 'Tap the sponsored button on this page; it routes straight to LiveJasmin with tracking handled automatically.'],
            [sprintf('Where can I find more from %s?', $name), 'Bookmark the model profile for blog posts, travel updates, and fresh teaser clips synced with this video.'],
        ];

        $blocks = [
            ['h2', 'Intro'],
            ['p', $intro],
            ['p', $overview],
            ['h2', 'Highlights'],
            ['p', $segment_one],
            ['p', $segment_two],
            ['p', $segment_three],
            ['h2', 'Production & Pacing'],
            ['p', $production],
            ['p', $audio],
            ['p', $workflow],
            ['p', $interaction],
            ['p', $engagement],
        ];
        if ($model_prompt) {
            $blocks[] = ['raw', '<p>' . $model_prompt . '</p>'];
        }
        $blocks = array_merge($blocks, [
            ['p', $cta_prompt],
            ['p', $support],
            ['h2', $name . ' — FAQ'],
        ], $this->faq_html($faq));

        $content = $this->html($blocks);

        return ['title' => $title, 'meta' => $meta, 'keywords' => $keywords, 'content' => $content];
    }

    /** MODEL: returns ['title','meta','keywords'=>[5],'content'] */
    public function generate_model(array $c): array {
        $name = $c['name'];
        $site = $c['site'];
        $video_link = $c['video_link'] ?? '';
        $video_title = $c['video_title'] ?? '';
        $keywords = array_merge([$c['focus']], array_slice((array) ($c['extras'] ?? []), 0, 4));

        $title = sprintf('%s — Live Chat Schedule, Bio & Gallery Hub', $name);
        $meta = sprintf('%s on %s. Explore bio notes, lookbook highlights, and jump straight into live chat with daily schedule cues.', $name, $site);

        $intro = sprintf('%s brings polished, friendly energy to every appearance. This profile keeps all core details in one place—bio, schedule notes, highlight videos, and quick links for real-time chat.', $name);
        $bio_top = sprintf('Fans know %s for relaxed pacing and carefully planned outfits. Sets rotate between studio backdrops and at-home streams so regulars can enjoy both cinematic lighting and casual, handheld check-ins.', $name);
        $bio_middle = sprintf('Between live shows, %s posts short-form updates: outfit polls, travel diaries, and warm-up selfies that tease the mood for upcoming sessions. Each update is archived here so nothing gets lost.', $name);
        $bio_bottom = 'Community moderators pin top fan questions and timecodes from recent streams, making it easy for new viewers to catch up before diving into chat.';
        $behind_scenes = sprintf('Exclusive behind-the-scenes notes outline equipment, playlists, and mood boards so superfans can mirror %s’s aesthetic at home or prepare themed gifts.', $name);
        $schedule = sprintf('Prime time is most evenings, but a pinned banner lists any pop-up shows or sponsor specials. Notifications go out via email list and social cards mirrored on %s.', $site);
        $experience = sprintf('New to the community? Start with the highlight playlists, then bookmark this page. The layout keeps CTA buttons and support links above the fold for quick conversions.', $name);
        $engagement_tips = 'Each section ends with a mini call-to-action—join the live chat, check the merch carousel, or DM the mod team—so visitors always know the next best step.';
        $membership = sprintf('VIP supporters get quarterly drop boxes with wallpaper packs, playlist codes, and handwritten notes from %s. Details live in the Support section and refresh every season.', $name);
        $video_prompt = $video_link
            ? sprintf('Watch the latest feature: <a href="%s">%s</a>. It loops key highlights and links back here so you never miss an update.', esc_url($video_link), esc_html($video_title ?: 'Latest video'))
            : '';
        $support = sprintf('Support %s by joining live chat, sharing the profile with friends, and leaving timestamped reactions under each new reel.', $name);
        $faq = [
            [sprintf('What makes %s’s style unique?', $name), sprintf('%s balances editorial lighting with an intimate tone, so each chat feels cinematic yet personal.', $name)],
            ['How often does the content update?', 'Fresh photos and clips land weekly, with micro-updates after every major live stream.'],
            ['Can I request custom sessions?', 'Use the pinned contact form or DM on the listed socials; availability is confirmed within 24 hours.'],
        ];

        $blocks = [
            ['h2', 'Intro'],
            ['p', $intro],
            ['h2', 'Bio'],
            ['p', $bio_top],
            ['p', $bio_middle],
            ['p', $bio_bottom],
            ['p', $behind_scenes],
            ['h2', 'Schedule & Experience'],
            ['p', $schedule],
            ['p', $experience],
            ['p', $engagement_tips],
            ['p', $membership],
        ];
        if ($video_prompt) {
            $blocks[] = ['raw', '<p>' . $video_prompt . '</p>'];
        }
        $blocks = array_merge($blocks, [
            ['p', $support],
            ['h2', $name . ' — FAQ'],
        ], $this->faq_html($faq));

        $content = $this->html($blocks);

        return ['title' => $title, 'meta' => $meta, 'keywords' => $keywords, 'content' => $content];
    }

    /* helpers */
    protected function html(array $blocks): string {
        $out = '';
        foreach ($blocks as $b) {
            [$tag, $txt] = $b;
            if ($tag === 'p') {
                $out .= '<p>' . esc_html($txt) . '</p>';
            } elseif ($tag === 'h2' || $tag === 'h3') {
                $out .= '<' . $tag . '>' . esc_html($txt) . '</' . $tag . '>';
            } elseif ($tag === 'raw') {
                $out .= wp_kses_post($txt);
            }
        }
        return $out;
    }

    protected function faq_html(array $rows): array {
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['h3', $r[0]];
            $out[] = ['p', $r[1]];
        }
        return $out;
    }
}
