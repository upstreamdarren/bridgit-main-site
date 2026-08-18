=== Bridgit Page Loader ===
Contributors: bridgitcare
Tags: bridgit, publishing, static site
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later

Loads the approved Bridgit user-site build on configured public routes while preserving WordPress administration and all other WordPress content.

== Installation ==

Upload this ZIP over the existing Bridgit WordPress Publisher / Bridgit Page Loader plugin. Because the ZIP keeps the existing bridgit-wordpress-publisher folder and main filename, WordPress will offer to replace the installed version rather than create a separate plugin.

After activation, open Settings > Bridgit Page Loader. Auto-detect is recommended. On ai.myuk.life the plugin publishes three routes: /, /se/ and /wm/.

== Changelog ==

= 1.5.0 =
* Adds auto-detection for every Bridgit user-site domain.
* Adds three-page routing for ai.myuk.life.
* Adds a safe stale-cache fallback when the build source is temporarily unavailable.
* Preserves WordPress admin, login, REST, feeds and all unconfigured paths.
* Keeps the historic plugin folder and main file so uploads update the existing installation.
