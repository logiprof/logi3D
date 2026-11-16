# Logi3D

Extension WordPress de **logiProf** : animations pédagogiques 3D pour la logistique (surfaces, volumes, rayonnages à palettes…). Chaque animation a son code court, utilisable dans une page, un article ou une leçon Sensei.

## Installation

1. Télécharger **logi3d.zip** depuis la [dernière version](https://github.com/logiprof/logi3d/releases/latest).
2. WordPress → **Extensions → Ajouter → Téléverser une extension** → choisir le .zip → **Activer**.

Ensuite, les nouvelles versions publiées ici arrivent toutes seules sur le site : Logi3D se met à jour automatiquement. Le lien **« Rechercher une mise à jour »** sous Logi3D, dans la page Extensions, force la vérification.

## Animations incluses

| Animation | Code court |
|---|---|
| Surfaces et volumes d'un entrepôt | `[logi3d_surfaces_volumes]` |
| Surfaces d'un entrepôt | `[logi3d_surfaces_entrepot]` |
| Volumes d'un entrepôt | `[logi3d_volumes_entrepot]` |
| Volumes d'un rayonnage à palettes | `[logi3d_volumes_rayonnage]` |

D'autres animations (fichiers `.html`) s'ajoutent depuis le menu **Logi3D** de WordPress, sans mise à jour de l'extension. Options : `logo="non"` pour cacher le logo, `hauteur="900"` pour la hauteur de départ.

## Publier une nouvelle version

1. Dans `logi3d.php`, augmenter la version aux **deux** endroits : la ligne `* Version:` et `define( 'LOGI3D_VERSION', … )`.
2. Pour une animation incluse : déposer ou remplacer son fichier dans `animations/` (le nom du fichier donne le code court : `mon-animation.html` → `[logi3d_mon_animation]`).
3. Créer une étiquette avec le même numéro, précédé de `v`, et la pousser :

   ```bash
   git tag v1.6.0
   git push origin main --tags
   ```

L'action GitHub **« Publier une version »** construit `logi3d.zip` et crée la release. Les sites voient la mise à jour dans les 6 heures (ou tout de suite avec « Rechercher une mise à jour »). Si les numéros ne correspondent pas, l'action s'arrête avec un message d'erreur.

## Structure

```
logi3d.php                        extension (codes courts, menu, logo)
includes/class-logi3d-updater.php mises à jour depuis GitHub
assets/logi3d-front.js            hauteur automatique des animations
animations/*.html                 animations incluses
.github/workflows/release.yml     construction et publication de logi3d.zip
```
