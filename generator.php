<?php
/*
 * Plugin Name: Generátor AI článků a obrázků
 * Plugin URI: https://www.kubicek.ai/wp-ai-generator/
 * Description: Tento plugin generuje články a obrázky pomocí OpenAI GPT-4o-mini a DALL-E API. Plugin umožňuje automatické nebo manuální generování článků na základě specifikovaných kategorií a témat.
 * Version: 1.1.1
 * Author: Webklient.cz & Kubicek.ai
 * Author URI: https://www.webklient.cz
 * Text Domain: webklient_ai_generator-main
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Pokud tento soubor není volán přímo z WordPress, ukonči.
}

register_activation_hook(__FILE__, 'aktivovat_generator_clanku');

function aktivovat_generator_clanku() {
    // Výchozí nastavení
    $vychozi_nastaveni = array(
        'cron_schedule' => 'daily',
        'article_length' => 2000,
        'language_selection' => 'cs',
        'post_status' => 'draft'
    );
    
    add_option('article_gen_options', $vychozi_nastaveni);
    
    // Naplánovat první CRON úlohu
    if (!wp_next_scheduled('article_gen_hook')) {
        wp_schedule_event(time(), 'daily', 'article_gen_hook');
    }
}

function WebklientArticleGenerator_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'options-general.php?page=article-generator' ) . '">' . __( 'Settings', 'webklient_ai_generator-main' ) . '</a>';
	array_unshift( $links, $settings_link );

	return $links;
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'WebklientArticleGenerator_add_settings_link' );
add_action( 'plugins_loaded', 'webklient_ai_generator_load_textdomain' );
function webklient_ai_generator_load_textdomain() {
	$domain = 'webklient_ai_generator-main';
	$locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
	load_textdomain( $domain, WP_LANG_DIR . '/plugins/' . $domain . '-' . $locale . '.mo' );
	load_plugin_textdomain(
		$domain,
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/lang'
	);
}

class ArticleGeneratorPlugin {
	public function __construct() {
		// Nastavení paměťového limitu a doby běhu
		$current_memory_limit       = ini_get( 'memory_limit' );
		$desired_memory_limit       = '256M';
		$current_memory_limit_bytes = $this->convert_hr_to_bytes( $current_memory_limit );
		$desired_memory_limit_bytes = $this->convert_hr_to_bytes( $desired_memory_limit );
		if ( $current_memory_limit_bytes < $desired_memory_limit_bytes ) {
			ini_set( 'memory_limit', $desired_memory_limit );
		}

		$current_execution_time = ini_get( 'max_execution_time' );
		$desired_execution_time = 300; // v sekundách
		if ( $current_execution_time < $desired_execution_time ) {
			set_time_limit( $desired_execution_time );
			ini_set( 'max_execution_time', $desired_execution_time );
		}
		
		$this->options = get_option('article_gen_options', array()); // Tato řádka chybí
		$cron_schedule = isset($this->options['cron_schedule']) ? $this->options['cron_schedule'] : 'daily';
	    if (!wp_next_scheduled('article_gen_hook')) {
    	    wp_schedule_event(time(), $cron_schedule, 'article_gen_hook');
    	}

		// Nastavení akcí WordPressu
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		//add_action( 'wp', array( $this, 'schedule_cron_job' ) );
		add_action( 'article_gen_hook', array( $this, 'generate_posts' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_toolbar_items' ), 100 );
		add_action( 'admin_post_generate_articles', array( $this, 'generate_posts' ) );
		add_action( 'admin_post_generate_custom_article', array( $this, 'generate_custom_article' ) );
		add_action( 'admin_post_generate_article', array( $this, 'handle_generate_article' ) );
		
       add_action('update_option_article_gen_options', array($this, 'update_cron_schedule'));
    }

    public function update_cron_schedule() {
        // Získání aktuálních možností
        $this->options = get_option('article_gen_options');

        // Získání plánování úloh z aktuálních možností
        $cron_schedule = isset($this->options['cron_schedule']) ? $this->options['cron_schedule'] : 'daily';

        // Zrušení předchozího naplánovaného úkolu
        $timestamp = wp_next_scheduled('article_gen_hook');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'article_gen_hook');
        }

        // Naplánování nového úkolu
        if (!wp_next_scheduled('article_gen_hook')) {
            wp_schedule_event(time(), $cron_schedule, 'article_gen_hook');
        }
    }

	private function convert_hr_to_bytes( $value ) {
		$value     = trim( $value );
		$last      = strtolower( $value[ strlen( $value ) - 1 ] );
		$num_value = substr( $value, 0, - 1 );

		if ( ! is_numeric( $num_value ) ) {
			error_log( "Nenumerická hodnota při konverzi na byty: " . $value );

			return 0;
		}

		$num_value = floatval( $num_value );
		switch ( $last ) {
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
			__( 'Article Generator', 'webklient_ai_generator-main' ),
			__( 'Article Generator', 'webklient_ai_generator-main' ),
			'manage_options',
			'article-generator',
			array( $this, 'create_admin_page' )
		);
	}

	public function create_admin_page() {
		$this->options = get_option( 'article_gen_options' );
		?>
        <div class="wrap">
            <h1><?php _e( 'Article Generator', 'webklient_ai_generator-main' ); ?></h1>
            <form method="post" action="options.php">
				<?php settings_fields( 'article_gen_option_group' ); ?>
				<?php do_settings_sections( 'article-generator' ); ?>
				<?php submit_button(); ?>
            </form>
        </div>
        <div class="wrap">
            <h2><?php _e( 'Manual Article Generation', 'webklient_ai_generator-main' ); ?></h2>
            <form method="post" action="<?php echo admin_url( 'admin-post.php?action=generate_article' ); ?>">
                <input type="hidden" name="action" value="generate_articles">
				<?php submit_button( __( 'Generate Articles', 'webklient_ai_generator-main' ) ); ?>
            </form>
        </div>
        <div class="wrap">
            <h2><?php _e( 'Generate Article on Specific Topic', 'webklient_ai_generator-main' ); ?></h2>
            <form method="post" action="<?php echo admin_url( 'admin-post.php?action=generate_article' ); ?>">
                <input type="hidden" name="action" value="generate_custom_article">
                <p>
                    <label for="custom_topic"><?php _e( 'Article Topic:', 'webklient_ai_generator-main' ); ?></label>
                    <input type="text" id="custom_topic" name="custom_topic" required>
                </p>
				<?php submit_button( __( 'Generate Article on Topic', 'webklient_ai_generator-main' ) ); ?>
            </form>

        </div>
        <div class="wrap">
            <p><?php echo __( "For support mail: ", 'webklient_ai_generator-main' ) . "podpora@webklient.cz"; ?></p>
        </div>
		<?php
	}

	public function page_init() {
		register_setting( 'article_gen_option_group', 'article_gen_options', array( $this, 'sanitize' ) );

		add_settings_section( 'setting_section_id', __( 'API Settings', 'webklient_ai_generator-main' ), array(
			$this,
			'print_section_info'
		), 'article-generator' );
		
		

		add_settings_field( 'openai_api_key', __( 'OpenAI API Key', 'webklient_ai_generator-main' ), array(
			$this,
			'openai_api_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'openai_org', __( 'OpenAI Organization', 'webklient_ai_generator-main' ), array(
			$this,
			'openai_org_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'ignore_categories', __( 'Ignore Categories (IDs separated by commas)', 'webklient_ai_generator-main' ), array(
			$this,
			'ignore_categories_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'target_audience', __( 'Target Audience', 'webklient_ai_generator-main' ), array(
			$this,
			'target_audience_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'website_focus', __( 'Website Focus', 'webklient_ai_generator-main' ), array(
			$this,
			'website_focus_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'image_generation', __( 'Image Generation', 'webklient_ai_generator-main' ), array(
			$this,
			'image_generation_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'dalle_model', __( 'DALL-E Model', 'webklient_ai_generator-main' ), array(
			$this,
			'dalle_model_callback'
		), 'article-generator', 'setting_section_id' );
		
		add_settings_field( 
    'image_style', 
    __( 'Image Style', 'webklient_ai_generator-main' ), 
    array( $this, 'image_style_callback' ), 
    'article-generator', 
    'setting_section_id' 
);

		
		add_settings_field( 'dalle_resolution', __( 'DALL-E Resolution', 'webklient_ai_generator-main' ), array(
			$this,
			'dalle_resolution_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'article_length', __( 'Article Length in Tokens', 'webklient_ai_generator-main' ), array(
			$this,
			'article_length_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'cron_schedule', __( 'Cron Schedule', 'webklient_ai_generator-main' ), array(
			$this,
			'cron_schedule_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'post_status', __( 'Post Status', 'webklient_ai_generator-main' ), array(
			$this,
			'post_status_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'language_selection', __( 'Language Selection', 'webklient_ai_generator-main' ), array(
			$this,
			'language_selection_callback'
		), 'article-generator', 'setting_section_id' );
		add_settings_field( 'generation_role', __( 'Generation Role', 'webklient_ai_generator-main' ), array(
			$this,
			'generation_role_callback'
		), 'article-generator', 'setting_section_id' );
	}

	public function language_selection_callback() {
		$languages         = [
			'cs' => __( 'Czech', 'webklient_ai_generator-main' ),
			'en' => __( 'English', 'webklient_ai_generator-main' ),
		];
		$selected_language = isset( $this->options['language_selection'] ) ? $this->options['language_selection'] : 'cs';
		echo '<select id="language_selection" name="article_gen_options[language_selection]">';
		foreach ( $languages as $lang_code => $lang_name ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $lang_code ),
				selected( $selected_language, $lang_code, false ),
				esc_html( $lang_name )
			);
		}
		echo '</select>';
	}

	
	public function generation_role_callback() {
    $roles = [
        'author' => __('General Author', 'webklient_ai_generator-main'),
        'experienced_reporter' => __('Experienced Reporter', 'webklient_ai_generator-main'),
        'expert_analyst' => __('Expert Analyst', 'webklient_ai_generator-main'),
        'storyteller' => __('Creative Storyteller', 'webklient_ai_generator-main'),
        'tech_writer' => __('Technical Writer', 'webklient_ai_generator-main'),
        'seo_specialist' => __('SEO Content Specialist', 'webklient_ai_generator-main'),
        'industry_expert' => __('Industry Expert', 'webklient_ai_generator-main')
		];
		$selected_role = isset( $this->options['generation_role'] ) ? $this->options['generation_role'] : 'author';
		echo '<select id="generation_role" name="article_gen_options[generation_role]">';
		foreach ( $roles as $role_value => $role_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $role_value ),
				selected( $selected_role, $role_value, false ),
				esc_html( $role_label )
			);
		}
		echo '</select>';
	}

	public function sanitize( $input ) {
		$new_input = array();
		if ( isset( $input['language_selection'] ) ) {
			$new_input['language_selection'] = sanitize_text_field( $input['language_selection'] );
		}
		if ( isset( $input['generation_role'] ) ) {
			$new_input['generation_role'] = sanitize_text_field( $input['generation_role'] );
		}
		if ( isset( $input['openai_api_key'] ) ) {
			$new_input['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
		}
		if ( isset( $input['openai_org'] ) ) {
			$new_input['openai_org'] = sanitize_text_field( $input['openai_org'] );
		}
		if ( isset( $input['ignore_categories'] ) ) {
			$new_input['ignore_categories'] = sanitize_text_field( $input['ignore_categories'] );
		}
		if ( isset( $input['target_audience'] ) ) {
			$new_input['target_audience'] = sanitize_text_field( $input['target_audience'] );
		}
		if ( isset( $input['website_focus'] ) ) {
			$new_input['website_focus'] = sanitize_text_field( $input['website_focus'] );
		}
		if ( isset( $input['image_generation'] ) ) {
			$new_input['image_generation'] = sanitize_text_field( $input['image_generation'] );
			
			if ( isset( $input['image_style'] ) ) {
    $new_input['image_style'] = sanitize_textarea_field( $input['image_style'] );
}

		}
		if ( isset( $input['dalle_model'] ) ) {
			$new_input['dalle_model'] = sanitize_text_field( $input['dalle_model'] );
		}
		if ( isset( $input['dalle_resolution'] ) ) {
			$new_input['dalle_resolution'] = sanitize_text_field( $input['dalle_resolution'] );
		}
		if ( isset( $input['article_length'] ) ) {
			$new_input['article_length'] = absint( $input['article_length'] );
		}
		if ( isset( $input['cron_schedule'] ) ) {
			$new_input['cron_schedule'] = sanitize_text_field( $input['cron_schedule'] );
		}
		if ( isset( $input['post_status'] ) ) {
			$new_input['post_status'] = sanitize_text_field( $input['post_status'] );
		}

		return $new_input;
	}

	public function print_section_info() {
		_e( 'Enter your API keys, OpenAI organization ID, target audience, and website focus:', 'webklient_ai_generator-main' );
	}

	public function openai_api_callback() {
		printf(
			'<input type="text" id="openai_api_key" name="article_gen_options[openai_api_key]" value="%s" />',
			isset( $this->options['openai_api_key'] ) ? esc_attr( $this->options['openai_api_key'] ) : ''
		);
	}

	public function openai_org_callback() {
		printf(
			'<input type="text" id="openai_org" name="article_gen_options[openai_org]" value="%s" />',
			isset( $this->options['openai_org'] ) ? esc_attr( $this->options['openai_org'] ) : ''
		);
	}

	public function ignore_categories_callback() {
		printf(
			'<input type="text" id="ignore_categories" name="article_gen_options[ignore_categories]" value="%s" />',
			isset( $this->options['ignore_categories'] ) ? esc_attr( $this->options['ignore_categories'] ) : ''
		);
	}

	public function target_audience_callback() {
		printf(
			'<input type="text" id="target_audience" name="article_gen_options[target_audience]" value="%s" />',
			isset( $this->options['target_audience'] ) ? esc_attr( $this->options['target_audience'] ) : ''
		);
	}

	public function website_focus_callback() {
		$value = isset( $this->options['website_focus'] ) ? esc_textarea( $this->options['website_focus'] ) : '';
		echo '<textarea id="website_focus" name="article_gen_options[website_focus]" rows="5" cols="50">' . $value . '</textarea>';
	}

	public function image_generation_callback() {
		$checked = isset( $this->options['image_generation'] ) && $this->options['image_generation'] === 'yes' ? 'checked' : '';
		printf(
			'<input type="checkbox" id="image_generation" name="article_gen_options[image_generation]" value="yes" %s /> %s',
			$checked,
			__( 'Enable Image Generation', 'webklient_ai_generator-main' )
		);
	}

	public function dalle_model_callback() {
		$model = isset( $this->options['dalle_model'] ) ? $this->options['dalle_model'] : 'dalle-2';
		?>
        <select id="dalle_model" name="article_gen_options[dalle_model]">
            <option value="dalle-3" <?php selected( $model, 'dalle-3' ); ?>><?php _e( 'DALL-E 3', 'webklient_ai_generator-main' ); ?></option>
            <option value="dalle-2" <?php selected( $model, 'dalle-2' ); ?>><?php _e( 'DALL-E 2', 'webklient_ai_generator-main' ); ?></option>
        </select>
		<?php
	}

	public function dalle_resolution_callback() {
		$resolution = isset( $this->options['dalle_resolution'] ) ? $this->options['dalle_resolution'] : '1024x1024';
		?>
        <select id="dalle_resolution" name="article_gen_options[dalle_resolution]">
            <option value="1024x1024" <?php selected( $resolution, '1024x1024' ); ?>>1024x1024</option>
            <option value="1024x1792" <?php selected( $resolution, '1024x1792' ); ?>>1024x1792</option>
            <option value="1792x1024" <?php selected( $resolution, '1792x1024' ); ?>>1792x1024</option>
            <option value="512x512" <?php selected( $resolution, '512x512' ); ?>>512x512</option>
            <option value="256x256" <?php selected( $resolution, '256x256' ); ?>>256x256</option>
        </select>
		<?php
	}

	public function article_length_callback() {
		$length = isset( $this->options['article_length'] ) ? intval( $this->options['article_length'] ) : 3000;
		printf(
			'<input type="number" id="article_length" name="article_gen_options[article_length]" value="%d" min="500" max="4000" />',
			$length
		);
	}

	public function cron_schedule_callback() {
    $schedule = isset( $this->options['cron_schedule'] ) ? $this->options['cron_schedule'] : 'daily';
    ?>
    <select id="cron_schedule" name="article_gen_options[cron_schedule]">
        <option value="daily" <?php selected( $schedule, 'daily' ); ?>><?php _e( 'Daily', 'webklient_ai_generator-main' ); ?></option>
        <option value="hourly" <?php selected( $schedule, 'hourly' ); ?>><?php _e( 'Hourly', 'webklient_ai_generator-main' ); ?></option>
    </select>
    <?php
}
	
	public function image_style_callback() {
    $image_style = isset( $this->options['image_style'] ) ? esc_textarea( $this->options['image_style'] ) : '';
    echo '<textarea id="image_style" name="article_gen_options[image_style]" rows="5" cols="50" placeholder="' . esc_attr__( 'Define the image style...', 'webklient_ai_generator-main' ) . '">' . $image_style . '</textarea>';
}

	

	public function post_status_callback() {
		$status = isset( $this->options['post_status'] ) ? $this->options['post_status'] : 'publish';
		?>
        <select id="post_status" name="article_gen_options[post_status]">
            <option value="publish" <?php selected( $status, 'publish' ); ?>><?php _e( 'Published', 'webklient_ai_generator-main' ); ?></option>
            <option value="draft" <?php selected( $status, 'draft' ); ?>><?php _e( 'Draft', 'webklient_ai_generator-main' ); ?></option>
        </select>
		<?php
	}

	public function schedule_cron_job() {
		$cron_schedule = isset( $this->options['cron_schedule'] ) ? $this->options['cron_schedule'] : 'daily';
		if ( ! wp_next_scheduled( 'article_gen_hook' ) ) {
			wp_schedule_event( time(), $cron_schedule, 'article_gen_hook' );
		}
	}

	public function add_toolbar_items( $admin_bar ) {
		$admin_bar->add_menu( array(
			'id'    => 'generate-article',
			'title' => __( 'Generate Article', 'webklient_ai_generator-main' ),
			'href'  => admin_url( 'admin-post.php?action=generate_article' ),
			'meta'  => array(
				'title' => __( 'Generate a New Article', 'webklient_ai_generator-main' ),
			),
		) );
	}

public function handle_generate_article() {
    // Kontrola oprávnění
    if (!current_user_can('manage_options')) {
        wp_die(__('Nedostatečná oprávnění'));
    }
    
    // Generování článku
    $this->generate_posts();
    
    // Přidání admin notice pro zpětnou vazbu
    add_settings_error(
        'article_generator',
        'article_generated',
        __('Článek byl úspěšně vygenerován.', 'webklient_ai_generator-main'),
        'success'
    );
    
    // Správné přesměrování zpět na stránku nastavení s parametrem pro zobrazení zprávy
    wp_safe_redirect(add_query_arg(
        array(
            'page' => 'article-generator',
            'settings-updated' => 'true'
        ),
        admin_url('options-general.php')
    ));
    exit;
}

	public function generate_posts() {
		global $wpdb;
		$table_prefix      = $wpdb->prefix;
		$options           = get_option( 'article_gen_options' );
		$api_openai        = $options['openai_api_key'];
		$openai_org        = $options['openai_org'];
		$ignore_categories = isset( $options['ignore_categories'] ) ? $options['ignore_categories'] : '';
		$target_audience   = isset( $options['target_audience'] ) ? $options['target_audience'] : __( 'readers', 'webklient_ai_generator-main' );
		$website_focus     = isset( $options['website_focus'] ) ? $options['website_focus'] : __( 'website focus', 'webklient_ai_generator-main' );
		$article_length    = isset( $options['article_length'] ) ? $options['article_length'] : 3000;
		$image_generation  = isset( $options['image_generation'] ) && $options['image_generation'] === 'yes';
		$dalle_model       = isset( $options['dalle_model'] ) ? $options['dalle_model'] : 'dalle-2';
		$dalle_resolution  = isset( $options['dalle_resolution'] ) ? $options['dalle_resolution'] : '1024x1024';
		$post_status       = isset( $options['post_status'] ) ? $options['post_status'] : 'publish';

		$ignore_categories_sql = '';
		if ( ! empty( $ignore_categories ) ) {
			$ignore_categories_sql = "AND term_id NOT IN ($ignore_categories)";
		}

		$sql        = "SELECT name, term_id FROM {$table_prefix}terms WHERE term_id IN ( SELECT term_id FROM {$table_prefix}term_taxonomy WHERE taxonomy = 'category' $ignore_categories_sql ) ORDER BY RAND() LIMIT 1";
		$result_kat = $wpdb->get_results( $sql, ARRAY_A );
		if ( $result_kat && count( $result_kat ) > 0 ) {
			foreach ( $result_kat as $row ) {
				$article = $this->generate_article( $row['name'], $api_openai, $openai_org, $target_audience, $website_focus, $article_length, $image_generation );
				if ( $article ) {
					$this->save_article( $article, $row['term_id'], $api_openai, $openai_org, $image_generation, $dalle_model, $dalle_resolution, $post_status );
				} else {
					error_log( __( 'Failed to generate article for category:', 'webklient_ai_generator-main' ) . ' ' . $row['name'] );
				}
			}
		} else {
			error_log( __( 'There are no categories in WordPress.', 'webklient_ai_generator-main' ) );
		}
	}

	public function generate_custom_article() {
		if ( isset( $_POST['custom_topic'] ) ) {
			$topic            = sanitize_text_field( $_POST['custom_topic'] );
			$options          = get_option( 'article_gen_options' );
			$api_openai       = $options['openai_api_key'];
			$openai_org       = $options['openai_org'];
			$target_audience  = isset( $options['target_audience'] ) ? $options['target_audience'] : 'čtenáře';
			$website_focus    = isset( $options['website_focus'] ) ? $options['website_focus'] : 'zaměření webu';
			$article_length   = isset( $options['article_length'] ) ? $options['article_length'] : 3000;
			$image_generation = isset( $options['image_generation'] ) && $options['image_generation'] === 'yes';
			$dalle_model      = isset( $options['dalle_model'] ) ? $options['dalle_model'] : 'dalle-2';
			$dalle_resolution = isset( $options['dalle_resolution'] ) ? $options['dalle_resolution'] : '1024x1024';
			$post_status      = isset( $options['post_status'] ) ? $options['post_status'] : 'publish';

			$article = $this->generate_article( $topic, $api_openai, $openai_org, $target_audience, $website_focus, $article_length, $image_generation );
			if ( $article ) {
				$this->save_article( $article, 1, $api_openai, $openai_org, $image_generation, $dalle_model, $dalle_resolution, $post_status );
				// Použijeme ID 1 jako výchozí kategorii
			} else {
				error_log( "Failed to generate an article on: " . $topic );
			}
		}
		wp_redirect( admin_url( 'options-general.php?page=article-generator' ) );
		exit;
	}

	/* Method declarations for generate_article, save_article, generate_image, and download_image
	are unchanged except for usage of __() for strings.
	They can be included here similarly as required. */

	private function generate_article($category, $api_key, $organization, $target_audience, $website_focus, $article_length, $image_generation) {
    $options = get_option('article_gen_options');
  
   $generation_role = isset($options['generation_role']) ? $options['generation_role'] : 'author';
    
    // Mapování rolí
    $role_mapping = [
        'author' => ['cs' => 'obecný autor', 'en' => 'general author'],
        'experienced_reporter' => ['cs' => 'zkušený novinář', 'en' => 'experienced journalist'],
        'expert_analyst' => ['cs' => 'odborný analytik', 'en' => 'expert analyst'],
        'storyteller' => ['cs' => 'kreativní vypravěč', 'en' => 'creative storyteller'],
        'tech_writer' => ['cs' => 'technický autor', 'en' => 'technical writer'],
        'seo_specialist' => ['cs' => 'SEO specialista', 'en' => 'SEO content specialist'],
        'industry_expert' => ['cs' => 'expert v oboru', 'en' => 'industry expert']
    ];
    
    $language = isset($options['language_selection']) ? $options['language_selection'] : 'cs';
$role_text = $role_mapping[$generation_role][$language] ?? $role_mapping['author'][$language];
		
    $language = isset($options['language_selection']) ? $options['language_selection'] : 'cs';
    
    $url = 'https://api.openai.com/v1/chat/completions';
    $headers = array(
        "Authorization: Bearer {$api_key}",
        "OpenAI-Organization: {$organization}",
        "Content-Type: application/json"
    );

    // Nastavení promptu podle jazyka
    if ($language === 'cs') {
        $content_prompt = 'Napiš ' . $article_length . ' tokenů dlouhý unikátní článek jako 
		' . $role_text . ' s titulkem na libovolné téma vhodné do kategorie ' . $category . '. ' .
            'Titulek by měl být jedna věta, bez dvojteček, nebude začínat Jak.. ' .
            'Text článku musí být česky. ' .
            'Cílová skupina je ' . $target_audience . '. ' .
            'Zaměření webové stránky je ' . $website_focus . '. ' .
            'Vložte titulek do značky <h1>. ' .
            'Článek začněte titulkem shrnujícím téma. ' .
            'Zařaďte do článku zajímavou a překvapivou skutečnost. ' .
            'Přidejte dva až tři podnadpisy ve značkách <h2> jazyka HTML. ' .
            'Vyhněte se opakování slov, pasivnímu hlasu a nepište poslední odstavec ve stylu \'Na závěr....\'.';
    } else {
        $content_prompt = 'Write a unique  ' . $article_length . ' token article as
		' . $role_text . ' with a headline fit to category ' . $category . '. ' .
            'The headline should be a single sentence without colons. ' .
            'Avoid starting title with How to ' .
            'The article must be in English. ' .
            'Target audience is ' . $target_audience . '. ' .
            'Website focus is ' . $website_focus . '. ' .
            'Put the title in <h1> tags. ' .
            'Start the article with a title summarizing the topic. ' .
            'Include an interesting and surprising fact. ' .
            'Add two to three subheadings in HTML <h2> tags. ' .
            'Avoid word repetition, passive voice, and don\'t write the last paragraph in a \'In conclusion...\' style.';
    }

    // Přidání promptu pro generování obrázku
    if ($image_generation) {
        if ($language === 'cs') {
            $content_prompt .= ' Na konci článku přidej prompt pro obrázek: \'Vygeneruj obrázek na téma: [název článku].\'';
        } else {
            $content_prompt .= ' At the end of the article, add a prompt for an image: \'Generate an image on the topic: [article title].\'';
        }
    }

    $messages = array(
        array("role" => "user", "content" => $content_prompt)
    );
    
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
        error_log(__('OpenAI API Error:', 'webklient_ai_generator-main') . ' ' . curl_error($curl));
        return false;
    } else {
        $response_data = json_decode($result, true);

        if (!isset($response_data['choices'][0]['message']['content'])) {
            error_log(__('OpenAI API Unexpected Response:', 'webklient_ai_generator-main') . ' ' . print_r($response_data, true));
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

    if ($image_generation) {
    $search_pattern = $language === 'cs' 
        ? '/(.*?)(Vygeneruj obrázek na téma: .*?\.)/s'
        : '/(.*?)(Generate an image on the topic: .*?\.)/s';
    
    if (preg_match($search_pattern, $content, $matches)) {
        $body = trim($matches[1]);
        $base_image_prompt = trim($matches[2]);
    } else {
        $body = $content;
        // Nastav fallback prompt, pokud regex nic nenajde
        $base_image_prompt = $language === 'cs' 
            ? 'Vygeneruj obrázek na téma: ' . $category . '.'
            : 'Generate an image on the topic: ' . $category . '.';
    }
    
    // Načti styl obrázku z administrace, pokud je nastaven
    $image_style = isset($options['image_style']) && !empty($options['image_style']) 
        ? trim($options['image_style']) 
        : '';

    // Sestavení výsledného promptu
    $image_prompt = $base_image_prompt;
    if (!empty($image_style)) {
        $image_prompt .= ' ' . $image_style;
    }
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

	private function save_article( $article, $category_id, $api_key, $organization, $image_generation, $dalle_model, $dalle_resolution, $post_status ) {
		$title        = $article['title'];
		$body         = $article['body'];
		$tags         = $article['tags'];
		$image_prompt = $article['image_prompt'];

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $body,
			'post_status'  => $post_status,
			'post_author'  => 1,
		);

		$post_id = wp_insert_post( $post_data );

		if ( $post_id != 0 ) {
			error_log( __( 'Article', 'webklient_ai_generator-main' ) . ' \'' . $title . '\' ' . __( 'successfully inserted with ID:', 'webklient_ai_generator-main' ) . ' ' . $post_id );
			wp_set_object_terms( $post_id, intval( $category_id ), 'category', false );
			wp_set_post_tags( $post_id, $tags, false );

			if ( $image_generation ) {
				$image_url = $this->generate_image( $image_prompt, $api_key, $organization, $dalle_model, $dalle_resolution );
				if ( $image_url ) {
					$image_data = $this->download_image( $image_url );
					if ( $image_data ) {
						$upload_dir  = wp_upload_dir();
						$upload_path = $upload_dir['path'] . '/openai/' . $post_id;
						$upload_url  = $upload_dir['url'] . '/openai/' . $post_id;

						if ( ! file_exists( $upload_path ) ) {
							mkdir( $upload_path, 0777, true );
						}

						$image_file = $upload_path . '/image.jpg';
						$image_url  = $upload_url . '/image.jpg';
						file_put_contents( $image_file, $image_data );

						$file = array(
							'name'     => basename( $image_file ),
							'tmp_name' => $image_file
						);

						$file_id = media_handle_sideload( $file, $post_id );

						if ( is_wp_error( $file_id ) ) {
							error_log( __( 'Error uploading file:', 'webklient_ai_generator-main' ) . ' ' . $file_id->get_error_message() );
						} else {
							set_post_thumbnail( $post_id, $file_id );
							error_log( __( 'Image successfully uploaded:', 'webklient_ai_generator-main' ) . ' ' . $image_prompt );
						}
					} else {
						error_log( __( 'Failed to download image.', 'webklient_ai_generator-main' ) );
					}
				} else {
					error_log( __( 'Failed to generate image.', 'webklient_ai_generator-main' ) );
				}
			}
		} else {
			error_log( __( 'Error creating post.', 'webklient_ai_generator-main' ) );
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
        'prompt' => $prompt,
        'size' => $dalle_resolution,
        'n' => 1,
        'model' => 'dall-e-3',

        'quality' => 'standard'
    );

    try {
        $curl = curl_init($url);
        curl_setopt_array($curl, array(
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true
        ));

        $response = curl_exec($curl);
        
        if (curl_errno($curl)) {
            error_log(__('DALL-E API Error:', 'webklient_ai_generator-main') . ' ' . curl_error($curl));
            curl_close($curl);
            return false;
        }
        
        curl_close($curl);
        $result = json_decode($response, true);

        if (isset($result['data'][0]['url'])) {
            return $result['data'][0]['url'];
        }

        error_log(__('Invalid DALL-E API response:', 'webklient_ai_generator-main') . ' ' . print_r($result, true));
        return false;

    } catch (Exception $e) {
        error_log(__('Exception in image generation:', 'webklient_ai_generator-main') . ' ' . $e->getMessage());
        return false;
    }
}
	private function download_image( $url ) {
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		$data  = curl_exec( $ch );
		$error = curl_error( $ch );
		curl_close( $ch );

		if ( $data === false ) {
			error_log( __( 'cURL error:', 'webklient_ai_generator-main' ) . ' ' . $error );

			return false;
		}

		return $data;
	}
}

$article_gen_plugin = new ArticleGeneratorPlugin();

if ( is_admin() ) {
	add_action( 'admin_post_generate_articles', array( $article_gen_plugin, 'generate_posts' ) );
	add_action( 'admin_post_generate_custom_article', array( $article_gen_plugin, 'generate_custom_article' ) );
}



function generate_posts() {
	$article_gen_plugin = new ArticleGeneratorPlugin();
	$article_gen_plugin->generate_posts();
}

// Přidat novou funkci
register_deactivation_hook(__FILE__, 'deactivate_article_generator');

function deactivate_article_generator() {
    $timestamp = wp_next_scheduled('article_gen_hook');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'article_gen_hook');
    }
}