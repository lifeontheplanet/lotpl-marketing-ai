<?php
/**
 * Plugin Name: LOTPL Ladakh Blog Generator (OpenAI)
 * Description: Generate LOTPL Ladakh drafts with GEO-friendly structures, comparison/question modes, structured JSON output, Rank Math fields, and image planning.
 * Version: 1.4.1
 * Author: OpenAI for LIFE on the PLANET LADAKH
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

class LOTPL_Ladakh_Blog_Generator_OpenAI {
    const OPTION_KEY = 'lotpl_lbg_openai_settings';
    const NONCE_ACTION = 'lotpl_lbg_create_post';
    const AJAX_NONCE_ACTION = 'lotpl_lbg_ajax';
    const LOG_OPTION = 'lotpl_lbg_last_log';
    const LOCK_TRANSIENT = 'lotpl_lbg_generation_lock';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_lotpl_lbg_generate', array($this, 'handle_generate'));
        add_action('admin_head', array($this, 'render_admin_styles'));

        add_action('add_meta_boxes', array($this, 'add_image_suggestions_meta_box'));
        add_action('add_meta_boxes', array($this, 'add_generated_assets_meta_box'));
        add_action('wp_ajax_lotpl_lbg_generate_image_suggestions', array($this, 'generate_image_suggestions_ajax'));
        add_action('add_attachment', array($this, 'queue_new_media_image_analysis'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Ladakh Blog Generator',
            'Blog Generator',
            'edit_posts',
            'lotpl-ladakh-blog-generator',
            array($this, 'render_admin_page'),
            'dashicons-edit-page',
            30
        );

        add_submenu_page(
            'lotpl-ladakh-blog-generator',
            'Settings',
            'Settings',
            'manage_options',
            'lotpl-ladakh-blog-generator-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'lotpl_lbg_settings_group',
            self::OPTION_KEY,
            array($this, 'sanitize_settings')
        );
    }

    public function sanitize_settings($input) {
        $current = $this->get_settings();

        $output = array(
            'api_key' => isset($input['api_key']) ? sanitize_text_field($input['api_key']) : $current['api_key'],
            'model' => isset($input['model']) ? sanitize_text_field($input['model']) : $current['model'],
            'enable_web_search' => !empty($input['enable_web_search']) ? 1 : 0,
            'default_word_count' => isset($input['default_word_count']) ? max(700, min(2200, intval($input['default_word_count']))) : $current['default_word_count'],
            'author_bio_name' => isset($input['author_bio_name']) ? sanitize_text_field($input['author_bio_name']) : $current['author_bio_name'],
            'blog_category_name' => isset($input['blog_category_name']) ? sanitize_text_field($input['blog_category_name']) : $current['blog_category_name'],
            'request_timeout' => isset($input['request_timeout']) ? max(60, min(240, intval($input['request_timeout']))) : $current['request_timeout'],
            'default_body_image_count' => isset($input['default_body_image_count']) && intval($input['default_body_image_count']) === 5 ? 5 : 4,
        );

        if (empty($output['model'])) {
            $output['model'] = 'gpt-5.4-mini';
        }

        if (empty($output['author_bio_name'])) {
            $output['author_bio_name'] = 'Junichiro Honjo';
        }

        if (empty($output['blog_category_name'])) {
            $output['blog_category_name'] = 'Blog';
        }

        return $output;
    }

    private function get_settings() {
        $defaults = array(
            'api_key' => '',
            'model' => 'gpt-5.4-mini',
            'enable_web_search' => 1,
            'default_word_count' => 1200,
            'author_bio_name' => 'Junichiro Honjo',
            'blog_category_name' => 'Blog',
            'request_timeout' => 180,
            'default_body_image_count' => 4,
        );

        $saved = get_option(self::OPTION_KEY, array());
        return wp_parse_args($saved, $defaults);
    }

    private function get_topic_map() {
        return array(
            'mixed' => 'Any interesting aspect of Ladakh or simple living in Ladakh',
            'culture' => 'Culture and history of Ladakh',
            'travel_tips' => 'Travel tips for visiting Ladakh',
            'sustainable' => 'Sustainable tourism in Ladakh',
            'seasonal' => 'Seasonal highlights and what to expect in Ladakh',
            'local_life' => 'Local life and people of Ladakh',
            'trekking' => 'Trekking experiences in Ladakh',
            'self_sufficiency' => 'Self-sufficiency in Ladakh: growing, making, and providing for yourself',
            'handcraft' => 'Handcraft in Ladakh: the value of making things by hand',
            'slowness' => 'Slowness in Ladakh: the case for living and moving more slowly',
            'empty_space' => 'Empty space in Ladakh: why emptiness matters',
            'living_small' => 'Living small in Ladakh: the freedom of a smaller life',
            'owning_less' => 'Owning less in Ladakh: what we gain when we let things go',
            'knowing_enough' => 'Knowing enough: the wisdom of sufficiency in Ladakh',
            'offgrid_living' => 'Off-grid living in Ladakh',
            'living_with_fire' => 'Living with fire in Ladakh',
            'living_with_water' => 'Living with water in Ladakh',
            'touching_the_soil' => 'Touching the soil in Ladakh',
            'sun_and_time' => 'Sun and time in Ladakh',
            'natures_rhythm' => 'Nature\'s rhythm in Ladakh',
            'fermentation' => 'Fermentation and preserved foods in Ladakh',
            'eating_in_season' => 'Eating in season in Ladakh',
            'local_food' => 'Local food in Ladakh',
            'eating_together' => 'Eating together in Ladakh',
            'joy_of_foraging' => 'Foraging and wild foods in Ladakh',
            'simple_food' => 'The richness of simple food in Ladakh',
            'walking_and_thinking' => 'Walking and thinking in Ladakh',
            'getting_lost' => 'Getting lost in Ladakh',
            'traveling_alone' => 'Traveling alone in Ladakh',
            'the_return_home' => 'Returning home after Ladakh',
            'traveling_light' => 'Traveling light in Ladakh',
            'passing_through' => 'Passing through versus staying in Ladakh',
            'reclaiming_night_sky' => 'Reclaiming the night sky in Ladakh',
            'value_of_silence' => 'The value of silence in Ladakh',
            'distance_from_wild' => 'Distance from the wild in Ladakh',
            'protecting_water' => 'Protecting water sources in Ladakh',
            'following_seasons' => 'Following the seasons in Ladakh',
            'glaciers_disappear' => 'When glaciers disappear in Ladakh',
            'being_hungry' => 'Being hungry and understanding need in Ladakh',
            'quality_of_sleep' => 'The quality of sleep in Ladakh',
            'what_walking_reveals' => 'What walking reveals in Ladakh',
            'making_by_hand' => 'Making by hand in Ladakh',
            'dawn_and_waking' => 'Dawn and waking in Ladakh',
            'living_in_noise' => 'Living in the noise versus Ladakh\'s quiet',
            'speed_as_sickness' => 'Speed as sickness seen from Ladakh',
            'overconsumption' => 'Overconsumption and restraint in Ladakh',
            'escaping_the_screen' => 'Escaping the screen in Ladakh',
            'dependence_and_freedom' => 'Dependence and freedom in Ladakh',
            'true_luxury' => 'What true luxury means in Ladakh',
        );
    }

    private function get_article_modes() {
        return array(
            'standard' => 'Standard GEO article',
            'comparison' => 'Comparison page',
            'faq' => 'FAQ page',
            'inquiry' => 'Inquiry-based article',
            'question' => 'Question-first page',
            'decision' => 'Decision page',
        );
    }

    private function get_travel_styles() {
        return array(
            'general' => 'General travelers',
            'family' => 'Family travel',
            'photography' => 'Photography-focused travelers',
            'first_time' => 'First-time visitors to Ladakh',
            'altitude_safe' => 'Altitude-conscious / acclimatization-first travelers',
            'slow_travel' => 'Slow travel / quieter pace',
            'short_trip' => 'Short-trip travelers with limited time',
            'comfort_first' => 'Comfort-first travelers',
            'culture_first' => 'Culture-first travelers',
            'scenic_first' => 'Scenery-first travelers',
        );
    }

    private function get_mode_instructions($mode) {
        $map = array(
            'standard' => 'Write a GEO-ready article with a quick answer first, then conditions, then a concise comparison section, then deeper explanation, FAQ, and a gentle inquiry CTA.',
            'comparison' => 'Write this as a comparison page. Start with a direct verdict. Include a short HTML comparison table near the top. Make differences explicit, practical, and decision-oriented. Add best-for / less-ideal-for guidance, FAQ, and a gentle inquiry CTA.',
            'faq' => 'Write this as a FAQ page. Start with a short direct answer block. Use tight, self-contained answers that AI systems can quote. Include one small comparison or bullet list where useful, then a gentle inquiry CTA.',
            'inquiry' => 'Write this as an article shaped from a real traveler inquiry. Start by answering the question directly in plain English. Then explain conditions, trade-offs, what to skip, and recommended route patterns. Include a comparison block, FAQ, and a gentle inquiry CTA.',
            'question' => 'Write this as a question-first page. Use the traveler-style question as the title direction, answer it immediately, then branch by conditions, include one short comparison block, then FAQ and a gentle inquiry CTA.',
            'decision' => 'Write this as a decision page. Start with a recommendation, explain why, show alternatives, say who each option suits and does not suit, include an HTML comparison table near the top, then FAQ and a gentle inquiry CTA.',
        );
        return isset($map[$mode]) ? $map[$mode] : $map['standard'];
    }

    private function get_travel_style_instructions($style) {
        $map = array(
            'general' => 'Write for generally interested Ladakh travelers and keep the advice broadly useful.',
            'family' => 'Prioritize family-friendliness, realistic drive times, comfort, pacing, and reduced altitude stress where possible.',
            'photography' => 'Prioritize light, landscape conditions, timing, viewpoint logic, and why one route may suit photographers better than another.',
            'first_time' => 'Prioritize clarity, reassurance, realistic acclimatization, what to skip, and what not to overpack into a first trip.',
            'altitude_safe' => 'Prioritize acclimatization, altitude risk reduction, gentler progression, and practical safety-minded route logic.',
            'slow_travel' => 'Prioritize fewer hotel changes, quieter pacing, meaningful local time, and avoiding checklist-style rushing.',
            'short_trip' => 'Prioritize ruthless clarity about what fits and what should be skipped when time is limited.',
            'comfort_first' => 'Prioritize easier pacing, comfort, recovery time, fewer tiring transfers, and a reassuring tone.',
            'culture_first' => 'Prioritize monasteries, villages, local rhythm, and why the route suits travelers who want cultural depth.',
                array('topic' => 'A comparison essay for travelers choosing between Ladakh and Nepal, examining what each destination offers and what kind of traveler belongs in each place.', 'keywords' => 'Ladakh vs Nepal, which is better, Himalayan travel, comparison, trekking, culture, altitude', 'question' => 'Should a traveler choose Ladakh or Nepal, and what does the answer reveal about what they are really seeking?', 'style' => 'general'),
                array('topic' => 'A comparison essay between trekking in Ladakh and trekking in Nepal, looking at terrain, altitude, remoteness, culture and what each experience demands of a traveler.', 'keywords' => 'Ladakh trekking vs Nepal trekking, Himalayan trekking comparison, which is harder, altitude, remoteness', 'question' => 'How does trekking in Ladakh compare to trekking in Nepal, and which suits a traveler who wants something less visited?', 'style' => 'general'),
                array('topic' => 'A comparison essay between Ladakh and Patagonia as trekking destinations, exploring what separates a Himalayan desert journey from a sub-Antarctic wilderness walk.', 'keywords' => 'Ladakh vs Patagonia, trekking comparison, world trekking, W trek, Markha Valley, best trekking destinations', 'question' => 'What separates a Ladakh trek from Patagonia, and why do some travelers choose the Himalayas over the ends of the earth?', 'style' => 'general'),
                array('topic' => 'A comparison essay between Ladakh and the Camino de Santiago, two journeys that share almost nothing except the fact that both change the people who walk them.', 'keywords' => 'Ladakh vs Camino de Santiago, spiritual trekking, walking journeys, pilgrimage, comparison', 'question' => 'How does walking in Ladakh compare to the Camino, and what does a traveler find in the Himalayas that a European pilgrimage cannot offer?', 'style' => 'culture_first'),
                array('topic' => 'A comparison essay between Ladakh and Bhutan as Himalayan destinations, examining access, culture, cost, and what each country protects by limiting its visitors.', 'keywords' => 'Ladakh vs Bhutan, Himalayan destinations, comparison, Buddhist culture, remote travel, sustainable tourism', 'question' => 'Should a traveler choose Ladakh or Bhutan, and what does each destination ask of someone who wants to travel with care?', 'style' => 'culture_first'),
                array('topic' => 'A comparison essay between the Markha Valley trek and the Everest Base Camp trek, two iconic Himalayan routes that attract very different kinds of travelers.', 'keywords' => 'Markha Valley vs Everest Base Camp, Himalayan trek comparison, EBC, Ladakh trekking, which trek', 'question' => 'How does the Markha Valley trek compare to Everest Base Camp, and why do some experienced trekkers prefer the quieter Ladakhi route?', 'style' => 'general'),
            'scenic_first' => 'Prioritize landscapes, dramatic contrasts, viewpoint logic, and why one route is visually stronger than another.',
        );
        return isset($map[$style]) ? $map[$style] : $map['general'];
    }

    private function get_mode_blurb($mode) {
        $map = array(
            'standard' => 'Answer-first article for GEO visibility and everyday destination publishing.',
            'comparison' => 'Built for A vs B pages, comparison tables, and clearer customer decisions.',
            'faq' => 'Short-answer format for AI-readable FAQ and objection handling.',
            'inquiry' => 'Turns a real traveler inquiry into a publishable article with strong answer-first logic.',
            'question' => 'Best when you want to publish exact traveler-style questions as standalone pages.',
            'decision' => 'Best for pages that recommend one option, explain trade-offs, and show alternatives.',
        );
        return isset($map[$mode]) ? $map[$mode] : '';
    }

    public function render_admin_styles() {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos((string) $screen->id, 'lotpl-ladakh-blog-generator') === false) {
            return;
        }
        echo '<style>
            .lotpl-lbg-shell{max-width:1100px}
            .lotpl-lbg-shell h1{font-weight:600;letter-spacing:-.02em}
            .lotpl-lbg-card{background:#fff;border:1px solid #e7dfd8;border-radius:18px;padding:24px 24px 18px;box-shadow:0 1px 2px rgba(35,25,15,.03);margin:18px 0}
            .lotpl-lbg-card h2{margin:0 0 16px;font-size:19px;line-height:1.3}
            .lotpl-lbg-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:20px;align-items:start}
            .lotpl-lbg-summary{background:linear-gradient(180deg,#fffdfb,#faf7f3);border:1px solid #eee4da;border-radius:16px;padding:18px}
            .lotpl-lbg-summary p{margin:0 0 10px;color:#5b544e;line-height:1.7}
            .lotpl-lbg-chip{display:inline-block;padding:4px 10px;border-radius:999px;background:#f5efe9;color:#7a3027;font-size:12px;font-weight:600;letter-spacing:.02em;margin:0 8px 8px 0}
            .lotpl-lbg-shell .form-table th{width:220px;padding:16px 12px 16px 0}
            .lotpl-lbg-shell .form-table td{padding:16px 0}
            .lotpl-lbg-shell input.regular-text,.lotpl-lbg-shell textarea.large-text,.lotpl-lbg-shell select{border-color:#d9d1ca;border-radius:10px;min-height:40px}
            .lotpl-lbg-shell textarea.large-text{min-height:110px;padding:10px 12px}
            .lotpl-lbg-shell .description{color:#6b625b}
            .lotpl-lbg-shell .button.button-primary.button-hero{background:#7a3027;border-color:#7a3027;min-height:46px;padding:0 20px;border-radius:999px;box-shadow:none}
            .lotpl-lbg-shell .button.button-primary.button-hero:hover{background:#67271f;border-color:#67271f}
            .lotpl-lbg-mode-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;margin-top:10px}
            .lotpl-lbg-mode-item{padding:12px;border:1px solid #eee4da;border-radius:12px;background:#fcfaf8}
            .lotpl-lbg-mode-item strong{display:block;margin-bottom:6px}
            @media (max-width:960px){.lotpl-lbg-grid{grid-template-columns:1fr}}
        </style>';
    }

    public function render_admin_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $settings = $this->get_settings();
        $topics = $this->get_topic_map();
        $article_modes = $this->get_article_modes();
        $travel_styles = $this->get_travel_styles();
        $log = get_option(self::LOG_OPTION, array());
        $notice = isset($_GET['lotpl_lbg_notice']) ? sanitize_text_field(wp_unslash($_GET['lotpl_lbg_notice'])) : '';
        $message = isset($_GET['lotpl_lbg_message']) ? sanitize_text_field(wp_unslash($_GET['lotpl_lbg_message'])) : '';
        ?>
        <div class="wrap lotpl-lbg-shell">
            <h1>Ladakh Essay Generator</h1>
            <p style="max-width:760px;font-size:15px;line-height:1.8;color:#5f5a54;">Create a quiet, traveler-facing Ladakh essay draft from the Research AI Technology guide. Use the media buttons to prepare and analyze Ladakh images, then create the essay draft. Images are still selected automatically from the analyzed Ladakh media library. Internal AI, SEO, GEO, and workflow language will not appear in the public article.</p>

            <?php if ($notice === 'success') : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message ? $message : 'Draft created successfully.'); ?></p></div>
            <?php elseif ($notice === 'error') : ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($message ? $message : 'Generation failed.'); ?></p></div>
            <?php endif; ?>

            <?php if (empty($settings['api_key'])) : ?>
                <div class="notice notice-warning"><p>OpenAI API key is not set yet. Please save it in <a href="<?php echo esc_url(admin_url('admin.php?page=lotpl-ladakh-blog-generator-settings')); ?>">Settings</a>.</p></div>
            <?php endif; ?>

            <div class="lotpl-lbg-grid">
                <div class="lotpl-lbg-card">
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="lotpl_lbg_generate">
                        <?php wp_nonce_field(self::NONCE_ACTION, 'lotpl_lbg_nonce'); ?>

                        <table class="form-table" role="presentation" style="display:none;">
                            <tr>
                                <th scope="row"><label for="lotpl_topic_category">Topic category</label></th>
                                <td>
                                    <select id="lotpl_topic_category" name="topic_category">
                                        <?php foreach ($topics as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html(ucwords(str_replace(array('_', '&amp;'), array(' ', '&'), $key)) . ' — ' . $label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Keep the base topic broad if you want, then steer the article with the custom topic or traveler question below.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_custom_topic">Custom Ladakh topic</label></th>
                                <td>
                                    <input type="text" class="regular-text" id="lotpl_custom_topic" name="custom_topic" placeholder="e.g. Apricot blossom walks in Sham Valley, women-led homestays, Stok winter mornings">
                                    <p class="description">Use this to steer the article toward a precise LOTPL angle.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_article_mode">Article mode</label></th>
                                <td>
                                    <select id="lotpl_article_mode" name="article_mode">
                                        <?php foreach ($article_modes as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Choose the publishing format you want the generator to prioritize.</p>
                                    <div class="lotpl-lbg-mode-grid">
                                        <?php foreach ($article_modes as $key => $label) : ?>
                                            <div class="lotpl-lbg-mode-item"><strong><?php echo esc_html($label); ?></strong><span><?php echo esc_html($this->get_mode_blurb($key)); ?></span></div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_travel_style">Travel type emphasis</label></th>
                                <td>
                                    <select id="lotpl_travel_style" name="travel_style">
                                        <?php foreach ($travel_styles as $key => $label) : ?>
                                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Shape the article for a specific LOTPL audience or planning style.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_source_question">Source inquiry / exact traveler question</label></th>
                                <td>
                                    <input type="text" class="regular-text" id="lotpl_source_question" name="source_question" placeholder="e.g. Is 7 days enough for a first trip to Ladakh with kids?">
                                    <p class="description">Use this for inquiry, question-first, decision, or answer-first pages.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_body_image_count">Body image count</label></th>
                                <td>
                                    <select id="lotpl_body_image_count" name="body_image_count">
                                        <option value="4" <?php selected(intval($settings['default_body_image_count']), 4); ?>>4 body images</option>
                                        <option value="5" <?php selected(intval($settings['default_body_image_count']), 5); ?>>5 body images</option>
                                    </select>
                                    <p class="description">Generate 4 or 5 image placements spread across the full article.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_keywords_hint">Related items / keywords</label></th>
                                <td>
                                    <textarea id="lotpl_keywords_hint" name="keywords_hint" rows="4" class="large-text" placeholder="e.g. Alchi, Takmachik, apricot blossom, village walks, eco stay, Indus Valley"></textarea>
                                    <p class="description">Free text or comma-separated hints. Good for route names, villages, traveler concerns, or comparison angles.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_word_count">Approximate word count</label></th>
                                <td>
                                    <input type="number" id="lotpl_word_count" name="word_count" value="<?php echo esc_attr($settings['default_word_count']); ?>" min="700" max="2200" step="50">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_tone">Writing angle</label></th>
                                <td>
                                    <select id="lotpl_tone" name="tone">
                                        <option value="editorial">Editorial and informative</option>
                                        <option value="essay">Essay-like and atmospheric</option>
                                        <option value="practical">Practical and traveler-friendly</option>
                                        <option value="seo">SEO-forward but natural</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="lotpl_future_dates_only">Future dates only</label></th>
                                <td>
                                    <label><input type="checkbox" id="lotpl_future_dates_only" name="future_dates_only" value="1" checked> Avoid past-dated event references unless rewritten naturally.</label>
                                </td>
                            </tr>
                        </table>

                        <div style="margin:18px 0 8px;padding:14px 16px;background:#faf8f4;border:1px solid #e7dfd6;border-radius:10px;max-width:760px;"><strong>Workflow</strong><br><span style="color:#6f665d;line-height:1.7;">1. Queue media images / 2. Analyze queued images / 3. Create a Ladakh essay draft with automatic image insertion.</span></div>
                        <p class="submit">
                            <button type="submit" class="button button-primary button-hero" name="lotpl_auto_source" value="research_ai">Create Essay Draft from Research AI Technology</button>
                            
                            <button type="submit" class="button button-secondary" name="lotpl_media_queue" value="1">Queue 30 Media Images</button>
                            <button type="submit" class="button button-secondary" name="lotpl_media_analyze_one" value="1">Analyze 10 Queued Images</button>
                            
                        </p>
                        <?php
                        $lotpl_queued_images = get_posts(array("post_type" => "attachment", "post_mime_type" => "image", "post_status" => "inherit", "posts_per_page" => -1, "fields" => "ids", "meta_key" => "_lotpl_ai_image_status", "meta_value" => "queued"));
                        $lotpl_done_images = get_posts(array("post_type" => "attachment", "post_mime_type" => "image", "post_status" => "inherit", "posts_per_page" => -1, "fields" => "ids", "meta_key" => "_lotpl_ai_image_status", "meta_value" => "done"));
                        $lotpl_usable_images = get_posts(array("post_type" => "attachment", "post_mime_type" => "image", "post_status" => "inherit", "posts_per_page" => -1, "fields" => "ids", "meta_query" => array(array("key" => "_lotpl_ai_image_status", "value" => "done"), array("key" => "_lotpl_ai_image_is_ladakh", "value" => "yes"))));
                        ?>
                        <p class="description" style="margin-top:8px;"><strong>Image Library:</strong> queued <?php echo intval(count($lotpl_queued_images)); ?> / analyzed <?php echo intval(count($lotpl_done_images)); ?> / usable Ladakh images <?php echo intval(count($lotpl_usable_images)); ?></p>
                        <?php $lotpl_last_angle = get_option("lotpl_lbg_last_angle", array()); if (count($lotpl_last_angle) > 0) : ?>
                        <p class="description" style="margin-top:8px;"><strong>Last angle used:</strong> <?php echo esc_html($lotpl_last_angle["topic"]); ?> <em style="color:#999;">(<?php echo esc_html($lotpl_last_angle["style"]); ?>)</em></p>
                        <p class="description"><strong>Question:</strong> <?php echo esc_html($lotpl_last_angle["question"]); ?></p>
                        <?php endif; ?>
                    </form>
                </div>
                <aside class="lotpl-lbg-summary" style="display:none;">
                    <p><span class="lotpl-lbg-chip">GEO</span><span class="lotpl-lbg-chip">Comparison</span><span class="lotpl-lbg-chip">Question-first</span><span class="lotpl-lbg-chip">Soft CTA</span></p>
                    <p>This generator is tuned for LOTPL pages that can be cited by AI systems, not just generic blog articles. It favors clear answers, comparison structures, practical travel decisions, and gentle inquiry paths.</p>
                    <div class="lotpl-lbg-mode-grid">
                        <?php foreach ($article_modes as $key => $label) : ?>
                            <div class="lotpl-lbg-mode-item"><strong><?php echo esc_html($label); ?></strong><span><?php echo esc_html($this->get_mode_blurb($key)); ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </div>

            <div class="lotpl-lbg-card">
                <h2>Recent Drafts</h2>
                <?php
                $drafts = get_posts(array(
                    'post_status' => 'draft',
                    'posts_per_page' => 8,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'post_type' => 'post',
                ));

                if ($drafts) {
                    echo '<ul>';
                    foreach ($drafts as $draft) {
                        echo '<li><a href="' . esc_url(get_edit_post_link($draft->ID)) . '">' . esc_html($draft->post_title) . '</a><br><small>Slug: <code>' . esc_html($draft->post_name ? $draft->post_name : '(no slug)') . '</code> — ' . esc_html(get_the_date('Y-m-d H:i', $draft)) . '</small>';
                        $m = get_post_meta($draft->ID, '_lotpl_lbg_article_mode', true);
                        $ts = get_post_meta($draft->ID, '_lotpl_lbg_travel_style', true);
                        if ($m || $ts) {
                            echo '<br><small>Mode: ' . esc_html($m ? $m : 'standard') . ' / Audience: ' . esc_html($ts ? $ts : 'general') . '</small>';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p>No drafts found.</p>';
                }
                ?>
            </div>

            <div class="lotpl-lbg-card">
                <h2>Last API log</h2>
                <?php if (!empty($log)) : ?>
                    <table class="widefat striped" style="max-width:1000px;">
                        <tbody>
                            <tr><td style="width:180px;"><strong>Time</strong></td><td><?php echo esc_html(isset($log['time']) ? $log['time'] : ''); ?></td></tr>
                            <tr><td><strong>Status</strong></td><td><?php echo esc_html(isset($log['status']) ? $log['status'] : ''); ?></td></tr>
                            <tr><td><strong>Message</strong></td><td><?php echo esc_html(isset($log['message']) ? $log['message'] : ''); ?></td></tr>
                            <tr><td><strong>HTTP code</strong></td><td><?php echo esc_html(isset($log['http_code']) ? $log['http_code'] : ''); ?></td></tr>
                            <tr><td><strong>Model</strong></td><td><?php echo esc_html(isset($log['model']) ? $log['model'] : ''); ?></td></tr>
                        </tbody>
                    </table>
                    <?php if (!empty($log['details'])) : ?>
                        <p><strong>Details</strong></p>
                        <textarea readonly class="large-text code" rows="12"><?php echo esc_textarea($log['details']); ?></textarea>
                    <?php endif; ?>
                <?php else : ?>
                    <p>No log yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->get_settings();
        ?>
        <div class="wrap lotpl-lbg-shell">
            <h1>Ladakh Blog Generator Settings</h1>
            <div class="lotpl-lbg-card">
                <form method="post" action="options.php">
                    <?php settings_fields('lotpl_lbg_settings_group'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="lotpl_api_key">OpenAI API key</label></th>
                            <td>
                                <input type="password" id="lotpl_api_key" name="<?php echo esc_attr(self::OPTION_KEY); ?>[api_key]" value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text" autocomplete="off">
                                <p class="description">Stored in WordPress options. Do not reuse old exposed keys.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotpl_model">Model</label></th>
                            <td>
                                <input type="text" id="lotpl_model" name="<?php echo esc_attr(self::OPTION_KEY); ?>[model]" value="<?php echo esc_attr($settings['model']); ?>" class="regular-text">
                                <p class="description">Default is <code>gpt-5.4-mini</code>. Switch to <code>gpt-5.4</code> when you want stronger comparison, research, and decision-style pages.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Web search</th>
                            <td>
                                <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_web_search]" value="1" <?php checked(!empty($settings['enable_web_search'])); ?>> Enable built-in web search for fresher factual grounding.</label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotpl_default_word_count">Default word count</label></th>
                            <td><input type="number" id="lotpl_default_word_count" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_word_count]" value="<?php echo esc_attr($settings['default_word_count']); ?>" min="700" max="2200" step="50"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotpl_author_bio_name">Author bio name</label></th>
                            <td><input type="text" id="lotpl_author_bio_name" name="<?php echo esc_attr(self::OPTION_KEY); ?>[author_bio_name]" value="<?php echo esc_attr($settings['author_bio_name']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotpl_blog_category_name">Default WP category name</label></th>
                            <td><input type="text" id="lotpl_blog_category_name" name="<?php echo esc_attr(self::OPTION_KEY); ?>[blog_category_name]" value="<?php echo esc_attr($settings['blog_category_name']); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotpl_request_timeout">Request timeout (seconds)</label></th>
                            <td><input type="number" id="lotpl_request_timeout" name="<?php echo esc_attr(self::OPTION_KEY); ?>[request_timeout]" value="<?php echo esc_attr($settings['request_timeout']); ?>" min="60" max="240" step="10"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="lotpl_default_body_image_count">Default body image count</label></th>
                            <td>
                                <select id="lotpl_default_body_image_count" name="<?php echo esc_attr(self::OPTION_KEY); ?>[default_body_image_count]">
                                    <option value="4" <?php selected(intval($settings['default_body_image_count']), 4); ?>>4 body images</option>
                                    <option value="5" <?php selected(intval($settings['default_body_image_count']), 5); ?>>5 body images</option>
                                </select>
                                <p class="description">Used as the default for new drafts and image suggestion planning.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_generate() {
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied.');
        }

        check_admin_referer(self::NONCE_ACTION, 'lotpl_lbg_nonce');

        if (get_transient(self::LOCK_TRANSIENT)) {
            $this->redirect_with_notice('error', 'Another generation request is still running. Please wait a moment and try again.');
        }

        set_transient(self::LOCK_TRANSIENT, 1, 5 * MINUTE_IN_SECONDS);

        if (isset($_POST["lotpl_media_queue"]) && sanitize_text_field(wp_unslash($_POST["lotpl_media_queue"])) === "1") {
            $queued_count = $this->queue_existing_media_images(30);
            delete_transient(self::LOCK_TRANSIENT);
            $this->redirect_with_notice("success", "Queued existing media images for AI analysis: " . intval($queued_count));
        }

        if (isset($_POST["lotpl_media_requeue"]) && sanitize_text_field(wp_unslash($_POST["lotpl_media_requeue"])) === "1") {
            global $wpdb;
            $ids=$wpdb->get_col("SELECT pm1.post_id FROM {$wpdb->postmeta} pm1 INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id=pm2.post_id WHERE pm1.meta_key='_lotpl_ai_image_status' AND pm1.meta_value='done' AND pm2.meta_key='_lotpl_ai_image_is_ladakh' AND pm2.meta_value='yes' AND pm1.post_id NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_lotpl_ai_image_reanalyzed' AND meta_value='yes') LIMIT 30"); if(!empty($ids)){$wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value='queued' WHERE meta_key='_lotpl_ai_image_status' AND post_id IN (".implode(",",array_map("intval",$ids)).")"); foreach($ids as $rid){update_post_meta(intval($rid),"_lotpl_ai_image_reanalyzed","pending");} }
            delete_transient(self::LOCK_TRANSIENT);
            $this->redirect_with_notice("success", "Re-queued 30 analyzed images.");
        }

        if (isset($_POST["lotpl_media_reanalyze"]) && sanitize_text_field(wp_unslash($_POST["lotpl_media_reanalyze"])) === "1") {
            $result=$this->analyze_queued_media_images(10);
            delete_transient(self::LOCK_TRANSIENT);
            if (is_wp_error($result)) { $this->redirect_with_notice("error",$result->get_error_message()); }
            $this->redirect_with_notice("success","Re-analyzed 10 queued images.");
        }

        if (isset($_POST["lotpl_media_analyze_one"]) && sanitize_text_field(wp_unslash($_POST["lotpl_media_analyze_one"])) === "1") {
            $analyzed_id = $this->analyze_queued_media_images(10);
            delete_transient(self::LOCK_TRANSIENT);
            if (is_wp_error($analyzed_id)) { $this->redirect_with_notice("error", $analyzed_id->get_error_message()); }
            $this->redirect_with_notice("success", "Analyzed queued media images: " . (is_array($analyzed_id) ? implode(", ", array_map("intval", $analyzed_id)) : intval($analyzed_id)));
        }

        $settings = $this->get_settings();
        $body_image_count = isset($_POST['body_image_count']) && intval($_POST['body_image_count']) === 5 ? 5 : intval($settings['default_body_image_count']);
        if ($body_image_count !== 5) { $body_image_count = 4; }
        if (empty($settings['api_key'])) {
            delete_transient(self::LOCK_TRANSIENT);
            $this->redirect_with_notice('error', 'OpenAI API key is missing. Save it in Settings first.');
        }

        $topic_category = isset($_POST['topic_category']) ? sanitize_text_field(wp_unslash($_POST['topic_category'])) : 'mixed';
        $custom_topic = isset($_POST['custom_topic']) ? sanitize_text_field(wp_unslash($_POST['custom_topic'])) : '';
        $keywords_hint = isset($_POST['keywords_hint']) ? sanitize_textarea_field(wp_unslash($_POST['keywords_hint'])) : '';
        $article_mode = isset($_POST['article_mode']) ? sanitize_text_field(wp_unslash($_POST['article_mode'])) : 'standard';
        $travel_style = isset($_POST['travel_style']) ? sanitize_text_field(wp_unslash($_POST['travel_style'])) : 'general';
        $source_question = isset($_POST['source_question']) ? sanitize_text_field(wp_unslash($_POST['source_question'])) : '';
        $body_image_count = isset($_POST['body_image_count']) && intval($_POST['body_image_count']) === 5 ? 5 : intval($settings['default_body_image_count']);
        if ($body_image_count !== 5) { $body_image_count = 4; }
        $word_count = isset($_POST['word_count']) ? max(700, min(2200, intval($_POST['word_count']))) : intval($settings['default_word_count']);
        $tone = isset($_POST['tone']) ? sanitize_text_field(wp_unslash($_POST['tone'])) : 'editorial';
        $future_dates_only = isset($_POST["future_dates_only"]);

        if (isset($_POST["lotpl_auto_source"]) && sanitize_text_field(wp_unslash($_POST["lotpl_auto_source"])) === "research_ai") {
            $topic_category = "mixed";
            $lotpl_essay_angles = array(
                array("topic" => "A quiet personal essay about arriving in Leh too quickly, then learning to slow down through light, altitude, tea, and the first evening walk.", "keywords" => "Leh, acclimatization, first evening, altitude, bazaar, tea, slow travel", "question" => "What does Ladakh begin to reveal when a traveler stops rushing on the first day?", "style" => "altitude_safe"),
                array("topic" => "An intimate essay about water, villages, and why life in a high-altitude desert gathers around small streams and patient seasons.", "keywords" => "Ladakh villages, water, barley fields, Indus Valley, local life, high-altitude desert", "question" => "How does water quietly shape the way a traveler understands Ladakh?", "style" => "culture_first"),
                array("topic" => "A reflective essay about the road to Nubra, where passes, military roads, poplars, sand, and silence change the meaning of distance.", "keywords" => "Nubra Valley, Khardung La, road journey, high passes, villages, silence", "question" => "Why does the road to Nubra feel like more than a transfer between places?", "style" => "general"),
                array("topic" => "A gentle essay about monasteries not as sightseeing stops, but as places where time, wind, prayer flags, and human patience gather.", "keywords" => "Ladakh monasteries, prayer flags, Hemis, Thiksey, Alchi, silence, culture", "question" => "How should a traveler approach monasteries in Ladakh without turning them into a checklist?", "style" => "culture_first"),
                array("topic" => "A personal essay about winter in Ladakh, when fewer travelers, harder cold, and a quieter sky reveal another side of the region.", "keywords" => "winter Ladakh, cold, snow, Leh winter, frozen mornings, quiet travel", "question" => "What kind of traveler is ready to meet Ladakh in winter?", "style" => "comfort_first"),
                array("topic" => "A slow essay about choosing one lake instead of trying to see every lake, and how Pangong or Tso Moriri asks for attention rather than collection.", "keywords" => "Pangong Tso, Tso Moriri, lake journey, altitude, slow route, Changthang", "question" => "Why might one lake, given enough time, be better than trying to see every famous place in Ladakh?", "style" => "short_trip"),
                array("topic" => "An observant essay about village homestays, kitchens, shared meals, and the small forms of hospitality that change a journey.", "keywords" => "Ladakh homestay, village life, kitchen, butter tea, local family, slow travel", "question" => "What does a traveler learn in Ladakh when the most important moment happens inside a kitchen?", "style" => "culture_first"),
                array("topic" => "A photographic essay in words about Ladakh light, morning shadows, dust, white stupas, mountain faces, and the patience needed to see well.", "keywords" => "Ladakh photography, light, mountains, stupas, morning, evening, landscape", "question" => "Why is patience more important than a camera in Ladakh?", "style" => "photography"),
                array("topic" => "A springtime essay about apricot blossoms in Ladakh, when white flowers open against brown mountains and the valley holds its breath before summer.", "keywords" => "apricot blossom, spring Ladakh, Sham Valley, flowers, orchard, April, seasonal", "question" => "What does Ladakh look like in the brief week when apricot trees are in bloom?", "style" => "general"),
                array("topic" => "A harvest essay about autumn in Ladakh, when fields turn gold, poplars go yellow, and families gather barley before the first cold arrives.", "keywords" => "harvest, autumn Ladakh, barley, golden fields, poplars, October, village life", "question" => "Why does autumn in Ladakh feel like the most grounded time to visit?", "style" => "culture_first"),
                array("topic" => "A river essay about the Indus in Ladakh, following its wide turns, its green-grey water, the villages that have grown beside it for centuries.", "keywords" => "Indus River, Ladakh, riverside villages, valley, water, landscape", "question" => "What does the Indus river reveal about how Ladakh was settled and how it still lives?", "style" => "general"),
                array("topic" => "A road essay about the journey toward Zanskar, where the route itself becomes the reason, long before any destination arrives.", "keywords" => "Zanskar, road, remote, mountain pass, river, silence, driving", "question" => "Why do some travelers say the road to Zanskar changes what they expect from travel?", "style" => "general"),
                array("topic" => "A quiet essay about nomadic families in Changthang, their tents, their animals, and a way of living that asks different questions of a traveler.", "keywords" => "Changthang, nomads, Changpa, pashmina, high plateau, tent, yak, livestock", "question" => "What does a visit to a nomadic family in Changthang ask of a traveler who is used to hotels?", "style" => "culture_first"),
                array("topic" => "A patient essay about searching for snow leopards in Ladakh, where waiting, cold, and stillness become the experience itself.", "keywords" => "snow leopard, wildlife, Hemis National Park, winter, patience, cold, mountains", "question" => "What kind of traveler finds meaning in a snow leopard search even without a sighting?", "style" => "comfort_first"),
                array("topic" => "A night essay about stargazing at altitude in Ladakh, where clear skies and thin air make the Milky Way feel close enough to reach.", "keywords" => "stargazing, Milky Way, night sky, altitude, Pangong, Nubra, dark sky", "question" => "Why does the night sky in Ladakh change the way a traveler thinks about scale?", "style" => "photography"),
                array("topic" => "An essay about walking through the old lanes of Leh, where carved wooden windows, prayer wheels, and unhurried shopkeepers remain.", "keywords" => "Leh old town, bazaar, wooden windows, prayer wheels, local shops, walking", "question" => "What survives of old Leh for a traveler willing to walk slowly through its lanes?", "style" => "altitude_safe"),
                array("topic" => "A festival essay about Hemis in summer, when monks in robes, masked dances, and drums fill the courtyard of the largest monastery in Ladakh.", "keywords" => "Hemis festival, masked dance, monastery, monks, drums, Ladakh culture, summer", "question" => "How should a traveler witness a monastery festival without it becoming just a photo opportunity?", "style" => "culture_first"),
                array("topic" => "A desert essay about the sand dunes at Hunder in Nubra Valley, where the landscape shifts from high passes to a warm, wind-shaped desert.", "keywords" => "Hunder, sand dunes, Nubra Valley, desert, landscape, surprising, terrain", "question" => "Why do the sand dunes of Nubra surprise travelers who thought Ladakh was only cold and rocky?", "style" => "general"),
                array("topic" => "A gentle essay about the Bactrian camels of Nubra, rare animals in a rare valley, carrying something of the old Silk Road still.", "keywords" => "Bactrian camel, Nubra Valley, Silk Road, Hunder, animals, desert, history", "question" => "What do the two-humped camels of Nubra Valley say about the older routes that once crossed this land?", "style" => "general"),
                array("topic" => "A walking essay about the Sham Valley, a gentle route through villages, fields, and small monasteries suited to travelers still finding their altitude legs.", "keywords" => "Sham Valley, walking, villages, Likir, Alchi, gentle trek, altitude safe", "question" => "Why is the Sham Valley walk the right introduction to Ladakh for a traveler who wants to go slowly?", "style" => "altitude_safe"),
                array("topic" => "An essay about the Markha Valley route, where days follow river crossings, campsites, high passes, and the simple rhythm of a long walk.", "keywords" => "Markha Valley, trek, river crossing, camping, passes, remote, walking", "question" => "What does the Markha Valley teach a traveler about the difference between travel and movement?", "style" => "general"),
                array("topic" => "An art essay about Alchi monastery, where ancient murals in faded colours have survived centuries in a quiet village beside the Indus.", "keywords" => "Alchi, murals, ancient art, monastery, Indus, village, heritage, colours", "question" => "Why do the paintings inside Alchi stop travelers who expected only mountains?", "style" => "culture_first"),
                array("topic" => "A winter essay about the Chadar trek on the frozen Zanskar river, where the route exists only for a few weeks each year and the cold is total.", "keywords" => "Chadar, frozen river, Zanskar, winter trek, ice, cold, extreme, January, February", "question" => "What draws travelers to walk on a frozen river in deepest winter when almost no one else is there?", "style" => "comfort_first"),
                array("topic" => "An essay about the women of Ladakh, their presence in fields, kitchens, festivals, and the way their daily work holds the region together.", "keywords" => "Ladakhi women, local life, culture, fields, kitchen, festival dress, family", "question" => "What does watching the women of a Ladakhi village teach a traveler about how the region endures?", "style" => "culture_first"),
                array("topic" => "An essay about prayer flags in Ladakh, their colours, their placement on passes and rooftops, and what they mean beyond decoration.", "keywords" => "prayer flags, passes, rooftops, Buddhism, colour, wind, meaning, Ladakh", "question" => "What should a traveler understand about prayer flags before they pass under them for the first time?", "style" => "culture_first"),
                array("topic" => "A high pass essay about crossing Khardung La or Chang La, where altitude, wind, and a view of folded mountains reframe the idea of a road.", "keywords" => "Khardung La, Chang La, high pass, altitude, mountain road, wind, crossing", "question" => "What does it mean to cross a high pass in Ladakh when the air is thin and the view is total?", "style" => "general"),
                array("topic" => "An essay about the sound of Ladakh, wind through prayer flags, distant bells, the crunch of gravel underfoot, and silence between villages.", "keywords" => "sound, silence, prayer flags, wind, walking, Ladakh sensory, bells", "question" => "What does Ladakh sound like to a traveler who stops to listen?", "style" => "photography"),
                array("topic" => "A food essay about what travelers eat in Ladakh, from thukpa to skyu to fresh apricots, and what each dish says about the land.", "keywords" => "Ladakhi food, thukpa, skyu, apricot, local cuisine, village meal, tsampa", "question" => "What does eating in Ladakh teach a traveler about surviving and thriving at altitude?", "style" => "culture_first"),
                array("topic" => "An essay about the Pangong Tso shoreline at dawn, when the lake changes colour by the minute and the mountains behind it are still in shadow.", "keywords" => "Pangong Tso, dawn, shoreline, colour, lake, morning, altitude, eastern Ladakh", "question" => "Why do travelers say Pangong is different at dawn from every other time of day?", "style" => "photography"),
                array("topic" => "A medical essay about altitude sickness in Ladakh, what it feels like, how to avoid it, and why the first two days decide everything.", "keywords" => "altitude sickness, AMS, acclimatization, Leh, health, first days, symptoms", "question" => "What does a traveler need to understand about altitude before landing in Leh?", "style" => "altitude_safe"),
                array("topic" => "An essay about the drive from Leh to Nubra via Khardung La, told as a series of small observations from the window of a slow vehicle.", "keywords" => "Leh to Nubra, drive, Khardung La, window, road, observations, poplars, villages", "question" => "What does the road from Leh to Nubra reveal that no map can show?", "style" => "general"),
                array("topic" => "A quiet essay about Tso Moriri, the high-altitude lake that fewer travelers reach, where birds, stillness, and distance make arrival feel earned.", "keywords" => "Tso Moriri, remote lake, Changthang, birds, stillness, altitude, Korzok", "question" => "What does Tso Moriri offer a traveler that Pangong, with its crowds, no longer can?", "style" => "short_trip"),
                array("topic" => "An essay about the light in Leh market in the late afternoon, when shadows lengthen across apricots, walnuts, and the faces of people winding down.", "keywords" => "Leh market, afternoon, light, apricots, walnuts, bazaar, local life, shadows", "question" => "Why does the Leh market at four in the afternoon feel more real than any tourist sight?", "style" => "altitude_safe"),
                array("topic" => "A heritage essay about the Leh Palace, its empty rooms, its view over the old city, and what remains when a dynasty quietly ends.", "keywords" => "Leh Palace, heritage, history, Namgyal dynasty, old town, ruins, view", "question" => "What does the Leh Palace say about the kind of power that once governed this high valley?", "style" => "culture_first"),
                array("topic" => "An essay about rivers and bridges in Ladakh, where a single suspension bridge connects two halves of a village and crossing it takes courage.", "keywords" => "suspension bridge, river crossing, Ladakh, Zanskar, Indus, village connection, bridge", "question" => "Why do the bridges of Ladakh carry more weight than just foot traffic?", "style" => "general"),
                array("topic" => "A slow travel essay about staying five days in one Ladakhi village, going nowhere, and finding that the less you move the more you see.", "keywords" => "slow travel, one village, Ladakh, stay, observe, fields, rhythm, unhurried", "question" => "What does a traveler discover in Ladakh when they stop moving and simply stay somewhere?", "style" => "culture_first"),
                array("topic" => "An essay about yaks in Ladakh, their bulk and patience, their importance to families at high altitude, and what it means to rely on an animal.", "keywords" => "yak, high altitude, nomads, Changthang, animal, Ladakhi life, pack animal", "question" => "What does the yak say about survival and dependence in Ladakh?", "style" => "culture_first"),
                array("topic" => "An essay about the Diskit monastery in Nubra Valley, its giant Buddha, its steep climb, and its view of the valley where poplars meet sand.", "keywords" => "Diskit monastery, Nubra Valley, giant Buddha, climb, view, poplars, Ladakh", "question" => "Why does the Diskit monastery above Nubra feel like a place that earns its view?", "style" => "general"),
                array("topic" => "An essay about the approach to Ladakh by road from Manali, when two days of mountain driving deposit a traveler into a landscape unlike anything before.", "keywords" => "Manali to Leh highway, road trip, mountain drive, passes, arrival, landscape change", "question" => "Why do travelers who arrive by road say Ladakh feels different from those who fly in?", "style" => "general"),
                array("topic" => "An essay about the Buddhist calendar in Ladakh, the festivals that structure the year, and how a traveler can arrive at the right moment without planning too hard.", "keywords" => "Buddhist calendar, Ladakh festivals, Losar, Hemis, Dosmoche, timing, annual", "question" => "How does the Buddhist calendar shape the best times to be in Ladakh?", "style" => "culture_first"),
                array("topic" => "A medical and personal essay about flying into Leh, what the body feels in the first hours, and why doing nothing is the right first day plan.", "keywords" => "flying to Leh, altitude arrival, first day, rest, acclimatization, body, oxygen", "question" => "What should a traveler do on the first afternoon in Leh when everything looks worth seeing but the body says stop?", "style" => "altitude_safe"),
                array("topic" => "An essay about the Ladakhi sky, its blue that exists at no other altitude, the clouds that form and dissolve in an afternoon, and what the sky does to the light below.", "keywords" => "Ladakh sky, blue, altitude, clouds, light, photography, colour, open", "question" => "Why do travelers keep photographing the sky in Ladakh even when the mountains are right there?", "style" => "photography"),
                array("topic" => "An essay about the poplar trees of Ladakh, their straight lines along irrigation channels, their yellow in autumn, and the shade they offer in summer lanes.", "keywords" => "poplar trees, Ladakh, autumn, irrigation, village lanes, shade, landscape, yellow", "question" => "Why do the poplar trees of Ladakh appear in almost every memory a traveler carries home?", "style" => "general"),
                array("topic" => "An essay about a solo traveler in Ladakh finding unexpected company in guesthouses, shared jeeps, and monastery courtyards.", "keywords" => "solo travel, Ladakh, guesthouse, shared jeep, chance encounter, traveler, monastery", "question" => "Why does Ladakh have a way of turning solo travelers into people with stories about strangers?", "style" => "short_trip"),
                array("topic" => "An essay about the dogs of Ladakh, the strays that sleep in monastery courtyards, follow trekkers, and seem to belong to the landscape itself.", "keywords" => "dogs, strays, monastery, Ladakh, trekking companion, animal, local life", "question" => "What do the dogs of Ladakh say about the relationship between humans and animals at altitude?", "style" => "general"),
                array("topic" => "An essay about the last light on Ladakhi mountains, when the peaks hold colour long after the valleys go dark and a traveler stops whatever they are doing.", "keywords" => "alpenglow, last light, mountains, evening, Leh, photography, colour, peaks", "question" => "Why does the last light on Ladakhi mountains make travelers stop mid-sentence?", "style" => "photography"),
                array("topic" => "An essay about returning to Ladakh a second time, when the familiar and the changed sit side by side and a traveler measures themselves against a place.", "keywords" => "returning, Ladakh, second visit, change, memory, familiar, grown, revisit", "question" => "What does Ladakh show a traveler who comes back after years away?", "style" => "general"),
                array('topic' => 'A comparison essay for travelers choosing between Pangong Tso and Tso Moriri, examining what each lake offers and what kind of traveler belongs at each one.', 'keywords' => 'Pangong vs Tso Moriri, which lake, Ladakh lakes, comparison, Changthang, altitude', 'question' => 'Should a traveler choose Pangong or Tso Moriri, and what does the answer say about what they are really looking for?', 'style' => 'short_trip'),
                array('topic' => 'A reflective essay about whether to travel Ladakh independently or with a tour, looking at what each approach gives and what it costs.', 'keywords' => 'Ladakh independent travel, Ladakh tour, self drive, guided tour, which is better', 'question' => 'Is it better to travel Ladakh independently or with a guide, and what does each choice actually mean for the experience?', 'style' => 'general'),
                array('topic' => 'An essay about returning to Ladakh as a repeat visitor, exploring what changes the second time and why some travelers find Ladakh more meaningful on a return.', 'keywords' => 'Ladakh second visit, repeat visitor, return trip, what changes, first time vs second time', 'question' => 'What does Ladakh reveal to a traveler who comes back, that it could not show the first time?', 'style' => 'general'),
                array('topic' => 'A comparison essay for travelers choosing between Nubra Valley and Zanskar, two very different extensions of a Ladakh trip.', 'keywords' => 'Nubra vs Zanskar, Ladakh extensions, which to choose, remote, road, valley', 'question' => 'For a traveler with time for only one extension beyond Leh, should it be Nubra or Zanskar?', 'style' => 'general'),
                array('topic' => 'An honest essay about visiting Ladakh during monsoon season in July and August, what actually happens with roads, weather and crowds.', 'keywords' => 'Ladakh monsoon, July August, rainy season, road closures, weather, is it safe', 'question' => 'What is Ladakh actually like in July and August, and who should still go despite the monsoon?', 'style' => 'altitude_safe'),
                array('topic' => 'An essay about visiting Ladakh in April and May, when the land is still waking up and the roads are just opening after winter.', 'keywords' => 'Ladakh April May, spring, early season, before summer, roads opening, quiet', 'question' => 'What does Ladakh look like in April and May, and why do some travelers prefer the quietness of the early season?', 'style' => 'altitude_safe'),
                array('topic' => 'An essay about winter Ladakh between December and February, a season most travelers avoid but some find more honest than any other.', 'keywords' => 'Ladakh winter, December January February, cold, snow, off season, who should go', 'question' => 'What kind of traveler finds Ladakh in winter more rewarding than Ladakh in summer?', 'style' => 'comfort_first'),
                array('topic' => 'An essay for photographers about the specific places and hours in Ladakh where light, landscape and patience come together.', 'keywords' => 'Ladakh photography, best spots, golden hour, light, landscape, photographer guide', 'question' => 'Where and when should a photographer in Ladakh wait, and what does patience look like at high altitude?', 'style' => 'photography'),
                array('topic' => 'A practical and personal essay about acclimatizing properly in Leh, what to do, what not to do, and why the first two days determine everything.', 'keywords' => 'altitude acclimatization Ladakh, Leh first days, AMS, high altitude, how to acclimatize', 'question' => 'What should a traveler actually do on the first two days in Leh to acclimatize well?', 'style' => 'altitude_safe'),
                array('topic' => 'An essay about designing a slow, unhurried Ladakh trip with fewer places, longer stays and room for nothing in particular.', 'keywords' => 'slow travel Ladakh, unhurried, fewer places, mindful, meaningful trip, quiet Ladakh', 'question' => 'What does a Ladakh trip look like when a traveler deliberately leaves space for nothing?', 'style' => 'culture_first'),
                array('topic' => 'An honest essay about traveling Ladakh with children, what is realistic, what is wonderful, and what parents need to know about altitude and pace.', 'keywords' => 'Ladakh with kids, family travel, children, altitude children, family itinerary, is it safe', 'question' => 'What is it actually like to bring children to Ladakh, and how should a family plan differently?', 'style' => 'altitude_safe'),
                array('topic' => 'An essay about Hanle, the remote dark sky village in Changthang that most Ladakh visitors never reach, and why the distance is worth it.', 'keywords' => 'Hanle Ladakh, dark sky, remote, stargazing, Changthang, off the beaten path', 'question' => 'What is waiting for a traveler who makes the long drive to Hanle, and why does it feel unlike anywhere else in Ladakh?', 'style' => 'photography'),
                array('topic' => 'An essay about the road to Zanskar and whether it still deserves its reputation as one of the most dramatic journeys in the Himalayas.', 'keywords' => 'Zanskar road, Zanskar valley, remote Ladakh, is it worth it, highway, gorge', 'question' => 'Is the road to Zanskar still worth the effort, and what does a traveler find when they finally arrive?', 'style' => 'general'),
                array('topic' => 'An essay about food in Ladakh, from thukpa to skyu to apricot jam, exploring what eating here says about altitude, seasons and the people who cook.', 'keywords' => 'Ladakh food, thukpa, skyu, local cuisine, what to eat, Ladakhi cooking, apricot', 'question' => 'What should a traveler in Ladakh eat, and what does the food say about how people have survived here for centuries?', 'style' => 'culture_first'),
                array('topic' => 'An essay about what it means to stay in a Ladakhi village, not just pass through, and what a traveler learns when they slow down enough to notice.', 'keywords' => 'Ladakh village stay, slow down, local life, village tourism, meaningful travel, homestay', 'question' => 'What does a Ladakhi village offer a traveler who stays long enough to stop being a visitor?', 'style' => 'culture_first'),
                array('topic' => 'A practical essay about the best season to visit Ladakh, comparing June, July, and September for weather, crowds, road access, and what each month actually feels like on the ground.', 'keywords' => 'best time to visit Ladakh, June July September, Ladakh season, when to go, road conditions, weather, crowds', 'question' => 'Is June, July, or September the better month to visit Ladakh, and how do the differences actually affect a trip?', 'style' => 'altitude_safe'),
                array('topic' => 'A comparison essay for travelers deciding between a 7-day and 10-day Ladakh itinerary, examining what each duration allows and what it forces you to skip.', 'keywords' => 'Ladakh 7 days, Ladakh 10 days, itinerary length, how many days, Ladakh trip duration, first visit', 'question' => 'What is the real difference between a 7-day and 10-day Ladakh trip, and which one suits a first-time visitor?', 'style' => 'short_trip'),
                array('topic' => 'A reflective essay comparing homestays and hotels in Ladakh, exploring what each type of accommodation gives a traveler and what it takes away.', 'keywords' => 'Ladakh homestay, Ladakh hotel, accommodation, where to stay, village stay, guesthouse, local family', 'question' => 'Should a traveler in Ladakh stay in a homestay or a hotel, and what does each choice say about the kind of trip they want?', 'style' => 'culture_first'),
                array('topic' => 'An honest essay about road closures in Ladakh, what actually happens when a pass shuts down, and how experienced travelers adapt without losing the journey.', 'keywords' => 'Ladakh road closure, Khardung La closed, weather, contingency, flexible travel, what to do', 'question' => 'What happens when the road closes in Ladakh, and how should a traveler plan for the unexpected?', 'style' => 'altitude_safe'),
                array('topic' => 'A personal essay about solo travel in Ladakh, what it actually feels like to be alone in a high-altitude landscape, and why the region is more welcoming than it looks.', 'keywords' => 'solo travel Ladakh, is Ladakh safe alone, solo female travel, single traveler, safety', 'question' => 'Is Ladakh a good destination for a solo traveler, and what should someone expect when they arrive alone?', 'style' => 'short_trip'),
                array('topic' => 'An honest essay for first-time high-altitude trekkers considering Ladakh, what to expect from the body, the terrain, and the pace required to do it well.', 'keywords' => 'first time trekking Ladakh, beginner trek, high altitude first time, is it safe, preparation', 'question' => 'Is Ladakh a good place for a first high-altitude trek, and what does a first-time trekker need to know before going?', 'style' => 'altitude_safe'),
                array('topic' => 'A practical essay about what to pack for Ladakh, what most travelers bring that they do not need, and what they almost always wish they had.', 'keywords' => 'what to pack Ladakh, packing list, what to bring, luggage, essential items, Ladakh preparation', 'question' => 'What should a traveler actually pack for Ladakh, and what is the one thing most people forget?', 'style' => 'altitude_safe'),
                array('topic' => 'An honest essay about connectivity in Ladakh, what Wi-Fi and phone signal are actually like, and why some travelers find the disconnection to be the best part.', 'keywords' => 'Ladakh wifi, internet Ladakh, phone signal, connectivity, offline travel, digital detox', 'question' => 'What is the internet and phone situation in Ladakh, and how should a traveler prepare to be offline?', 'style' => 'general'),
                array('topic' => 'A practical essay about the real cost of a Ladakh trip, what most budget estimates miss, and how travelers can plan honestly without undershooting or overshooting.', 'keywords' => 'Ladakh budget, how much does Ladakh cost, travel costs, expenses, budget planning, price', 'question' => 'How much does a Ladakh trip actually cost, and what do most budget guides leave out?', 'style' => 'general'),
                array('topic' => 'A practical essay comparing flying into Leh versus arriving by road from Manali or Srinagar, what each approach costs in time and what it gives back.', 'keywords' => 'fly to Leh vs road, Manali Leh highway, Srinagar Leh, how to get to Ladakh, flight vs drive', 'question' => 'Should a traveler fly into Leh or arrive by road, and what does each choice mean for acclimatization and experience?', 'style' => 'altitude_safe'),
                array('topic' => 'A quiet essay about what sustainable travel means in Ladakh specifically, where the land is fragile, the communities are small, and the choices a visitor makes leave traces.', 'keywords' => 'sustainable travel Ladakh, responsible tourism, eco travel, community impact, environment, how to travel well', 'question' => 'What does it mean to travel sustainably in Ladakh, and what small choices make the biggest difference?', 'style' => 'culture_first'),
                array('topic' => 'An essay about what a custom itinerary in Ladakh actually means compared to a fixed package tour, and why the difference matters more in Ladakh than almost anywhere else.', 'keywords' => 'custom itinerary Ladakh, package tour vs custom, bespoke travel, tailored trip, LOTPL', 'question' => 'What is the real difference between a custom Ladakh itinerary and a package tour, and why does it matter?', 'style' => 'general'),
                array('topic' => 'An essay about what it means to travel with a local guide in Ladakh, what opens up, what becomes visible, and what a traveler misses when they go without one.', 'keywords' => 'local guide Ladakh, guided travel, why use a guide, what a guide gives, local knowledge', 'question' => 'What does a local guide actually change about a Ladakh journey, and when is going without one a mistake?', 'style' => 'culture_first'),
                array('topic' => 'A patient essay about the reality of searching for snow leopards in Ladakh, the cold, the waiting, the disappointment, and why travelers who find meaning in the search come back.', 'keywords' => 'snow leopard Ladakh, wildlife watching, Hemis, winter wildlife, how to see snow leopard', 'question' => 'What is a snow leopard search in Ladakh really like, and what kind of traveler finds it worthwhile even without a sighting?', 'style' => 'comfort_first'),
                array('topic' => 'A springtime essay about the apricot blossom season in Ladakh, when the valley briefly turns white and pink and the roads are just opening after winter.', 'keywords' => 'apricot blossom Ladakh, spring, April, when do blossoms happen, early season, Sham Valley', 'question' => 'When do the apricot trees bloom in Ladakh, and why is this brief window one of the quietest and most beautiful times to visit?', 'style' => 'general'),
                array('topic' => 'An essay about what makes LIFE on the PLANET LADAKH different from other Ladakh travel companies, rooted in fifteen years of sustainable community-based travel since 2010.', 'keywords' => 'LOTPL, LIFE on the PLANET LADAKH, about us, sustainable tourism, why choose us, community travel, est 2010', 'question' => 'What makes LIFE on the PLANET LADAKH the right choice for a traveler who wants to go slowly and responsibly?', 'style' => 'culture_first'),
                array('topic' => 'An essay about river rafting on the Indus and Zanskar rivers in Ladakh, what the experience offers beyond the rush, and who finds it most meaningful.', 'keywords' => 'river rafting Ladakh, Indus rafting, Zanskar river, white water, adventure, water activity', 'question' => 'What is river rafting in Ladakh actually like, and what kind of traveler finds it worth building a day around?', 'style' => 'general'),
                array('topic' => 'An essay about wellness and stillness in Ladakh, where altitude, silence, and a slower pace create conditions that a planned retreat rarely manufactures.', 'keywords' => 'wellness Ladakh, retreat, yoga, stillness, slow travel, mindfulness, healing', 'question' => 'What does Ladakh offer a traveler who is looking for stillness, and why does the region work as a natural retreat?', 'style' => 'comfort_first'),
                array('topic' => 'A practical essay about birdwatching in Ladakh, where to find black-necked cranes, bar-headed geese, and the surprising density of life in a high-altitude desert.', 'keywords' => 'birdwatching Ladakh, black-necked crane, bar-headed goose, Tso Moriri birds, wildlife, birding', 'question' => 'What birds can a traveler find in Ladakh, and which season and location give the best chance of seeing the rarest species?', 'style' => 'photography'),
                array('topic' => 'An essay about the dramatic landscape of Lamayuru in Ladakh, its moon valley terrain, its ancient monastery, and why it rewards a traveler who stays rather than passes through.', 'keywords' => 'Lamayuru, moon landscape, monastery, Ladakh, lunar terrain, Moonland, western Ladakh', 'question' => 'What makes Lamayuru worth stopping at, and what does a traveler find there that photographs cannot capture?', 'style' => 'general'),
                array('topic' => 'A gentle essay about Ladakh as a honeymoon destination, what makes it unusual, what makes it right for certain couples, and what to consider before choosing altitude over beaches.', 'keywords' => 'honeymoon Ladakh, romantic travel, couple trip, Ladakh for couples, unusual honeymoon', 'question' => 'Is Ladakh a good honeymoon destination, and what kind of couple finds the mountains more romantic than the sea?', 'style' => 'comfort_first'),
                array('topic' => 'An honest essay about Ladakh for older travelers, what is realistic, what is wonderful, and how a slower pace often reveals more than a rushed younger itinerary.', 'keywords' => 'Ladakh for seniors, older travelers, senior travel, is Ladakh safe for elderly, gentle pace, comfortable', 'question' => 'What does Ladakh offer an older traveler, and how should the itinerary be shaped differently?', 'style' => 'comfort_first'),
                array('topic' => 'An essay about what climate change is visibly doing to Ladakh, the retreating glaciers, the changing seasons, the water stress, and what a traveler notices if they pay attention.', 'keywords' => 'climate change Ladakh, glaciers melting, water shortage, environment, future, impact', 'question' => 'What is climate change doing to Ladakh, and what does a traveler see today that may not be there in a generation?', 'style' => 'culture_first'),
                array('topic' => 'An essay about water in Ladakh, the glaciers that feed it, the irrigation channels that carry it, the villages that depend on it, and what a traveler learns by following its path.', 'keywords' => 'water Ladakh, glacier, irrigation, water shortage, village life, environment, ice stupa', 'question' => 'Why does water matter so much in Ladakh, and what does understanding it change about how a traveler sees the region?', 'style' => 'culture_first'),
                array('topic' => 'A practical essay about the Inner Line Permit and other permits required in Ladakh, which areas need them, how to get them, and what happens when a traveler does not plan ahead.', 'keywords' => 'Inner Line Permit Ladakh, ILP, permits, Nubra permit, Pangong permit, how to get permit', 'question' => 'What permits does a traveler need for Ladakh, and how do they affect which routes and areas are accessible?', 'style' => 'altitude_safe'),
                array('topic' => 'A practical essay about basic phrases in Ladakhi and Hindi that a traveler can use, and what happens when you try to speak even a few words in the local language.', 'keywords' => 'Ladakhi language, basic phrases, Hindi Ladakh, local words, how to greet, juley, communication', 'question' => 'What happens when a traveler in Ladakh tries to speak a few words of the local language, and which phrases open the most doors?', 'style' => 'culture_first'),
                array('topic' => 'An essay about why French travelers are drawn to Ladakh in particular, what they find that other Himalayan destinations do not offer, and what the French sensibility brings to a journey in the high desert.', 'keywords' => 'French travelers Ladakh, why French love Ladakh, France Ladakh, French tourism India, European travelers', 'question' => 'What is it about Ladakh that consistently draws French travelers, and what do they tend to find most meaningful?', 'style' => 'culture_first'),
                array('topic' => 'An essay about Italian travelers in Ladakh, what draws them to the high desert, how their eye for beauty and culture shapes what they notice, and why Ladakh surprises them.', 'keywords' => 'Italian travelers Ladakh, Italy Ladakh, Italian tourism, European travelers India, Italian sensibility', 'question' => 'What does Ladakh offer an Italian traveler, and what does someone who loves beauty, history and food find in a high-altitude desert?', 'style' => 'culture_first'),
                array('topic' => 'An essay about the long journey from Europe to Ladakh, what it means to travel so far for a landscape, and why the distance itself changes the quality of arrival.', 'keywords' => 'Europe to Ladakh, long haul travel, flying to India, journey from Europe, how to get to Ladakh from Europe', 'question' => 'What does a European traveler gain from the long journey to Ladakh that a closer destination cannot offer?', 'style' => 'general'),
                array('topic' => 'An essay about what a traveler from the Alps finds in Ladakh, where the mountains are familiar in scale but different in everything else: dryness, colour, culture, and sky.', 'keywords' => 'Alps vs Ladakh, European mountains, alpine traveler, Switzerland France Italy mountains, Himalayan comparison', 'question' => 'What does someone who grew up with Alpine mountains discover when they arrive in the Himalayas of Ladakh?', 'style' => 'general'),
                array('topic' => 'A comparison essay between Ladakh and the Scottish Highlands, two landscapes shaped by remoteness, weather, and a culture that held on through difficulty, and what each offers a traveler who loves solitude.', 'keywords' => 'Ladakh vs Scotland, Scottish Highlands, remote landscapes, solitude travel, comparison, European wilderness', 'question' => 'How does Ladakh compare to the Scottish Highlands, and what does each landscape offer a traveler who seeks quiet and space?', 'style' => 'general'),
                array('topic' => 'An essay about what Tibetan Buddhist culture left behind in Ladakh, the monasteries, the iconography, the festivals, the calendar, and why this living inheritance matters to a traveler who pays attention.', 'keywords' => 'Tibetan Buddhism Ladakh, Buddhist culture, monasteries, Tibetan influence, religion, heritage', 'question' => 'What did Tibetan Buddhist culture bring to Ladakh, and what remains alive for a traveler who visits today?', 'style' => 'culture_first'),
                array('topic' => 'An essay about the Silk Road memory in Ladakh, the old caravan routes from Central Asia, the traders, the languages, and why Leh was once one of the great crossing points of the world.', 'keywords' => 'Silk Road Ladakh, caravan route, Central Asia, trade history, Leh history, ancient route', 'question' => 'What remains of the old Silk Road in Ladakh, and what does a traveler feel when they walk through a town that was once a crossroads of empires?', 'style' => 'culture_first'),
                array('topic' => 'An essay about what a first-time visitor from Europe finds most surprising about Ladakh, what their expectations were and what the reality turned out to be.', 'keywords' => 'first time Ladakh Europe, European first visit, what to expect, surprises, expectations vs reality', 'question' => 'What surprises a European traveler most when they arrive in Ladakh for the first time?', 'style' => 'altitude_safe')
            );
            $recent_used = get_option("lotpl_lbg_recent_angles", array());
            $all_indices = range(0, count($lotpl_essay_angles) - 1);
            $available = array_values(array_diff($all_indices, $recent_used));
            if (empty($available)) { $recent_used = array(); $available = $all_indices; }
            $chosen_index = $available[wp_rand(0, count($available) - 1)];
            $recent_used[] = $chosen_index;
            if (count($recent_used) > 5) { $recent_used = array_slice($recent_used, -5); }
            update_option("lotpl_lbg_recent_angles", $recent_used);
            $lotpl_essay_angle = $lotpl_essay_angles[$chosen_index];
            update_option("lotpl_lbg_last_angle", array("index" => $chosen_index, "topic" => $lotpl_essay_angle["topic"], "question" => $lotpl_essay_angle["question"], "style" => $lotpl_essay_angle["style"]));
            $topic_category = "mixed";
            $custom_topic = $lotpl_essay_angle["topic"];
            $daily_update_path = ABSPATH . ".codex/daily_update.txt";
            $daily_update = file_exists($daily_update_path) ? trim(file_get_contents($daily_update_path)) : "";
            $daily_note = ($daily_update !== "") ? " | Editor note for today: " . $daily_update . ". Use this only as hidden context. Never mention it directly." : "";
            $keywords_hint = $lotpl_essay_angle["keywords"] . " | Use this only as hidden editorial inspiration for traveler usefulness, structure, clarity, and emotional freshness. Never mention AI, SEO, GEO, or internal workflow in the public article." . $daily_note;
            $article_mode = "essay";
            $travel_style = $lotpl_essay_angle["style"];
            $source_question = $lotpl_essay_angle["question"];
            $tone = "essay";
            $word_count = intval($word_count);
            $future_dates_only = true;
        }

        $result = $this->create_post_via_openai(array(
            'topic_category' => $topic_category,
            'custom_topic' => $custom_topic,
            'keywords_hint' => $keywords_hint,
            'article_mode' => $article_mode,
            'travel_style' => $travel_style,
            'source_question' => $source_question,
            'body_image_count' => $body_image_count,
            'word_count' => $word_count,
            'tone' => $tone,
            'future_dates_only' => $future_dates_only,
        ));

        delete_transient(self::LOCK_TRANSIENT);

        if (is_wp_error($result)) {
            $this->redirect_with_notice('error', $result->get_error_message());
        }

        $edit_link = get_edit_post_link($result, '');
        $message = 'Draft created successfully. Post ID: ' . intval($result) . ($edit_link ? ' — You can now edit it.' : '');
        $this->redirect_with_notice('success', $message);
    }

    private function redirect_with_notice($type, $message) {
        $url = add_query_arg(array(
            'page' => 'lotpl-ladakh-blog-generator',
            'lotpl_lbg_notice' => $type,
            'lotpl_lbg_message' => rawurlencode($message),
        ), admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    private function generated_article_has_public_forbidden_terms($data) {
        $text = "";
        if (isset($data["title"])) { $text .= " " . $data["title"]; }
        if (isset($data["content"])) { $text .= " " . wp_strip_all_tags($data["content"]); }
        $text = strtolower($text);
        if (preg_match("/\\bai\\b/i", $text)) { update_option("lotpl_lbg_last_forbidden_term", "ai"); return true; }
        foreach (array("artificial intelligence", "automation", "automated", "prompt", "plugin", "rank math", "seo", "content generation", "blog generator", "technology update", "research ai") as $term) {
            if (strpos(" " . $text . " ", $term) !== false) { update_option("lotpl_lbg_last_forbidden_term", $term); return true; }
        }
        return false;
    }

    private function create_post_via_openai($args) {
        $settings = $this->get_settings();
        $topics = $this->get_topic_map();
        $article_modes = $this->get_article_modes();
        $travel_styles = $this->get_travel_styles();
        $topic_text = isset($topics[$args['topic_category']]) ? $topics[$args['topic_category']] : $topics['mixed'];
        $schema = $this->get_blog_schema(isset($args['body_image_count']) ? intval($args['body_image_count']) : intval($settings['default_body_image_count']));

        $instructions = $this->build_blog_instructions($topic_text, $args, $settings);

        $payload = array(
            'model' => $settings['model'],
            'input' => $instructions,
            'reasoning' => array('effort' => 'none'),
            'text' => array(
                'verbosity' => 'medium',
                'format' => array(
                    'type' => 'json_schema',
                    'name' => 'ladakh_blog_post',
                    'schema' => $schema,
                    'strict' => true,
                ),
            ),
        );

        if (!empty($settings['enable_web_search'])) {
            $payload['tools'] = array(
                array('type' => 'web_search'),
            );
        }

        $response = $this->openai_request($payload, $settings['api_key'], intval($settings['request_timeout']), 2);
        if (is_wp_error($response)) {
            return $response;
        }

        $data = $this->extract_structured_json($response);
        if (is_wp_error($data)) {
            return $data;
        }

        if ($this->generated_article_has_public_forbidden_terms($data)) {
            $payload["input"] .= "\nRewrite completely for travelers only. Remove all references to AI, technology, automation, prompts, plugins, SEO, GEO, Rank Math, content generation, and internal systems. The public article must read like a polished Ladakh travel guide.";
            $response = $this->openai_request($payload, $settings["api_key"], intval($settings["request_timeout"]), 1);
            if (is_wp_error($response)) { return $response; }
            $data = $this->extract_structured_json($response);
            if (is_wp_error($data)) { return $data; }
            if ($this->generated_article_has_public_forbidden_terms($data)) { return new WP_Error("forbidden_public_terms", "Generated article still contained internal AI or system wording after retry. Please generate again."); }
        }

        $post_id = $this->insert_generated_post($data, $settings);
        if (is_wp_error($post_id)) {
            return $post_id;
        }

        if (!empty($data['image_suggestions'])) {
            update_post_meta($post_id, '_ladakh_image_suggestions', $data['image_suggestions']);
            $this->apply_generated_images_to_post($post_id, $data['image_suggestions']);
        }
        if (!empty($data['short_answer_block'])) {
            update_post_meta($post_id, '_lotpl_lbg_short_answer_block', wp_kses_post($data['short_answer_block']));
        }
        if (!empty($data['inquiry_reply_draft'])) {
            update_post_meta($post_id, '_lotpl_lbg_inquiry_reply_draft', sanitize_textarea_field($data['inquiry_reply_draft']));
        }
        if (!empty($data['suggested_offsite_angle'])) {
            update_post_meta($post_id, '_lotpl_lbg_suggested_offsite_angle', sanitize_text_field($data['suggested_offsite_angle']));
        }

        update_post_meta($post_id, '_lotpl_lbg_article_mode', isset($args['article_mode']) ? sanitize_text_field($args['article_mode']) : 'standard');
        update_post_meta($post_id, '_lotpl_lbg_travel_style', isset($args['travel_style']) ? sanitize_text_field($args['travel_style']) : 'general');
        if (!empty($args['source_question'])) {
            update_post_meta($post_id, '_lotpl_lbg_source_question', sanitize_text_field($args['source_question']));
        }
        update_post_meta($post_id, '_lotpl_lbg_body_image_count', isset($args['body_image_count']) && intval($args['body_image_count']) === 5 ? 5 : 4);

        return $post_id;
    }

    private function build_blog_instructions($topic_text, $args, $settings) {
        $tone_map = array(
            'editorial' => 'editorial and informative',
            'essay' => 'essay-like, atmospheric, and vivid but still grounded',
            'practical' => 'practical, traveler-friendly, and concrete',
            'seo' => 'SEO-aware but still natural and human',
        );

        $tone_text = isset($tone_map[$args['tone']]) ? $tone_map[$args['tone']] : $tone_map['editorial'];
        $mode = !empty($args['article_mode']) ? $args['article_mode'] : 'standard';
        $travel_style = !empty($args['travel_style']) ? $args['travel_style'] : 'general';
        $source_question = !empty($args['source_question']) ? trim($args['source_question']) : '';
        $body_image_count = isset($args['body_image_count']) && intval($args['body_image_count']) === 5 ? 5 : 4;

        $instructions = array();
        $instructions[] = 'You are writing for LIFE on the PLANET LADAKH, a high-quality Ladakh travel company focused on meaningful, experience-rich travel.';
        $instructions[] = 'Your job is to write a quiet, deeply felt Ladakh travel essay that feels personal, reflective, and grounded in real experience. Avoid any business, SEO, or marketing tone. Write as if you are gently guiding a traveler who is trying to understand Ladakh more deeply.';
        $instructions[] = 'Write one original English blog post focused on Ladakh.';
        $instructions[] = 'Primary topic: ' . $topic_text . '.';
        $instructions[] = 'Article mode: ' . $mode . '.';
        $instructions[] = 'Audience emphasis: ' . $travel_style . '.';
        $instructions[] = 'Tone: ' . $tone_text . '.';
        $instructions[] = "Write a naturally complete article. Do not force length. End when the travel story feels complete, useful, and meaningful for the reader.";

        if (!empty($args['custom_topic'])) {
            $instructions[] = 'Custom user topic to prioritize: ' . $args['custom_topic'] . '.';
        }

        if (!empty($args['keywords_hint'])) {
            $instructions[] = 'Related items and hints from the user: ' . $args['keywords_hint'] . '.';
        }

        if ($source_question !== '') {
            $instructions[] = 'Real traveler inquiry or question to anchor the article: ' . $source_question . '.';
            $instructions[] = 'Use the real question only as quiet background for the title, opening scene, and traveler concern. Do not create an answer block.';
        }

        $instructions[] = $this->get_mode_instructions($mode);
        $instructions[] = $this->get_travel_style_instructions($travel_style);
        $instructions[] = 'This is not a generic travel article. It should feel specific to Ladakh, intimate, observant, and useful without becoming business-like.';
        $instructions[] = 'Favor a personal essay shape over comparison pages, question pages, decision pages, checklists, or broad inspirational introductions.';
        $instructions[] = "Structure the article like a natural travel guide or essay. Use headings only where they genuinely help the reader. Do not force business-like sections such as Conclusion, Detailed explanation, or Who this suits.";
        $instructions[] = 'Let the article begin naturally, with a scene, a small observation, a traveler concern, or a quiet question. Do not force an answer-first block.';
        $instructions[] = 'Do not include an H1 in the article body. The plugin will automatically insert an internal H2 title heading at the very top of the content.';
        $instructions[] = 'At the very start, write a natural essay opening. Let practical meaning emerge through the story rather than forcing a direct answer.';
        $instructions[] = 'When giving advice, keep the language gentle and human rather than categorical or sales-like.';
        $instructions[] = 'Do not force comparison blocks or tables. Use them only if they genuinely make the essay clearer for a traveler.';
        $instructions[] = 'Do not place mechanical tables near the top. Let the article flow like an essay first.';
        $instructions[] = 'Do not force a who-this-suits section. If suitability matters, mention it gently inside the essay.';
        $instructions[] = 'When practical advice is useful, express it gently through the essay rather than as a hard checklist.';
        $instructions[] = 'Do not force FAQ sections. If common questions matter, answer them naturally inside the essay.';
        $instructions[] = 'Use clear HTML with <p>, <h2>, <h3>, <ul>, <ol>, and <table> where appropriate.';
        $instructions[] = 'Do not rush toward an answer. Let the essay breathe, while keeping every paragraph meaningful for a traveler.';
        $instructions[] = 'Avoid meta commentary, process notes, or mentioning SEO, GEO, prompts, or AI in the article body.';
        $instructions[] = 'Do not mention being an AI.';
        $instructions[] = 'Never write about AI, technology, automation, prompts, content generation, blog creation, SEO, GEO, Rank Math, or internal marketing systems in the public article body.';
        $instructions[] = 'The public article must be written only for travelers considering a real Ladakh journey. Focus on places, seasons, altitude, landscapes, local life, culture, pace, comfort, and practical travel decisions.';
        $instructions[] = 'Do not explain how the article was made. Do not mention tools, systems, updates, plugins, or workflows.';
        $instructions[] = 'All visible captions, headings, labels, and body text must be in natural English only.';
        $instructions[] = 'Avoid generic business-growth language. Write as a refined Ladakh travel guide, not as a technology article.';
        $instructions[] = 'Do not write a pushy sales ending. End quietly, with a human sense that a Ladakh journey can be shaped around each traveler and their real time, pace, and concerns.';
        $instructions[] = 'If a closing invitation appears, make it brief, quiet, and human, without promotional language.';
        $instructions[] = 'If differences between routes, seasons, or travel styles matter, weave them naturally into the essay.';
        $instructions[] = 'If traveler questions appear, answer them naturally within the essay rather than creating a rigid FAQ format.';
        $instructions[] = 'If a real traveler concern is present, let it shape the emotional center of the essay without turning the article into a direct reply.';
        $instructions[] = 'If the audience emphasis is family, prioritize family-friendliness, realistic pace, and comfort.';
        $instructions[] = 'If the audience emphasis is photography, prioritize light, timing, and scenic trade-offs.';
        $instructions[] = 'If the audience emphasis is first_time or altitude_safe, prioritize acclimatization and what to skip rather than overpacking the itinerary.';
        $instructions[] = 'If the audience emphasis is short_trip, be strict about what does not fit.';
        $instructions[] = 'If the audience emphasis is comfort_first, emphasize comfort, gentler logistics, and fewer tiring transitions.';
        $instructions[] = 'If the audience emphasis is culture_first, emphasize monasteries, villages, local life, and slower understanding.';
        $instructions[] = 'If the audience emphasis is scenic_first, emphasize visual drama, viewpoint logic, and landscape trade-offs.';
        $instructions[] = 'Use only facts you are confident about. If web search is available, use it when helpful for current grounding.';

        if (!empty($args['future_dates_only'])) {
            $instructions[] = 'If you mention event dates, schedules, or plans, avoid past-dated references unless the sentence is rewritten naturally without anchoring it to a past schedule.';
        }

        $instructions[] = 'Return exactly five focus keywords.';
        $instructions[] = 'Also return a short answer block, a suggested off-site angle for third-party publishing, and a short inquiry reply draft that LOTPL could adapt when someone asks a similar question.';
        $instructions[] = 'Also return an image placement plan with exactly 1 featured image and exactly ' . $body_image_count . ' body images.';
        $instructions[] = 'Spread the ' . $body_image_count . ' body images across the full article, not only near the beginning.';
        $instructions[] = 'For each image, provide a concrete English placement label, a specific English search keyword phrase, and a short reason. All image captions and labels must be in natural English.';
        $instructions[] = 'End the article with a short author bio for ' . $settings['author_bio_name'] . ', founder of LIFE on the PLANET LADAKH and advocate of sustainable tourism.';
        $instructions[] = 'Return data matching the provided JSON schema exactly.';

        // 
        $is_comparison = preg_match('/\bvs\b|comparison|choosing between|deciding between|best season|homestay.*hotel|independent.*tour/i', $topic_text);
        if ($is_comparison) {
            $instructions[] = 'This is a comparison article. Structure it clearly for a traveler making a real decision.';
            $instructions[] = 'Start with a direct conclusion: who should choose each option. Put this in the first paragraph.';
            $instructions[] = 'Include a section called Best for with 2-3 specific traveler types for each option.';
            $instructions[] = 'Include a section called Not ideal for with honest reasons each option does not suit certain travelers.';
            $instructions[] = 'Include a short Risk or What to know section with the most important practical consideration.';
            $instructions[] = 'End with a quiet one-line invitation such as: Not sure which suits your situation? Share your dates and pace with us.';
            $instructions[] = 'A comparison table is welcome here if it makes the decision clearer.';
        $instructions[] = 'Never append a list of keywords, tags, or SEO terms at the end of the article. Do not output any keyword list, tag list, or metadata in the content field. The content field must contain only the article body HTML.';
        }
        return implode("
", $instructions);
    }

    private function get_blog_schema($body_image_count = 4) {
        $body_image_count = intval($body_image_count) === 5 ? 5 : 4;
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'title' => array('type' => 'string'),
                'content' => array('type' => 'string'),
                'meta_description' => array('type' => 'string'),
                'short_answer_block' => array('type' => 'string'),
                'suggested_offsite_angle' => array('type' => 'string'),
                'inquiry_reply_draft' => array('type' => 'string'),
                'keywords' => array(
                    'type' => 'array',
                    'minItems' => 5,
                    'maxItems' => 5,
                    'items' => array('type' => 'string'),
                ),
                'image_suggestions' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'featured_image' => array(
                            'type' => 'object',
                            'additionalProperties' => false,
                            'properties' => array(
                                'placement' => array('type' => 'string'),
                                'keyword' => array('type' => 'string'),
                                'reason' => array('type' => 'string'),
                            ),
                            'required' => array('placement', 'keyword', 'reason'),
                        ),
                        'content_images' => array(
                            'type' => 'array',
                            'minItems' => $body_image_count,
                            'maxItems' => $body_image_count,
                            'items' => array(
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => array(
                                    'placement' => array('type' => 'string'),
                                    'location' => array('type' => 'string'),
                                    'keyword' => array('type' => 'string'),
                                    'reason' => array('type' => 'string'),
                                ),
                                'required' => array('placement', 'location', 'keyword', 'reason'),
                            ),
                        ),
                    ),
                    'required' => array('featured_image', 'content_images'),
                ),
            ),
            'required' => array('title', 'content', 'meta_description', 'short_answer_block', 'suggested_offsite_angle', 'inquiry_reply_draft', 'keywords', 'image_suggestions'),
        );
    }

    private function get_media_analysis_schema() {
        return array(
            "type" => "object",
            "additionalProperties" => false,
            "properties" => array(
                "is_ladakh" => array("type" => "string", "enum" => array("yes", "no", "uncertain")),
                "alt_text" => array("type" => "string"),
                "keywords" => array("type" => "array", "minItems" => 5, "maxItems" => 10, "items" => array("type" => "string")),
                "description" => array("type" => "string")
            ),
            "required" => array("is_ladakh", "alt_text", "keywords", "description")
        );
    }

    private function get_image_schema($body_image_count = 4) {
        $body_image_count = intval($body_image_count) === 5 ? 5 : 4;
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'featured_image' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => array(
                        'placement' => array('type' => 'string'),
                        'keyword' => array('type' => 'string'),
                        'reason' => array('type' => 'string'),
                    ),
                    'required' => array('placement', 'keyword', 'reason'),
                ),
                'content_images' => array(
                    'type' => 'array',
                    'minItems' => $body_image_count,
                    'maxItems' => $body_image_count,
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => array(
                            'placement' => array('type' => 'string'),
                            'location' => array('type' => 'string'),
                            'keyword' => array('type' => 'string'),
                            'reason' => array('type' => 'string'),
                        ),
                        'required' => array('placement', 'location', 'keyword', 'reason'),
                    ),
                ),
            ),
            'required' => array('featured_image', 'content_images'),
        );
    }

    private function prepend_internal_title_heading($title, $content) {
        $title = trim(wp_strip_all_tags((string) $title));
        $content = trim((string) $content);

        if ($title === '' || $content === '') {
            return $content;
        }

        $normalized = strtolower(trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($content))));
        $title_normalized = strtolower(trim(preg_replace('/\s+/', ' ', $title)));

        if (strpos($normalized, $title_normalized) === 0 && preg_match('/^<h2[^>]*>.*?<\/h2>/is', $content)) {
            return $content;
        }

        return $content;

    }

    private function insert_generated_post($data, $settings) {
        $title = isset($data['title']) ? sanitize_text_field($data['title']) : '';
        $content = isset($data['content']) ? wp_kses_post($data['content']) : '';
        $meta_description = isset($data['meta_description']) ? sanitize_text_field($data['meta_description']) : '';
        $keywords = isset($data['keywords']) && is_array($data['keywords']) ? array_map('sanitize_text_field', $data['keywords']) : array();

        if (empty($title) || empty($content) || count($keywords) !== 5) {
            return new WP_Error('invalid_payload', 'The model response was incomplete. Check the Last API log for details.');
        }

        $content = $this->prepend_internal_title_heading($title, $content);

        $slug = wp_unique_post_slug(
            sanitize_title($title),
            0,
            'draft',
            'post',
            0
        );

        $category_name = !empty($settings['blog_category_name']) ? $settings['blog_category_name'] : 'Blog';
        $cat_id = get_cat_ID($category_name);
        if (!$cat_id) {
            $created = wp_create_category($category_name);
            if (!is_wp_error($created)) {
                $cat_id = intval($created);
            }
        }

        $postarr = array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_name' => $slug,
            'post_author' => get_current_user_id(),
            'post_type' => 'post',
        );

        if ($cat_id) {
            $postarr['post_category'] = array($cat_id);
        }

        $post_id = wp_insert_post(wp_slash($postarr), true);
        if (is_wp_error($post_id)) {
            return new WP_Error('insert_failed', 'WordPress could not create the draft: ' . $post_id->get_error_message());
        }
        if ($meta_description !== '') { wp_update_post(array('ID' => $post_id, 'post_excerpt' => $meta_description)); }

        update_post_meta($post_id, 'rank_math_title', $title);
        update_post_meta($post_id, 'rank_math_focus_keyword', implode(', ', $keywords));
        update_post_meta($post_id, 'rank_math_description', $meta_description);
        update_post_meta($post_id, 'rank_math_pillar_content', 'on');
        update_post_meta($post_id, '_lotpl_lbg_keywords', $keywords);
        update_post_meta($post_id, '_lotpl_lbg_meta_description', $meta_description);

        return $post_id;
    }

    private function openai_request($payload, $api_key, $timeout = 180, $max_retries = 2) {
        $attempt = 0;
        $last_error = null;

        while ($attempt <= $max_retries) {
            $attempt++;

            $response = wp_remote_post('https://api.openai.com/v1/responses', array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ),
                'body' => wp_json_encode($payload),
                'timeout' => $timeout,
            ));

            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                continue;
            }

            $http_code = intval(wp_remote_retrieve_response_code($response));
            $body = wp_remote_retrieve_body($response);
            $json = json_decode($body, true);

            if ($http_code >= 200 && $http_code < 300) {
                $this->write_log('success', 'OpenAI request succeeded.', $http_code, isset($payload['model']) ? $payload['model'] : '', '');
                return $json;
            }

            $details = $body;
            $last_error = 'HTTP ' . $http_code;

            if (is_array($json) && !empty($json['error']['message'])) {
                $last_error .= ': ' . $json['error']['message'];
            }

            $this->write_log('error', $last_error, $http_code, isset($payload['model']) ? $payload['model'] : '', $details);

            if ($http_code < 500 || $attempt > $max_retries) {
                return new WP_Error('openai_http_error', 'OpenAI request failed. ' . $last_error);
            }
        }

        $this->write_log('error', $last_error ? $last_error : 'Unknown OpenAI error.', 0, isset($payload['model']) ? $payload['model'] : '', '');
        return new WP_Error('openai_error', 'OpenAI request failed. ' . ($last_error ? $last_error : 'Unknown error.'));
    }

    private function extract_structured_json($response_json) {
        if (!is_array($response_json)) {
            return new WP_Error('invalid_response', 'OpenAI returned a non-JSON response.');
        }

        if (!empty($response_json['output_text'])) {
            $decoded = json_decode($response_json['output_text'], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        if (!empty($response_json['output']) && is_array($response_json['output'])) {
            foreach ($response_json['output'] as $item) {
                if (!empty($item['content']) && is_array($item['content'])) {
                    foreach ($item['content'] as $content_item) {
                        if (isset($content_item['text'])) {
                            $decoded = json_decode($content_item['text'], true);
                            if (is_array($decoded)) {
                                return $decoded;
                            }
                        }
                    }
                }
            }
        }

        $this->write_log('error', 'Structured JSON could not be extracted.', 200, '', wp_json_encode($response_json));
        return new WP_Error('json_parse_error', 'The model response could not be parsed as structured JSON.');
    }

    private function write_log($status, $message, $http_code = 0, $model = '', $details = '') {
        update_option(self::LOG_OPTION, array(
            'time' => current_time('mysql'),
            'status' => $status,
            'message' => $message,
            'http_code' => $http_code,
            'model' => $model,
            'details' => is_string($details) ? $details : wp_json_encode($details),
        ), false);
    }

    public function add_generated_assets_meta_box() {
        add_meta_box(
            'lotpl_lbg_generated_assets',
            'LOTPL Generated Assets',
            array($this, 'render_generated_assets_meta_box'),
            'post',
            'normal',
            'default'
        );
    }

    public function render_generated_assets_meta_box($post) {
        $short_answer = get_post_meta($post->ID, '_lotpl_lbg_short_answer_block', true);
        $offsite = get_post_meta($post->ID, '_lotpl_lbg_suggested_offsite_angle', true);
        $reply = get_post_meta($post->ID, '_lotpl_lbg_inquiry_reply_draft', true);
        if ($short_answer === '' && $offsite === '' && $reply === '') {
            echo '<p>No extra generated assets yet for this draft.</p>';
            return;
        }
        echo '<div style="display:grid;gap:16px;">';
        if ($short_answer !== '') {
            echo '<div style="padding:14px 16px;border:1px solid #ebe2d8;border-radius:14px;background:#fffdfb">';
            echo '<p style="margin:0 0 8px;"><strong>Short answer block</strong></p>';
            echo '<div>' . wp_kses_post(wpautop($short_answer)) . '</div>';
            echo '</div>';
        }
        if ($offsite !== '') {
            echo '<div style="padding:14px 16px;border:1px solid #ebe2d8;border-radius:14px;background:#fffdfb">';
            echo '<p style="margin:0 0 8px;"><strong>Suggested off-site angle</strong></p>';
            echo '<p style="margin:0;">' . esc_html($offsite) . '</p>';
            echo '</div>';
        }
        if ($reply !== '') {
            echo '<div style="padding:14px 16px;border:1px solid #ebe2d8;border-radius:14px;background:#fffdfb">';
            echo '<p style="margin:0 0 8px;"><strong>Inquiry reply draft</strong></p>';
            echo '<textarea readonly class="large-text code" rows="8">' . esc_textarea($reply) . '</textarea>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function add_image_suggestions_meta_box() {
        add_meta_box(
            'lotpl_lbg_image_suggestions',
            '画像提案 - アイキャッチ & 挿入画像',
            array($this, 'render_image_suggestions_meta_box'),
            'post',
            'side',
            'high'
        );
    }

    public function render_image_suggestions_meta_box($post) {
        $suggestions = get_post_meta($post->ID, '_ladakh_image_suggestions', true);
        $saved_body_image_count = intval(get_post_meta($post->ID, '_lotpl_lbg_body_image_count', true));
        if ($saved_body_image_count !== 5) { $saved_body_image_count = 4; }
        $ajax_nonce = wp_create_nonce(self::AJAX_NONCE_ACTION);

        if (!empty($suggestions) && is_array($suggestions)) {
            $featured = isset($suggestions['featured_image']) ? $suggestions['featured_image'] : array();
            echo '<div style="background:#f6fbff;padding:10px;border-left:3px solid #2271b1;margin-bottom:12px;">';
            echo '<p><strong>📸 アイキャッチ画像</strong></p>';
            if (is_array($featured)) {
                echo '<p style="margin:0 0 8px;"><strong>配置:</strong> ' . esc_html(isset($featured['placement']) ? $featured['placement'] : '記事の代表画像') . '</p>';
                echo '<p style="margin:0 0 8px;"><strong>検索キーワード:</strong><br><code>' . esc_html(isset($featured['keyword']) ? $featured['keyword'] : '') . '</code></p>';
                if (!empty($featured['reason'])) {
                    echo '<p style="margin:0;"><strong>理由:</strong> ' . esc_html($featured['reason']) . '</p>';
                }
            } else {
                echo '<p style="margin:0;"><code>' . esc_html($featured) . '</code></p>';
            }
            echo '</div>';

            if (!empty($suggestions['content_images']) && is_array($suggestions['content_images'])) {
                echo '<div style="background:#fff9e6;padding:10px;border-left:3px solid #dba617;margin-bottom:12px;">';
                echo '<p><strong>🖼️ 本文画像配置プラン</strong><br><small>1枚目だけに寄せず、記事全体に満遍なく配置する前提よ。現在の本文画像数: ' . intval(count($suggestions['content_images'])) . '枚。</small></p>';
                foreach ($suggestions['content_images'] as $index => $img) {
                    echo '<div style="margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid #eadfb8;">';
                    echo '<p style="margin:0 0 6px;"><strong>' . esc_html('画像 ' . ($index + 1)) . '</strong></p>';
                    if (!empty($img['placement'])) {
                        echo '<p style="margin:0 0 4px;"><strong>配置:</strong> ' . esc_html($img['placement']) . '</p>';
                    }
                    if (!empty($img['location'])) {
                        echo '<p style="margin:0 0 4px;"><strong>挿入場所:</strong> ' . esc_html($img['location']) . '</p>';
                    }
                    echo '<p style="margin:0 0 4px;"><strong>検索キーワード:</strong><br><code>' . esc_html(isset($img['keyword']) ? $img['keyword'] : '') . '</code></p>';
                    if (!empty($img['reason'])) {
                        echo '<p style="margin:0;"><strong>理由:</strong> ' . esc_html($img['reason']) . '</p>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
        } else {
            echo '<p>この投稿の内容に合わせて、アイキャッチ1枚＋本文4枚または5枚の画像配置プランと検索キーワードを生成します。</p>';
        }

        echo '<p><label for="lotpl_lbg_body_image_count_meta"><strong>本文画像数</strong></label> ';
        echo '<select id="lotpl_lbg_body_image_count_meta" style="margin-left:8px;">';
        echo '<option value="4"' . selected($saved_body_image_count, 4, false) . '>4枚</option>';
        echo '<option value="5"' . selected($saved_body_image_count, 5, false) . '>5枚</option>';
        echo '</select></p>';

        echo '<button type="button" class="button button-primary" id="lotpl-lbg-image-btn">画像配置プランを生成 / 再生成</button>';
        echo '<p id="lotpl-lbg-image-status" style="margin-top:10px;"></p>';
        ?>
        <script>
        (function($){
            $('#lotpl-lbg-image-btn').on('click', function(){
                $('#lotpl-lbg-image-status').text('生成中...');
                $.post(ajaxurl, {
                    action: 'lotpl_lbg_generate_image_suggestions',
                    post_id: <?php echo intval($post->ID); ?>,
                    body_image_count: $('#lotpl_lbg_body_image_count_meta').val(),
                    nonce: '<?php echo esc_js($ajax_nonce); ?>'
                }).done(function(response){
                    if (response && response.success) {
                        $('#lotpl-lbg-image-status').text('生成できたわ。ページを再読み込みします。');
                        window.location.reload();
                    } else {
                        $('#lotpl-lbg-image-status').text(response && response.data ? response.data : '生成に失敗しました');
                    }
                }).fail(function(){
                    $('#lotpl-lbg-image-status').text('通信エラーが起きました');
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    public function generate_image_suggestions_ajax() {
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }

        check_ajax_referer(self::AJAX_NONCE_ACTION, 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            wp_send_json_error('投稿が見つかりません');
        }

        $settings = $this->get_settings();
        $body_image_count = isset($_POST['body_image_count']) && intval($_POST['body_image_count']) === 5 ? 5 : intval($settings['default_body_image_count']);
        if ($body_image_count !== 5) { $body_image_count = 4; }
        if (empty($settings['api_key'])) {
            wp_send_json_error('OpenAI API key が未設定です');
        }

        $payload = array(
            'model' => $settings['model'],
            'input' => implode("\n", array(
                'Create an image placement plan for this Ladakh blog post.',
                'Return exactly 1 featured image and exactly ' . $body_image_count . ' body images.',
                'Body images must be spread across the full article, not concentrated near the beginning.',
                'Return search phrases in English.',
                'Return placement labels and insertion locations in natural English only.',
                'Use concrete keyword phrases suitable for stock search, archive search, or WordPress media search.',
                'Each image must include a short reason explaining why it fits that section.',
                'Use section names or article flow clues from the content when choosing insertion locations.',
                'Title: ' . $post->post_title,
                'Content: ' . wp_strip_all_tags($post->post_content),
                'Return data matching the provided JSON schema exactly.',
            )),
            'reasoning' => array('effort' => 'none'),
            'text' => array(
                'verbosity' => 'low',
                'format' => array(
                    'type' => 'json_schema',
                    'name' => 'ladakh_image_suggestions',
                    'schema' => $this->get_image_schema($body_image_count),
                    'strict' => true,
                ),
            ),
        );

        $response = $this->openai_request($payload, $settings['api_key'], max(60, intval($settings['request_timeout']) - 30), 1);
        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $data = $this->extract_structured_json($response);
        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        update_post_meta($post_id, '_ladakh_image_suggestions', $data);
        update_post_meta($post_id, '_lotpl_lbg_body_image_count', $body_image_count);
        wp_send_json_success($data);
    }
    public function queue_new_media_image_analysis($attachment_id) {
        $attachment_id = intval($attachment_id);
        if ($attachment_id <= 0) { return; }
        $mime = get_post_mime_type($attachment_id);
        if (strpos((string) $mime, "image/") !== 0) { return; }
        update_post_meta($attachment_id, "_lotpl_ai_image_status", "queued");
        update_post_meta($attachment_id, "_lotpl_ai_image_is_ladakh", "pending");
    }

    private function queue_existing_media_images($limit = 30) {
        $ids = get_posts(array(
            "post_type" => "attachment",
            "post_status" => "inherit",
            "post_mime_type" => "image",
            "posts_per_page" => max(1, min(100, intval($limit))),
            "fields" => "ids",
            "meta_query" => array(
                array(
                    "key" => "_lotpl_ai_image_status",
                    "compare" => "NOT EXISTS",
                ),
            ),
        ));
        $count = 0;
        foreach ($ids as $attachment_id) {
            $this->queue_new_media_image_analysis($attachment_id);
            $count++;
        }
        return $count;
    }

    private function get_media_image_file_hash($attachment_id) {
        $path = get_attached_file($attachment_id);
        if (is_string($path) === false || $path === "" || file_exists($path) === false) { return ""; }
        $hash = md5_file($path);
        if (is_string($hash) && $hash !== "") { update_post_meta($attachment_id, "_lotpl_ai_image_file_hash", $hash); return $hash; }
        return "";
    }

    private function mark_duplicate_media_image_done($attachment_id) {
        $url = wp_get_attachment_url($attachment_id);
        $hash = $this->get_media_image_file_hash($attachment_id);
        if ($url === false || $url === "") { return false; }
        $ids = get_posts(array("post_type" => "attachment", "post_status" => "inherit", "post_mime_type" => "image", "posts_per_page" => 50, "fields" => "ids"));
        foreach ($ids as $src) {
            if (intval($src) === intval($attachment_id)) { continue; }
            $src_hash = $this->get_media_image_file_hash($src);
            if (wp_get_attachment_url($src) !== $url && ($hash === "" || $src_hash === "" || $src_hash !== $hash)) { continue; }
            if (get_post_meta($src, "_lotpl_ai_image_status", true) !== "done") { continue; }
            foreach (array("_lotpl_ai_image_is_ladakh", "_lotpl_ai_image_keywords", "_lotpl_ai_image_description", "_wp_attachment_image_alt") as $key) {
                $value = get_post_meta($src, $key, true);
                if ($value !== "") { update_post_meta($attachment_id, $key, $value); }
            }
            update_post_meta($attachment_id, "_lotpl_ai_image_status", "done"); if(get_post_meta($attachment_id,"_lotpl_ai_image_reanalyzed",true)==="pending"){update_post_meta($attachment_id,"_lotpl_ai_image_reanalyzed","yes");}
            update_post_meta($attachment_id, "_lotpl_ai_image_duplicate_source", intval($src));
            return true;
        }
        return false;
    }

    private function analyze_next_queued_media_image() {
        $settings = $this->get_settings();
        if ($settings["api_key"] === "") { return new WP_Error("missing_api_key", "OpenAI API key is missing."); }
        $attachment_id = $this->get_next_queued_media_image_id();
        if ($attachment_id <= 0) { return new WP_Error("no_queued_media", "No queued media image found."); }
        if ($this->mark_duplicate_media_image_done($attachment_id)) { return $attachment_id; }
        $url = wp_get_attachment_url($attachment_id);
        if ($url === false || $url === "") { return new WP_Error("missing_image_url", "Image URL not found."); }
        update_post_meta($attachment_id, "_lotpl_ai_image_status", "processing");
        update_post_meta($attachment_id, "_lotpl_ai_image_url", $url);
        $payload = array(
            "model" => $settings["model"],
            "input" => array(array(
                "role" => "user",
                "content" => array(
                    array("type" => "input_text", "text" => "Analyze this WordPress media image for LIFE on the PLANET LADAKH. Decide if it is a real photograph clearly relevant to Ladakh travel. Mark is_ladakh as no if: the image is a logo, icon, illustration, cartoon, drawing, banner, graphic design, text-only image, watermark, badge, or any non-photographic image. Mark is_ladakh as no if unrelated to Ladakh or Himalayan travel. Only real photographs of Ladakh landscapes, people, culture, or travel scenes should be marked yes. Return concise useful alt text and 8 to 12 specific English keywords. Keywords must include: (1) specific subject such as monastery, river, camel, prayer flags, villager, monk, tent, frozen river, apricot blossom, sand dunes, mountain pass, homestay, ice hockey; (2) season such as winter, summer, spring, autumn; (3) time of day if clear such as morning, sunset, night; (4) specific location name if visible such as Nubra, Pangong, Zanskar, Leh, Chiktan, Sham Valley, Hanle; (5) activity if present such as trekking, driving, ceremony, farming, cooking, camping. Avoid vague generic words like travel, landscape, nature, beauty as standalone keywords."),
                    array("type" => "input_image", "image_url" => $url, "detail" => "low")
                )
            )),
            "reasoning" => array("effort" => "none"),
            "text" => array("verbosity" => "low", "format" => array("type" => "json_schema", "name" => "lotpl_media_image_analysis", "schema" => $this->get_media_analysis_schema(), "strict" => true))
        );
        update_post_meta($attachment_id, "_lotpl_ai_image_payload_ready", "yes");
        $response = $this->openai_request($payload, $settings["api_key"], max(60, intval($settings["request_timeout"]) - 30), 1);
        if (is_wp_error($response)) {
            update_post_meta($attachment_id, "_lotpl_ai_image_status", "error");
            return $response;
        }
        $data = $this->extract_structured_json($response);
        if (is_wp_error($data)) {
            update_post_meta($attachment_id, "_lotpl_ai_image_status", "error");
            return $data;
        }
        update_post_meta($attachment_id, "_lotpl_ai_image_response_ready", "yes");
        $is_ladakh = isset($data["is_ladakh"]) ? sanitize_text_field($data["is_ladakh"]) : "uncertain";
        $alt_text = isset($data["alt_text"]) ? sanitize_text_field($data["alt_text"]) : "";
        $keywords = isset($data["keywords"]) && is_array($data["keywords"]) ? array_map("sanitize_text_field", $data["keywords"]) : array();
        $description = isset($data["description"]) ? sanitize_textarea_field($data["description"]) : "";
        update_post_meta($attachment_id, "_lotpl_ai_image_is_ladakh", $is_ladakh);
        update_post_meta($attachment_id, "_lotpl_ai_image_keywords", implode(", ", $keywords));
        update_post_meta($attachment_id, "_lotpl_ai_image_description", $description);
        update_post_meta($attachment_id, "_lotpl_ai_image_status", "done"); if(get_post_meta($attachment_id,"_lotpl_ai_image_reanalyzed",true)==="pending"){update_post_meta($attachment_id,"_lotpl_ai_image_reanalyzed","yes");}
        if ($is_ladakh === "yes" && $alt_text !== "") { update_post_meta($attachment_id, "_wp_attachment_image_alt", $alt_text); }
        return $attachment_id;
    }

    private function analyze_queued_media_images($limit = 3) {
        $limit = max(1, min(10, intval($limit)));
        $done = array();
        for ($i = 0; $i < $limit; $i++) {
            $result = $this->analyze_next_queued_media_image();
            if (is_wp_error($result)) {
                if (count($done) > 0) { break; }
                return $result;
            }
            $done[] = intval($result);
        }
        return $done;
    }

    private function get_next_queued_media_image_id() {
        $ids = get_posts(array(
            "post_type" => "attachment",
            "post_status" => "inherit",
            "post_mime_type" => "image",
            "posts_per_page" => 1,
            "fields" => "ids",
            "meta_key" => "_lotpl_ai_image_status",
            "meta_value" => "queued",
        ));
        if (is_array($ids) && isset($ids[0])) { return intval($ids[0]); }
        return 0;
    }

    private function build_image_suggestion_query($suggestion) {
        if (is_array($suggestion) === false) { return sanitize_text_field((string) $suggestion); }
        $parts = array();
        foreach (array("keyword", "reason", "placement", "location") as $key) {
            if (isset($suggestion[$key]) && trim((string) $suggestion[$key]) !== "") { $parts[] = (string) $suggestion[$key]; }
        }
        return sanitize_text_field(implode(" ", $parts));
    }

    private function apply_generated_images_to_post($post_id, $suggestions) {
        $post_id = intval($post_id);
        if ($post_id <= 0 || is_array($suggestions) === false) { return; }
        $used_image_ids = array();
        $used_image_hashes = array();
        if (isset($suggestions["featured_image"])) {
            $featured_id = $this->find_media_image_by_keyword($this->build_image_suggestion_query($suggestions["featured_image"]));
            if ($featured_id > 0) { set_post_thumbnail($post_id, $featured_id); update_post_meta($post_id, "_lotpl_lbg_auto_featured_image_id", $featured_id); $used_image_ids[] = intval($featured_id); $featured_hash = $this->get_media_image_file_hash($featured_id); if ($featured_hash !== "") { $used_image_hashes[] = $featured_hash; } }
        }
        if (isset($suggestions["content_images"]) === false || is_array($suggestions["content_images"]) === false) { return; }
        $post = get_post($post_id);
        if ($post === null) { return; }
        $content = (string) $post->post_content;
        $parts = explode("</p>", $content);
        if (count($parts) < 3) { return; }
        $inserted = 0;
        $total = count($suggestions["content_images"]);
        foreach ($suggestions["content_images"] as $img) {
            if (isset($img["keyword"]) === false) { continue; }
            $image_id = $this->find_media_image_by_keyword($this->build_image_suggestion_query($img), false);
            if ($image_id <= 0) { continue; }
            if (in_array(intval($image_id), $used_image_ids, true)) { continue; }
            $image_hash = $this->get_media_image_file_hash($image_id);
            if ($image_hash !== "" && in_array($image_hash, $used_image_hashes, true)) { continue; }
            $used_image_ids[] = intval($image_id);
            if ($image_hash !== "") { $used_image_hashes[] = $image_hash; }
            $caption = ""; if (function_exists("icl_t")){ $caption = icl_t("media", "media_".$image_id."_alt", get_post_meta($image_id, "_wp_attachment_image_alt", true)); } else { $caption = get_post_meta($image_id, "_wp_attachment_image_alt", true); } if(trim((string)$caption)===""){ $caption = get_post_meta($image_id, "_wp_attachment_image_alt", true); } if($caption === "") { $caption = "Ladakh travel scene"; } $caption = sanitize_text_field($caption);
            $html = "<figure class=\"lotpl-lbg-auto-figure\">" . wp_get_attachment_image($image_id, "large", false, array("class" => "lotpl-lbg-auto-image")) . "<figcaption>" . esc_html($caption) . "</figcaption></figure>";
            $pos = min(count($parts) - 1, max(1, intval(round((count($parts) / ($total + 1)) * ($inserted + 1)))));
            $parts[$pos] .= "</p>" . $html;
            $inserted++;
        }
        if ($inserted > 0) { wp_update_post(array("ID" => $post_id, "post_content" => implode("</p>", $parts))); update_post_meta($post_id, "_lotpl_lbg_auto_body_images", $inserted); }
    }

    private function media_image_is_landscape($attachment_id) {
        $meta = wp_get_attachment_metadata($attachment_id);
        $w = isset($meta["width"]) ? intval($meta["width"]) : 0;
        $h = isset($meta["height"]) ? intval($meta["height"]) : 0;
        if ($w < 800 || $h <= 0) { return false; }
        return ($w >= intval($h * 1.25));
    }

    private function media_image_is_large($attachment_id) {
        $meta = wp_get_attachment_metadata($attachment_id);
        $w = isset($meta["width"]) ? intval($meta["width"]) : 0;
        $h = isset($meta["height"]) ? intval($meta["height"]) : 0;
        return (($w >= 800 || $h >= 800) && $w > 0 && $h > 0);
    }

    private function find_media_image_by_keyword($keyword, $landscape_only = true) {
        $keyword = strtolower(sanitize_text_field($keyword));
        $keyword = preg_replace("/[^a-z0-9 ]+/", " ", $keyword);
        if ($keyword === "") { return 0; }
        $stop_words = array("image","article","featured","essay","central","mood","establish","lengthening","atmosphere","intimate","shows","helps","readers","feel","supports","practical","point","connects","directly","sensory","details","provides","visual","ending","matches","opening","section","after","near","alongside","placement","location","reason","keyword","light","travel","traveler","ladakh");
        $ids = get_posts(array(
            "post_type" => "attachment",
            "post_status" => "inherit",
            "post_mime_type" => "image",
            "posts_per_page" => 100,
            "fields" => "ids",
            "meta_query" => array(array("key" => "_lotpl_ai_image_is_ladakh", "value" => "yes")),
        ));
        $ids = array_values(array_filter(array_map("intval", $ids), array($this, $landscape_only ? "media_image_is_landscape" : "media_image_is_large"))); $ids = array_values(array_filter($ids, function($id) { $bad = array("logo","icon","illustration","banner","graphic","text","poster","badge","label","symbol","watermark","stamp"); $hay = strtolower(get_the_title($id)." ".get_post_meta($id,"_wp_attachment_image_alt",true)." ".get_post_meta($id,"_lotpl_ai_image_keywords",true)." ".get_post_meta($id,"_lotpl_ai_image_description",true)); foreach($bad as $b){ if(strpos($hay,$b)!==false) return false; } return true; }));
        if (count($ids) === 0) { return 0; }
        $matches = array();
        foreach ($ids as $id) {
            $haystack = strtolower(get_the_title($id) . " " . get_post_meta($id, "_wp_attachment_image_alt", true) . " " . get_post_meta($id, "_lotpl_ai_image_keywords", true) . " " . get_post_meta($id, "_lotpl_ai_image_description", true));
            $score = 0;
            $generic = array("ladakh","himalaya","mountain","travel","landscape","culture","local","village");
            foreach (array_unique(preg_split("/[, ]+/", str_replace(",", " ", $keyword))) as $word) {
                $word = trim($word);
                if (strlen($word) < 4 || in_array($word, $stop_words, true) || strpos($haystack, $word) === false) { continue; }
                $score += in_array($word, $generic) ? 0 : 2;
            }
            if ($score >= 2) { $matches[intval($id)] = $score; }
        }
        if (count($matches) === 0) { return 0; }
        arsort($matches);
        $matches = array_keys($matches);
        $recent_imgs = get_option("lotpl_lbg_recent_images", array());
        $pool = array_values(array_diff($matches, $recent_imgs));
        if (empty($pool)) { $recent_imgs = array(); $pool = $matches; }
        if (empty($pool)) { return 0; }
        $chosen = intval($pool[wp_rand(0, count($pool) - 1)]);
        $recent_imgs[] = $chosen;
        if (count($recent_imgs) > 20) { $recent_imgs = array_slice($recent_imgs, -20); }
        update_option("lotpl_lbg_recent_images", $recent_imgs);
        return $chosen;
    }
}

new LOTPL_Ladakh_Blog_Generator_OpenAI();
