# Diflowrin AI Content Generation

A free WordPress plugin that turns a keyword — or just a link — into a publish-ready article, using
your own OpenRouter API key. No accounts, no credits, no subscription: you pay your AI provider
directly for what you generate, and nothing else.

Available on the WordPress.org plugin directory:
**https://wordpress.org/plugins/diflowrin-ai-content-generation/**

- **Requires WordPress** 5.6 or newer
- **Requires PHP** 7.4 or newer
- **License** GPL v2 or later

## What it does

- **Bring-your-own-key generation via [OpenRouter](https://openrouter.ai/).** One key gives you
  Claude, Gemini, GPT and most other models — you pick the slug.
- **Live web research before writing.** A Perplexity Sonar pass researches the topic (or reads your
  source link) so drafts are grounded in current facts rather than stale training data.
- **Article from a link.** Paste a URL with no topic at all and get an original article written about
  that page.
- **Images, AI or real.** A featured image plus up to four in-article illustrations, one per section,
  uploaded to the media library with alt text. Generate them with any OpenRouter image model, or
  switch the source to Pexels for real photographs — every photo is published with its photographer
  credited and linked, as the Pexels terms require.
- **Headings written from the subject.** No article is forced to open with "Introduction" and close
  with "Conclusion"; each heading comes from what its section actually says, in the language you
  generate in.
- **Optional AI disclosure.** A tick box appends a short notice telling readers the article was
  written with AI. Off unless you ask for it, with wording you control.
- **50 article languages**, and short / medium / long length presets.
- **SEO meta over the REST API.** Meta title and description for Yoast SEO, SiteSEO, SEOPress and
  RankMath are exposed to the REST API — but only to users allowed to edit that specific post — so a
  connected client can fill them in when it publishes.
- **One-click connect** to the SEO Content Architect desktop app through WordPress Application
  Passwords. The plugin never generates or stores a password itself.

Full feature list, FAQ, changelog and the third-party services disclosure live in
[`readme.txt`](readme.txt), which is the canonical text for the WordPress.org listing.

## Installation

From WordPress: **Plugins → Add New → Search** for "Diflowrin AI Content Generation", then Activate.

From this repository:

1. Download or clone into `wp-content/plugins/diflowrin-ai-content-generation/`.
2. Activate **Diflowrin AI Content Generation** on the Plugins screen.
3. Go to **AI Content → Settings** and paste your OpenRouter key
   ([openrouter.ai/keys](https://openrouter.ai/keys)).
4. Generate from **AI Content → Generate**.

No build step, no Composer, no npm — the plugin ships as plain PHP with a small autoloader.

## How your keys are stored

API keys are encrypted at rest with libsodium `crypto_secretbox`, using a key derived from the
site's own `wp_salt('auth')`. A database dump alone is not enough to read them; an attacker also
needs `wp-config.php`. Rotating your salts invalidates the stored keys and you simply re-enter them.

When you connect the desktop app, WordPress core issues an Application Password and hands it to the
app over a custom URL scheme. The plugin does not create, transmit or store that password.

## Layout

```
diflowrin-ai-content-generation.php   Bootstrap: constants, autoloader, plugin instance
includes/
  Plugin.php                          Wiring and the custom protocol filter
  Admin/                              Admin pages, AJAX handlers and views
  Connect/                            Application Passwords handoff to the desktop app
  Generator/                          Prompt building, Sonar research pass, article assembly
  Rest/SeoMeta.php                    Yoast / SiteSEO / SEOPress / RankMath meta over REST
  Services/                           OpenRouter, Pexels and remote-image clients
  Settings/                           Options, sanitising, secret encryption
assets/                               Admin CSS and JS
.wordpress-org/                       Banner, icon and screenshots for the directory listing
```

Namespace is `Diflowrin\ContentGenerator\`; the autoloader maps it straight onto `includes/`. Coding
style follows the WordPress PHP standards (tabs, Yoda conditions, `esc_*` on output, nonces and
capability checks on every write).

## Contributing

Bug reports and pull requests are welcome via GitHub issues. When changing behaviour, please keep
`readme.txt` — its feature list, FAQ and changelog — in step with the code, since that file is what
users read on WordPress.org.

## The desktop app

The plugin is useful on its own. Connecting it to
[SEO Content Architect](https://diflowrin.com/seo-content-architect/) adds bulk generation, scheduled
auto-posting across many sites, an image pipeline, social repurposing, and an SEO toolkit (keyword
research, competitor and SERP analysis, content scoring, rank tracking, site audits). The desktop app
is a separate, closed-source product and is not part of this repository.

## License

GPL v2 or later — see [LICENSE](LICENSE).
