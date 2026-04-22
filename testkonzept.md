# Testkonzept & Regressionstests: Flattask Application

## 1. Teststrategie
Um die Stabilität der Anwendung bei Weiterentwicklungen zu gewährleisten, wird eine automatisierte Regressions-Testsuite implementiert. Diese konzentriert sich auf die **End-to-End (E2E) Funktionalität** der kritischen Geschäftsprozesse.

**Tools:** Playwright (E2E), Axios (API Integration Tests).

## 2. Testbereiche & Testfälle

### 2.1 Authentifizierung (Auth)
- **TC-AUTH-01:** Login mit gültigen Daten -> Erwartet: Weiterleitung zum Dashboard, Token im LocalStorage.
- **TC-AUTH-02:** Login mit ungültigen Daten -> Erwartet: Fehlermeldung, kein Login.
- **TC-AUTH-03:** Logout -> Erwartet: Zurück zum Login, Token gelöscht.

### 2.2 Aufgabenverwaltung (Todos)
- **TC-TODO-01:** Erstellen einer Aufgabe -> Erwartet: Aufgabe erscheint in der Liste und ist nach Reload vorhanden.
- **TC-TODO-02:** Status ändern (offen -> erledigt) -> Erwartet: Visuelle Kennzeichnung (durchgestrichen), Status-Update im Backend.
- **TC-TODO-03:** Inline-Edit Titel -> Erwartet: Speicherung bei Blur, Persistenz geprüft.
- **TC-TODO-04:** Pinning -> Erwartet: Erscheint in der Favoriten-Leiste oben.

### 2.3 Arbeitszeiterfassung (Time Tracking)
- **TC-TIME-01:** Timer Start/Stopp (Einfach) -> Erwartet: `accumulatedMs` erhöht sich um die Differenz.
- **TC-TIME-02:** Kumulative Session -> Erwartet: Start -> 5s warten -> Stop -> Start -> 5s warten -> Stop. Gesamtsumme muss ca. 10s sein.
- **TC-TIME-03:** Reload-Stabilität -> Erwartet: Laufender Timer läuft nach Seiten-Reload nahtlos weiter.

### 2.4 Notizen & Tagebuch (Notes)
- **TC-NOTE-01:** Notiz schreiben -> Erwartet: Automatisches Speichern bei Blur, Persistenz geprüft.
- **TC-NOTE-02:** Suche -> Erwartet: Filtert vergangene Notizen korrekt nach Suchbegriff.

### 2.5 Einstellungen & Theme
- **TC-SET-01:** Dark Mode Toggle -> Erwartet: UI-Farben ändern sich, Einstellung bleibt nach Reload erhalten.

## 3. Testautomatisierung (Regression)
Die Tests werden in der Datei `tests/regression.spec.js` hinterlegt und können manuell oder via CI/CD getriggert werden.

### Ausführung:
```bash
npm run test:regression
```
