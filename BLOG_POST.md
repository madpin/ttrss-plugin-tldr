---
title: "Supercharge Your TTRSS: New OpenAI Plugin Features - Auto-Tagging & Smarter TL;DRs!"
date: YYYY-MM-DD
tags: [TTRSS, OpenAI, Plugin, Productivity, News, Summarization, Tagging]
---

Are you drowning in a sea of articles in your Tiny Tiny RSS (TTRSS) setup? Wish you could quickly grasp the essence of long reads and keep your content perfectly organized? Get ready to revolutionize your news consumption, because the Enhanced OpenAI Plugin for TTRSS just got a massive upgrade!

We're thrilled to announce two powerful new features, alongside a host of new customization options, designed to make your TTRSS experience smarter and more efficient: **Automatic Article Tagging** and **Highly Configurable TL;DR Summaries with Advanced Truncation**.

## Introducing: Auto-Tagging with OpenAI!

Ever wished your articles would magically organize themselves? Now they can! Our new Auto-Tag feature uses the intelligence of OpenAI to analyze your articles and automatically assign relevant labels (tags) within TTRSS.

**How it Works:**

*   **Intelligent Analysis**: OpenAI processes the article content (and title) to identify key themes, topics, and entities.
*   **Context-Aware**: The plugin can show your existing TTRSS labels to OpenAI, encouraging it to use your established tags when appropriate, while still being free to suggest new, relevant ones.
*   **Automatic Label Creation**: If a suggested tag is new, the plugin seamlessly creates it in TTRSS with a randomly generated, visually distinct color scheme.
*   **Fine-Grained Control**:
    *   Enable auto-tagging globally, then toggle it for individual feeds.
    *   Choose a specific OpenAI model (like `gpt-4o-mini` for cost-effectiveness or `gpt-4` for maximum power) just for tagging.
    *   Specify the desired language for your tags.
    *   Set the maximum number of tags per article and a minimum article length to qualify for tagging.

Imagine your feed automatically sorting articles into categories like "Technology," "AI Research," "Project Management," or "Local News" without you lifting a finger!

## TL;DR Summaries: Now More Powerful and Flexible!

Our popular TL;DR summarization feature has also received significant enhancements, giving you more control over what and how content is summarized.

*   **Customizable Prompts**: Tailor the exact instruction given to OpenAI for generating summaries.
*   **Token Control**: Define the maximum number of tokens (length) for the generated summary.
*   **Minimum Article Length**: Prevent summaries for very short articles that don't need one.
*   **Smarter Content Handling with Advanced Truncation**: (See below!)

## Advanced Text Truncation: Precision Control for OpenAI Prompts

Both TL;DR Summaries and Auto-Tagging now benefit from a sophisticated text truncation system. Why is this important? OpenAI API calls are often limited by token count (related to text length). Sending overly long articles can lead to errors or increased costs. Our new truncation options give you precise control:

*   **Trigger Length**: Define a character length for an article. If it's longer, truncation rules apply.
*   **Keep Start/End**: Specify exactly how many characters to keep from the beginning and from the end of the article. The plugin then sends this "condensed" version (e.g., the first 1000 chars and the last 200 chars, joined by "...") to OpenAI. This is great for long-form content where the introduction and conclusion often hold the most summary-worthy information.
*   **Fallback Length**: If the above method isn't active or the article doesn't meet the trigger length, a traditional "max character" fallback ensures you're still within reasonable limits.
*   **Separate Controls**: TL;DR and Auto-Tagging have their own independent sets of these truncation settings, allowing you to optimize for each task. For example, you might send more context for a summary than for quick tagging.

## Key Configuration Highlights

You'll find all these new options within TTRSS under `Preferences` -> `Feeds` -> `TLDR Summarizer Settings (tldrplugin)`:

*   **Core OpenAI Settings**: Your API Key, Base URL, and a default OpenAI model.
*   **TL;DR Specific Settings**: Prompt, tokens, min length, and all new truncation options.
*   **Auto Tag Settings**: Global enable, tagging model, label language, max tags, min length, and its own truncation options.
*   **Connection Settings**: Fine-tune cURL timeouts.
*   **Per-Feed Toggles**: Easily enable/disable TL;DR and Auto-Tagging in the "Edit Feed" dialog under the "Plugins" tab.

## Get Started Today!

Upgrading your TTRSS with these intelligent features is simple:
1.  Ensure you have the latest version of the `tldr_plugin` (check the [repository_url_here_if_available] for updates).
2.  Enable it in your TTRSS plugin settings.
3.  Head to the configuration pane to enter your OpenAI API key and customize the features to your liking.

We believe these enhancements will dramatically improve how you interact with your news feeds, saving you time and helping you stay better organized.

**We'd love to hear your feedback!** Try out the new Auto-Tagging and enhanced TL;DR features and let us know what you think. Happy reading (and tagging)!
