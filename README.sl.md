# WP Bunny Stream

> [🇬🇧 English](README.md) · [🇫🇷 Français](README.fr.md) · 🇸🇮 Slovenščina

Samostojno gostujoči most med **WordPressom** in **[Bunny Streamom](https://bunny.net/stream/)** — nalagajte zelo velike videoposnetke, jih upravljajte kot custom post type, ustvarjajte poglavja / trenutke / podnapise z UI in vgradite popolnoma prilagodljiv predvajalnik.

## Funkcionalnosti

- **Custom post type** `bunny_video` z vgrajenima taksonomijama **kategorije** in **oznake**.
- **Nadaljljivo nalaganje TUS** — datoteke več GB, brez težav z `upload_max_filesize` v PHP.
- **Ročno povezovanje preko GUID** — pripnite katerikoli obstoječi Bunny videoposnetek na WordPress objavo.
- **Samodejna sličica** — izpostavljena slika prenešena z Bunny CDN.
- **Sprejemnik webhookov** — status, podnapisi, dimenzije in UI dogodki se sinhronizirajo samodejno.
- **Domorodni Bunny predvajalnik** s prepisljivimi privzetimi nastavitvami (autoplay, loop, muted, preload, barva poudarka, začetni čas).
- **Samodejno zaznavanje razmerja** (16:9, 9:16, 1:1, …) z omejeno širino za pokončne videoposnetke.
- **Shortcode** `[bunny_video id="42"]`.
- **Gutenberg blok** z iskalnikom v živo in možnostmi predvajalnika.
- **Beaver Builder modul** s prilagojenim samodokončalnikom.
- **UI funkcionalnosti**:
  - Ustvarjanje samo poglavij in trenutkov (brezplačno, če transkripcija obstaja).
  - Transkripcija + ustvarjanje podnapisov + naslov + opis + poglavja + trenutki. Večjezično z integracijo **Polylang**.
- **Urejevalniki** za poglavja, trenutke in podnapise (VTT / SRT). Vgrajen VTT urejevalnik za popravljanje tipkarskih napak.

## Namestitev

1. Prenesite ali klonirajte ta repozitorij v `wp-content/plugins/wp-bunny-stream`.
2. Aktivirajte **WP Bunny Stream** v WordPressu.
3. Pojdite v **Bunny Videos → Settings** in vnesite:
   - **Library ID** (številčni, npr. `661274`)
   - **API ključ** (iz Stream → vaša knjižnica → API)
   - **CDN ime gostitelja** (neobvezno, uporablja se za sličice in pridobivanje podnapisov, npr. `vz-xxxxx.b-cdn.net`)
4. (Neobvezno) Dodajte URL webhooka, prikazan na strani z nastavitvami, v svojo Bunny knjižnico, da se statusi objav samodejno posodabljajo. Nastavite skupno skrivnost na isti strani in dodajte `?secret=VAŠA_SKRIVNOST` na URL.

## Uporaba

### Nalaganje videoposnetka
1. **Bunny Videos → Add New**.
2. Vnesite naslov, izberite kategorije / oznake.
3. Povlecite video datoteko v meta okno.
4. Nalaganje napreduje v nadaljljivih kosih. Bunny GUID se shrani na objavo **preden** se TUS zažene, tako da je tudi prekinjeno nalaganje obnovljivo.
5. Kliknite **Save / Publish** — stran se ne osveži samodejno.

### Povezovanje obstoječega Bunny videoposnetka
Če videoposnetek že obstaja na Bunnyju (naložen preko nadzorne plošče ali drugega orodja), uporabite razdelek **Link an existing Bunny video by GUID** v meta oknu. Prilepite GUID, vtičnik ga preveri preko API in pridobi metapodatke (trajanje, dimenzije, sličica).

### UI: poglavja, trenutki, podnapisi
Plošča **AI** v meta oknu ima dva gumba:
- **Generate chapters + moments** — kliče `/smart`. Brezplačno, če ima videoposnetek že transkripcijo.
- **Transcribe + generate all (paid)** — kliče `/transcribe`. Sproži transkripcijo, prevod v ciljne jezike (Polylang-zavedno), ter UI poglavja/trenutke. Zaračuna se **$0.10 na minuto na jezik** s strani Bunny.

Ko Bunny zaključi obdelavo (asinhrono), kliknite **Refresh status**, da prenesete rezultate v urejevalnike v meta oknu.

### Ročno urejanje
- **Poglavja** — mreža vrstic *naslov / začetek / konec*. Sprejema `mm:ss`, `hh:mm:ss` ali surove sekunde. Ob shranjevanju razvrščeno po času začetka in poslano na Bunny.
- **Trenutki** — *oznaka / časovni žig*. Prikazani kot oznake na časovnici Bunny predvajalnika.
- **Podnapisi** — seznam po jezikih z **Edit** (vgrajeni VTT urejevalnik, vsebina pridobljena iz CDN), **Delete**, **+ Add caption** (nalaganje datoteke `.vtt` / `.srt` ali prilepljanje vsebine).

### Vgradnja
| Metoda | Primer |
|---|---|
| Shortcode | `[bunny_video id="42" autoplay="1" ratio="9:16"]` |
| Gutenberg blok | *Bunny Video* — izberite preko iskalnega polja |
| Beaver Builder | Modul *Bunny Video* — izbirnik z iskanjem med tipkanjem |
| PHP | `echo WPBS_Shortcode::render( [ 'id' => 42 ] );` |

#### Atributi shortcodea

| Atribut | Privzeto | Opis |
|---|---|---|
| `id` | – | ID objave tipa `bunny_video` |
| `guid` | – | Neposreden Bunny GUID (prepiše `id`) |
| `autoplay` / `loop` / `muted` / `preload` | globalna privzetost | Prepiše možnosti predvajalnika |
| `color` | globalna privzetost | Hex barva za poudarek predvajalnika |
| `t` | – | Začetni čas v sekundah |
| `ratio` | auto | `auto`, `16:9`, `9:16`, `1:1`, `4:3`, `21:9`, `4:5`, ali decimalna `H/W` |
| `width` | – | Maksimalna širina (px ali %) |

## Polylang

Če je nameščen Polylang, so izbirniki jezikov v plošči UI napolnjeni z vašimi Polylang jeziki in trenutni jezik objave je prednastavljen kot izvor transkripcije. Brez Polylanga se uporabi WordPress jezik strani.

## Webhook

Končna točka: `https://vasespletisce.tld/wp-json/wp-bunny-stream/v1/webhook`

Vrstni red preverjanja:
1. Če je nastavljena skupna skrivnost, mora zahteva vključevati `?secret=VAŠA_SKRIVNOST`.
2. V nasprotnem primeru vtičnik preveri HMAC-SHA256 surovega telesa z Read-only API ključem knjižnice, ki ga Bunny pošlje preko `X-BunnyStream-Signature`.

Obravnavani statusi (`3` Finished, `4` Resolution finished, `9` Captions generated, `10` Title/description generated) sprožijo polno sinhronizacijo dimenzij, trajanja, podnapisov, poglavij, trenutkov in sličice.

## Hooks / filtri

Vtičnik namensko ohranja ravno površino. Vstopne točke za ponovno uporabo:
- `WPBS_Bunny_API::*` — javne metode za klicanje katerekoli Bunny končne točke iz vaše kode.
- `WPBS_Shortcode::render( $atts )` — neposredno izrise HTML iframe.
- `WPBS_Webhook::sync_video_to_post( $post_id, $video_array )` — ponovno uporabi logiko sinhronizacije.

Prilagojeni meta ključi (registrirani preko `register_post_meta` z `show_in_rest`):
`_wpbs_video_guid`, `_wpbs_library_id`, `_wpbs_status`, `_wpbs_duration`, `_wpbs_width`, `_wpbs_height`, `_wpbs_thumbnail_url`, `_wpbs_chapters`, `_wpbs_moments`, `_wpbs_captions`, `_wpbs_description`, `_wpbs_smart_status`, `_wpbs_player_override`.

## Odpravljanje težav

| Simptom | Rešitev |
|---|---|
| Modul se prikaže prazen | Rdeč diagnostični trak se prikaže administratorjem / v Beaver Builderju in pokaže točno, kaj manjka (ni GUID, ni knjižnice, ni izbire). |
| Beaver Builder izbirnik prazen | Prepričajte se, da so videoposnetki na seznamu **Bunny Videos**. Izbirnik prikaže osnutke s pripono `[draft]` in osamele objave z `⚠ no upload`. |
| Nalaganje končano na Bunnyju, a ne na WP | Odprite objavo, uporabite **Link an existing Bunny video by GUID** za povezavo. |
| Plošča UI brez jezika | Namestite **Polylang** ali nastavite jezik WordPress strani. |

## Zahteve

- WordPress 6.0+
- PHP 7.4+
- Bunny Stream knjižnica

## Licenca

GPL-2.0-or-later. Glejte [LICENSE](LICENSE).
