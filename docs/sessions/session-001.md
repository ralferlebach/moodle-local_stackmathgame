# Session 001 — Grobkonzeption der RPG-Gamification

**Status:** abgeschlossen
**Quelle:** rekonstruiert in Sitzung 003 aus dem Verlauf
„[STACKGame] 001: Grob-Konzeption RPG-Gamification".

> **Hinweis zur Rekonstruktion.** Dieses Protokoll wurde nicht während der Sitzung geführt,
> sondern nachträglich aus dem Gesprächsverlauf zusammengetragen. Es enthält deshalb nur, was
> dort belegbar steht: die getroffenen Entscheidungen, den Ausgangspunkt und die offenen
> Punkte. Zwischenschritte, verworfene Alternativen und der Verlauf der Diskussion fehlen —
> genau der Teil, der ein zeitnah geführtes Protokoll wertvoll macht.

---

## Ausgangspunkt

Ralf brachte ein eigenständiges JavaScript, `alquiz-fantasy-bg-ver3.js` (© 2022 Malte
Neugebauer, Hochschule Bochum, MIT), das STACK-Aufgaben in einem Moodle-Quiz in ein
RPG-artiges Spiel verwandelt. Es funktionierte, war aber nicht wartbar.

Ziel der Sitzung: das Skript verstehen und einen Weg zu einem echten Moodle-`local`-Plugin
aufzeigen, das

* den Einsatz flexibel konfigurierbar macht,
* Spielstände speichert und über Sitzungen **und über Quizze mit gleichem Label hinweg**
  synchronisiert,
* auf Mobilgerät, Hochkant, Quer und Laptop stabil läuft,
* mit `local_stackmatheditor` kompatibel bleibt.

## Fünf Architekturentscheidungen

Vorgelegt als Auswahlfragen, von Ralf beantwortet:

| Frage | Entscheidung |
|---|---|
| Moodle-Mindestversion | **4.5 (LTS)** |
| STACK-Antwortverarbeitung | **Browser-AJAX-Kette beibehalten, aber besser gekapselt** — der konservative Weg, nicht die serverseitige Auswertung über die question_engine |
| Label-Scope | **Site-weit** — ein Label gilt instanzweit, nicht pro Kurs oder Kategorie |
| STACK-Speicher-Hack (`maxima-string` als Spielstand-Backup) | **Nein, nur noch DB-basiert** |
| Design-Verwaltung | **Admin plus eigene Rolle „Game Designer"** — nicht Lehrende, nicht nur Admin |

Diese fünf Entscheidungen sind alle bis heute wirksam. Die Site-weite Label-Regel ist der Grund,
warum die Capability `managelabels` Lehrenden bewusst nicht gegeben wird; die Absage an den
`maxima-string`-Hack ist der Grund für das eigene Datenmodell.

## Datenmodell

Von Beginn an auf Erweiterbarkeit angelegt, eine Tabelle je Verantwortungsbereich:

* `..._label` — die „Spielwelt"-Bezeichnung, die Fortschritt zusammenfasst
* `..._gamestate` — Fortschritt je Spieler × Label
* `..._score` — Punktetypen, erweiterbar
* `..._inventory` — Gegenstände und Booster
* `..._quiz` — quizspezifische Konfiguration

## Offener Punkt: Asset-Lizenzen

Die rund 30 Assets des Ausgangsskripts stammen aus zwei Quellen: Sprites von craftpix.net,
UI-Elemente vermutlich von freepik/fontawesome. Beide arbeiten mit Lizenzmodellen, die eine
Übernahme in ein veröffentlichtes Plugin nicht ohne Weiteres erlauben — craftpix mit Free- und
Bezahlvarianten, freepik mit Attributionspflicht im Free-Tier.

Das wurde als potentieller Blocker für eine Veröffentlichung festgehalten. Als Ausweg wurden
CC0-Quellen wie OpenGameArt.org und itch.io genannt.

> **Nachtrag aus Sitzung 003 (Patch 21): erledigt, und zwar von selbst.** Beim Nachsehen im
> ausgelieferten Stand zeigte sich, dass keines dieser Assets je übernommen wurde. Ausgeliefert
> werden 18 selbst erzeugte SVG-Platzhalter — ein farbiges Rechteck mit dem Asset-Schlüssel als
> Beschriftung. Der Blocker bestand nur noch auf dem Papier. Geblieben war eine irreführende
> `LICENSE.txt` je Paket („License status to be curated later"), die einen offenen Rechtsstatus
> suggerierte, wo keiner war; sie ist durch eine präzise Angabe ersetzt.

## Ergebnis

Auf Basis der fünf Entscheidungen wurde das vollständige Plugin-Skelett erzeugt: `db/install.xml`,
`classes/external/submit_answer.php` und der refaktorierte AMD-Kern.
