<?php
/**
 * tldrplugin for Tiny Tiny RSS
 *
 * Generates TL;DR summaries and automatically tags articles using the OpenAI API.
 * Offers extensive configuration for both summarization and tagging features,
 * including content length limits, API parameters, and text truncation options.
 */
class tldrplugin extends Plugin
{
    /** @var PluginHost $host Reference to the PluginHost object */
    private $host;
    
    /**
     * Returns information about the plugin.
     * @return array Plugin information
     */
    public function about()
    {
        return array(
            1.1, // Version
            "Generates TL;DR summaries and auto-tags articles using OpenAI, with advanced configuration.", // Description
            "your_github_username/tldr_plugin" // Author/URL (replace with actual if available)
        );
    }

    /**
     * Declares plugin capabilities.
     * @return array Associative array of flags
     */
    public function flags()
    {
        return array(
            "needs_curl" => true // Requires cURL for API calls
        );
    }

    /**
     * Saves plugin settings from the preferences screen.
     * Handles validation and persistence of all configurable options.
     * @return void
     */
    public function save()
    {
        $openai_api_key = trim($_POST["openai_api_key"] ?? "");
        $openai_base_url = trim($_POST["openai_base_url"] ?? "");
        $openai_model = trim($_POST["openai_model"] ?? "");
        $tldr_prompt = trim($_POST["tldr_prompt"] ?? "");
        $tldr_max_tokens = (int)($_POST["tldr_max_tokens"] ?? 150);
        // $tldr_max_prompt_length removed, replaced by tldr_fallback_max_chars
        $curl_timeout = (int)($_POST["curl_timeout"] ?? 60);
        $curl_connect_timeout = (int)($_POST["curl_connect_timeout"] ?? 30);
        $tldr_min_article_length = (int)($_POST["tldr_min_article_length"] ?? 200);

        // Auto Tag settings
        $autotag_enabled = isset($_POST["autotag_enabled"]) && $_POST["autotag_enabled"] === "on";
        $autotag_label_language = trim($_POST["autotag_label_language"] ?? "English");
        $autotag_openai_model = trim($_POST["autotag_openai_model"] ?? "gpt-3.5-turbo");
        $autotag_max_tags = (int)($_POST["autotag_max_tags"] ?? 5);
        $autotag_min_article_length = (int)($_POST["autotag_min_article_length"] ?? 50);

        // TLDR Truncation settings
        $tldr_fallback_max_chars = (int)($_POST["tldr_fallback_max_chars"] ?? 15000);
        $tldr_truncate_trigger_length = (int)($_POST["tldr_truncate_trigger_length"] ?? 1200);
        $tldr_truncate_keep_start = (int)($_POST["tldr_truncate_keep_start"] ?? 1000);
        $tldr_truncate_keep_end = (int)($_POST["tldr_truncate_keep_end"] ?? 200);

        // AutoTag Truncation settings
        $autotag_fallback_max_chars = (int)($_POST["autotag_fallback_max_chars"] ?? 10000);
        $autotag_truncate_trigger_length = (int)($_POST["autotag_truncate_trigger_length"] ?? 1000);
        $autotag_truncate_keep_start = (int)($_POST["autotag_truncate_keep_start"] ?? 800);
        $autotag_truncate_keep_end = (int)($_POST["autotag_truncate_keep_end"] ?? 200);

        // Validate API key format
        if (empty($openai_api_key)) {
            echo __("OpenAI API Key is required.");
            return;
        }
        if (!preg_match('/^sk-[a-zA-Z0-9\-_]+$/', $openai_api_key)) {
            echo __("Invalid OpenAI API Key format.");
            return;
        }
        
        // Validate base URL format if provided
        if (!empty($openai_base_url) && !filter_var($openai_base_url, FILTER_VALIDATE_URL)) {
            echo __("Invalid OpenAI Base URL format.");
            return;
        }
        
        $this->host->set($this, "openai_api_key", $openai_api_key);
        $this->host->set($this, "openai_base_url", empty($openai_base_url) ? "https://api.openai.com/v1" : $openai_base_url);
        $this->host->set($this, "openai_model", empty($openai_model) ? "gpt-3.5-turbo" : $openai_model);
        $this->host->set($this, "tldr_prompt", empty($tldr_prompt) ? "Please provide a concise TL;DR summary of the following article in 1-2 sentences. Focus on the main points and key takeaways." : $tldr_prompt);
        $this->host->set($this, "tldr_max_tokens", $tldr_max_tokens > 0 ? $tldr_max_tokens : 150);
        $this->host->set($this, "curl_timeout", $curl_timeout > 0 ? $curl_timeout : 60);
        $this->host->set($this, "curl_connect_timeout", $curl_connect_timeout > 0 ? $curl_connect_timeout : 30);
        $this->host->set($this, "tldr_min_article_length", $tldr_min_article_length >= 0 ? $tldr_min_article_length : 200);

        // Save Auto Tag settings
        $this->host->set($this, "autotag_enabled", $autotag_enabled);
        $this->host->set($this, "autotag_label_language", !empty($autotag_label_language) ? $autotag_label_language : "English");
        $this->host->set($this, "autotag_openai_model", !empty($autotag_openai_model) ? $autotag_openai_model : "gpt-3.5-turbo");
        $this->host->set($this, "autotag_max_tags", $autotag_max_tags > 0 ? $autotag_max_tags : 5);
        $this->host->set($this, "autotag_min_article_length", $autotag_min_article_length >= 0 ? $autotag_min_article_length : 50);

        // Save TLDR Truncation settings
        $this->host->set($this, "tldr_fallback_max_chars", $tldr_fallback_max_chars > 0 ? $tldr_fallback_max_chars : 15000);
        $this->host->set($this, "tldr_truncate_trigger_length", $tldr_truncate_trigger_length >= 0 ? $tldr_truncate_trigger_length : 1200);
        $this->host->set($this, "tldr_truncate_keep_start", $tldr_truncate_keep_start >= 0 ? $tldr_truncate_keep_start : 1000);
        $this->host->set($this, "tldr_truncate_keep_end", $tldr_truncate_keep_end >= 0 ? $tldr_truncate_keep_end : 200);

        // Save AutoTag Truncation settings
        $this->host->set($this, "autotag_fallback_max_chars", $autotag_fallback_max_chars > 0 ? $autotag_fallback_max_chars : 10000);
        $this->host->set($this, "autotag_truncate_trigger_length", $autotag_truncate_trigger_length >= 0 ? $autotag_truncate_trigger_length : 1000);
        $this->host->set($this, "autotag_truncate_keep_start", $autotag_truncate_keep_start >= 0 ? $autotag_truncate_keep_start : 800);
        $this->host->set($this, "autotag_truncate_keep_end", $autotag_truncate_keep_end >= 0 ? $autotag_truncate_keep_end : 200);

        echo __("TLDR Summarizer & Auto Tag settings saved.");
    }

    /**
     * Initializes the plugin, sets up hooks and handlers.
     * @param PluginHost $host The PluginHost object.
     * @return void
     */
    public function init($host)
    {
        $this->host = $host;
        
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            // Consider logging this or displaying an admin notice if possible
            return;
        }

        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
        $host->add_hook($host::HOOK_PREFS_TAB, $this);
        $host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
        $host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
        $host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
        
        // Note: HOOK_ARTICLE_FILTER_ACTION is deprecated.
        // If "tldr_summarize" was intended for a specific action, it might need a different approach.
        // For now, assuming it's related to the general article processing.
        // $host->add_filter_action($this, "tldr_summarize", __("TLDR Summarize"));
        // This line seems to be unused if hook_article_filter_action is not defined or used.
        // It was originally in the plugin, but its pair hook_article_filter_action was not.
        // If it's for a custom action, it needs the corresponding hook method.
        // For now, I'm commenting it out as its purpose is unclear without the counterpart.

        $host->add_handler("summarizeArticle", "*", $this); // AJAX handler for manual TLDR
        $host->add_handler("testApiConnection", "*", $this);  // AJAX handler for testing API
        
        _debug("tldrplugin: Plugin initialized successfully");
    }

    /**
     * Returns the JavaScript code for the plugin.
     * @return string JavaScript code.
     */
    public function get_js()
    {
        return file_get_contents(__DIR__ . "/tldr_plugin.js");
    }

    /**
     * Adds a button to the article display for manual TL;DR generation.
     * Implements HOOK_ARTICLE_BUTTON.
     * @param array $line Article data.
     * @return string HTML for the button.
     */
    public function hook_article_button($line)
    {
        return "<i class='material-icons'
			style='cursor : pointer' onclick='Plugins.tldrplugin.summarizeArticle(".$line["id"].")'
			title='".__('Generate TL;DR')."'>short_text</i>";
    }

    /**
     * Renders the plugin's settings tab in preferences.
     * Implements HOOK_PREFS_TAB.
     * @param string $args Name of the active preferences tab.
     * @return void
     */
    public function hook_prefs_tab($args)
    {
        if ($args != "prefFeeds") {
            return;
        }

        print "<div dojoType='dijit.layout.AccordionPane' 
            title=\"<i class='material-icons'>short_text</i> ".__('TLDR Summarizer Settings (tldrplugin)')."\">";

        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            print_error("This plugin requires PHP 7.0."); // Consider if this is still relevant for OpenAI calls
        } else {
            print "<h2>" . __("OpenAI Configuration") . "</h2>";

            print "<form dojoType='dijit.form.Form'>";

            print "<script type='dojo/method' event='onSubmit' args='evt'>
                evt.preventDefault();
                if (this.validate()) {
                xhr.post(\"backend.php\", this.getValues(), (reply) => {
                            Notify.info(reply);
                        })
                }
                </script>";

            print \Controls\pluginhandler_tags($this, "save");

            $openai_api_key = $this->host->get($this, "openai_api_key");
            $openai_base_url = $this->host->get($this, "openai_base_url", "https://api.openai.com/v1");
            $openai_model = $this->host->get($this, "openai_model", "gpt-3.5-turbo");
            $tldr_prompt = $this->host->get($this, "tldr_prompt", "Please provide a concise TL;DR summary of the following article in 1-2 sentences. Focus on the main points and key takeaways.");
            $tldr_max_tokens = $this->host->get($this, "tldr_max_tokens", 150);
            // $tldr_fallback_max_chars is fetched directly in the input field value below
            $curl_timeout = $this->host->get($this, "curl_timeout", 60);
            $curl_connect_timeout = $this->host->get($this, "curl_connect_timeout", 30);

            print "<fieldset>";
            print "<legend>" . __("Core OpenAI Settings") . "</legend>";
            print "<label for='openai_api_key'>" . __("OpenAI API Key:") . "</label>";
            print "<input dojoType='dijit.form.ValidationTextBox' required='1' type='password' name='openai_api_key' id='openai_api_key' value='" . htmlspecialchars($openai_api_key, ENT_QUOTES) . "'/>";

            print "<label for='openai_base_url'>" . __("OpenAI Base URL:") . "</label>";
            print "<input dojoType='dijit.form.TextBox' name='openai_base_url' id='openai_base_url' value='" . htmlspecialchars($openai_base_url, ENT_QUOTES) . "' placeholder='https://api.openai.com/v1'/>";

            print "<label for='openai_model'>" . __("OpenAI Model:") . "</label>";
            print "<input dojoType='dijit.form.TextBox' name='openai_model' id='openai_model' value='" . htmlspecialchars($openai_model, ENT_QUOTES) . "' placeholder='gpt-3.5-turbo'/>";
            print "</fieldset>";

            print "<fieldset>";
            print "<legend>" . __("TL;DR Specific Settings") . "</legend>";
            print "<label for='tldr_prompt'>" . __("TL;DR Prompt Instruction:") . "</label>";
            print "<textarea dojoType='dijit.form.Textarea' name='tldr_prompt' id='tldr_prompt' style='width: 100%; height: 80px;'>" . htmlspecialchars($tldr_prompt, ENT_QUOTES) . "</textarea>";
            print "<span class='text-muted'>" . __("This text is sent to OpenAI to instruct it on how to summarize. The article title and content will be appended automatically.") . "</span>";

            print "<label for='tldr_max_tokens'>" . __("TL;DR Max Tokens (Summary Length):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='tldr_max_tokens' id='tldr_max_tokens' value='" . ((int)$tldr_max_tokens) . "' constraints='{min:50,max:1000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("Max number of tokens for the generated summary. Approx 3-4 tokens per word.") . "</span>";

            print "<label for='tldr_fallback_max_chars'>" . __("TL;DR Fallback Max Characters:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='tldr_fallback_max_chars' id='tldr_fallback_max_chars' value='" . ((int)$this->host->get($this, "tldr_fallback_max_chars", 15000)) . "' constraints='{min:1000,max:100000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("Fallback: Max characters sent if advanced truncation below is not active (e.g. keep start/end are 0).") . "</span>";

            print "<label for='tldr_truncate_trigger_length'>" . __("TL;DR Truncate if longer than (Chars):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='tldr_truncate_trigger_length' id='tldr_truncate_trigger_length' value='" . ((int)$this->host->get($this, "tldr_truncate_trigger_length", 1200)) . "' constraints='{min:0,max:100000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("If content is longer than this, apply start/end truncation. Set 0 to disable this truncation method.") . "</span>";

            print "<label for='tldr_truncate_keep_start'>" . __("TL;DR Keep Start Chars:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='tldr_truncate_keep_start' id='tldr_truncate_keep_start' value='" . ((int)$this->host->get($this, "tldr_truncate_keep_start", 1000)) . "' constraints='{min:0,max:50000,places:0}' style='width: 100px;'/>";

            print "<label for='tldr_truncate_keep_end'>" . __("TL;DR Keep End Chars:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='tldr_truncate_keep_end' id='tldr_truncate_keep_end' value='" . ((int)$this->host->get($this, "tldr_truncate_keep_end", 200)) . "' constraints='{min:0,max:50000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("If content exceeds trigger length, keep X chars from start and Y from end. Set trigger to 0 to disable.") . "</span>";

            print "<label for='tldr_min_article_length'>" . __("TL;DR Min Article Length (Chars):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='tldr_min_article_length' id='tldr_min_article_length' value='" . ((int)$this->host->get($this, "tldr_min_article_length", 200)) . "' constraints='{min:0,max:5000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("Only generate TL;DR if article content (after stripping HTML) is longer than this. Set to 0 to always attempt.") . "</span>";
            print "</fieldset>";

            print "<fieldset>";
            print "<legend>" . __("Connection Settings") . "</legend>";
            print "<label for='curl_timeout'>" . __("cURL Timeout (seconds):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='curl_timeout' id='curl_timeout' value='" . ((int)$curl_timeout) . "' constraints='{min:10,max:300,places:0}' style='width: 100px;'/>";

            print "<label for='curl_connect_timeout'>" . __("cURL Connect Timeout (seconds):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='curl_connect_timeout' id='curl_connect_timeout' value='" . ((int)$curl_connect_timeout) . "' constraints='{min:5,max:60,places:0}' style='width: 100px;'/>";
            print "</fieldset>";

            print "<hr/>";
            print "<h2>" . __("Auto Tag Settings") . "</h2>";

            $autotag_enabled = $this->host->get($this, "autotag_enabled", false);
            $autotag_label_language = $this->host->get($this, "autotag_label_language", "English"); // Default to English
            $autotag_openai_model = $this->host->get($this, "autotag_openai_model", "gpt-3.5-turbo");
            $autotag_max_tags = $this->host->get($this, "autotag_max_tags", 5);
            $autotag_min_article_length = $this->host->get($this, "autotag_min_article_length", 50);
            // API Key and Base URL will reuse the main settings for now.

            print "<fieldset>";
            print "<legend>" . __("General Auto Tagging") . "</legend>";
            print "<label class='checkbox'><input dojoType='dijit.form.CheckBox' type='checkbox' name='autotag_enabled' id='autotag_enabled' " . ($autotag_enabled ? "checked" : "") . ">&nbsp;" . __('Enable Auto Tagging globally') . "</label>";
            print "<span class='text-muted'>" . __("If enabled, tags will be automatically generated for articles based on per-feed settings below and content length.") . "</span>";
            print "</fieldset>";

            print "<fieldset>";
            print "<legend>" . __("Auto Tagging - OpenAI Settings") . "</legend>";
            print "<label for='autotag_openai_model'>" . __("OpenAI Model for Tagging:") . "</label>";
            print "<input dojoType='dijit.form.TextBox' name='autotag_openai_model' id='autotag_openai_model' value='" . htmlspecialchars($autotag_openai_model, ENT_QUOTES) . "' placeholder='gpt-3.5-turbo'/>";
            print "<span class='text-muted'>" . __("Model to use for generating tags (e.g., gpt-3.5-turbo, gpt-4o-mini). API Key and Base URL from Core settings will be used.") . "</span>";

            print "<label for='autotag_label_language'>" . __("Label Language:") . "</label>";
            print "<input dojoType='dijit.form.TextBox' name='autotag_label_language' id='autotag_label_language' value='" . htmlspecialchars($autotag_label_language, ENT_QUOTES) . "' placeholder='English'/>";
            print "<span class='text-muted'>" . __("Language for the generated tags (e.g., English, Spanish, zh-CN).") . "</span>";

            print "<label for='autotag_max_tags'>" . __("Max Tags per Article:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='autotag_max_tags' id='autotag_max_tags' value='" . ((int)$autotag_max_tags) . "' constraints='{min:1,max:10,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("Maximum number of tags to generate per article.") . "</span>";

            print "<label for='autotag_min_article_length'>" . __("Min Article Length for Tags (Chars):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='autotag_min_article_length' id='autotag_min_article_length' value='" . ((int)$autotag_min_article_length) . "' constraints='{min:0,max:5000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("Only generate tags if article content (after stripping HTML) is longer than this. Set to 0 to always attempt.") . "</span>";

            print "<label for='autotag_fallback_max_chars'>" . __("AutoTag Fallback Max Characters:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='autotag_fallback_max_chars' id='autotag_fallback_max_chars' value='" . ((int)$this->host->get($this, "autotag_fallback_max_chars", 10000)) . "' constraints='{min:1000,max:100000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("Fallback: Max characters sent if advanced truncation below is not active.") . "</span>";

            print "<label for='autotag_truncate_trigger_length'>" . __("AutoTag Truncate if longer than (Chars):") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='autotag_truncate_trigger_length' id='autotag_truncate_trigger_length' value='" . ((int)$this->host->get($this, "autotag_truncate_trigger_length", 1000)) . "' constraints='{min:0,max:100000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("If content is longer than this, apply start/end truncation. Set 0 to disable.") . "</span>";

            print "<label for='autotag_truncate_keep_start'>" . __("AutoTag Keep Start Chars:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='autotag_truncate_keep_start' id='autotag_truncate_keep_start' value='" . ((int)$this->host->get($this, "autotag_truncate_keep_start", 800)) . "' constraints='{min:0,max:50000,places:0}' style='width: 100px;'/>";

            print "<label for='autotag_truncate_keep_end'>" . __("AutoTag Keep End Chars:") . "</label>";
            print "<input dojoType='dijit.form.NumberSpinner' name='autotag_truncate_keep_end' id='autotag_truncate_keep_end' value='" . ((int)$this->host->get($this, "autotag_truncate_keep_end", 200)) . "' constraints='{min:0,max:50000,places:0}' style='width: 100px;'/>";
            print "<span class='text-muted'>" . __("If content exceeds trigger length, keep X chars from start and Y from end. Set trigger to 0 to disable.") . "</span>";
            print "</fieldset>";


            print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\">".__('Save Settings')."</button>";
            
            print "&nbsp;<button dojoType=\"dijit.form.Button\" type=\"button\" onclick=\"
                xhr.json('backend.php', App.getPhArgs('tldrplugin', 'testApiConnection'), (reply) => {
                    if (reply.error) {
                        Notify.error('API Test Failed: ' + reply.error);
                    } else {
                        Notify.info('API Test Successful: ' + reply.response);
                    }
                });
            \">".__('Test API Connection')."</button>";
            
            print "</form>";

            print "<h2>" . __("Per-feed Auto-summarization & Auto-tagging") . "</h2>";
            print_notice("Enable for specific feeds in the feed editor.");

            $tldr_enabled_feeds = $this->host->get_array($this, "tldr_enabled_feeds");
            // Potentially filter unknown feeds if necessary, like before.
            // $tldr_enabled_feeds = $this->filter_unknown_feeds($tldr_enabled_feeds);
            $this->host->set($this, "tldr_enabled_feeds", $tldr_enabled_feeds); // Save back, in case filter_unknown_feeds modified it

            if (count($tldr_enabled_feeds) > 0) {
                print "<h3>" . __("TL;DR enabled for (click to edit):") . "</h3>";
                print "<ul class='panel panel-scrollable list list-unstyled'>";
                foreach ($tldr_enabled_feeds as $f) {
                    $feed_title = Feeds::_get_title($f); // Assuming Feeds class is available
                    print "<li><i class='material-icons'>rss_feed</i> <a href='#' onclick='CommonDialogs.editFeed($f)'>". htmlspecialchars($feed_title) . " (ID: $f)</a></li>";
                }
                print "</ul>";
            } else {
                print "<p>" . __("TL;DR auto-summarization is not enabled for any feeds.") . "</p>";
            }

            // Display feeds enabled for Auto Tagging
            $autotag_enabled_feeds = $this->host->get_array($this, "autotag_enabled_feeds");
            // Potentially filter unknown feeds
            // $autotag_enabled_feeds = $this->filter_unknown_feeds($autotag_enabled_feeds);
            $this->host->set($this, "autotag_enabled_feeds", $autotag_enabled_feeds);

            if (count($autotag_enabled_feeds) > 0) {
                print "<h3>" . __("Auto-Tagging enabled for (click to edit):") . "</h3>";
                print "<ul class='panel panel-scrollable list list-unstyled'>";
                foreach ($autotag_enabled_feeds as $f) {
                    $feed_title = Feeds::_get_title($f);
                    print "<li><i class='material-icons'>label</i> <a href='#' onclick='CommonDialogs.editFeed($f)'>". htmlspecialchars($feed_title) . " (ID: $f)</a></li>";
                }
                print "</ul>";
            } else {
                 print "<p>" . __("Auto-tagging is not enabled for any feeds.") . "</p>";
            }

        }
        print "</div>";
    }

    /**
     * Renders plugin-specific options in the feed editor.
     * Implements HOOK_PREFS_EDIT_FEED.
     * @param int $feed_id The ID of the feed being edited.
     * @return void
     */
    public function hook_prefs_edit_feed($feed_id)
    {
        // TLDR per-feed setting
        print "<header>".__("TLDR Summarizer")."</header>";
        print "<section>";
        
        $tldr_enabled_feeds = $this->host->get_array($this, "tldr_enabled_feeds");
        $tldr_checked = in_array($feed_id, $tldr_enabled_feeds) ? "checked" : "";

        print "<fieldset>";
        print "<label class='checkbox'><input dojoType='dijit.form.CheckBox' type='checkbox' id='tldr_plugin_enabled' name='tldr_plugin_enabled' $tldr_checked>&nbsp;" . __('Generate TL;DR summary for this feed') . "</label>";
        print "</fieldset>";
        print "</section>";

        // Auto Tag per-feed setting
        print "<header>".__("Auto Tagging")."</header>";
        print "<section>";

        $autotag_enabled_feeds = $this->host->get_array($this, "autotag_enabled_feeds");
        $autotag_checked = in_array($feed_id, $autotag_enabled_feeds) ? "checked" : "";

        print "<fieldset>";
        print "<label class='checkbox'><input dojoType='dijit.form.CheckBox' type='checkbox' id='autotag_plugin_enabled' name='autotag_plugin_enabled' $autotag_checked>&nbsp;" . __('Automatically generate tags for this feed') . "</label>";
        print "</fieldset>";
        print "</section>";
    }

    /**
     * Saves plugin-specific options from the feed editor.
     * Implements HOOK_PREFS_SAVE_FEED.
     * @param int $feed_id The ID of the feed being saved.
     * @return void
     */
    public function hook_prefs_save_feed($feed_id)
    {
        // Save TLDR per-feed setting
        $tldr_enabled_feeds = $this->host->get_array($this, "tldr_enabled_feeds");
        $enable_tldr = checkbox_to_sql_bool($_POST["tldr_plugin_enabled"] ?? "");
        $tldr_key = array_search($feed_id, $tldr_enabled_feeds);

        if ($enable_tldr) {
            if ($tldr_key === false) {
                array_push($tldr_enabled_feeds, $feed_id);
            }
        } else {
            if ($tldr_key !== false) {
                unset($tldr_enabled_feeds[$tldr_key]);
            }
        }
        $this->host->set($this, "tldr_enabled_feeds", $tldr_enabled_feeds);

        // Save Auto Tag per-feed setting
        $autotag_enabled_feeds = $this->host->get_array($this, "autotag_enabled_feeds");
        $enable_autotag = checkbox_to_sql_bool($_POST["autotag_plugin_enabled"] ?? "");
        $autotag_key = array_search($feed_id, $autotag_enabled_feeds);

        if ($enable_autotag) {
            if ($autotag_key === false) {
                array_push($autotag_enabled_feeds, $feed_id);
            }
        } else {
            if ($autotag_key !== false) {
                unset($autotag_enabled_feeds[$autotag_key]);
            }
        }
        $this->host->set($this, "autotag_enabled_feeds", $autotag_enabled_feeds);
    }

    /**
     * Potentially handles actions triggered on articles, if `add_filter_action` was used.
     * Example: HOOK_ARTICLE_FILTER_ACTION (deprecated)
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @param array $article The article data.
     * @param string $action The action being performed.
     * @return array The (potentially modified) article data.
     */
    public function hook_article_filter_action($article, $action)
    {
        // The action parameter could be used to differentiate if needed, e.g. "tldr_summarize"
        // This method is currently not actively hooked due to add_filter_action being commented out.
        return $this->process_article($article);
    }

    /**
     * Calls the OpenAI API to generate a TL;DR summary for the given text content.
     * Uses configured settings for API key, model, prompt, tokens, and truncation.
     *
     * @param string $text_content The raw article content (HTML or plain text).
     * @param string $article_title The title of the article (optional).
     * @return string The generated summary text, or an empty string on failure.
     */
    private function get_openai_summary($text_content, $article_title = "") {
        $api_key = $this->host->get($this, "openai_api_key");
        $base_url = $this->host->get($this, "openai_base_url", "https://api.openai.com/v1");
        $model = $this->host->get($this, "openai_model", "gpt-3.5-turbo");
        $tldr_prompt_setting = $this->host->get($this, "tldr_prompt", "Please provide a concise TL;DR summary of the following article in 1-2 sentences. Focus on the main points and key takeaways.");
        $tldr_max_tokens = (int)$this->host->get($this, "tldr_max_tokens", 150);
        $curl_timeout = (int)$this->host->get($this, "curl_timeout", 60);
        $curl_connect_timeout = (int)$this->host->get($this, "curl_connect_timeout", 30);

        _debug("tldrplugin: Starting TLDR summary generation with model: $model, base_url: $base_url, max_tokens: $tldr_max_tokens");

        // Basic text cleaning
        $text_content = strip_tags($text_content);
        $text_content = trim($text_content);
        
        // Get TLDR truncation settings
        $tldr_fallback_max_chars = (int)$this->host->get($this, "tldr_fallback_max_chars", 15000);
        $tldr_truncate_trigger_length = (int)$this->host->get($this, "tldr_truncate_trigger_length", 1200); // Default from UI
        $tldr_truncate_keep_start = (int)$this->host->get($this, "tldr_truncate_keep_start", 1000);   // Default from UI
        $tldr_truncate_keep_end = (int)$this->host->get($this, "tldr_truncate_keep_end", 200);     // Default from UI
        
        $text_content = $this->truncate_text(
            $text_content,
            $tldr_truncate_trigger_length,
            $tldr_truncate_keep_start,
            $tldr_truncate_keep_end,
            $tldr_fallback_max_chars
        );
        _debug("tldrplugin: TLDR content length after potential truncation: " . mb_strlen($text_content));

        $prompt_body = $tldr_prompt_setting;
        if (!empty($article_title)) {
            $prompt_body .= " The title of the article is \"" . htmlspecialchars($article_title) . "\"."; // Added htmlspecialchars for safety
        }
        $prompt_body .= "\n\nArticle content:\n\n" . $text_content;

        $headers = [
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        ];

        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => "You are a helpful assistant that provides concise summaries."],
                ["role" => "user", "content" => $prompt_body]
            ],
            "max_tokens" => $tldr_max_tokens
        ];

        _debug("tldrplugin: Making API request to: " . rtrim($base_url, '/') . "/chat/completions");
        _debug("tldrplugin: Request data model: " . $model . ", max_tokens_for_summary: " . $tldr_max_tokens);

        $ch = curl_init(rtrim($base_url, '/') . "/chat/completions");
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $curl_timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curl_connect_timeout);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        _debug("tldrplugin: API response - HTTP code: $http_code");

        _debug("tldrplugin: Raw API response: " . substr($response, 0, 500) . (strlen($response) > 500 ? "..." : ""));

        $decoded_response = json_decode($response, true);

        $summary = trim($decoded_response['choices'][0]['message']['content']);
        _debug("tldrplugin: Successfully generated summary: " . substr($summary, 0, 100) . "...");
        return $summary;
    }
    
    /**
     * Processes an article to potentially add a TL;DR summary.
     * Checks configured minimum article length before attempting summarization.
     * Prepends the summary to the article content if successful.
     *
     * @param array $article The article data array.
     * @return array The modified (or original) article data array.
     */
    public function process_article($article)
    {
        $tldr_min_article_length = (int)$this->host->get($this, "tldr_min_article_length", 200);
        $content_for_length_check = trim(strip_tags($article["content"]));

        if ($tldr_min_article_length > 0 && mb_strlen($content_for_length_check) < $tldr_min_article_length) {
            _debug("tldrplugin: Article content length " . mb_strlen($content_for_length_check) . " is less than minimum " . $tldr_min_article_length . ". Skipping TLDR for article ID: " . $article["id"]);
            return $article;
        }

        $summary_text = $this->get_openai_summary($article["content"], $article["title"]);

        if (empty($summary_text)) {
             _debug("tldrplugin: Failed to get summary or summary was empty for article ID: " . $article["id"]);
            return $article; // Don't modify if summary generation failed
        }

        $tldr_html = "<div class='tldr-summary' style='border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; background-color: #f9f9f9;'>";
        $tldr_html .= "<p><strong>TL;DR</strong></p>";
        $tldr_html .= "<p>" . htmlspecialchars($summary_text) . "</p>";
        $tldr_html .= "</div>";
        $article["content"] = $tldr_html . $article["content"];

        return $article;
    }

    /**
     * Filters article data. Main hook for automatic TL;DR and Auto-Tagging.
     * Implements HOOK_ARTICLE_FILTER.
     *
     * @param array $article The article data array.
     * @return array The modified (or original) article data array.
     */
    public function hook_article_filter($article)
    {
        // TLDR processing
        $tldr_enabled_feeds = $this->host->get_array($this, "tldr_enabled_feeds");
        if (in_array($article["feed"]["id"], $tldr_enabled_feeds)) {
            $article = $this->process_article($article); // This handles its own min length check for TLDR
        }

        // Auto Tag processing
        $autotag_globally_enabled = $this->host->get($this, "autotag_enabled", false);
        if ($autotag_globally_enabled) {
            $autotag_enabled_feeds = $this->host->get_array($this, "autotag_enabled_feeds");

            if (in_array($article["feed"]["id"], $autotag_enabled_feeds)) {
                $autotag_min_article_length = (int)$this->host->get($this, "autotag_min_article_length", 50);
                $content_for_tag_length_check = trim(strip_tags($article["content"])); // Use the potentially TLDR-prepended content

                if ($autotag_min_article_length > 0 && mb_strlen($content_for_tag_length_check) < $autotag_min_article_length) {
                    _debug("tldrplugin: AutoTag: Article content length " . mb_strlen($content_for_tag_length_check) . " is less than minimum " . $autotag_min_article_length . " for tagging. Skipping for article ID: " . $article["id"]);
                } else {
                    _debug("tldrplugin: AutoTag: Processing article ID: " . $article["id"] . " for auto-tagging.");
                    $suggested_tags = $this->get_tags_from_openai($article["content"], $article["title"], $article["owner_uid"]);

                    if (!empty($suggested_tags)) {
                        if (!is_array($article["labels"])) {
                            $article["labels"] = [];
                        }
                        foreach ($suggested_tags as $tag_caption) {
                            // Check if label already exists by caption to avoid duplicates from different casing or slight variations if OpenAI is not consistent
                            $label_exists = false;
                            foreach ($article["labels"] as $existing_label_arr) {
                                if (is_array($existing_label_arr) && count($existing_label_arr) > 1 && mb_strtolower($existing_label_arr[1]) === mb_strtolower($tag_caption)) {
                                    $label_exists = true;
                                    break;
                                }
                            }

                            if (!$label_exists) {
                                $label_data = $this->get_or_create_label($tag_caption, $article["owner_uid"]);
                                if ($label_data) {
                                    array_push($article["labels"], $label_data);
                                     _debug("tldrplugin: AutoTag: Added tag '$tag_caption' to article ID: " . $article["id"]);
                                }
                            } else {
                                _debug("tldrplugin: AutoTag: Tag '$tag_caption' already exists (case-insensitive) for article ID: " . $article["id"]);
                            }
                        }
                    }
                }
            }
        }
        return $article;
    }

    /**
     * Returns the API version of the plugin.
     * Required by TTRSS.
     * @return int API version.
     */
    public function api_version()
    {
        return 2; // Keep this, tt-rss might expect it.
    }

    /**
     * Handles AJAX request to manually summarize an article.
     * Fetches article content, calls OpenAI, and returns summary HTML or error.
     * @return void Outputs JSON response.
     */
    public function summarizeArticle() {
        header('Content-Type: application/json');
        
        $article_id = (int) ($_REQUEST["id"] ?? 0);
        if (!$article_id) {
            print json_encode(["error" => "missing_id", "message" => __("Article ID is missing.")]);
            return;
        }
        
        _debug("tldrplugin: summarizeArticle called for article ID: $article_id");

        // Use the proper tt-rss database access method
        $pdo = Db::pdo();

        $sth = $pdo->prepare("SELECT content, title FROM ttrss_entries WHERE id = ? AND owner_uid = ?");
        $sth->execute([$article_id, $_SESSION['uid']]); // Ensure user owns the article

        $article_row = $sth->fetch();

        if (!$article_row) {
            _debug("tldrplugin: Article not found or access denied for ID: $article_id");
            print json_encode(["error" => "article_not_found", "message" => __("Article not found or access denied.")]);
            return;
        }

        _debug("tldrplugin: Found article: " . substr($article_row["title"], 0, 50) . "...");

        $content_for_length_check = trim(strip_tags($article_row["content"]));
        $actual_content_length = mb_strlen($content_for_length_check);
        _debug("tldrplugin: Content length (stripped for check): " . $actual_content_length . " characters");

        $tldr_min_article_length = (int)$this->host->get($this, "tldr_min_article_length", 200);

        if ($tldr_min_article_length > 0 && $actual_content_length < $tldr_min_article_length) {
            _debug("tldrplugin: Article content length " . $actual_content_length . " is less than minimum " . $tldr_min_article_length . ". Not generating TLDR for article ID: " . $article_id);
            print json_encode([
                "error" => "article_too_short",
                "message" => __("Article content is too short for a summary (min: %d chars, found: %d chars).", $tldr_min_article_length, $actual_content_length)
            ]);
            return;
        }

        $summary_text = $this->get_openai_summary($article_row["content"], $article_row["title"]);

        if (empty($summary_text)) {
            _debug("tldrplugin: Failed to get summary or summary was empty for article ID: " . $article_id . " (manual trigger)");
            print json_encode([
                "error" => "summary_generation_failed",
                "message" => __("Failed to generate summary. Check plugin logs for details.")
            ]);
            return;
        }

        $tldr_html = "<div class=\"tldr-summary\" style='border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; background-color: #f9f9f9;'>";
        $tldr_html .= "<p><strong>TL;DR</strong></p>";
        $tldr_html .= "<p>" . htmlspecialchars($summary_text) . "</p>";
        $tldr_html .= "</div>";
        _debug("tldrplugin: Successfully generated TLDR HTML for manual request");
        print json_encode(["tldr_html" => $tldr_html]);
    }

    /**
     * Handles AJAX request to test the OpenAI API connection with current settings.
     * @return void Outputs JSON response.
     */
    public function testApiConnection() {
        header('Content-Type: application/json');
        
        $api_key = $this->host->get($this, "openai_api_key");
        $base_url = $this->host->get($this, "openai_base_url", "https://api.openai.com/v1");
        $model = $this->host->get($this, "openai_model", "gpt-3.5-turbo");
        $curl_timeout = (int)$this->host->get($this, "curl_timeout", 30); // Using a shorter timeout for test
        $curl_connect_timeout = (int)$this->host->get($this, "curl_connect_timeout", 15);


        // Test with a simple request
        $headers = [
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        ];

        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "user", "content" => "Hello, this is a test. Please respond with 'API connection successful'."]
            ],
            "max_tokens" => 20
        ];

        $ch = curl_init(rtrim($base_url, '/') . "/chat/completions");
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $curl_timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curl_connect_timeout);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $decoded_response = json_decode($response, true);

        print json_encode(["success" => "API connection successful", "response" => $decoded_response['choices'][0]['message']['content']]);
    }

    // --- Text Truncation Helper ---

    /**
     * Truncates text based on specified rules: either by keeping start/end parts or by a fallback max length.
     *
     * @param string $text The text to truncate.
     * @param int $trigger_length If text is longer than this, advanced truncation (start/end) applies. 0 to disable.
     * @param int $keep_start Number of characters to keep from the start for advanced truncation.
     * @param int $keep_end Number of characters to keep from the end for advanced truncation.
     * @param int $fallback_max_length Fallback maximum length if advanced truncation is not active/triggered.
     * @return string The truncated text.
     */
    private function truncate_text($text, $trigger_length, $keep_start, $keep_end, $fallback_max_length) {
        $original_length = mb_strlen($text);

        if ($trigger_length > 0 && $original_length > $trigger_length) {
            // Advanced truncation: keep_start + "..." + keep_end
            if ($keep_start > 0 || $keep_end > 0) {
                $start_text = ($keep_start > 0) ? mb_substr($text, 0, $keep_start) : "";
                $end_text = ($keep_end > 0) ? mb_substr($text, $original_length - $keep_end, $keep_end) : "";

                // Ensure we don't overlap if the text is shorter than keep_start + keep_end
                if ($keep_start + $keep_end >= $original_length) {
                    return $text; // Return original text if sum of parts is too large
                }

                $separator = (!empty($start_text) && !empty($end_text)) ? "\n...\n" : "";
                return $start_text . $separator . $end_text;
            }
        }

        // Fallback to simple max length truncation if advanced is not active or not triggered
        if ($original_length > $fallback_max_length) {
            return mb_substr($text, 0, $fallback_max_length);
        }

        return $text;
    }

    // --- Auto Tagging Helper Functions ---

    /** @var bool Flag to track if label color palette is initialized. */
    private $label_colors_initialized = false;
    /** @var array Palette of hex color codes for labels. */
    private $label_palette = [];

    /**
     * Retrieves all existing label captions for a given user.
     * @param int $owner_uid The user's ID.
     * @return string[] An array of label captions.
     */
    private function get_existing_labels($owner_uid) {
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT caption FROM ttrss_labels2 WHERE owner_uid = ? ORDER BY caption");
        $sth->execute([$owner_uid]);

        $labels = array();
        while ($row = $sth->fetch()) {
            $labels[] = $row['caption'];
        }
        return $labels;
    }

    /**
     * Initializes the color palette for new labels.
     * Generates a list of visually distinct colors, avoiding very light/dark ones.
     * Ensures the palette is initialized only once.
     * @return void
     */
    private function initialize_label_colors() {
        if ($this->label_colors_initialized) return;
        // Generate colors based on the same quantization as colorPalette function in TTRSS
        for ($r = 0; $r <= 0xFF; $r += 0x33) {
            for ($g = 0; $g <= 0xFF; $g += 0x33) {
                for ($b = 0; $b <= 0xFF; $b += 0x33) {
                    // Filter out very light colors to ensure contrast with white text,
                    // and very dark colors for general visibility.
                    // This is a heuristic and might need adjustment.
                    $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
                    if ($brightness > 40 && $brightness < 200) { // Avoid too bright and too dark
                         $this->label_palette[] = sprintf('%02X%02X%02X', $r, $g, $b);
                    }
                }
            }
        }
        if (empty($this->label_palette)) { // Fallback if filter is too aggressive
            $this->label_palette[] = "007bff"; // A default nice blue
        }
        $this->label_colors_initialized = true;
    }

    /**
     * Selects a random background color from the initialized palette
     * and determines a contrasting foreground (text) color (black or white).
     * @return string[] Array containing two hex color codes: [fg_color, bg_color].
     */
    private function get_random_label_colors() {
        $this->initialize_label_colors();

        $bg_color = $this->label_palette[array_rand($this->label_palette)];

        // Determine text color (black or white) based on background brightness
        list($r, $g, $b) = sscanf($bg_color, "%02x%02x%02x");
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        $fg_color = ($brightness > 125) ? '000000' : 'FFFFFF'; // Black text on light, white on dark

        return [$fg_color, $bg_color];
    }

    /**
     * Retrieves an existing label by caption for a user, or creates a new one if not found.
     * New labels are assigned random foreground/background colors.
     *
     * @param string $caption The caption of the label.
     * @param int $owner_uid The user's ID.
     * @return array|null An array representing the label [feed_id, caption, fg_color, bg_color],
     *                    or null if creation failed.
     */
    private function get_or_create_label($caption, $owner_uid) {
        $pdo = Db::pdo();
        $sth = $pdo->prepare("SELECT id, fg_color, bg_color FROM ttrss_labels2
            WHERE caption = ? AND owner_uid = ?");
        $sth->execute([$caption, $owner_uid]);

        if ($row = $sth->fetch()) {
            return array(
                Labels::label_to_feed_id($row["id"]), // This converts label_id to the format TTRSS expects for article labels
                $caption,
                $row["fg_color"],
                $row["bg_color"]
            );
        }

        list($fg_color, $bg_color) = $this->get_random_label_colors();

        $sth = $pdo->prepare("INSERT INTO ttrss_labels2
            (owner_uid, caption, fg_color, bg_color)
            VALUES (?, ?, ?, ?)");

        try {
            $sth->execute([$owner_uid, $caption, $fg_color, $bg_color]);
            $label_id = $pdo->lastInsertId();

            return array(
                Labels::label_to_feed_id($label_id),
                $caption,
                $fg_color,
                $bg_color
            );
        } catch (PDOException $e) {
            // Handle potential duplicate caption race condition or other DB errors
            _debug("tldrplugin: Error creating label '$caption': " . $e->getMessage());
            // Try fetching again in case it was created by a concurrent process
            $sth_retry = $pdo->prepare("SELECT id, fg_color, bg_color FROM ttrss_labels2 WHERE caption = ? AND owner_uid = ?");
            $sth_retry->execute([$caption, $owner_uid]);
            if ($row_retry = $sth_retry->fetch()) {
                 return array(
                    Labels::label_to_feed_id($row_retry["id"]),
                    $caption,
                    $row_retry["fg_color"],
                    $row_retry["bg_color"]
                );
            }
            return null; // Failed to create or find label
        }
    }

    /**
     * Calls the OpenAI API to generate tags for the given article content.
     * Uses configured settings for API key, model, language, max tags, and truncation.
     * Includes existing user labels in the prompt for context.
     *
     * @param string $article_content The raw article content (HTML or plain text).
     * @param string $article_title The title of the article.
     * @param int $owner_uid The user's ID.
     * @return string[] An array of suggested tag strings, or an empty array on failure or if no tags suggested.
     */
    private function get_tags_from_openai($article_content, $article_title, $owner_uid) {
        $api_key = $this->host->get($this, "openai_api_key"); // Reuse TLDR API key
        $base_url = $this->host->get($this, "openai_base_url", "https://api.openai.com/v1"); // Reuse TLDR base URL
        $tag_model = $this->host->get($this, "autotag_openai_model", "gpt-3.5-turbo");
        $label_language = $this->host->get($this, "autotag_label_language", "English");
        $max_tags = (int)$this->host->get($this, "autotag_max_tags", 5);
        $curl_timeout = (int)$this->host->get($this, "curl_timeout", 60);
        $curl_connect_timeout = (int)$this->host->get($this, "curl_connect_timeout", 30);

        _debug("tldrplugin: AutoTag: Starting tag generation. Model: $tag_model, Lang: $label_language, MaxTags: $max_tags");

        $text_content_stripped = strip_tags($article_content);
        $text_content_stripped = trim($text_content_stripped);

        // Get AutoTag truncation settings
        $autotag_fallback_max_chars = (int)$this->host->get($this, "autotag_fallback_max_chars", 10000);
        $autotag_truncate_trigger_length = (int)$this->host->get($this, "autotag_truncate_trigger_length", 1000);
        $autotag_truncate_keep_start = (int)$this->host->get($this, "autotag_truncate_keep_start", 800);
        $autotag_truncate_keep_end = (int)$this->host->get($this, "autotag_truncate_keep_end", 200);

        $text_for_prompt = $this->truncate_text(
            $text_content_stripped,
            $autotag_truncate_trigger_length,
            $autotag_truncate_keep_start,
            $autotag_truncate_keep_end,
            $autotag_fallback_max_chars
        );
        _debug("tldrplugin: AutoTag content length after potential truncation: " . mb_strlen($text_for_prompt));

        $existing_labels = $this->get_existing_labels($owner_uid);
        $existing_labels_json = json_encode($existing_labels, JSON_UNESCAPED_UNICODE);

        $system_prompt = "You are an expert at analyzing text and suggesting relevant tags for articles in a news aggregator. Your goal is to provide concise and accurate tags.";
        $user_prompt = "Analyze the following article content (and title, if provided) and suggest up to $max_tags relevant tags. The tags should be in $label_language.\n";
        $user_prompt .= "Here is a list of existing tags in the system. Prioritize using these if they are highly relevant, but also suggest new tags if appropriate: $existing_labels_json\n";
        if (!empty($article_title)) {
            $user_prompt .= "Article Title: \"" . htmlspecialchars($article_title) . "\"\n";
        }
        $user_prompt .= "Article Content:\n\"" . $text_for_prompt . "\"\n\n"; // Use the truncated text
        $user_prompt .= "Respond with a JSON object containing a single key \"tags\", which is an array of strings. Each string is a suggested tag. If no suitable tags are found, return an empty array. Do not include explanations or apologies in your response, only the JSON object.";

        $headers = [
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        ];
        $data = [
            "model" => $tag_model,
            "messages" => [
                ["role" => "system", "content" => $system_prompt],
                ["role" => "user", "content" => $user_prompt]
            ],
            "response_format" => ["type" => "json_object"],
            "temperature" => 0.3, // Lower temperature for more deterministic tags
            "max_tokens" => $max_tags * 10 + 50 // Estimate tokens for tags + JSON overhead
        ];

        _debug("tldrplugin: AutoTag: Making API request to: " . rtrim($base_url, '/') . "/chat/completions. Prompt text (first 200 chars): ".substr($user_prompt,0,200)." Existing labels (first 100): ".substr($existing_labels_json,0,100));

        $ch = curl_init(rtrim($base_url, '/') . "/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $curl_timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $curl_connect_timeout);

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        _debug("tldrplugin: AutoTag: API response HTTP code: $http_code. Body (first 200): ".substr($response_body,0,200));

        if ($curl_error) {
            _debug("tldrplugin: AutoTag: cURL error: $curl_error");
            return [];
        }
        if ($http_code !== 200) {
            _debug("tldrplugin: AutoTag: API error. HTTP $http_code. Body: $response_body");
            return [];
        }

        $response_data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['choices'][0]['message']['content'])) {
            _debug("tldrplugin: AutoTag: Failed to parse OpenAI JSON response or expected content missing. Error: " . json_last_error_msg() . " Body: " . $response_body);
            return [];
        }

        $message_content_json = $response_data['choices'][0]['message']['content'];
        $tags_data = json_decode($message_content_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($tags_data['tags']) || !is_array($tags_data['tags'])) {
             _debug("tldrplugin: AutoTag: Failed to parse tags JSON from message content or 'tags' array missing/invalid. Content: " . $message_content_json);
            // Attempt to gracefully handle if the response is just a list of tags not in the expected JSON structure as a fallback
            $potential_tags = array_map('trim', explode(',', $message_content_json));
            $filtered_tags = array_filter($potential_tags, function($tag) { return !empty($tag); });
            if (!empty($filtered_tags) && count($filtered_tags) <= $max_tags * 2) { // Heuristic: if it looks like a list of tags
                 _debug("tldrplugin: AutoTag: Attempting fallback parsing of tags from: " . $message_content_json);
                 return array_slice($filtered_tags, 0, $max_tags);
            }
            return [];
        }

        // Sanitize and limit tags
        $final_tags = [];
        foreach ($tags_data['tags'] as $tag) {
            if (is_string($tag) && !empty(trim($tag))) {
                $final_tags[] = trim($tag);
            }
        }
        _debug("tldrplugin: AutoTag: Successfully extracted tags: " . implode(", ", array_slice($final_tags, 0, $max_tags)));
        return array_slice($final_tags, 0, $max_tags); // Ensure max_tags limit
    }
}
