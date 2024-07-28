<?php
/*
* Plugin Name:       Generátor AI článků a obrázků 
* Plugin URI:        https://www.kubicek.ai/wp-ai-generator/
* Description:       Tento WordPress plugin generuje články a obrázky pomocí OpenAI GPT-4o-mini a DALL-E API. Plugin umožňuje automatické nebo manuální generování článků na základě specifikovaných kategorií a témat.
* Version:           1.0
* Author:            Webklient.cz & Kubicek.ai
* Author URI:        https://www.webklient.cz
* Text Domain:       WP-AI-article-generator-main
*/

if (!defined('ABSPATH')) {
    exit; // Pokud tento soubor není volán přímo z WordPress, ukonči.
}

function WebklientArticleGenerator_add_settings_link($links)
{
    $settings_link = '<a href="' . admin_url('options-general.php?page=article-generator') . '">' . __('Settings', 'article-generator') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'WebklientArticleGenerator_add_settings_link');

class ArticleGeneratorPlugin {

    public function __construct() {
        // Kontrola a nastavení paměťového limitu
        // ... (your existing memory and execution time setup)
        
        // Nastavení akcí WordPressu
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp', array($this, 'schedule_cron_job'));
        add_action('article_gen_hook', array($this, 'generate_posts'));
        add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);
        add_action('admin_post_generate_article', array($this, 'handle_generate_article'));
        add_action('init', array($this, 'register_block'));
        add_action('rest_api_init', array($this, 'register_api_routes'));
    }

    // (rest of the existing code including utility functions)

    public function add_plugin_page() {
        add_options_page(
            'Generátor článků', 
            'Generátor článků', 
            'manage_options', 
            'article-generator', 
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('article_gen_options');
        ?>
        <div class="wrap">
            <h1>Generátor článků</h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields('article_gen_option_group');
                    do_settings_sections('article-generator');
                    submit_button();
                ?>
            </form>
        </div>

        <!-- Existing forms for manual generation -->
        <div class="wrap">
            <h2>Manuální generování článků</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="generate_articles">
                <?php submit_button('Generovat články'); ?>
            </form>
            <?php
            if (isset($_POST['action']) && $_POST['action'] === 'generate_articles') {
                $this->generate_posts();
            }
            ?>
        </div>

        <div class="wrap">
            <h2>Generování článku na konkrétní téma</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="generate_custom_article">
                <p>
                    <label for="custom_topic">Téma článku:</label>
                    <input type="text" id="custom_topic" name="custom_topic" required>
                </p>
                <?php submit_button('Generovat článek na téma'); ?>
            </form>
        </div>
        <?php
        if (isset($_POST['action']) && $_POST['action'] === 'generate_custom_article') {
            $this->generate_custom_article($_POST['custom_topic']);
        }
    }

    public function page_init() {
        register_setting(
            'article_gen_option_group', 
            'article_gen_options', 
            array($this, 'sanitize')
        );

        add_settings_section(
            'setting_section_id', 
            'Nastavení API', 
            array($this, 'print_section_info'), 
            'article-generator'
        );  

        add_settings_field(
            'openai_api_key', 
            'OpenAI API klíč', 
            array($this, 'openai_api_callback'), 
            'article-generator', 
            'setting_section_id'
        );      

        add_settings_field(
            'openai_org', 
            'OpenAI Organizace', 
            array($this, 'openai_org_callback'), 
            'article-generator', 
            'setting_section_id'
        );   

        add_settings_field(
            'ignore_categories', 
            'Ignorovat kategorie (ID oddělená čárkami)', 
            array($this, 'ignore_categories_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'target_audience', 
            'Cílová skupina čtenářů', 
            array($this, 'target_audience_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'website_focus', 
            'Zaměření webu', 
            array($this, 'website_focus_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'image_generation', 
            'Generování obrázků', 
            array($this, 'image_generation_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'dalle_model', 
            'DALL-E Model', 
            array($this, 'dalle_model_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'dalle_resolution', 
            'DALL-E Rozlišení', 
            array($this, 'dalle_resolution_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'article_length', 
            'Délka článku v tokenech', 
            array($this, 'article_length_callback'), 
            'article-generator', 
            'setting_section_id'
        );
    }

    public function sanitize($input) {
        $new_input = array();
        if(isset($input['openai_api_key']))
            $new_input['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        if(isset($input['openai_org']))
            $new_input['openai_org'] = sanitize_text_field($input['openai_org']);
        if(isset($input['ignore_categories']))
            $new_input['ignore_categories'] = sanitize_text_field($input['ignore_categories']);
        if(isset($input['target_audience']))
            $new_input['target_audience'] = sanitize_text_field($input['target_audience']);
        if(isset($input['website_focus']))
            $new_input['website_focus'] = sanitize_text_field($input['website_focus']);
        if(isset($input['image_generation']))
            $new_input['image_generation'] = sanitize_text_field($input['image_generation']);
        if(isset($input['dalle_model']))
            $new_input['dalle_model'] = sanitize_text_field($input['dalle_model']);
        if(isset($input['dalle_resolution']))
            $new_input['dalle_resolution'] = sanitize_text_field($input['dalle_resolution']);
        if(isset($input['article_length']))
            $new_input['article_length'] = absint($input['article_length']);
        return $new_input;
    }

    public function print_section_info() {
        print 'Zadejte vaše API klíče, ID organizace OpenAI, cílovou skupinu čtenářů a zaměření webu:';
    }

    public function openai_api_callback() {
        printf(
            '<input type="text" id="openai_api_key" name="article_gen_options[openai_api_key]" value="%s" />',
            isset($this->options['openai_api_key']) ? esc_attr($this->options['openai_api_key']) : ''
        );
    }

    public function openai_org_callback() {
        printf(
            '<input type="text" id="openai_org" name="article_gen_options[openai_org]" value="%s" />',
            isset($this->options['openai_org']) ? esc_attr($this->options['openai_org']) : ''
        );
    }

    public function ignore_categories_callback() {
        printf(
            '<input type="text" id="ignore_categories" name="article_gen_options[ignore_categories]" value="%s" />',
            isset($this->options['ignore_categories']) ? esc_attr($this->options['ignore_categories']) : ''
        );
    }

    public function target_audience_callback() {
        printf(
            '<input type="text" id="target_audience" name="article_gen_options[target_audience]" value="%s" />',
            isset($this->options['target_audience']) ? esc_attr($this->options['target_audience']) : ''
        );
    }

    public function website_focus_callback() {
        printf(
            '<input type="text" id="website_focus" name="article_gen_options[website_focus]" value="%s" />',
            isset($this->options['website_focus']) ? esc_attr($this->options['website_focus']) : ''
        );
    }

    public function image_generation_callback() {
        $checked = isset($this->options['image_generation']) && $this->options['image_generation'] === 'yes' ? 'checked' : '';
        printf(
            '<input type="checkbox" id="image_generation" name="article_gen_options[image_generation]" value="yes" %s /> Povolit generování obrázků',
            $checked
        );
    }

    public function dalle_model_callback() {
        $model = isset($this->options['dalle_model']) ? $this->options['dalle_model'] : 'dalle-2';
        ?>
        <select id="dalle_model" name="article_gen_options[dalle_model]">
            <option value="dalle-3" <?php selected($model, 'dalle-3'); ?>>DALL-E 3</option>
            <option value="dalle-2" <?php selected($model, 'dalle-2'); ?>>DALL-E 2</option>
        </select>
        <?php
    }

    public function dalle_resolution_callback() {
        $resolution = isset($this->options['dalle_resolution']) ? $this->options['dalle_resolution'] : '1024x1024';
        ?>
        <select id="dalle_resolution" name="article_gen_options[dalle_resolution]">
            <option value="1024x1024" <?php selected($resolution, '1024x1024'); ?>>1024x1024</option>
            <option value="1024x1792" <?php selected($resolution, '1024x1792'); ?>>1024x1792</option>
            <option value="1792x1024" <?php selected($resolution, '1792x1024'); ?>>1792x1024</option>
            <option value="512x512" <?php selected($resolution, '512x512'); ?>>512x512</option>
            <option value="256x256" <?php selected($resolution, '256x256'); ?>>256x256</option>
        </select>
        <?php
    }

    public function article_length_callback() {
        $length = isset($this->options['article_length']) ? intval($this->options['article_length']) : 3000;
        printf(
            '<input type="number" id="article_length" name="article_gen_options[article_length]" value="%d" min="500" max="4000" />',
            $length
        );
    }

    public function schedule_cron_job() {
        // Schedule hourly or daily based on settings
        $cron_schedule = isset($this->options['cron_schedule']) ? $this->options['cron_schedule'] : 'hourly';
        if (!wp_next_scheduled('article_gen_hook')) {
            wp_schedule_event(time(), $cron_schedule, 'article_gen_hook');
        }
    }

    public function add_toolbar_items($admin_bar) {
        $admin_bar->add_menu(array(
            'id'    => 'generate-article',
            'title' => 'Generovat článek',
            'href'  => admin_url('admin-post.php?action=generate_article'),
            'meta'  => array(
                'title' => __('Generovat nový článek'),
            ),
        ));
    }

    public function handle_generate_article() {
        $this->generate_posts();
        wp_redirect(admin_url('edit.php'));
        exit;
    }

    public function generate_posts() {
        global $wpdb;
        $table_prefix = $wpdb->prefix;

        $options = get_option('article_gen_options');
        $api_openai = $options['openai_api_key'];
        $openai_org = $options['openai_org'];
        $ignore_categories = isset($options['ignore_categories']) ? $options['ignore_categories'] : '';
        $target_audience = isset($options['target_audience']) ? $options['target_audience'] : 'čtenáře';
        $website_focus = isset($options['website_focus']) ? $options['website_focus'] : 'zaměření webu';
        $article_length = isset($options['article_length']) ? $options['article_length'] : 3000;

        // (existing SQL query to get category)

        // (existing loop to generate articles)
    }

    // (existing methods for custom article generation, saving articles, image generation, and downloading images)

    public function register_block() {
        // (existing Gutenberg block registration)
    }

    public function register_api_routes() {
        // (existing REST API route registration)
    }

    // (rest of the existing methods)
}

if(is_admin())
    $article_gen_plugin = new ArticleGeneratorPlugin();

// spuštění generování článků pomocí WP cronu
if (!wp_next_scheduled('article_gen_hook')) {
    wp_schedule_event(time(), 'hourly', 'article_gen_hook');
}
add_action('article_gen_hook', 'generate_posts');

function generate_posts() {
    $article_gen_plugin = new ArticleGeneratorPlugin();
    $article_gen_plugin->generate_posts();
}
