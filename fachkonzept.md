# Fachkonzept: Flattask Application

## 1. Einleitung und Zielsetzung
Die vorliegende Anwendung **Flattask** ist eine webbasierte, Single-Page Application (SPA) zur Verwaltung von Aufgaben (Todos). Ziel ist es, dem Nutzer ein intuitives, schnelles und hochgradig anpassbares Werkzeug für das persönliche oder berufliche Task-Management zur Verfügung zu stellen. Die Applikation zeichnet sich durch flexible Gruppierungs- und Filterfunktionen, Drag-and-Drop-Unterstützung sowie ein integriertes Tag-System aus. Besonderes Augenmerk liegt auf der Geschwindigkeit im Workflow (Effizienz) und einer klaren, modernen Ästhetik.

## 2. Zielgruppe & Anwendungsbereich
Die Anwendung richtet sich primär an Einzelnutzer, die eine datenschutzfreundliche und leichtgewichtige Lösung zur Aufgabenverwaltung suchen. Der Anwendungsbereich erstreckt sich von einfachen Einkaufslisten bis hin zur Organisation komplexer, terminierter Projekte mittels Kalenderwochen- oder Monatsgruppierungen.

## 3. Funktionsübersicht

### 3.1 Todo-Verwaltung (CRUD & Workflow)
- **Erstellen:** Neue Aufgaben können mit einem Titel (max. 500 Zeichen), einem optionalen Zieldatum, einer Rich-Text-Beschreibung, Tags und einem Status angelegt werden.
- **Lesen & Anzeigen:** Aufgaben werden übersichtlich in einer Liste oder gruppiert dargestellt. Jeder Task hat einen Status (offen oder erledigt).
- **Aktualisieren:** Inline-Bearbeitung von Titel, Zieldatum, Status und Tags. Die Bearbeitung erfolgt nahtlos ohne modale Dialoge.
- **Auto-Save (Save-on-Blur):** Änderungen an Titeln, Tags oder Beschreibungen werden automatisch gespeichert, sobald das Eingabefeld den Fokus verliert (Klick außerhalb).
- **Löschen & Archivieren:** Aufgaben werden in ein Archiv verschoben, um die Hauptliste sauber zu halten. Aus dem Archiv können sie dauerhaft gelöscht oder wiederhergestellt werden.

### 3.2 Strukturierung & Organisation
- **Tag-System:** Todos können mit mehreren Tags versehen werden. Eine Autovervollständigung (Suggested Tags) erleichtert die Zuweisung.
- **Anheften (Pinning):** Wichtige Aufgaben können "angepinnt" werden. Diese werden in der regulären Liste blau hervorgehoben und zusätzlich in einer speziellen Favoriten-Aggregation am oberen Bildschirmrand angezeigt.
- **Filterung & Suche:** 
  - Filterung nach Tags (Inklusive/Exklusive Logik).
  - Ein-/Ausblenden erledigter Aufgaben.
  - **Echtzeit-Suche mit Highlighting:** Eine intelligente Freitextsuche scannt Titel und Beschreibung. Treffer werden im Titel visuell hervorgehoben.
- **Gruppierung:** Dynamische Ansichten nach Zeit (Tag, Woche, Monat) oder Tags.
- **Sortierung:** Manuelle Sortierung per Drag-and-Drop oder automatische Sortierung nach Zieldatum. Angepinnte Aufgaben werden innerhalb ihrer Gruppen stets priorisiert.

### 3.3 Benutzeroberfläche & Interaktion (UI/UX)
- **Sticky Navigation & Favoriten:** Die Filterleiste und die Liste der angepinnten Aufgaben bleiben beim Scrollen am oberen Rand fixiert, während die restliche Liste darunter hindurchgleitet.
- **Dark Mode:** Ein systemweiter Dunkelmodus kann über einen minimalistischen Schalter in der Navigation aktiviert werden. Die Einstellung wird dauerhaft gespeichert.
- **Dynamisches Title-Scaling:** Die Schriftgröße des Aufgabentitels passt sich automatisch an dessen Länge an, um ein Umbrechen zu verhindern und die Einzeiligkeit zu wahren.
- **Truncation & Tooltips:** Sehr lange Titel werden mit "..." gekürzt. Beim Hovern erscheint sofort ein Tooltip mit dem vollständigen Text.
- **Responsive Design:** Optimierte Darstellung für Desktop und Mobilgeräte (Hamburger-Menü, Touch-Optimierung).

### 3.4 Statistik-Dashboard
Ein dediziertes Dashboard bietet einen Überblick über die eigene Produktivität:
- **Fortschritt:** Visuelle Anzeige der Erledigungsquote in Prozent.
- **Kennzahlen:** Übersicht über Gesamtzahl, offene, erledigte und überfällige Aufgaben.
- **Tag-Verteilung:** Analyse der am häufigsten genutzten Tags als Balkendiagramm.

### 3.5 Authentifizierung & Profilverwaltung
- Sicherer Login zum Schutz der Daten.
- Token-basierte Authentifizierung (Bearer Token).
- Verwaltung von Benutzernamen und Passwörtern direkt in der App.

### 3.6 Arbeitszeiterfassung (Time Tracking)
- **Kumulativer Timer:** Unterstützung für mehrere Start/Stopp-Zyklen pro Tag. Die Zeit wird präzise aufsummiert (`accumulatedMs`).
- **Live-Counter:** Echtzeitanzeige der aktuellen Arbeitszeit in der Detailansicht und minimiert in der Navigationsleiste.
- **Wochen-Visualisierung:** Grafische Darstellung der Arbeitsstunden der letzten 8 Wochen mit Zielabgleich (Weekly Goal).
- **Pausenmanagement:** Automatische Berücksichtigung von Pausenzeiten bei der Nettoberechnung.
- **Export:** Export der Zeiterfassungsdaten als CSV-Datei für die Abrechnung.

### 3.7 Notizen & Tagebuch
- **Tagesnotizen:** Kalenderbasiertes Notizsystem zur Erfassung von Gedanken oder Protokollen pro Tag.
- **Rich-Text Support:** Formatierung der Notizen mittels integriertem Editor.
- **Volltextsuche:** Suche über alle vergangenen Notizen mit Treffer-Hervorhebung.
- **Archivierung:** Notizen können archiviert werden, um die Übersicht zu wahren.

### 3.8 Backup, Archiv & Historie
- **Export/Import:** Vollständige Sicherung aller Daten (inkl. Archiv und Historie) als JSON-Datei.
- **Archiv-Management:** Funktion zum endgültigen Leeren des gesamten Archivs in einem Schritt.
- **Historie (Changelog):** Lückenlose Protokollierung aller Änderungen mit Detailansicht (Vorher/Nachher) und Undo-Funktionalität.
- **Debug-Logging:** Backend-seitige Protokollierung (`debug.log`) zur Nachverfolgung von Synchronisationsvorgängen.

### 3.9 Einstellungen (Settings)
- **Personalisierung:** Konfiguration von Standard-Pausenzeiten und wöchentlichen Arbeitsstunden-Zielen.
- **Ansichts-Optionen:** Umschalten zwischen Kompakt-Modus und Standard-Ansicht.
- **Theme-Management:** Dark Mode / Light Mode Steuerung.

## 4. Datenmodell (Erweiterte Struktur)

- **Todo-Objekt:**
  - `id`: Eindeutiger Identifier (Timestamp).
  - `name`: Titel (max. 500 Zeichen).
  - `description`: Detaillierte Beschreibung (HTML/Rich-Text).
  - `targetDate`: Optionales Zieldatum (YYYY-MM-DD).
  - `tags`: Array von Strings.
  - `status`: 'offen' oder 'erledigt'.
  - `pinned`: Boolean (wahr/falsch).
  - `order`: Integer für Sortierung.
- **Settings-Objekt:** Speichert UI-Zustände (Dark Mode), wöchentliche Ziele und Standardpausen.
- **Changelog-Objekt:** Detaillierte Änderungshistorie für Audit-Trail und Undo.
- **WorkLog-Objekt:**
  - `accumulatedMs`: Summe der bereits abgeschlossenen Sitzungen des Tages.
  - `startTimeStamp`: Beginn der aktuellen (laufenden) Sitzung.
  - `isRunning`: Status-Flag.
  - `pause`: Abzuziehende Pausenzeit in Minuten.
- **Note-Objekt:** Mapping von Datumsschlüsseln auf HTML-Inhalte.

## 5. Qualitätssicherung
- **Performance:** Die App ist auf Schnelligkeit optimiert (minimale Ladezeiten, flüssiges Scrollen).
- **Datenkonsistenz:** Transaktionale Speicherung der Flat-Files im Backend zur Vermeidung von Datenverlust.
- **Barrierefreiheit:** Kontrastreiche Darstellung (insb. im Dark Mode) und intuitive Bedienbarkeit.
