# Generátor AI článků a obrázků pro WordPress / AI Article and Image Generator for WordPress

## Popis / Description

**CZ:** Tento WordPress plugin generuje články a obrázky pomocí OpenAI GPT-4 a DALL-E API. Plugin umožňuje automatické nebo manuální generování článků na základě specifikovaných kategorií a témat.

**EN:** This WordPress plugin generates articles and images using OpenAI's GPT-4 and DALL-E APIs. The plugin allows for automatic or manual generation of articles based on specified categories and topics.

## Funkce / Features

**CZ:**
- **Generování článků:** Automatické generování článků pomocí GPT-4 s možností specifikace kategorie a tématu.
- **Generování obrázků:** Využití DALL-E API pro generování obrázků na základě textového promptu.
- **Podpora WP cronu:** Možnost automatického generování článků v pravidelných intervalech pomocí WP cronu.
- **REST API:** Podpora REST API pro externí volání generování obsahu.

**EN:**
- **Article Generation:** Automatically generate articles using GPT-4 with the option to specify category and topic.
- **Image Generation:** Utilize DALL-E API to generate images based on textual prompts.
- **WP Cron Support:** Automatically generate articles at regular intervals using WP cron.
- **REST API:** Support for REST API for external content generation calls.

## Instalace / Installation

**CZ:**
1. Stáhněte plugin z repozitáře nebo použijte git k naklonování repozitáře do složky `wp-content/plugins`:
   git clone https://github.com/mediatoring/webklient_ai_generator
2. Aktivujte plugin prostřednictvím WordPress administrace.
3. Přejděte do Nastavení > Generátor článků a zadejte své API klíče pro OpenAI.

**EN:**
1. Download the plugin from the repository or use git to clone the repository into the wp-content/plugins directory:
git clone https://github.com/mediatoring/webklient_ai_generator
2. Activate the plugin through the WordPress admin dashboard.
3. Go to Settings > Article Generator and enter your OpenAI API keys.

## Nastavení / Configuration
**CZ:**
Po aktivaci pluginu můžete přistoupit k nastavení API klíčů a dalších možností:

1. OpenAI API klíč: Klíč pro přístup k OpenAI GPT-4 API.
2. OpenAI Organizace: ID organizace pro OpenAI.
3. Ignorovat kategorie: ID kategorií, které mají být ignorovány, oddělené čárkami.
4. Cílová skupina čtenářů: Specifikace cílové skupiny pro generovaný obsah.
5. Zaměření webu: Zaměření vašeho webu, které bude použito při generování obsahu.

**EN:**
After activating the plugin, you can configure the API keys and other settings:

1. OpenAI API Key: The key for accessing OpenAI GPT-4 API.
2. OpenAI Organization: The organization ID for OpenAI.
3. Ignore Categories: IDs of categories to be ignored, separated by commas.
4. Target Audience: Specify the target audience for the generated content.
5. Website Focus: The focus of your website, which will be used when generating content.


## Použití / Usage ## 

## Manuální generování článků / Manual Article Generation

**CZ:**
Přejděte na Nastavení > Generátor článků.
V sekci Manuální generování článků klikněte na tlačítko „Generovat články“.

**EN:**
Navigate to Settings > Article Generator.
In the Manual Article Generation section, click the "Generate Articles" button.

## Automatické generování článků / Automatic Article Generation

**CZ:** Plugin je nastaven pro automatické generování článků pomocí WP cronu každou hodinu. Ujistěte se, že váš WordPress cron je správně nastaven.

**EN:** The plugin is set to automatically generate articles using WP cron every hour. Ensure your WordPress cron is properly set up.

## Generování článků na konkrétní téma / Generating Articles on a Specific Topic

**CZ:**
- Přejděte na Nastavení > Generátor článků.
- V sekci Generování článku na konkrétní téma zadejte téma a klikněte na „Generovat článek na téma“.

**EN:**
- Navigate to Settings > Article Generator.
- In the Generate Article on Specific Topic section, enter the topic and click "Generate Article on Topic."
   
