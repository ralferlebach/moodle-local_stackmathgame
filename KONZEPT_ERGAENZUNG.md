# `local_stackmathgame` – Ergänzungskonzept
## Themes & Narrative · Shortcodes · Questionbehaviour · Externe Integrationen

---

## 1. Theme-Auswahl für Lehrende: Kachel-UI

### 1.1 Designziele

- Lehrende sehen alle verfügbaren Designs als **Kacheln (Cards)** mit Thumbnail,
  Titelzeile, Kurzbeschreibung und direkter Vorschau der Spielfiguren.
- Die Auswahl erfolgt auf der Quiz-Einstellungsseite (`quiz_settings.php`).
- Das gewählte Design ist sofort für alle Studierenden wirksam, die den Test öffnen.

### 1.2 Theme-Card-Datenstruktur

Jede Theme-Card speichert zusätzlich zu den bisherigen Feldern:

```json
{
  "id": 1,
  "shortname": "fantasy",
  "name": "Fantasy-Abenteuer",
  "description": "Ein klassisches Fantasy-RPG mit Elfe, Trolls und Golems im Zauberwald.",
  "thumbnail": "themes/fantasy/thumbnail.png",
  "preview": {
    "player_sprite": "themes/fantasy/sprites/Elf_03__IDLE_spritesheet.png",
    "enemy_sprite":  "themes/fantasy/sprites/Troll_01_1_IDLE_spritesheet.png",
    "background":    "themes/fantasy/backgrounds/bg-forest1.png",
    "palette": ["#2d6a4f", "#40916c", "#b7e4c7"]
  },
  "narrative": { /* → Abschnitt 2 */ },
  "player": { /* Sprite-Defs */ },
  "enemies": [ /* … */ ],
  "backgrounds": { /* … */ },
  "ui": { /* … */ }
}
```

### 1.3 Kachel-Layout im Einstellungsformular (Mustache)

```html
<!-- templates/theme_picker.mustache -->
<div class="smg-theme-picker">
  {{#themes}}
  <label class="smg-theme-card {{#selected}}smg-theme-card--selected{{/selected}}"
         for="smg-theme-{{id}}">

    <input type="radio" id="smg-theme-{{id}}" name="themeid"
           value="{{id}}" {{#selected}}checked{{/selected}}>

    <!-- Hintergrundbild als Thumbnail -->
    <div class="smg-theme-card__bg"
         style="background-image:url({{thumbnail_url}})">

      <!-- Sprites als Mini-Vorschau-Animation -->
      <div class="smg-theme-card__sprites">
        <img class="smg-theme-card__player-sprite"
             src="{{player_sprite_url}}" alt="">
        <img class="smg-theme-card__enemy-sprite"
             src="{{enemy_sprite_url}}"  alt="">
      </div>
    </div>

    <div class="smg-theme-card__body">
      <h4 class="smg-theme-card__title">{{name}}</h4>
      <p  class="smg-theme-card__desc">{{description}}</p>
      <div class="smg-theme-card__palette">
        {{#palette}}
        <span class="smg-theme-card__swatch"
              style="background:{{.}}"></span>
        {{/palette}}
      </div>
    </div>

    <div class="smg-theme-card__badge">
      {{#selected}}<span class="badge badge-success">{{#str}}selected,local_stackmathgame{{/str}}</span>{{/selected}}
    </div>
  </label>
  {{/themes}}
</div>
```

### 1.4 PHP: Theme-Daten für Mustache aufbereiten

```php
// classes/output/theme_picker.php
namespace local_stackmathgame\output;

class theme_picker implements \renderable, \templatable {

    public function __construct(
        private array  $themes,
        private int    $selectedThemeid,
        private string $assetBaseUrl
    ) {}

    public function export_for_template(\renderer_base $output): array {
        $cards = [];
        foreach ($this->themes as $theme) {
            $cfg   = json_decode($theme->configjson, true);
            $base  = $this->assetBaseUrl . $theme->shortname . '/';

            $cards[] = [
                'id'               => (int) $theme->id,
                'shortname'        => $theme->shortname,
                'name'             => format_string($theme->name),
                'description'      => format_string($theme->description ?? ''),
                'thumbnail_url'    => $base . ($cfg['thumbnail'] ?? 'thumbnail.png'),
                'player_sprite_url'=> $base . 'sprites/' . ($cfg['player']['sprites']['idle']['file'] ?? ''),
                'enemy_sprite_url' => $base . 'sprites/' . ($cfg['enemies'][0]['sprites']['idle']['file'] ?? ''),
                'palette'          => $cfg['preview']['palette'] ?? [],
                'selected'         => ($theme->id === $this->selectedThemeid),
            ];
        }
        return ['themes' => $cards];
    }
}
```

### 1.5 CSS: Responsive Kacheln

```css
/* styles.css */
.smg-theme-picker {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.smg-theme-card {
    cursor: pointer;
    border: 2px solid var(--bs-border-color, #dee2e6);
    border-radius: .5rem;
    overflow: hidden;
    transition: border-color .15s, box-shadow .15s;
}

.smg-theme-card:hover,
.smg-theme-card--selected {
    border-color: var(--bs-primary, #0f6cbf);
    box-shadow: 0 0 0 3px rgba(15,108,191,.25);
}

.smg-theme-card input[type=radio] { display: none; }

.smg-theme-card__bg {
    height: 120px;
    background-size: cover;
    background-position: center;
    position: relative;
}

.smg-theme-card__sprites {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    justify-content: space-around;
    align-items: flex-end;
    padding: 0 1rem;
}

.smg-theme-card__player-sprite,
.smg-theme-card__enemy-sprite {
    height: 48px;
    width: auto;
    /* Crop to first frame via object-position */
    object-fit: none;
    object-position: 0 0;
    /* Scale sprite sheet to show single frame */
    max-width: 48px;
    overflow: hidden;
}

.smg-theme-card__body { padding: .75rem; }
.smg-theme-card__title { font-size: 1rem; font-weight: 600; margin: 0 0 .25rem; }
.smg-theme-card__desc  { font-size: .8rem; color: var(--bs-secondary, #6c757d); margin: 0; }

.smg-theme-card__palette { display: flex; gap: 4px; margin-top: .5rem; }
.smg-theme-card__swatch  { width: 16px; height: 16px; border-radius: 50%; }
```

---

## 2. Narrativ-System

### 2.1 Konzept

Das Narrativ ist Teil des Theme-Designs. Jedes Theme bringt **standardisierte Textbausteine**
mit, die in definierten Spielsituationen angezeigt werden. Lehrende können diese
Textbausteine überschreiben (Quiz-spezifische Overrides in `configjson`).

### 2.2 Narrativ-Slots (standardisiert)

| Slot-ID              | Auslöser                                        | Variablen verfügbar                     |
|----------------------|-------------------------------------------------|-----------------------------------------|
| `world_enter`        | Spieler betritt eine neue Gruppe/Welt           | `{{world_name}}`, `{{player_name}}`     |
| `battle_start`       | Erste Frage einer Gruppe                        | `{{enemy_name}}`, `{{question_count}}`  |
| `victory`            | Frage korrekt beantwortet                       | `{{question_name}}`, `{{score_fairies}}` |
| `defeat`             | Falsche Antwort                                 | `{{score_mana}}`, `{{mana_lost}}`       |
| `low_mana`           | Mana < 5                                        | `{{score_mana}}`                        |
| `boss_approach`      | Letzte Frage einer Gruppe                       | `{{world_name}}`, `{{boss_name}}`       |
| `boss_defeated`      | Letzte Frage einer Gruppe korrekt beantwortet   | `{{world_name}}`, `{{score_fairies}}`   |
| `world_complete`     | Alle Fragen einer Gruppe gelöst                 | `{{world_name}}`, `{{next_world}}`      |
| `game_complete`      | Gesamtes Quiz abgeschlossen                     | `{{score_fairies}}`, `{{player_name}}`  |
| `hint_intro`         | Hilfe-Elfe erscheint                            | `{{question_name}}`                     |
| `skip_warning`       | Spieler versucht Frage zu überspringen          | —                                       |
| `intro`              | Allererste Anzeige (kein Spielstand vorhanden)  | `{{player_name}}`                       |

### 2.3 Theme-configjson: Narrativ-Abschnitt

```json
{
  "narrative": {
    "world_enter": [
      "Welcome to {{world_name}}, {{player_name}}! Mysterious creatures await.",
      "The forest falls silent as you enter {{world_name}}…",
      "{{player_name}}, prepare yourself — {{world_name}} holds many challenges."
    ],
    "victory": [
      "Your spell strikes true! The creature recoils.",
      "Brilliant, {{player_name}}! {{score_fairies}} fairies freed so far.",
      "The monster stumbles — your magic is formidable!"
    ],
    "defeat": [
      "The creature barely noticed. You lose {{mana_lost}} mana.",
      "Not quite, {{player_name}}. {{score_mana}} mana remaining.",
      "The spell fizzles. Try again — you can do this."
    ],
    "boss_defeated": [
      "You've defeated the guardian of {{world_name}}! A new path opens.",
      "Victory! The creatures of {{world_name}} have been vanquished."
    ],
    "game_complete": [
      "Incredible, {{player_name}}! You've freed {{score_fairies}} fairies and mastered every challenge.",
      "The realm is saved! Your journey through the enchanted forest is complete."
    ]
  }
}
```

*Mehrere Texte pro Slot → zufällige Auswahl bei Anzeige.*

### 2.4 PHP: Narrativ-Renderer

```php
// classes/game/narrative_renderer.php
namespace local_stackmathgame\game;

class narrative_renderer {

    /**
     * Render a narrative slot for the given theme and context.
     *
     * @param  array  $themeConfig   Decoded theme configjson
     * @param  string $slot          One of the standardized slot IDs
     * @param  array  $vars          Template variables, e.g. ['player_name' => 'Alice']
     * @param  array  $quizOverrides Optional per-quiz overrides from configjson
     * @return string  Rendered text (empty string if slot undefined)
     */
    public static function render(
        array  $themeConfig,
        string $slot,
        array  $vars = [],
        array  $quizOverrides = []
    ): string {
        // Quiz-specific override takes precedence.
        $pool = $quizOverrides['narrative'][$slot]
            ?? $themeConfig['narrative'][$slot]
            ?? null;

        if (empty($pool)) {
            return '';
        }

        // Pick randomly from pool.
        $template = $pool[array_rand($pool)];

        // Variable substitution: {{var_name}} → value.
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            static fn($m) => htmlspecialchars($vars[$m[1]] ?? ''),
            $template
        );
    }
}
```

---

## 3. Shortcodes via `filter_shortcodes`

### 3.1 Wie `filter_shortcodes` funktioniert

Das Plugin von branchup stellt einen Moodle-Textfilter bereit. Andere Plugins
**registrieren ihre Shortcodes** via `db/shortcodes.php` und implementieren
eine Handler-Klasse unter `classes/local/shortcodes.php`.

```
[smg_score type="fairies"]
    → filter_shortcodes erkennt [smg_*]
    → ruft local_stackmathgame\local\shortcodes::smg_score() auf
    → gibt gerendertes HTML zurück
```

### 3.2 Registrierung: `db/shortcodes.php`

```php
// db/shortcodes.php
defined('MOODLE_INTERNAL') || die();

$shortcodes = [
    // [smg_score type="fairies"] → aktuelle Punkte anzeigen
    'smg_score' => [
        'callback' => 'local_stackmathgame\local\shortcodes::smg_score',
        'description' => 'Displays the current player score for a given type (fairies, mana).',
    ],
    // [smg_progress label="linAlg-WS25"] → Fortschrittsbalken
    'smg_progress' => [
        'callback' => 'local_stackmathgame\local\shortcodes::smg_progress',
        'description' => 'Displays a progress bar for a game label.',
    ],
    // [smg_narrative scene="victory"] → Narrativ-Textbaustein inline
    'smg_narrative' => [
        'callback' => 'local_stackmathgame\local\shortcodes::smg_narrative',
        'description' => 'Inserts a narrative text from the active theme.',
    ],
    // [smg_badge type="fairy"] → Icon-Badge mit aktuellem Wert
    'smg_badge' => [
        'callback' => 'local_stackmathgame\local\shortcodes::smg_badge',
        'description' => 'Displays a game badge icon with current value.',
    ],
    // [smg_leaderboard label="linAlg-WS25" limit="10"] → Bestenliste
    'smg_leaderboard' => [
        'callback' => 'local_stackmathgame\local\shortcodes::smg_leaderboard',
        'description' => 'Displays a leaderboard for a game label.',
    ],
];
```

### 3.3 Handler-Klasse: `classes/local/shortcodes.php`

```php
// classes/local/shortcodes.php
namespace local_stackmathgame\local;

use local_stackmathgame\game\state_machine;

class shortcodes {

    /**
     * [smg_score type="fairies"] or [smg_score type="mana"]
     * Renders the raw numeric score for the current user.
     * Requires a label to be determinable from context.
     */
    public static function smg_score(string $shortcode, array $args, ?string $content, object $env): string {
        global $USER;

        $type    = clean_param($args['type'] ?? 'fairies', PARAM_ALPHA);
        $labelid = self::resolve_labelid($args, $env);
        if (!$labelid) {
            return '';
        }

        $scores  = state_machine::load_scores((int) $USER->id, $labelid);
        $value   = (int) ($scores[$type] ?? 0);

        return \html_writer::span($value, 'smg-inline-score smg-inline-score--' . $type,
            ['data-scoretype' => $type, 'data-labelid' => $labelid]);
    }

    /**
     * [smg_progress label="linAlg-WS25"]
     * Renders a progress bar: solved questions / total questions.
     */
    public static function smg_progress(string $shortcode, array $args, ?string $content, object $env): string {
        global $USER, $DB;

        $labelname = clean_param($args['label'] ?? '', PARAM_TEXT);
        $label     = $DB->get_record('local_stackmathgame_label', ['name' => $labelname]);
        if (!$label) {
            return '';
        }

        $state   = state_machine::load((int) $USER->id, (int) $label->id);
        $solved  = count($state->solved);
        $total   = (int) ($args['total'] ?? $solved); // total can be passed as hint

        if ($total < 1) {
            return '';
        }

        $pct = min(100, round(($solved / $total) * 100));

        return \html_writer::div(
            \html_writer::div('', 'progress-bar bg-success', [
                'role'          => 'progressbar',
                'style'         => "width:{$pct}%",
                'aria-valuenow' => $pct,
                'aria-valuemin' => 0,
                'aria-valuemax' => 100,
            ]),
            'progress smg-progress-bar',
            ['title' => "{$solved} / {$total}"]
        );
    }

    /**
     * [smg_narrative scene="victory" world="1"]
     * Inserts a narrative text from the active theme.
     * Context-sensitive: tries to find the theme from the current quiz.
     */
    public static function smg_narrative(string $shortcode, array $args, ?string $content, object $env): string {
        global $USER, $PAGE;

        $scene = clean_param($args['scene'] ?? '', PARAM_ALPHANUMEXT);
        if (!$scene) {
            return '';
        }

        // Resolve theme from current page context.
        $themeconfig = self::resolve_theme($env);
        if (!$themeconfig) {
            return '';
        }

        $vars = [
            'player_name'  => fullname(\core_user::get_user($USER->id)),
            'world_name'   => clean_param($args['world'] ?? '', PARAM_TEXT),
        ];

        return \html_writer::span(
            \local_stackmathgame\game\narrative_renderer::render($themeconfig, $scene, $vars),
            'smg-narrative smg-narrative--' . $scene
        );
    }

    /**
     * [smg_leaderboard label="linAlg-WS25" limit="10" type="fairies"]
     */
    public static function smg_leaderboard(string $shortcode, array $args, ?string $content, object $env): string {
        global $DB;

        $labelname = clean_param($args['label'] ?? '', PARAM_TEXT);
        $limit     = min(50, (int) ($args['limit'] ?? 10));
        $type      = clean_param($args['type'] ?? 'fairies', PARAM_ALPHA);

        $label = $DB->get_record('local_stackmathgame_label', ['name' => $labelname]);
        if (!$label) {
            return '';
        }

        $rows = $DB->get_records_sql(
            "SELECT s.userid, s.value, u.firstname, u.lastname
               FROM {local_stackmathgame_score} s
               JOIN {user} u ON u.id = s.userid
              WHERE s.labelid = :labelid AND s.scoretype = :type
              ORDER BY s.value DESC
              LIMIT :lim",
            ['labelid' => $label->id, 'type' => $type, 'lim' => $limit]
        );

        if (empty($rows)) {
            return '';
        }

        $html = \html_writer::start_tag('ol', ['class' => 'smg-leaderboard']);
        $rank = 1;
        foreach ($rows as $row) {
            $html .= \html_writer::tag('li',
                \html_writer::span($rank . '.', 'smg-lb-rank') .
                \html_writer::span(fullname($row), 'smg-lb-name') .
                \html_writer::span($row->value, 'smg-lb-score'),
                ['class' => 'smg-lb-row']
            );
            $rank++;
        }
        $html .= \html_writer::end_tag('ol');

        return $html;
    }

    // ---- Helpers ----

    private static function resolve_labelid(array $args, object $env): int {
        global $DB, $PAGE;

        if (!empty($args['label'])) {
            $r = $DB->get_record('local_stackmathgame_label',
                ['name' => clean_param($args['label'], PARAM_TEXT)], 'id');
            return $r ? (int) $r->id : 0;
        }

        // Try to infer from current quiz CM.
        $cm = $PAGE->cm ?? null;
        if ($cm && $cm->modname === 'quiz') {
            $cfg = \local_stackmathgame\game\quiz_configurator::get_plugin_config((int) $cm->instance);
            return (int) ($cfg->labelid ?? 0);
        }

        return 0;
    }

    private static function resolve_theme(object $env): ?array {
        global $PAGE, $DB;

        $cm = $PAGE->cm ?? null;
        if (!$cm || $cm->modname !== 'quiz') {
            return null;
        }

        $cfg = \local_stackmathgame\game\quiz_configurator::get_plugin_config((int) $cm->instance);
        if (!$cfg || !$cfg->themeid) {
            return null;
        }

        $theme = $DB->get_record('local_stackmathgame_theme', ['id' => $cfg->themeid]);
        return $theme ? json_decode($theme->configjson, true) : null;
    }
}
```

---

## 4. Brauchen wir ein eigenes `qbehaviour`-Plugin?

### 4.1 Kurze Antwort: **Nein, für v1 nicht erforderlich.**

### 4.2 Detaillierte Begründung

STACK-Fragen nutzen standardmäßig `qbehaviour_adaptivemultipart`. Dieses Behaviour bietet genau das, was das Spiel braucht:

| Benötigte Eigenschaft | Bereitgestellt durch | Wie unser Plugin damit umgeht |
|---|---|---|
| Redo / erneuter Versuch | `adaptivemultipart` → Redo-Button | JS erkennt `.mod_quiz-redo_question_button` |
| Teil-Feedback pro Input | `adaptivemultipart` → `.stackprtfeedback` | JS liest `.correct` / `.incorrect` |
| Sofortiges Feedback | `adaptivemultipart` | Standard, kein Eingriff nötig |
| „Check"-Button verstecken | — | CSS: `input[value=Check] { display:none }` |
| Sequencecheck-Pflege | Moodle core | JS-`SequenceCheckManager` |

### 4.3 Wann ein eigenes `qbehaviour` sinnvoll wird (v2+)

Ein eigenes Behaviour wäre nur dann notwendig, wenn:

1. **Rendering-Kontrolle auf PHP-Ebene** gewünscht wird: z.B. damit Moodle von Anfang an
   keinen Check-Button rendert und keine nativen Feedback-Divs erzeugt.
2. **Custom Scoring-Formel**: Das Spiel möchte den Moodle-Attempt-Score abweichend
   von der normalen Bewertung setzen (z.B. Teilpunkte durch Mana-Abzug beeinflussen).
3. **Spiel-Events als Moodle-Events**: Saubere Integration mit block_xp, Competencies
   oder Gradebook erfordert korrekte Moodle-Attempt-States.

**Empfehlung für v2:** `qbehaviour_stackmathgame` als dünner Wrapper um `adaptivemultipart`,
der ausschließlich die Render-Methoden überschreibt, um den Check-Button und
native Feedback-Container zu unterdrücken.

```
qbehaviour_stackmathgame
└── extends qbehaviour_adaptivemultipart
    ├── render_question_controls() → leer (kein Check-Button)
    └── render_specific_feedback()  → leer (Feedback kommt aus Spiel-JS)
```

### 4.4 Praktische Anforderung für v1

Das Plugin sollte prüfen und in der Doku klar machen:
- **Unterstützte Behaviours:** `adaptivemultipart`, `adaptive`, `interactivecountattempts`
- **Nicht empfohlen:** `deferredfeedback` (kein sofortiges Feedback → kein Redo)

```php
// classes/game/quiz_configurator.php (Erweiterung)
public static function validate_quiz_behaviour(int $quizid): bool {
    global $DB;
    $quiz = $DB->get_record('quiz', ['id' => $quizid], 'preferredbehaviour');
    $supported = ['adaptivemultipart', 'adaptive', 'interactivecountattempts'];
    return in_array($quiz->preferredbehaviour, $supported, true);
}
```

---

## 5. Externe Integrationen

### 5.1 `block_xp` – XP und Level-System

**Was block_xp bietet:**
- Site- oder kursweites XP/Leveling für Studierende
- Konfigurierbare Level-Schwellen mit Badges/Belohnungen
- Eigenes Ranking und Profilbadge

**Integrationsstrategie: Moodle-Events**

block_xp hört auf Moodle-Core-Events (Kursaktivität, Forenpost, etc.). Wir
ergänzen eigene Events, auf die block_xp konfiguriert werden kann:

```php
// classes/event/question_solved.php
namespace local_stackmathgame\event;

class question_solved extends \core\event\base {
    protected function init() {
        $this->data['crud']     = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_stackmathgame_gamestate';
    }

    public static function create_from_question(
        int $userid, int $labelid, string $questionid, int $cmid
    ): self {
        $event = static::create([
            'userid'    => $userid,
            'contextid' => \context_module::instance($cmid)->id,
            'objectid'  => $labelid,
            'other'     => ['questionid' => $questionid, 'labelid' => $labelid],
        ]);
        return $event;
    }
}
```

block_xp kann nun so konfiguriert werden, dass `local_stackmathgame\event\question_solved`
eine definierbare Menge XP vergibt.

**Optionale direkte Integration** (falls block_xp installiert):

```php
// classes/game/integrations/xp_bridge.php
namespace local_stackmathgame\game\integrations;

class xp_bridge {
    public static function award(int $userid, int $courseid, int $xp): void {
        if (!class_exists('\block_xp\local\xp\course_world_factory')) {
            return; // block_xp nicht installiert → still ignorieren
        }
        try {
            $world = \block_xp\di::get('course_world_factory')->get_world($courseid);
            $store = $world->get_store();
            $store->increase($userid, $xp);
        } catch (\Throwable $e) {
            debugging('block_xp integration error: ' . $e->getMessage());
        }
    }
}
```

**Spielkonzept mit block_xp:**
- Jede gelöste Frage → +50 XP (konfigurierbar)
- Level-Up triggert visuelles Feedback im Spiel (Event-basiert)
- Spiel-Level (`level_1 = Elfe`, `level_5 = Erzmagierin`) könnte Theme-Skin beeinflussen

### 5.2 `block_stash` – Inventar und Items

**Was block_stash bietet:**
- Kursweites Item/Inventar-System
- Items werden „fallen gelassen" und von Studierenden eingesammelt
- Visueller Stash-Block im Kurs

**Integrationsstrategie: Items als Spielbelohnung**

Statt einer eigenen `inventory`-Tabelle zu bauen, delegieren wir das Item-System
vollständig an block_stash. Unsere Tabelle `local_stackmathgame_inventory` bleibt
als Fallback für den Fall, dass block_stash nicht installiert ist.

```php
// classes/game/integrations/stash_bridge.php
namespace local_stackmathgame\game\integrations;

class stash_bridge {

    /**
     * Drop a game item into the user's stash (called on question solve).
     *
     * @param int    $userid
     * @param int    $courseid
     * @param string $itemshortname  e.g. 'magic_potion', 'xp_booster'
     */
    public static function drop_item(int $userid, int $courseid, string $itemshortname): bool {
        if (!class_exists('\block_stash\manager')) {
            return false;
        }
        try {
            $manager = \block_stash\manager::get($courseid);
            $item    = $manager->get_item_by_idnumber($itemshortname);
            if (!$item) {
                return false;
            }
            $manager->pickup_item($userid, $item->get_id());
            return true;
        } catch (\Throwable $e) {
            debugging('block_stash integration error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user has a specific item (for booster logic).
     */
    public static function has_item(int $userid, int $courseid, string $itemshortname): bool {
        if (!class_exists('\block_stash\manager')) {
            return false;
        }
        try {
            $manager = \block_stash\manager::get($courseid);
            $item    = $manager->get_item_by_idnumber($itemshortname);
            if (!$item) {
                return false;
            }
            return $manager->user_has_item($userid, $item->get_id());
        } catch (\Throwable $e) {
            return false;
        }
    }
}
```

**Item-Konzept:**

| Stash-Item         | Spieleffekt                             | Triggermoment                    |
|--------------------|-----------------------------------------|----------------------------------|
| `magic_potion`     | Mana +10 (einmalig einlösbar)           | Gegner besiegt (Zufalls-Drop)    |
| `xp_booster_2x`    | Nächste Frage gibt doppelte Feen        | Selten-Drop bei Boss-Sieg        |
| `skip_scroll`      | Eine Frage überspringen ohne Mana-Abzug | Als Belohnung konfigurierbar     |
| `hint_stone`       | Zusätzlicher Hinweis zur Frage          | Lehrende können vorab verteilen  |
| `fairy_wing`       | Kosmetik: Elfe mit Flügeln              | Bei 10 befreiten Feen            |

### 5.3 Abhängigkeits-Matrix

```
local_stackmathgame
│
├── FEST REQUIRED
│   ├── Moodle 4.5+
│   └── qtype_stack (ANY_VERSION)
│
├── SOFT REQUIRED (Plugin prüft auf Existenz, deaktiviert Feature wenn fehlt)
│   └── filter_shortcodes
│       → Ohne: [smg_*]-Tags werden als Klartext angezeigt
│
├── OPTIONAL (nahtlose Integration wenn installiert)
│   ├── block_xp
│   │   → Aktiviert: XP-Vergabe bei Fragelösung, Level-Anzeige im Spiel
│   │   → Check: class_exists('\block_xp\di')
│   │
│   ├── block_stash
│   │   → Aktiviert: Item-Drops, Booster-System, Inventar-Anzeige
│   │   → Check: class_exists('\block_stash\manager')
│   │
│   └── local_stackmatheditor
│       → Aktiviert: Verbesserte Formel-Eingabe im Spiel-Input
│       → Check: class_exists('\local_stackmatheditor\…')
│
└── ZUKÜNFTIG GEPLANT
    ├── qbehaviour_stackmathgame       (v2: sauberes Rendering)
    └── local_stackmathgame_pack_* (Erweiterungs-Packs für neue Themes)
```

### 5.4 Integration-Check in der Konfigurationsseite

```php
// quiz_settings.php – zeigt verfügbare Integrationen dem Lehrenden
$integrations = [
    'block_xp'    => class_exists('\block_xp\di'),
    'block_stash' => class_exists('\block_stash\manager'),
    'filter_shortcodes' => \core_plugin_manager::instance()
                            ->get_plugin_info('filter_shortcodes') !== null,
];
```

---

## 6. Vollständige Abhängigkeits- und Erweiterungsstrategie

### 6.1 Ergänzung `version.php`

```php
$plugin->dependencies = [
    'qtype_stack' => ANY_VERSION,
    // Soft dependencies: listed but not enforced by Moodle's installer
    // filter_shortcodes, block_xp, block_stash → checked at runtime
];
```

### 6.2 Empfohlener Installations-Stack (Synergien)

```
Moodle 4.5
├── qtype_stack                  (STACK-Fragen)
├── local_stackmathgame          (Gamification-Engine) ← unser Plugin
├── filter_shortcodes            (Shortcodes für Textbausteine)
├── block_xp                     (XP und Level, kursübergreifend)
├── block_stash                  (Items und Inventar)
└── local_stackmatheditor        (Besserer Formel-Editor)
```

### 6.3 Datenfluss mit allen Integrationen

```
Studierender löst Frage korrekt
    │
    ├─► state_machine::mark_solved()          [DB: gamestate]
    │
    ├─► mechanic_registry::trigger('solved')
    │       ├─► score_mechanic  → fairies +1  [DB: score]
    │       ├─► fairy_mechanic  → fairy freed [→ JS Event]
    │       └─► (future) item_drop_mechanic   [→ stash_bridge]
    │
    ├─► xp_bridge::award()                    [→ block_xp]
    │
    ├─► stash_bridge::drop_item()             [→ block_stash]
    │
    ├─► question_solved Event::trigger()      [→ block_xp listeners]
    │
    └─► JS GameEngine::_processResult()
            ├─► StateManager::markSolved()
            ├─► _animateVictory()
            ├─► ScoreDisplay::update()
            └─► NarrativeDisplay::show('victory')
```

---

## 7. Neue Datenbankfelder für Narrativ-Overrides

Die Tabelle `mdl_local_stackmathgame_quiz.configjson` wird um den `narrative`-Schlüssel ergänzt:

```json
{
  "groups": { … },
  "mechanics": { … },
  "narrative": {
    "world_enter":   ["Willkommen in Welt {{world_name}}!"],
    "victory":       ["Gut gemacht, {{player_name}}!"],
    "boss_defeated": ["Ihr habt den Endboss besiegt!"]
  }
}
```

Kein Schema-Change nötig — alles in `configjson` (bereits `TEXT NOTNULL false`).
