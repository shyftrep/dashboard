=== SHYFT Dashboard ===
Contributors: shyftrep
Tags: dashboard, shyft, kunden
Requires at least: 6.4
Requires PHP: 8.1
Stable tag: 2.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gebrandetes Kunden-Dashboard unter /dashboard mit Anfragen, Website-Status, Matomo-Kennzahlen und Änderungswünschen.

== Description ==

Das SHYFT Dashboard bietet Kundinnen und Kunden einen eigenen Bereich unter /dashboard – ohne Zugriff auf das WordPress-Backend.

== Changelog ==

= 2.1.6 =
* Logo: Neues transparentes shyft.-Logo von shyft.rocks im Plugin abgelegt.

= 2.1.5 =
* Design: Übersichtlicher – klare Abschnitte, Karten statt durchgehendem Kachel-Gitter.

= 2.1.4 =
* Design: Heller nahtloser Header mit feiner Linie, dunkles Logo (bundled), Kachel-Grid wie Website.
* Warmup: Heller Vorladebildschirm mit kleinem Logo.

= 2.1.3 =
* Design: Neues SHYFT-Layout – Bricolage Grotesque, Instrument Serif, Farben #172A39 / #E0DBD7 / #FC573B.

= 2.1.2 =
* Design: 8BEES-Farbschema zurückgenommen, ursprüngliches SHYFT-Dashboard-Design wiederhergestellt.

= 2.1.1 =
* Design: Farbschema Slate #7789AB, Schwarz #000000, Lime #C7F022.

= 2.1.0 =
* Aufgaben-Tracker: Änderungswünsche als Aufgaben, Hinweis oben, Abhaken für Admins.

= 2.0.11 =
* Änderungswünsche: Anhänge in der Mediathek, E-Mail nur mit Links (keine Anhänge mehr).

= 2.0.10 =
* Admins werden nach dem Login wie Kunden zum Dashboard weitergeleitet (ggf. über Warmup).

= 2.0.9 =
* Warmup auch für Admins: Admin-Leiste „Kunden-Dashboard“ und stilles Preload im Backend.

= 2.0.8 =
* Neu: Täglicher Hintergrund-Warmup der Dashboard-Seiten (nach Login und still im Frontend).

= 2.0.7 =
* Fix: Dashboard über template_include statt exit (LiteSpeed/WordPress-Header bleiben korrekt).
* Fix: Frühe HTML-Header, LiteSpeed/WP-Rocket-Cache-Ausschluss, keine Output-Buffer-Zerstörung.

= 2.0.6 =
* Fix: Zeitraum-URLs als /dashboard/7/ statt ?shyft_period= (Cache lieferte text/plain).
* Fix: Dashboard von Page-Cache ausgeschlossen, Content-Type wird mehrfach als text/html erzwungen.

= 2.0.5 =
* Fix: Dashboard rendert beim ersten Laden und beim Zeitraumwechsel zuverlässig als HTML (kein Quelltext).

= 2.0.4 =
* Fix: Updates installieren nach wp-content/plugins/shyft-dashboard (nicht 01_shyft-dashboard).

= 2.0.3 =
* Zeitraum-Wähler für Kennzahlen: 7, 30 oder 90 Tage.

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
