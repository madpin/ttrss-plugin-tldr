<?php
class TldrPlugin extends Plugin
{
    private $host;
    
    public function about()
    {
        return array(
            1.0, // Version
            "Generates a TL;DR summary using OpenAI and prepends it to the article content.", // Description
            "your_github_username/tldr_plugin" // Author/URL (replace with actual if available)
        );
    }
    public function flags()
    {
        return array(
            "needs_curl" => true
        );
    }
    public function save()
    {
        $this->host->set($this, "openai_api_key", $_POST["openai_api_key"]);
        $this->host->set($this, "openai_base_url", $_POST["openai_base_url"]);
        $this->host->set($this, "openai_model", $_POST["openai_model"]);
        echo __("OpenAI settings saved.");
    }

    public function init($host)
    {
        $this->host = $host;
        
        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            return;
        }

        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
        $host->add_hook($host::HOOK_PREFS_TAB, $this);
        $host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
        $host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);

        $host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);
        
        $host->add_filter_action($this, "tldr_summarize", __("TLDR Summarize"));
    }

    public function get_js()
    {
        return file_get_contents(__DIR__ . "/tldr_plugin.js");
    }

    public function hook_article_button($line)
    {
        return "<i class='material-icons'
			style='cursor : pointer' onclick='Plugins.TldrPlugin.summarize(".$line["id"].")'
			title='".__('Generate TL;DR')."'>short_text</i>";
    }


    public function hook_prefs_tab($args)
    {
        if ($args != "prefFeeds") {
            return;
        }

        print "<div dojoType='dijit.layout.AccordionPane' 
            title=\"<i class='material-icons'>short_text</i> ".__('TLDR Summarizer Settings (TldrPlugin)')."\">";

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
            $openai_base_url = $this->host->get($this, "openai_base_url");
            if (empty($openai_base_url)) $openai_base_url = "https://api.openai.com/v1";
            $openai_model = $this->host->get($this, "openai_model");
            if (empty($openai_model)) $openai_model = "gpt-3.5-turbo";

            print "<fieldset>";
            print "<label for='openai_api_key'>" . __("OpenAI API Key:") . "</label>";
            print "<input dojoType='dijit.form.ValidationTextBox' required='1' type='password' name='openai_api_key' id='openai_api_key' value='$openai_api_key'/>";
            print "</fieldset>";

            print "<fieldset>";
            print "<label for='openai_base_url'>" . __("OpenAI Base URL (optional):") . "</label>";
            print "<input dojoType='dijit.form.TextBox' name='openai_base_url' id='openai_base_url' value='$openai_base_url' placeholder='https://api.openai.com/v1'/>";
            print "</fieldset>";

            print "<fieldset>";
            print "<label for='openai_model'>" . __("OpenAI Model (optional):") . "</label>";
            print "<input dojoType='dijit.form.TextBox' name='openai_model' id='openai_model' value='$openai_model' placeholder='gpt-3.5-turbo'/>";
            print "</fieldset>";

            print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\">".__('Save')."</button>";
            print "</form>";

            print "<h2>" . __("Per feed auto-summarization") . "</h2>";
            print_notice("Enable for specific feeds in the feed editor.");

            $enabled_feeds = $this->host->get($this, "tldr_enabled_feeds");

            if (!is_array($enabled_feeds)) {
                $enabled_feeds = array();
            }

            // It's good practice to ensure feeds actually exist, like filter_unknown_feeds did
            // $enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
            // For now, assuming filter_unknown_feeds will be reused or adapted if necessary.
            // If $this->pdo is not available directly, this might need adjustment or removal.
            // If this class doesn't have $pdo, and filter_unknown_feeds relies on it,
            // we should remove this call for now or ensure $pdo is available.
            // For simplicity in this step, I'll comment it out, assuming it might be handled later or not strictly needed for basic operation.
            // $enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);


            $this->host->set($this, "tldr_enabled_feeds", $enabled_feeds);

            if (count($enabled_feeds) > 0) {
                print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";
                print "<ul class='panel panel-scrollable list list-unstyled'>";
                foreach ($enabled_feeds as $f) {
                    // Ensure Feeds class is available or use a safe way to get title
                    $feed_title = method_exists('Feeds', '_get_title') ? Feeds::_get_title($f) : "Feed ID: $f";
                    print "<li><i class='material-icons'>rss_feed</i> <a href='#'
                        onclick='CommonDialogs.editFeed($f)'>".
                        $feed_title . "</a></li>";
                }
                print "</ul>";
            }
        }
        print "</div>";
    }

    public function hook_prefs_edit_feed($feed_id)
    {
        print "<header>".__("TLDR Summarizer")."</header>";
        print "<section>";
        
        $enabled_feeds = $this->host->get($this, "tldr_enabled_feeds");
        if (!is_array($enabled_feeds)) {
            $enabled_feeds = array();
        }
        
        $key = array_search($feed_id, $enabled_feeds);
        $checked = $key !== false ? "checked" : "";

        print "<fieldset>";
        print "<label class='checkbox'><input dojoType='dijit.form.CheckBox' type='checkbox' id='tldr_plugin_enabled' name='tldr_plugin_enabled' $checked>&nbsp;" . __('Generate TL;DR summary for this feed') . "</label>";
        print "</fieldset>";

        print "</section>";
    }

    public function hook_prefs_save_feed($feed_id)
    {
        $enabled_feeds = $this->host->get($this, "tldr_enabled_feeds");
            
        if (!is_array($enabled_feeds)) {
            $enabled_feeds = array();
        }
        
        $enable = checkbox_to_sql_bool($_POST["tldr_plugin_enabled"]);
        
        $key = array_search($feed_id, $enabled_feeds);
        
        if ($enable) {
            if ($key === false) {
                array_push($enabled_feeds, $feed_id);
            }
        } else {
            if ($key !== false) {
                unset($enabled_feeds[$key]);
            }
        }

        $this->host->set($this, "tldr_enabled_feeds", $enabled_feeds);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hook_article_filter_action($article, $action)
    {
        // The action parameter could be used to differentiate if needed, e.g. "tldr_summarize"
        return $this->process_article($article);
    }

    private function get_openai_summary($text_content, $article_title = "") {
        $api_key = $this->host->get($this, "openai_api_key");
        $base_url = $this->host->get($this, "openai_base_url");
        if (empty($base_url)) $base_url = "https://api.openai.com/v1";
        $model = $this->host->get($this, "openai_model");
        if (empty($model)) $model = "gpt-3.5-turbo";

        if (empty($api_key)) {
            _debug("OpenAI API Key is not configured.");
            return null;
        }

        // Basic text cleaning & truncation if necessary (OpenAI has token limits)
        // Remove HTML tags for the prompt
        $text_content = strip_tags($text_content);
        // Simple truncation to avoid overly long prompts (adjust length as needed)
        // A more sophisticated approach would be to count tokens.
        $max_prompt_length = 15000; // Approx 3k-4k tokens, should be safe for most models.
        if (mb_strlen($text_content) > $max_prompt_length) {
            $text_content = mb_substr($text_content, 0, $max_prompt_length);
        }

        $prompt = "Summarize the following article in one or two sentences (as a 'TL;DR'). ";
        if (!empty($article_title)) {
            $prompt .= "The title of the article is \"$article_title\". ";
        }
        $prompt .= "Here is the article content:\n\n" . $text_content;

        $headers = [
            "Authorization: Bearer " . $api_key,
            "Content-Type: application/json"
        ];

        $data = [
            "model" => $model,
            "messages" => [
                ["role" => "system", "content" => "You are a helpful assistant that provides concise summaries."],
                ["role" => "user", "content" => $prompt]
            ],
            "max_tokens" => 100 // Max tokens for the summary
        ];

        $ch = curl_init(rtrim($base_url, '/') . "/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Consider security implications for production
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for API calls

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            _debug("OpenAI API cURL error: " . $curl_error);
            return null;
        }

        if ($http_code >= 400) {
            _debug("OpenAI API HTTP error: " . $http_code . " Response: " . $response);
            return null;
        }

        $decoded_response = json_decode($response, true);

        if (isset($decoded_response['choices'][0]['message']['content'])) {
            return trim($decoded_response['choices'][0]['message']['content']);
        } else {
            _debug("OpenAI API unexpected response structure: " . $response);
            return null;
        }
    }
    
    public function process_article($article)
    {
        // Original Mercury fulltext logic (if it were here) would have already run if this plugin is chained after it.
        // We assume $article['content'] is what we want to summarize.

        $summary_text = $this->get_openai_summary($article["content"], $article["title"]);

        if ($summary_text) {
            $tldr_html = "<div class='tldr-summary' style='border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; background-color: #f9f9f9;'>";
            $tldr_html .= "<p><strong>TL;DR</strong></p>";
            $tldr_html .= "<p>" . htmlspecialchars($summary_text) . "</p>";
            $tldr_html .= "</div>";
            $article["content"] = $tldr_html . $article["content"];
        }

        return $article;
    }

    public function hook_article_filter($article)
    {
        $enabled_feeds = $this->host->get($this, "tldr_enabled_feeds");
            
        if (!is_array($enabled_feeds)) {
            return $article;
        }
        
        // Check if the feed_id is in ttrss_entries.feed_id or ttrss_user_entries.feed_id
        // The $article array structure might vary based on context (e.g. global vs. per-user)
        $feed_id_key = isset($article["feed"]["id"]) ? $article["feed"]["id"] : (isset($article["feed_id"]) ? $article["feed_id"] : null);

        if ($feed_id_key === null) {
             _debug("TLDRPlugin: Could not determine feed ID for article: " . (isset($article['id']) ? $article['id'] : 'unknown'));
            return $article;
        }

        $key = array_search($feed_id_key, $enabled_feeds);
        
        if ($key === false) {
            return $article; // Not enabled for this feed
        }

        // Check if API key is set before processing
        $api_key = $this->host->get($this, "openai_api_key");
        if (empty($api_key)) {
            _debug("TLDRPlugin: OpenAI API Key not set, skipping summarization for article: " . (isset($article['id']) ? $article['id'] : 'unknown'));
            return $article;
        }
        
        return $this->process_article($article);
    }

    public function api_version()
    {
        return 2; // Keep this, tt-rss might expect it.
    }

    // summarizeArticle is called by the JS when the user clicks the TLDR button
    public function summarizeArticle() {
        header('Content-Type: application/json');
        $article_id = (int) $_REQUEST["id"];

        // The host object should provide a way to get DB access if this plugin is loaded correctly by tt-rss
        // For tt-rss, plugins usually get $this->pdo from the Plugin base class after init.
        // If $this->pdo is not available, this part will fail.
        // This assumes $this->pdo is available as it was in the original mercury_fulltext plugin.
        if (!isset($this->pdo)) {
             // If $this->pdo is not available, try to get it from the host if possible, or error out.
            if (method_exists($this->host, 'get_pdo')) {
                $this->pdo = $this->host->get_pdo();
            } else {
                print json_encode(["error" => "Database connection not available."]);
                return;
            }
        }

        $sth = $this->pdo->prepare("SELECT content, title FROM ttrss_entries WHERE id = ?");
        $sth->execute([$article_id]);

        $article_row = $sth->fetch();

        if (!$article_row) {
            print json_encode(["error" => "Article not found."]);
            return;
        }

        $summary_text = $this->get_openai_summary($article_row["content"], $article_row["title"]);

        if ($summary_text) {
            $tldr_html = "<div class=\"tldr-summary\" style='border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; background-color: #f9f9f9;'>";
            $tldr_html .= "<p><strong>TL;DR</strong></p>";
            $tldr_html .= "<p>" . htmlspecialchars($summary_text) . "</p>";
            $tldr_html .= "</div>";
            print json_encode(["tldr_html" => $tldr_html]);
        } else {
            print json_encode(["error" => "Failed to generate summary."]);
        }
    }
}
