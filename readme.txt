=== Novoslate Site Agent ===
Contributors: novoslate
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later

Reports WordPress health (pending updates, versions) to Novoslate Ops once a day.

== Description ==
Runs alongside ManageWP. Once a day it sends Novoslate Ops the pending core, plugin and theme updates, plus the WordPress and PHP versions. It never changes anything on the site.

== Setup ==
1. Upload the zip under Plugins > Add New > Upload Plugin and activate.
2. In Novoslate Ops, open the client and copy the Site Agent token.
3. Go to Settings > Novoslate Agent, paste the token and click Save and send report.

== Updates ==
Updates arrive from GitHub releases like any other plugin update.

== Changelog ==
= 1.1.0 =
* Updates itself from GitHub releases.
* Endpoint defaults to ops.novoslate.com, so only the token is needed.

= 1.0.0 =
* First release.
