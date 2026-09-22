# MEDIfogg — web (náhľad na pripomienkovanie)

Statický build webu MEDIfogg, nasadený cez GitHub Pages **len na interné pripomienkovanie** (build má `noindex`).
Zdrojový kód je v privátnom repe `danieldhudak-eng/medifogg-web`; tento repozitár sa prepisuje automaticky
pri každom pushi do `main` (GitHub Action) alebo ručne cez `npm run deploy:pages`.

## 🔗 Živý náhľad

**https://danieldhudak-eng.github.io/**

## Stránky

| Stránka | Odkaz |
|---|---|
| Domov | [/](https://danieldhudak-eng.github.io/) |
| Problém | [/problem](https://danieldhudak-eng.github.io/problem) |
| Inštalácia a prevádzka | [/instalacia-a-prevadzka](https://danieldhudak-eng.github.io/instalacia-a-prevadzka) |
| Úrovne systému | [/urovne-systemu](https://danieldhudak-eng.github.io/urovne-systemu) |
| Reporting | [/reporting](https://danieldhudak-eng.github.io/reporting) |
| Klinická štúdia | [/klinicka-studia](https://danieldhudak-eng.github.io/klinicka-studia) |
| Referencie | [/referencie](https://danieldhudak-eng.github.io/referencie) |
| Bezpečnosť a certifikácia | [/bezpecnost-a-certifikacia](https://danieldhudak-eng.github.io/bezpecnost-a-certifikacia) |
| O nás | [/o-nas](https://danieldhudak-eng.github.io/o-nas) |
| Kontakt | [/kontakt](https://danieldhudak-eng.github.io/kontakt) |

**Pre vašu rolu / pracovisko:** [pre technikov](https://danieldhudak-eng.github.io/pre-technikov) · [pre personál](https://danieldhudak-eng.github.io/pre-personal) · [pre management](https://danieldhudak-eng.github.io/pre-management) · [pre nemocnice](https://danieldhudak-eng.github.io/pre-nemocnice) · [pre polikliniky](https://danieldhudak-eng.github.io/pre-polikliniky) · [pre ambulancie](https://danieldhudak-eng.github.io/pre-ambulancie)

## Čo v náhľade NEFUNGUJE

- **Kontaktný formulár neodošle** – spolieha sa na `api/kontakt.php`, GitHub Pages nevie spúšťať PHP. Vizuál a validácia fungujú.
- **Presmerovania z `.htaccess`** (HTTPS, orezanie lomky, `/stranka.html` → `/stranka`) – Pages nie je Apache. Na Websupporte fungujú.

Všetko ostatné (obsah, dizajn, responzivita, videá, animácie, navigácia) zodpovedá ostrej verzii.
