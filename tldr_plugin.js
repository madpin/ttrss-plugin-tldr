/* global xhr, App, Plugins, Article, Notify */

Plugins.TldrPlugin = {
  summarize: function (id) {
    const contentElement = App.find(
      App.isCombinedMode()
        ? `.cdm[data-article-id="${id}"] .content-inner`
        : `.post[data-article-id="${id}"] .content`
    );

    Notify.progress("Generating TL;DR, please wait...");

    xhr.json(
      "backend.php",
      App.getPhArgs("TldrPlugin", "summarizeArticle", { id: id }),
      (reply) => {
        if (contentElement && reply && reply.tldr_html) {
          // Prepend the TL;DR summary
          contentElement.innerHTML = reply.tldr_html + contentElement.innerHTML;
          Notify.info("TL;DR summary generated and prepended.");

          if (App.isCombinedMode()) Article.cdmMoveToId(id);

        } else if (reply && reply.error) {
          Notify.error("Failed to generate TL;DR: " + reply.error);
        }
        else {
          Notify.error("Unable to generate TL;DR for this article. Empty or invalid response from server.");
        }
      }
    );
  },
};
