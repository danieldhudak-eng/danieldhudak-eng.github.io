# MEDIfogg — web (náhľad na pripomienkovanie)

Statický build webu MEDIfogg, nasadený cez GitHub Pages **len na interné pripomienkovanie**.

Aktuálna verzia: build z 18. 9. 2026 18:16 — font Inter, 11 nových interaktívnych
komponentov (PetriDish, RoomFog, SkMap, LiveCounter, SystemDiagram a ďalšie)
a nová stránka `/lab`.

## 🔗 Živý náhľad

**https://danieldhudak-eng.github.io/**

Stačí kliknúť a preklikať sa webom ako po reálnej stránke.

## Čo si prezrieť

| Stránka | Odkaz |
|---|---|
| Domov | [/](https://danieldhudak-eng.github.io/) |
| **Design lab** (nová) | [/lab](https://danieldhudak-eng.github.io/lab) |
| Problém | [/problem](https://danieldhudak-eng.github.io/problem) |
| Úrovne systému | [/urovne-systemu](https://danieldhudak-eng.github.io/urovne-systemu) |
| Klinická štúdia | [/klinicka-studia](https://danieldhudak-eng.github.io/klinicka-studia) |
| Bezpečnosť a certifikácia | [/bezpecnost-a-certifikacia](https://danieldhudak-eng.github.io/bezpecnost-a-certifikacia) |
| Inštalácia a prevádzka | [/instalacia-a-prevadzka](https://danieldhudak-eng.github.io/instalacia-a-prevadzka) |
| Reporting | [/reporting](https://danieldhudak-eng.github.io/reporting) |
| Referencie | [/referencie](https://danieldhudak-eng.github.io/referencie) |
| O nás | [/o-nas](https://danieldhudak-eng.github.io/o-nas) |
| Kontakt | [/kontakt](https://danieldhudak-eng.github.io/kontakt) |

**Segmentové stránky:** [pre nemocnice](https://danieldhudak-eng.github.io/pre-nemocnice) · [pre polikliniky](https://danieldhudak-eng.github.io/pre-polikliniky) · [pre ambulancie](https://danieldhudak-eng.github.io/pre-ambulancie) · [pre management](https://danieldhudak-eng.github.io/pre-management) · [pre personál](https://danieldhudak-eng.github.io/pre-personal) · [pre technikov](https://danieldhudak-eng.github.io/pre-technikov)

## Čo v náhľade NEFUNGUJE

- **Kontaktný formulár neodošle.** Spolieha sa na `api/kontakt.php`, a GitHub Pages nevie spúšťať PHP. Vizuál a validácia polí fungujú, samotné odoslanie nie.
- **Presmerovania z `.htaccess`** (HTTPS redirect, orezanie koncovej lomky, `/stranka.html` → `/stranka`) sa neaplikujú — Pages nie je Apache. Na ostrom hostingu (Websupport) fungujú.

Všetko ostatné — obsah, dizajn, responzivita, videá, animácie, navigácia — zodpovedá ostrej verzii.

## Ako pripomienkovať

Najjednoduchšie: [založ Issue](../../issues/new) s názvom stránky a popisom. Prípadne pošli pripomienky priamo Danielovi.

## Technické

Build: Astro (statický export, `build.format = "file"`).
Zdrojový projekt je v samostatnom privátnom repozitári `medifogg-web` — **tu je len výsledný build, needituj to priamo.**

Súbor `.nojekyll` je nutný, aby GitHub Pages neignoroval priečinok `_astro/`.
