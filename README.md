# APA Agadev

Plugin WordPress qui restitue les formulaires et accords APA fournis par Maivou, puis synchronise localement les lots finalises pour les publier sous `/lot/{code}/`.

Tous les appels HTTP et l'authentification Maivou sont delegues a **ACL WordPress API Bridge** via `acl_flows_api_call()`.

## Prerequis

- WordPress 6.0 ou superieur.
- PHP 8.0 ou superieur.
- ACL WordPress API Bridge installe, active et configure.
- Un client machine Maivou autorise a demander le scope `lots.read`.
- La route Maivou `GET /api/machine/lots` disponible.

Le plugin ne duplique ni l'URL de l'API, ni les identifiants machine, ni les tokens geres par le Bridge.

## Installation

1. Installer et activer `ACL WordPress API Bridge`.
2. Configurer l'URL de Maivou et les identifiants machine dans le Bridge.
3. Copier le plugin dans `wp-content/plugins/plugin-apa-agadev`.
4. Activer `APA Agadev` depuis l'administration WordPress.
5. Ouvrir `Reglages > APA Agadev` pour controler la synchronisation.
6. Ajouter les shortcodes necessaires dans les pages WordPress.

L'activation enregistre le cron horaire ainsi que les permaliens `/lot/{code}/`.

## Configuration

La connexion a Maivou est configuree dans :

`Reglages > ACL WordPress API Bridge`

La page `Reglages > APA Agadev` permet de consulter :

- la date de la derniere tentative de synchronisation ;
- le nombre de lots synchronises ;
- le nombre d'erreurs ;
- le resultat explicite du dernier traitement ;
- le bouton securise `Synchroniser maintenant`.

Les formulaires et les accords utilisent la session de l'utilisateur connecte. La synchronisation des lots utilise les client credentials du Bridge et fonctionne sans utilisateur WordPress connecte.

## Flux fonctionnels

### Formulaire APA (`[apa_agadev_form]`)

1. Le plugin envoie `POST /api/_catalog` avec la cle `agreement`.
2. Maivou retourne le formulaire filtre selon les droits de l'utilisateur.
3. Le plugin construit le formulaire WordPress a partir de ce catalogue.
4. L'utilisateur renseigne puis valide les champs.
5. Le plugin normalise uniquement les donnees declarees par Maivou.
6. La demande est envoyee avec `POST /api/agreements`.
7. La reponse ou l'erreur Maivou est affichee explicitement.

Les champs de fichier restent indisponibles tant que Maivou ne fournit pas de contrat de televersement pour les documents concernes.

### Accords APA (`[apa_agadev_agreements]`)

1. Le plugin appelle `GET /api/agreements`.
2. Maivou applique l'authentification et les permissions du compte.
3. WordPress restitue uniquement les accords renvoyes par l'API.

### Synchronisation des lots finalises

1. WP-Cron declenche la synchronisation toutes les heures.
2. Le plugin parcourt toutes les pages de `GET /api/machine/lots` par groupes de 100.
3. ACL WordPress API Bridge obtient un jeton machine avec le scope `lots.read`.
4. Seuls les lots `sold` ou `cancelled` possedant une request associee sont acceptes.
5. Chaque lot est cree ou mis a jour dans le CPT `apa_lot` selon son UUID, puis son code.
6. Le payload public et l'association `request.uuid -> lot.code` sont stockes dans les metadonnees WordPress.
7. Une erreur API ou de pagination ne supprime aucun lot deja synchronise.

Le controle des statuts est egalement effectue cote WordPress. Un lot non final, sans UUID valide ou sans request associee est refuse.

### Fiche publique d'un lot

Chaque lot synchronise est disponible sous l'URL canonique :

```txt
/lot/{code}/
```

La fiche affiche :

- le code du lot ;
- le statut traduit en `Vendu` ou `Annule` ;
- le produit ;
- la quantite ;
- le conditionnement ;
- la province ;
- le departement ;
- la signature lorsqu'elle est disponible.

Le CPT ne possede aucune archive publique et reste exclu de la recherche WordPress. Il n'existe pas de page de liste publique des lots.

### Compatibilite des QR existants

Une ancienne URL contenant uniquement l'UUID de la request est resolue grace a l'association stockee localement.

- Si la request est connue, WordPress effectue une redirection permanente vers `/lot/{code}/`.
- Si aucune correspondance n'a ete synchronisee, WordPress retourne une erreur 404.

Aucune page de liste basee sur les requests n'est creee.

## Shortcodes

- `[apa_agadev_form]` : affiche le formulaire APA filtre et transmet la demande.
- `[apa_agadev_form role="chercheur"]` : demande le catalogue correspondant au role indique, sous reserve des permissions Maivou.
- `[apa_agadev_agreements]` : affiche les accords visibles par l'utilisateur connecte.

L'ancien shortcode `[apa_agadev_public_lots]` n'est plus enregistre. Les lots sont accessibles uniquement par leur fiche individuelle.

## Routes Maivou utilisees

- `POST /api/_catalog` : recuperation du formulaire filtre avec la cle `agreement`.
- `POST /api/agreements` : creation d'une demande APA.
- `GET /api/agreements` : consultation des accords visibles.
- `GET /api/machine/lots` : synchronisation paginee des lots finalises.

## Release automatique via GitHub Actions

Le workflow `.github/workflows/release.yml` cree une Release GitHub a chaque push sur `main`.

Usage :

1. Incrementer la version dans l'en-tete de `plugin-apa-agadev.php`.
2. Aligner `PLUGIN_APA_AGADEV_VERSION` et le `Stable tag` de `readme.txt`.
3. Ajouter l'entree correspondante dans le changelog.
4. Pousser le commit sur `main`.
5. GitHub Actions cree la Release `vX.Y.Z`.

## Autoupdate via GitHub

Le plugin interroge la derniere Release publique du depot `Alex-Saba/apa-agadev` et utilise le mecanisme natif de mise a jour WordPress.

Lorsqu'une version plus recente est disponible :

1. WordPress propose la nouvelle version.
2. L'installation automatique est autorisee pour APA Agadev.
3. Le zipball de la Release est telecharge.
4. Le dossier extrait est renomme `plugin-apa-agadev`.
5. Le plugin est reactive apres l'installation.

Le depot etant public, aucun token GitHub n'est requis par APA Agadev.

## Diagnostic

### Synchronisation en erreur

- Verifier l'URL et les client credentials dans ACL WordPress API Bridge.
- Verifier que le client Maivou peut demander le scope `lots.read`.
- Verifier que `GET /api/machine/lots` est disponible.
- Consulter le resultat dans `Reglages > APA Agadev`.
- Utiliser `Synchroniser maintenant` pour relancer le meme traitement que le cron.

### Fiche de lot introuvable

- Verifier que le lot apparait dans le menu `Lots APA`.
- Verifier que son statut Maivou est `sold` ou `cancelled`.
- Verifier qu'une request lui est associee.
- Enregistrer de nouveau les permaliens WordPress.
- Utiliser l'URL `/lot/{code}/`, avec le slug WordPress en minuscules si necessaire.

### QR historique en erreur 404

Le lot associe a l'UUID de request n'a pas encore ete synchronise. Le plugin ne fabrique aucune correspondance de remplacement.

### Listes deroulantes du formulaire vides

Les options proviennent du catalogue et des endpoints declares par Maivou. Une liste vide signifie qu'aucune option exploitable n'a ete retournee pour le champ concerne.

### Aucune mise a jour WordPress proposee

- Verifier que la Release GitHub existe avec un tag de la forme `vX.Y.Z`.
- Verifier que sa version est superieure a la version installee.
- Vider le cache des mises a jour WordPress puis relancer leur verification.
- Verifier que le serveur WordPress peut joindre `api.github.com`.

## Verification

Commandes recommandees avant une release :

```bash
composer validate
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;
git diff --check
```

