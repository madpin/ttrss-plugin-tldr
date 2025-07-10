# Enhanced OpenAI Plugin for Tiny Tiny RSS (TL;DR Summarizer & Auto-Tagger)

This plugin for [Tiny Tiny RSS (tt-rss)](https://tt-rss.org) supercharges your reading experience by leveraging the OpenAI API for two main functionalities:
1.  **TL;DR Summarizer**: Generates concise "Too Long; Didn't Read" summaries for articles and prepends them to the content.
2.  **Auto-Tagger**: Automatically analyzes article content and assigns relevant tags (labels) within TTRSS.

It offers extensive configuration options for fine-tuning both features.

## Features

*   **Automatic & Manual TL;DR Summaries**:
    *   Configure automatic summary generation for specific feeds.
    *   Manually trigger summary generation for any article via a button.
*   **Automatic Article Tagging (Auto-Tag)**:
    *   Enable globally and then per-feed to automatically assign labels to new articles.
    *   Considers existing user labels to maintain consistency and suggest new ones.
    *   New labels are created with randomly assigned, contrasting colors.
*   **Rich OpenAI Integration**:
    *   Uses the OpenAI API (e.g., GPT-3.5-turbo, GPT-4, GPT-4o-mini) for summaries and tagging.
    *   Configurable API key, base URL (for proxies/alternative endpoints), and model selection (separate for TL;DR and Auto-Tag).
*   **Highly Configurable**:
    *   **TL;DR Settings**: Customize the OpenAI prompt, max summary tokens, minimum article length to trigger summarization.
    *   **Auto-Tag Settings**: Customize the OpenAI model, desired label language, max tags per article, minimum article length for tagging.
    *   **Advanced Text Truncation**: Separate controls for TL;DR and Auto-Tag to manage content sent to OpenAI:
        *   Define a character length to trigger truncation.
        *   Specify how many characters to keep from the beginning and end of the article.
        *   Set a fallback maximum character length if advanced truncation isn't active/triggered.
    *   **Connection Settings**: Configure cURL timeout and connection timeout values.
*   **Per-Feed Control**: Enable/disable automatic TL;DR generation and auto-tagging for each feed individually.
*   **API Test**: A button in settings to test your OpenAI API connection.

## Requirements

*   A running instance of Tiny Tiny RSS.
*   PHP 7.0+ with the cURL extension enabled.
*   An OpenAI API key.

## Installation

1.  **Download/Clone Plugin**:
    *   Navigate to your TTRSS `plugins.local` directory (recommended) or `plugins` directory.
        ```bash
        cd /path/to/your/tt-rss/plugins.local/
        ```
    *   Clone this repository. If you name the directory `tldr_plugin` (or similar):
        ```bash
        git clone <repository_url_of_this_plugin> tldr_plugin
        ```
        (Replace `<repository_url_of_this_plugin>` with the actual URL.)
    *   Alternatively, download the plugin files (`init.php`, `tldr_plugin.js`, `README.md`) and place them in a directory like `plugins.local/tldr_plugin`. The main PHP file **must** be `init.php` inside this directory.

2.  **Enable Plugin**:
    *   In TTRSS, go to `Preferences` (the gear icon) -> `Plugins`.
    *   Find "tldrplugin" (the description will mention TL;DR and Auto-Tag) in the list and check the box to enable it.

## Configuration

All settings are managed under `Preferences` -> `Feeds` tab -> `TLDR Summarizer Settings (tldrplugin)` accordion pane.

### 1. Core OpenAI Settings
*   **OpenAI API Key**: (Required) Your secret API key from OpenAI.
*   **OpenAI Base URL**: (Optional) Defaults to `https://api.openai.com/v1`. Change this if you use a proxy or a compatible alternative API endpoint.
*   **OpenAI Model**: (Optional) Defaults to `gpt-3.5-turbo`. This is the *default model for TL;DR summaries* if not overridden by the Auto-Tag specific model. You can specify other models like `gpt-4`, `gpt-4o-mini`, etc., depending on your API key access.

### 2. TL;DR Specific Settings
*   **TL;DR Prompt Instruction**: The text sent to OpenAI to instruct how to summarize. Article title and content are appended automatically.
*   **TL;DR Max Tokens (Summary Length)**: Max tokens for the generated summary (e.g., 150).
*   **TL;DR Fallback Max Characters**: If advanced truncation (below) is not active/triggered, this is the maximum number of characters from the article content sent to OpenAI.
*   **TL;DR Truncate if longer than (Chars)**: If article content (stripped HTML) exceeds this length, the "Keep Start/End" truncation method is applied. Set to 0 to primarily use the Fallback Max Characters method.
*   **TL;DR Keep Start Chars**: Characters to keep from the beginning of the article for truncation.
*   **TL;DR Keep End Chars**: Characters to keep from the end of the article for truncation (joined with "..." to the start part).
*   **TL;DR Min Article Length (Chars)**: Only generate TL;DR if stripped article content is longer than this (e.g., 200). Set to 0 to always attempt.

### 3. Auto Tag Settings
*   **Enable Auto Tagging globally**: Master switch for the auto-tagging feature. Per-feed settings also apply.
*   **OpenAI Model for Tagging**: (Optional) Specific model for tag generation (e.g., `gpt-4o-mini`, `gpt-3.5-turbo`). Uses the Core OpenAI Model if left blank.
*   **Label Language**: Language for generated tags (e.g., `English`, `Spanish`, `zh-CN`).
*   **Max Tags per Article**: Maximum number of tags to generate (e.g., 5).
*   **Min Article Length for Tags (Chars)**: Only generate tags if stripped article content is longer than this (e.g., 50). Set to 0 to always attempt.
*   **AutoTag Fallback Max Characters**: Similar to TL;DR, but for auto-tagging content.
*   **AutoTag Truncate if longer than (Chars)**: Trigger length for auto-tag content truncation.
*   **AutoTag Keep Start Chars**: Characters to keep from start for auto-tag content.
*   **AutoTag Keep End Chars**: Characters to keep from end for auto-tag content.

### 4. Connection Settings
*   **cURL Timeout (seconds)**: Overall timeout for OpenAI API requests.
*   **cURL Connect Timeout (seconds)**: Timeout for establishing the connection to the API.

### 5. Test API Connection
*   A button to send a simple test request to OpenAI using your current Core API settings to verify connectivity.

### Enabling Per-Feed Features

1.  In TTRSS, navigate to a feed in the feed tree.
2.  Right-click on the feed name or select it and go to `Actions` -> `Edit feed`.
3.  In the `Edit Feed` dialog, go to the `Plugins` tab.
    *   **TLDR Summarizer**: Check/uncheck `Generate TL;DR summary for this feed`.
    *   **Auto Tagging**: Check/uncheck `Automatically generate tags for this feed`.
4.  Save the feed settings.

The plugin settings page (`Preferences` -> `Feeds` -> `TLDR Summarizer Settings`) will also list which feeds currently have TL;DR and Auto-Tagging enabled.

## Manual TL;DR Generation

If the plugin is active, you should see a "short_text" icon (tooltip: "Generate TL;DR") on articles in the headline view or when an article is open. Click this icon to generate a TL;DR summary for that specific article. This manual action respects the "TL;DR Min Article Length" setting.

## Notes on Plugin Order

If you use other plugins that modify article content (e.g., full-text extraction plugins like `mercury_fulltext`), this plugin should ideally run *after* them to ensure it summarizes and tags the most complete and relevant content. Plugin execution order in TTRSS can sometimes be influenced by plugin directory names (alphabetical) or the order of enabling them if your TTRSS version doesn't offer explicit ordering. You might need to rename plugin directories (e.g., `01_mercury_fulltext`, `02_tldr_plugin`) to enforce a sequence.

## Troubleshooting

*   **Check API Key & Quota**: Ensure your OpenAI API key is correct, active, and has sufficient quota.
*   **System Logs**: Enable debug mode in TTRSS (`config.php`) and check `ttrss/storage/logs/app.log` (or similar path) for detailed error messages from the plugin (prefixed with `tldrplugin:`).
*   **cURL Extension**: Verify the cURL PHP extension is installed and enabled.
*   **Connectivity**: Use the "Test API Connection" button. If it fails, check your server's ability to reach the OpenAI API Base URL (firewalls, DNS issues).
*   **Permissions**: Ensure your TTRSS `cache/images` directory is writable if any issues arise with label color generation (though unlikely to be an issue for this plugin's color logic).
*   **Plugin Conflicts**: If issues persist, try disabling other plugins temporarily to check for conflicts.

## License

This plugin is released under the MIT License. (You would need to add a `LICENSE.md` file if you intend to distribute this with a specific license).
