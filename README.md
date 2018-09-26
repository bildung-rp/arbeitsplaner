# Der „Arbeitsplaner“ – ein Moodle-Werkzeug für inklusiven und individualisierenden Unterricht

## Ziel des Arbeitsplaners ist es ein Moodle-Werkzeug zu schaffen, das u.a.
* einfach und intuitiv zu bedienen ist
* Übersichten für Schüler und Lehrer liefert
* auf eine Aktivität bezogene Kommunikation ermöglicht
* Lernen in heterogenen Gruppen unterstützt
* Individuelles Lernen fördert

**Voraussetzung:**
* Moodle 3.5+
* Grid Kursformat (https://moodle.org/plugins/format_grid)


## Installation:

Bei der Konfiguration des Arbeitsplaner Plugins ist die Matrix der Zugriffssteuerung entsprechend einzustellen.
Vor allem bei welcher Aktivität die Sichtbarkeit eingestellt werden kann.


## Technische Dokumentation:
### Ordnerstruktur
* blocks/acl_coursenavigation
* course/report/modrating
* course/report/modreview
* local/aclmodules
* theme/rlp_responsive

**Einbau in ein anderes Theme**
In der config.php muss zunächst folgende Zeile enthalten sein.

$THEME->rendererfactory = 'theme_overridden_renderer_factory';

Im classes Ordner (falls noch nicht vorhanden, muss dieser angelegt werden)

Dort müssen die PHP Dateien core_course_renderer und core_renderer angelegt bzw. ergänzt werden.

Bei boost ist die Struktur etwas anders, dort liegt unterhalb von classes/output die Datei core_renderer und im Unterordner core die Datei course_renderer.php

### Für den Arbeitsplaner sind folgende Methoden relevant

* core_course_renderer.php
 * public function course_section_cm_availability
 * public function print_multiple_section_page
* core_renderer.php
 * public function course_content_header
 * public function course_content_header
