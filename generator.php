<?php
/*
* Plugin Name:       Generátor AI článků a obrázků 
* Plugin URI:        https://www.kubicek.ai/wp-ai-generator/
* Description:       Tento plugin generuje články a obrázky pomocí OpenAI GPT-4o-mini a DALL-E API. Plugin umožňuje automatické nebo manuální generování článků na základě specifikovaných kategorií a témat.
* Version:           1.1
* Author:            Webklient.cz & Kubicek.ai
* Author URI:        https://www.webklient.cz
* Text Domain:       webklient_ai_generator
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
        // Nastavení paměťového limitu a doby běhu
        $current_memory_limit = ini_get('memory_limit');
        $desired_memory_limit = '256M';
        $current_memory_limit_bytes = $this->convert_hr_to_bytes($current_memory_limit);
        $desired_memory_limit_bytes = $this->convert_hr_to_bytes($desired_memory_limit);

        if ($current_memory_limit_bytes < $desired_memory_limit_bytes) {
            ini_set('memory_limit', $desired_memory_limit);
        }

        $current_execution_time = ini_get('max_execution_time');
        $desired_execution_time = 300; // v sekundách

        if ($current_execution_time < $desired_execution_time) {
            set_time_limit($desired_execution_time);
            ini_set('max_execution_time', $desired_execution_time);
        }

        // Nastavení akcí WordPressu
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp', array($this, 'schedule_cron_job'));
        add_action('article_gen_hook', array($this, 'generate_posts'));
        add_action('admin_bar_menu', array($this, 'add_toolbar_items'), 100);
        add_action('admin_post_generate_articles', array($this, 'generate_posts'));
        add_action('admin_post_generate_custom_article', array($this, 'generate_custom_article'));
        add_action('admin_post_generate_article', array($this, 'handle_generate_article'));
    }

    private function convert_hr_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $num_value = substr($value, 0, -1);

        if (!is_numeric($num_value)) {
            error_log("Nenumerická hodnota při konverzi na byty: " . $value);
            return 0;
        }

        $num_value = floatval($num_value);

        switch ($last) {
            case 'g':
                $num_value *= 1024;
            case 'm':
                $num_value *= 1024;
            case 'k':
                $num_value *= 1024;
        }

        return $num_value;
    }

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

        <div class="wrap">
            <h2>Manuální generování článků</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="generate_articles">
                <?php submit_button('Generovat články'); ?>
            </form>
        </div>

        <div class="wrap">
            <h2>Generování článku na konkrétní téma</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="generate_custom_article">
                <p>
                    <label for="custom_topic">Téma článku:</label>
                    <input type="text" id="custom_topic" name="custom_topic" required>
                </p>
                <?php submit_button('Generovat článek na téma'); ?>
            </form>
        </div>
        <?php
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

        add_settings_field(
            'cron_schedule', 
            'Cron plán', 
            array($this, 'cron_schedule_callback'), 
            'article-generator', 
            'setting_section_id'
        );

        add_settings_field(
            'post_status', 
            'Stav příspěvku', 
            array($this, 'post_status_callback'), 
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
        if(isset($input['cron_schedule']))
            $new_input['cron_schedule'] = sanitize_text_field($input['cron_schedule']);
        if(isset($input['post_status']))
            $new_input['post_status'] = sanitize_text_field($input['post_status']);
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

    public function cron_schedule_callback() {
        $schedule = isset($this->options['cron_schedule']) ? $this->options['cron_schedule'] : 'hourly';
        ?>
        <select id="cron_schedule" name="article_gen_options[cron_schedule]">
            <option value="hourly" <?php selected($schedule, 'hourly'); ?>>Hodinově</option>
            <option value="daily" <?php selected($schedule, 'daily'); ?>>Denně</option>
        </select>
        <?php
    }

    public function post_status_callback() {
        $status = isset($this->options['post_status']) ? $this->options['post_status'] : 'publish';
        ?>
        <select id="post_status" name="article_gen_options[post_status]">
            <option value="publish" <?php selected($status, 'publish'); ?>>Publikovaný</option>
            <option value="draft" <?php selected($status, 'draft'); ?>>Koncept</option>
        </select>
        <?php
    }

    public function schedule_cron_job() {
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
        wp_redirect(admin_url('options-general.php?page=article-generator'));
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
        $image_generation = isset($options['image_generation']) && $options['image_generation'] === 'yes';
        $dalle_model = isset($options['dalle_model']) ? $options['dalle_model'] : 'dalle-2';
        $dalle_resolution = isset($options['dalle_resolution']) ? $options['dalle_resolution'] : '1024x1024';
        $post_status = isset($options['post_status']) ? $options['post_status'] : 'publish';

        $ignore_categories_sql = '';
        if (!empty($ignore_categories)) {
            $ignore_categories_sql = "AND term_id NOT IN ($ignore_categories)";
        }

        $sql = "SELECT name, term_id
                FROM {$table_prefix}terms
                WHERE term_id IN (
                  SELECT term_id
                  FROM {$table_prefix}term_taxonomy
                  WHERE taxonomy = 'category'
                  $ignore_categories_sql
                )
                ORDER BY RAND()
                LIMIT 1";

        $result_kat = $wpdb->get_results($sql, ARRAY_A);

        if ($result_kat && count($result_kat) > 0) {
            foreach ($result_kat as $row) {
                $article = $this->generate_article($row['name'], $api_openai, $openai_org, $target_audience, $website_focus, $article_length, $image_generation);
                if ($article) {
                    $this->save_article($article, $row['term_id'], $api_openai, $openai_org, $image_generation, $dalle_model, $dalle_resolution, $post_status);
                } else {
                    error_log("Nepodařilo se vygenerovat článek pro kategorii: " . $row['name']);
                }
            }
        } else {
            error_log("Ve WordPressu nejsou žádné kategorie.");
        }
    }

    public function generate_custom_article() {
        if (isset($_POST['custom_topic'])) {
            $topic = sanitize_text_field($_POST['custom_topic']);
            $options = get_option('article_gen_options');
            $api_openai = $options['openai_api_key'];
            $openai_org = $options['openai_org'];
            $target_audience = isset($options['target_audience']) ? $options['target_audience'] : 'čtenáře';
            $website_focus = isset($options['website_focus']) ? $options['website_focus'] : 'zaměření webu';
            $article_length = isset($options['article_length']) ? $options['article_length'] : 3000;
            $image_generation = isset($options['image_generation']) && $options['image_generation'] === 'yes';
            $dalle_model = isset($options['dalle_model']) ? $options['dalle_model'] : 'dalle-2';
            $dalle_resolution = isset($options['dalle_resolution']) ? $options['dalle_resolution'] : '1024x1024';
            $post_status = isset($options['post_status']) ? $options['post_status'] : 'publish';

            $article = $this->generate_article($topic, $api_openai, $openai_org, $target_audience, $website_focus, $article_length, $image_generation);
            if ($article) {
                $this->save_article($article, 1, $api_openai, $openai_org, $image_generation, $dalle_model, $dalle_resolution, $post_status); // Použijeme ID 1 jako výchozí kategorii
            } else {
                error_log("Nepodařilo se vygenerovat článek na téma: " . $topic);
            }
        }
        wp_redirect(admin_url('options-general.php?page=article-generator'));
        exit;
    }

    private function generate_article($category, $api_key, $organization, $target_audience, $website_focus, $article_length, $image_generation) {
        $url = 'https://api.openai.com/v1/chat/completions';
        $headers = array(
            "Authorization: Bearer {$api_key}",
            "OpenAI-Organization: {$organization}",
            "Content-Type: application/json"
        );

        $content_prompt = "Napiš delší (šest až devět odstavců) unikátní článek jako zkušený novinář s nadpisem na libovolné téma z kategorie " . $category . ". Nadpis my měl být jedna věta, žádné dvojtečky. Text článku bude v češtině. Cílovou čtenářskou skupinou jsou " . $target_audience . ". Zaměření webu je " . $website_focus . ". Nadpis dej do tagu <h1></h1>. Článek začni perexem, kde shrneš téma. V článku napiš nějaký zajímavý a překvapivý fakt. Do článku dej dva až tři mezititulky v HTML tagu <h2></h2>. Neopakuj slova, nepoužívej seznamy a odrážky, trpný rod a poslední odstaven nepiš ve stylu Závěrem....";

        if ($image_generation) {
            $content_prompt .= " Na konec článku přidej prompt pro obrázek: 'Vygeneruj skutečně fotorealistický obrázek na téma: [téma článku].'";
        }

        $messages = array(array("role" => "user", "content" => $content_prompt));

        $data = array(
            "model" => "gpt-4o-mini",
            "messages" => $messages,
            "max_tokens" => $article_length
        );

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('OpenAI API Error: ' . curl_error($curl));
            return false;
        } else {
            $response_data = json_decode($result, true);
            if (!isset($response_data['choices'][0]['message']['content'])) {
                error_log('OpenAI API Unexpected Response: ' . print_r($response_data, true));
                return false;
            }
            $content = $response_data['choices'][0]['message']['content'];

            $title = '';
            $body = '';
            $tags = array();
            $image_prompt = '';

            if (preg_match('/<h1>(.*?)<\/h1>/', $content, $matches)) {
                $title = $matches[1];
                $content = str_replace($matches[0], '', $content);
            }

            if ($image_generation && preg_match('/(.*?)(Vygeneruj skutečně fotorealistický obrázek na téma: .*?\.)/s', $content, $matches)) {
                $body = trim($matches[1]);
                $image_prompt = trim($matches[2]);
            } else {
                $body = $content;
            }

            if (preg_match('/\[tags\](.*?)\[\/tags\]/', $content, $matches)) {
                $tags = array_map('trim', explode(',', $matches[1]));
            }

            curl_close($curl);
            return array(
                'title' => $title,
                'body' => $body,
                'tags' => $tags,
                'image_prompt' => $image_prompt
            );
        }
    }

    private function save_article($article, $category_id, $api_key, $organization, $image_generation, $dalle_model, $dalle_resolution, $post_status) {
        $title = $article['title'];
        $body = $article['body'];
        $tags = $article['tags'];
        $image_prompt = $article['image_prompt'];

        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $body,
            'post_status'   => $post_status,
            'post_author'   => 1,
        );

        $post_id = wp_insert_post($post_data);

        if ($post_id != 0) {
            error_log("Článek '".$title."' byl úspěšně vložen s ID: " . $post_id);

            wp_set_object_terms($post_id, intval($category_id), 'category', false);
            wp_set_post_tags($post_id, $tags, false);

            if ($image_generation) {
                $image_url = $this->generate_image($image_prompt, $api_key, $organization, $dalle_model, $dalle_resolution);

                if ($image_url) {
                    $image_data = $this->download_image($image_url);

                    if ($image_data) {
                        $upload_dir = wp_upload_dir();
                        $upload_path = $upload_dir['path'] . '/openai/' . $post_id;
                        $upload_url = $upload_dir['url'] . '/openai/' . $post_id;

                        if (!file_exists($upload_path)) {
                            mkdir($upload_path, 0777, true);
                        }

                        $image_file = $upload_path . '/image.jpg';
                        $image_url = $upload_url . '/image.jpg';

                        file_put_contents($image_file, $image_data);

                        $file = array(
                            'name'     => basename($image_file),
                            'tmp_name' => $image_file
                        );

                        $file_id = media_handle_sideload($file, $post_id);

                        if (is_wp_error($file_id)) {
                            error_log("Chyba při nahrávání souboru: " . $file_id->get_error_message());
                        } else {
                            set_post_thumbnail($post_id, $file_id);
                            error_log("Obrázek úspěšně nahrán: " . $image_prompt);
                        }
                    } else {
                        error_log("Nepodařilo se stáhnout obrázek.");
                    }
                } else {
                    error_log("Nepodařilo se vygenerovat obrázek.");
                }
            }
        } else {
            error_log("Při vytváření příspěvku došlo k chybě.");
        }
    }

    private function generate_image($prompt, $api_key, $organization, $dalle_model, $dalle_resolution) {
        $url = 'https://api.openai.com/v1/images/generations';
        $headers = array(
            "Authorization: Bearer {$api_key}",
            "OpenAI-Organization: {$organization}",
            "Content-Type: application/json"
        );

        $data = array(
            "model" => $dalle_model,
            "prompt" => $prompt,
            "size" => $dalle_resolution,
            "quality" => "standard",
            "n" => 1
        );

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 120);

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            error_log('OpenAI Image API Error: ' . curl_error($curl));
            return false;
        } else {
            $response_data = json_decode($result, true);
            if (isset($response_data['data'][0]['url'])) {
                return $response_data['data'][0]['url'];
            } else {
                error_log("Chyba při generování obrázku: " . print_r($response_data, true));
                return false;
            }
        }
        curl_close($curl);
    }

    private function download_image($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($data === false) {
            error_log('cURL error: ' . $error);
            return false;
        }

        return $data;
    }
}

if(is_admin()) {
    $article_gen_plugin = new ArticleGeneratorPlugin();
    add_action('admin_post_generate_articles', array($article_gen_plugin, 'generate_posts'));
    add_action('admin_post_generate_custom_article', array($article_gen_plugin, 'generate_custom_article'));
}

// spuštění generování článků pomocí WP cronu
if (!wp_next_scheduled('article_gen_hook')) {
    wp_schedule_event(time(), 'hourly', 'article_gen_hook');
}
add_action('article_gen_hook', 'generate_posts');

function generate_posts() {
    $article_gen_plugin = new ArticleGeneratorPlugin();
    $article_gen_plugin->generate_posts();
}
