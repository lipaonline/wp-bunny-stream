# WP Bunny Stream

> [🇬🇧 English](README.md) · 🇫🇷 Français · [🇸🇮 Slovenščina](README.sl.md)

Pont auto-hébergé entre **WordPress** et **[Bunny Stream](https://bunny.net/stream/)** — uploadez des vidéos très volumineuses, gérez-les en tant que custom post type, générez chapitres / moments / sous-titres avec l'IA, et intégrez un player entièrement personnalisable.

## Fonctionnalités

- **Custom post type** `bunny_video` avec taxonomies **catégories** et **étiquettes** intégrées.
- **Upload TUS résumable** — fichiers multi-Go, pas de souci avec `upload_max_filesize` PHP.
- **Liaison manuelle par GUID** — rattachez n'importe quelle vidéo Bunny existante à un post WordPress.
- **Miniature automatique** — image à la une récupérée depuis le CDN Bunny.
- **Récepteur webhook** — status, captions, dimensions et événements IA synchronisés automatiquement.
- **Player Bunny natif** avec défauts surchargeables (autoplay, loop, muted, preload, couleur d'accent, temps de départ).
- **Détection automatique du ratio** (16:9, 9:16, 1:1, …) avec largeur max raisonnable pour les vidéos verticales.
- **Shortcode** `[bunny_video id="42"]`.
- **Bloc Gutenberg** avec sélecteur à recherche live et options du player.
- **Module Beaver Builder** avec autocomplétion custom.
- **Fonctionnalités IA** :
  - Générer uniquement chapitres et moments (gratuit, si une transcription existe).
  - Transcrire + générer sous-titres + titre + description + chapitres + moments. Multi-langues avec intégration **Polylang**.
- **Éditeurs** pour chapitres, moments et sous-titres (VTT / SRT). Éditeur VTT inline pour corriger les fautes.
- **Liste de chapitres cliquables** sous le player en front avec seek dans la timeline.

## Installation

1. Téléchargez ou clonez ce dépôt dans `wp-content/plugins/wp-bunny-stream`.
2. Activez **WP Bunny Stream** dans WordPress.
3. Allez dans **Bunny Videos → Settings** et saisissez :
   - **Library ID** (numérique, ex. `661274`)
   - **API key** (depuis Stream → votre bibliothèque → API)
   - **CDN hostname** (optionnel, utilisé pour les miniatures et la récupération des sous-titres, ex. `vz-xxxxx.b-cdn.net`)
4. (Optionnel) Ajoutez l'URL webhook affichée dans les réglages à votre bibliothèque Bunny pour que les statuts des posts se mettent à jour automatiquement. Définissez un secret partagé sur la même page et ajoutez `?secret=VOTRE_SECRET` à l'URL.

## Utilisation

### Uploader une vidéo
1. **Bunny Videos → Ajouter**.
2. Tapez le titre, choisissez catégories / étiquettes.
3. Déposez le fichier vidéo dans la meta box.
4. L'upload progresse par chunks résumables. Le GUID Bunny est sauvegardé sur le post **avant** que TUS ne démarre, donc même un upload interrompu reste récupérable.
5. Cliquez **Enregistrer / Publier** — la page ne se recharge pas automatiquement.

### Lier une vidéo Bunny existante
Si une vidéo existe déjà sur Bunny (uploadée via le dashboard ou un autre outil), utilisez la section **Link an existing Bunny video by GUID** dans la meta box. Collez le GUID, le plugin le valide via l'API et tire les métadonnées (durée, dimensions, miniature).

### IA : chapitres, moments, sous-titres
Le panneau **AI** de la meta box expose deux boutons :
- **Generate chapters + moments** — appelle `/smart`. Gratuit si la vidéo a déjà été transcrite.
- **Transcribe + generate all (paid)** — appelle `/transcribe`. Déclenche transcription, traduction vers les langues cibles (compatible Polylang), et chapitres/moments IA. Facturé **0,10 $/minute/langue** par Bunny.

Une fois le traitement Bunny terminé (asynchrone), cliquez **Refresh status** pour rapatrier les résultats dans les éditeurs de la meta box.

### Édition manuelle
- **Chapitres** — grille de lignes *titre / début / fin*. Accepte `mm:ss`, `hh:mm:ss` ou des secondes brutes. Trié par début et poussé vers Bunny à la sauvegarde.
- **Moments** — *label / timestamp*. Affiché en marqueurs sur la timeline du player Bunny.
- **Sous-titres** — liste par langue avec **Edit** (éditeur VTT inline, contenu récupéré depuis le CDN), **Delete**, **+ Add caption** (upload `.vtt` / `.srt` ou collage de contenu).

### Intégration
| Méthode | Exemple |
|---|---|
| Shortcode | `[bunny_video id="42" autoplay="1" ratio="9:16"]` |
| Bloc Gutenberg | *Bunny Video* — sélection par recherche |
| Beaver Builder | Module *Bunny Video* — sélecteur avec autocomplétion |
| PHP | `echo WPBS_Shortcode::render( [ 'id' => 42 ] );` |

#### Attributs du shortcode

| Attribut | Défaut | Description |
|---|---|---|
| `id` | – | ID du post `bunny_video` |
| `guid` | – | GUID Bunny direct (prime sur `id`) |
| `autoplay` / `loop` / `muted` / `preload` | défaut global | Surcharge les options du player |
| `color` | défaut global | Couleur hex pour l'accent du player |
| `t` | – | Temps de départ en secondes |
| `ratio` | auto | `auto`, `16:9`, `9:16`, `1:1`, `4:3`, `21:9`, `4:5`, ou décimal `H/W` |
| `width` | – | Largeur max (px ou %) |
| `chapters` | `auto` | `auto` affiche la liste sous le player si des données existent, `off` la cache |

## Polylang

Si Polylang est installé, les sélecteurs de langue dans le panneau IA sont peuplés avec vos langues Polylang et la langue actuelle du post est pré-sélectionnée comme source de transcription. Sans Polylang, c'est la locale WordPress qui est utilisée.

## Webhook

Endpoint : `https://votresite.tld/wp-json/wp-bunny-stream/v1/webhook`

Ordre de validation :
1. Si un secret partagé est configuré, la requête doit inclure `?secret=VOTRE_SECRET`.
2. Sinon le plugin valide le HMAC-SHA256 du body brut avec la Read-only API key de la bibliothèque, envoyé par Bunny via `X-BunnyStream-Signature`.

Les codes status traités (`3` Finished, `4` Resolution finished, `9` Captions generated, `10` Title/description generated) déclenchent une synchronisation complète des dimensions, durée, sous-titres, chapitres, moments et miniature.

## Hooks / filtres

Le plugin garde une surface plate volontairement. Points d'entrée réutilisables :
- `WPBS_Bunny_API::*` — méthodes publiques pour appeler n'importe quel endpoint Bunny depuis votre code.
- `WPBS_Shortcode::render( $atts )` — rend directement le HTML de l'iframe.
- `WPBS_Webhook::sync_video_to_post( $post_id, $video_array )` — réutiliser la logique de sync.

Clés meta custom (enregistrées via `register_post_meta` avec `show_in_rest`) :
`_wpbs_video_guid`, `_wpbs_library_id`, `_wpbs_status`, `_wpbs_duration`, `_wpbs_width`, `_wpbs_height`, `_wpbs_thumbnail_url`, `_wpbs_chapters`, `_wpbs_moments`, `_wpbs_captions`, `_wpbs_description`, `_wpbs_smart_status`, `_wpbs_player_override`.

## Dépannage

| Symptôme | Correction |
|---|---|
| Module vide en front | Un bandeau de diagnostic rouge apparaît pour les admins / dans Beaver Builder en montrant exactement ce qui manque (pas de GUID, pas de bibliothèque, rien sélectionné). |
| Sélecteur Beaver Builder vide | Vérifiez que des vidéos existent dans **Bunny Videos**. Le sélecteur affiche les brouillons avec `[draft]` et les posts orphelins avec `⚠ no upload`. |
| Upload terminé sur Bunny mais pas sur WP | Ouvrez le post, utilisez **Link an existing Bunny video by GUID** pour le rattacher. |
| Panneau IA sans langue | Installez **Polylang** ou définissez la langue du site WordPress. |

## Pré-requis

- WordPress 6.0+
- PHP 7.4+
- Bibliothèque Bunny Stream

## Licence

GPL-2.0-or-later. Voir [LICENSE](LICENSE).
