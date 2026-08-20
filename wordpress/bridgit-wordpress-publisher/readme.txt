=== Bridgit Page Publisher ===
Contributors: bridgitcare
Tags: cloudflare, pages, publishing, static site
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.1
License: GPLv2 or later

Safely serves selected Bridgit marketing routes from the approved Cloudflare Pages production site while WordPress continues to own the blog and every other route.

== What it changes ==

Nothing immediately after activation. Public publishing starts disabled.

When enabled, only the exact routes listed under Settings > Bridgit Publisher are served from Cloudflare. The plugin always protects WordPress blog, admin, REST API, login, media and feed paths.

The optional WordPress blog branding setting adds the same Bridgit navigation, support chooser and footer used on the new marketing site while WordPress continues to render and manage every post, archive and search result.

All GitHub-to-Cloudflare deployments automatically become available after the short page cache expires. Administrators can clear that cache from the settings screen.

== Installation ==

1. Upload the plugin ZIP under Plugins > Add New > Upload Plugin.
2. Activate Bridgit Page Publisher.
3. Open Settings > Bridgit Publisher.
4. Leave public publishing disabled and use the safe homepage preview.
5. Check the configured routes.
6. Enable public publishing when ready.

Deactivating the plugin immediately returns every route to WordPress.

== Security and scope ==

The Cloudflare origin is fixed in the plugin and cannot be supplied by a visitor.
Only GET and HEAD requests for explicitly allow-listed routes are handled.
The plugin never writes posts, pages, users, plugins, theme files or WordPress settings outside its own option and temporary cache records.
It never handles /blog, /wp-admin, /wp-json, /wp-login.php, /wp-content, /wp-includes or /feed.

== Changelog ==

= 1.5.0 =
* Add the six new guided-tool routes, including the Social Impact Advisor delivered with Social Enterprise UK.
* Upgrade report and website-lead messages to accessible, branded HTML emails with clear sections, trusted resources and a booking call to action.
* Support new report types for digital readiness, demand and capacity, pathway mapping, responsible AI, partnership working and social impact.

= 1.4.4 =
* Add the Tools hub, Sandy co-production coach and Digital Commissioning Toolkit to managed routes.
* Add a protected, consent-based report endpoint that emails a user's completed toolkit plan directly to them.
* Add Tools and both guided resources to the shared WordPress navigation and footer.

= 1.4.2 =
* Add the evidence-led Our Impact page to the managed marketing routes.
* Add Our Impact to the shared WordPress header and footer.
* Preserve the existing plugin folder so this release replaces the installed publisher in place.

= 1.4.1 =
* Add Responsible AI and Social Investors to the managed marketing routes.
* Preserve existing route choices while adding the new pages during upgrade.

= 1.4.0 =
* Consolidate adult carers, young carers and employers under one Carer services sales page in shared navigation and footers.
* Redirect the retired Young Carers and Employers sales URLs to the combined Carer Services page.

= 1.3.1 =
* Keep the header Book a call text white across WordPress themes and link states.

= 1.3.0 =
* Extend the shared Bridgit navigation and footer to privacy, accessibility, terms, intellectual-property, contact and co-production pages.
* Add the retained legal and organisational pages to the shared footer.

= 1.2.0 =
* Add an independently switchable shared header, navigation, support chooser and footer for the WordPress blog.
* Keep posts, archives, categories, authors, search and all blog editing inside WordPress.
* Hide only the legacy Astra or Elementor blog header and footer when the shared blog shell is enabled.

= 1.1.0 =
* Add a protected, consent-based ElevenLabs lead webhook that emails contact@bridgit.care through WordPress.
* Keep lead details out of WordPress content and expose webhook credentials only to administrators.

= 1.0.2 =
* Add the leadership team page to the managed marketing routes without changing the public publishing setting.

= 1.0.1 =
* Serve managed pages directly during WordPress routing for compatibility with theme and page-builder stacks.
* Add an administrator-only origin diagnostic instead of allowing a failed preview to appear blank.
* Retain compatibility with WordPress installations where wp_is_json_request is unavailable.

= 1.0.0 =
* Initial release.
