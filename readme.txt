
=== Diflowrin AI Content Generation ===
Contributors: diflowrin
Tags: ai, content writer, seo, openrouter, auto post
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Write SEO-ready articles with AI (bring your own OpenRouter key) and connect your site to the SEO Content Architect desktop app.

== Description ==

Diflowrin AI Content Generation helps you turn a keyword into a publish-ready draft, right inside WordPress. Bring your own OpenRouter API key — one key gives you access to Claude, Gemini, GPT and many other models — and keep full control of your costs and your data.

Need to do more? Connect your site to the SEO Content Architect desktop app in one click. The app offers the premium version of article generation — deeper research, fact-checking and a high-quality editorial pass — plus bulk generation, scheduled auto-posting across many sites, an image pipeline, Social Studio for turning posts into social content, and a full SEO toolkit: keyword research, competitor and SERP analysis, content scoring, rank tracking and site audits.

**What this plugin does**

* One-click connect to the SEO Content Architect desktop app using WordPress Application Passwords (no password is ever stored by this plugin).
* Bring-your-own-key generation via OpenRouter.
* Sonar web research: before writing, Perplexity Sonar researches your topic on the live web (or reads your source link) so articles are grounded in current facts, not stale training data.
* Article from a link: paste just a URL and get an original article written about that page — no topic needed.
* AI images with the model you choose: a featured image and up to four illustrations placed inside the article, one per section, uploaded to your media library with alt text. You set the OpenRouter image model yourself, so you can use Gemini, GPT Image or any other model with image output, and an optional style keeps every visual consistent.
* Or real photographs instead: switch the image source to Pexels and the same slots are filled with stock photos, using your own free Pexels key. Nothing is invented, there is no per-image cost, and every photo is published with its photographer credited and linked, as the Pexels terms require.
* Headings written from your subject, not from a template. Articles do not all open with "Introduction" and close with "Conclusion": every section heading is built from what that section actually says, in whichever language you generate in.
* Optional AI disclosure: one tick box adds a short notice at the end of the article telling readers it was written with AI. Off unless you ask for it, with wording you control, and a setting to have it ticked by default.
* 50 article languages — the same list as the SEO Content Architect desktop app, from English and Spanish to Romanian, Japanese and Swahili.
* SEO meta support: the meta title and meta description of Yoast SEO, SiteSEO, SEOPress and RankMath are exposed to the WordPress REST API, so the connected desktop app can fill them in when it publishes.
* A clean, contextual link to the desktop app where it genuinely adds value — no nag screens.

**Free and self-directed**

You use your own API key, so there are no per-article credits or subscriptions inside the plugin. You only pay your AI provider for what you generate.

The same applies to the desktop app's article generation and content research: both are free to use with your own API key, with no subscription.

== External Services ==

This plugin can connect to third-party services that you configure. Below is what is sent and when.

= OpenRouter API =
* Purpose: generate article content using the AI model you choose, (when Sonar web research is enabled) run a research pass with a Perplexity Sonar model before writing, and generate images with the image model you choose.
* Data sent: the prompt/topic you enter, the optional source URL, and generation options. Image requests send a prompt built from the article title, the section heading being illustrated and your optional style setting. No personal data is sent unless you include it in your prompt.
* When: only when you trigger a generation. With Sonar research enabled, each generation makes one additional OpenRouter request for the research pass. Each image you ask for is one further request.
* Terms: https://openrouter.ai/terms
* Privacy: https://openrouter.ai/privacy

= Generated image downloads =
* Purpose: image models usually return the picture inline, but some reply with a link to their own file storage instead. In that case your web server downloads the image from that link so it can be saved to your media library.
* Data sent: a standard HTTP GET request to the URL the model returned. Nothing of yours is included.
* When: only for generations where you asked for images and the model replied with a link.

= Pexels API (only when you choose Pexels as your image source) =
* Purpose: find a stock photograph for the article instead of generating one with AI.
* Data sent: a few search words taken from your article title or section heading, sent with your own Pexels API key. Nothing else about you or your site is included. The chosen photo is then downloaded to your media library.
* When: only for generations where you asked for images and set the image source to Pexels.
* Attribution: every Pexels photo is published with a caption crediting the photographer and linking back to Pexels, as their API terms require.
* Terms: https://www.pexels.com/api/terms/
* Privacy: https://www.pexels.com/privacy-policy/

= Source URL fetching =
* Purpose: when you provide a source URL and the Sonar research pass is disabled or fails, your web server fetches that page directly to use its text as source material.
* Data sent: a standard HTTP GET request to the URL you entered, identifying itself as Diflowrin\ContentGenerator with your site URL.
* When: only for generations where you entered a source URL.

= SEO Content Architect desktop app =
* Purpose: optional connection so the desktop app can publish to your site.
* Data sent: when you choose to connect, WordPress issues an Application Password to the app. This plugin does not transmit or store that password itself.
* Website: https://diflowrin.com/seo-content-architect/
* Installed from: https://apps.microsoft.com/store/detail/9NL3GZLPH01Z

== Installation ==

1. Upload the `diflowrin-ai-content-generation` folder to `/wp-content/plugins/`, or install it from the Plugins screen.
2. Activate the plugin.
3. Go to **AI Content > Settings** and paste your OpenRouter API key. You can now generate articles right away from **AI Content > Generate**.
4. Optional — for images: on the same Settings screen, choose whether pictures are generated by an AI model or taken from Pexels, and how many go inside each article. Pexels needs its own free key from pexels.com/api; AI generation uses the OpenRouter key you already entered.
5. Optional — for bulk generation and auto-posting: install the SEO Content Architect desktop app on your computer from the Microsoft Store (https://apps.microsoft.com/store/detail/9NL3GZLPH01Z), open it once, then click **Connect** on the **AI Content > Connect** page. The Connect button only works when the desktop app is installed locally.

== Frequently Asked Questions ==

= Do I need an account or subscription? =
No. You bring your own OpenRouter API key and pay your provider directly for what you generate.

= Does the plugin store my credentials? =
Your OpenRouter key is stored encrypted in your site database (the encryption key is derived from your site's secret salts in wp-config.php) and used only for requests you trigger. When you connect the desktop app, WordPress creates an Application Password and hands it to the app; the plugin does not store it.

= Does it fill in my SEO plugin's meta title and description? =
Yes. When the connected desktop app publishes an article, it can set the meta title and meta description for Yoast SEO, SiteSEO, SEOPress or RankMath — whichever you have installed. WordPress hides those fields from the REST API by default, so this plugin exposes them, and only to users who are allowed to edit the post in question.

= Which image models can I use? =
Any OpenRouter model that produces image output — for example google/gemini-2.5-flash-image or an OpenAI image model. Paste the model slug into the Image model field in Settings; you can see the full list at openrouter.ai/models filtered by image output. If a model returns no image, the plugin tells you so instead of failing the draft, and the article is still created.

= Why do my images never contain any text? =
That is deliberate. By default every image prompt instructs the model to draw no text, because most image models render words as misspelled gibberish, and a wrong word baked into a picture cannot be translated, read out by a screen reader, or corrected without generating the image again. If your image model handles typography well — Google's Nano Banana (Gemini image models) or an OpenAI image model, for example — tick **Allow text inside generated images** in Settings. The ban is then dropped and the prompt asks for correctly spelled words in the language your article is written in.

= Can I use real photos instead of AI-generated images? =
Yes. In Settings, set **Where images come from** to *Pexels stock photos* and paste a free key from pexels.com/api. The featured image and the in-article slots are then filled with photographs. Search terms are taken from your article title and section headings, so the results work best for articles written in English; for other languages Pexels often has no match and the plugin falls back to a handpicked photo rather than leaving the slot empty.

= Do I have to credit the photographers? =
The plugin does it for you, and it is not optional. Pexels requires attribution wherever their photos appear, so each in-article photo is published with a caption naming the photographer and linking to both them and the photo on Pexels, and the featured image carries the same credit in its attachment caption.

= Can I tell readers the article was written with AI? =
Yes. Tick **Tell readers the article was written with AI** on the Generate screen and a short notice is added as the last paragraph of the article, after the images. The box starts unticked, so nothing is added unless you ask for it; if you want the notice on everything you write, tick **Add the AI disclosure notice by default** in Settings and the box starts ticked instead, still leaving you free to untick it for a particular article. The wording is a plain sentence you can rewrite in Settings, and it is inserted exactly as you saved it, so write it in the language you publish in.

= What happens if an image fails? =
The draft is never lost. A failed featured image is reported on its own, and if an in-article image fails the plugin stops there rather than repeating a request that is likely to fail the same way, keeps the images it already produced, and shows you the reason.

= Why does connecting require HTTPS? =
WordPress Application Passwords require a secure (HTTPS) connection. The Connect screen tells you if anything needs to be fixed first.

== Screenshots ==

1. Generate an article — turn a topic, or just a source URL, into a publish-ready draft with your own OpenRouter key. Choose tone, length and one of 50 languages; Sonar web research grounds every draft in live data.
2. Connect your site to the SEO Content Architect desktop app in one click, using WordPress Application Passwords — no password is ever stored by the plugin.
3. Settings — bring your own OpenRouter key and pick the model you write with. Choose where images come from: AI generation invents a picture from your article, or free Pexels stock photos credited to the photographer. Both keys are stored encrypted in your own database.
4. Settings, continued — keep a consistent image style, decide whether generated pictures may contain text, edit the AI disclosure notice added at the end of an article, and switch on Perplexity Sonar so every draft is grounded in live web research.

== Changelog ==

= 1.2.1 =
* Tested with WordPress 7.1.
* The plugin listing now shows the Settings screen as well, so you can see what you are configuring before you install.
* Housekeeping only: no change to how articles or images are generated.

= 1.2.0 =

**Images inside your articles**

* Choose how many illustrations a draft gets, up to four. They are placed one per section, uploaded to your media library with alt text, and attached to the post.
* Pick where they come from: an AI model, or real photographs from Pexels using your own free key. Pexels costs nothing per image and invents nothing, and every photo is published with its photographer credited and linked, as the Pexels terms require.
* For AI generation you choose the model yourself, any OpenRouter model with image output, plus an optional style so your visuals stay consistent across articles.
* New setting: allow text inside generated images. Off by default, because most image models render words as misspelled gibberish. Switch it on for a model that handles typography well and the prompt asks for correctly spelled words in your article's language instead.
* The result panel now shows the featured image it produced. It is the post thumbnail rather than part of the article body, so the content preview alone never revealed whether a cover had been made.

**Writing**

* Articles no longer reuse the same section headings. Every draft used to open with "Introduction" and close with "Conclusion", because the prompt asked for them by name. Every heading is now written from the article's own subject, in whichever language you generate in.
* Optional AI disclosure: a tick box above the Generate button adds a short notice at the very end of the article telling readers it was written with AI. It starts unticked, so nothing is added unless you ask for it, and you can rewrite the sentence in Settings or have it ticked by default.

**Fixes**

* Fixed image generation, which failed outright with "model not found". Images now go through the chat completions endpoint, which is what OpenRouter's image-capable models actually use.
* Fixed in-article images overflowing the content column. They were inserted at their full original size with no width or height, so the browser rendered them at whatever the file happened to be, often close to 1900 pixels wide. Images now carry proper dimensions, are served at a sensible size, and load lazily.
* Generated and downloaded images are checked before they are saved: only real PNG, JPEG, WebP or GIF files reach your uploads folder.

**Elsewhere**

* The admin sidebar entry is now labelled "AI Content" instead of the full plugin name, which wrapped onto three lines.
* The desktop app is now linked where it belongs: install links point at its Microsoft Store listing, and "Get the desktop app" opens its product page. The Connect screen also lists the app's SEO toolkit and notes that its article generation and content research are free.

= 1.1.0 =
* SEO meta over the REST API: the meta title and meta description of Yoast SEO, SiteSEO, SEOPress and RankMath can now be set when a post is created or updated, so the SEO Content Architect desktop app fills them in automatically when it publishes.
* Editing SEO meta requires permission to edit that specific post.

= 1.0.1 =
* Renamed all global PHP/JS identifiers to a single unique prefix (functions, options, hooks, constants, namespace) to prevent conflicts with other plugins and themes.

= 1.0.0 =
* Initial release.
* AI article generation with your own OpenRouter key (any model: GPT, Claude, Gemini and more).
* Sonar web research pass (Perplexity Sonar via OpenRouter) grounding every generation in live web data.
* Article from a link: generate an original article from just a source URL, no topic needed.
* 50 article languages and short/medium/long length presets.
* Optional AI-generated featured image.
* One-click Connect to the SEO Content Architect desktop app via WordPress Application Passwords, with connection status and disconnect.
* OpenRouter API key stored encrypted at rest (key derived from your site's secret salts).
