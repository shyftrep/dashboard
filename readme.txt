=== SHYFT Dashboard ===
Contributors: shyftrep
Tags: dashboard, shyft, kunden
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 2.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gebrandetes Kunden-Dashboard unter /dashboard mit Anfragen, Website-Status, Matomo-Kennzahlen und Änderungswünschen.

== Description ==

Das SHYFT Dashboard bietet Kundinnen und Kunden einen eigenen Bereich unter /dashboard – ohne Zugriff auf das WordPress-Backend.

== Changelog ==

= 2.0.2 =
* Fix: Nach Plugin-Update keine manuelle Reaktivierung mehr nötig (Upgrade-Routine).

= 2.0.1 =
* Test-Release für den automatischen Updater.

= 2.0.0 =
* Major release: stabiler GitHub-Updater mit automatischen Releases.

= 1.0.10 =
* Fix: WordPress zeigt GitHub-Updates zuverlässig (Fallback + Cache-Reset).

= 1.0.9 =
* Öffentliches GitHub-Repository: Updates ohne Token.

= 1.0.8 =
* Test-Release für automatischen Updater.

= 1.0.7 =
* GitHub-Token zentral in includes/github-token.php (ohne wp-config pro Site).

= 1.0.6 =
* Zuverlässigere GitHub-Releases (ein Workflow).
* Update-Diagnose unter Einstellungen → SHYFT Dashboard.

= 1.0.5 =
* Test-Release für den automatischen Updater.

= 1.0.4 =
* Automatische GitHub-Releases per Actions (Tag + ZIP).

= 1.0.3 =
* GitHub-Token kann über wp-config.php (SHYFT_DASHBOARD_GITHUB_TOKEN) gesetzt werden.
* Versionsanzeige im Dashboard-Footer (zum Testen von Updates).

= 1.0.2 =
* Automatische Updates über GitHub Releases.

= 1.0.1 =
* Routing- und Darstellungsverbesserungen für /dashboard.
* Matomo-Kennzahlen und externe Link-Domains (max. 10).
