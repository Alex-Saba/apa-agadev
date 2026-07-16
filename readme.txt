=== Plugin APA Agadev ===
Contributors: agadev
Tags: apa, agadev, maivou, agreements, lots
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 2026.7.11
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client WordPress des formulaires APA, des accords et des fiches publiques de lots finalises exposees par Maivou via ACL WordPress API Bridge.

== Description ==

APA Agadev restitue dans WordPress les donnees APA fournies par Maivou.

Le plugin permet :

* de recuperer le formulaire APA filtre pour l'utilisateur connecte ;
* de transmettre une demande APA a Maivou ;
* d'afficher les accords visibles par l'utilisateur ;
* de synchroniser localement les lots `sold` et `cancelled` ayant une request associee ;
* d'exposer chaque lot finalise sur une fiche publique `/lot/{code}/`.

Il n'existe aucune page de liste ou archive publique des lots.

APA Agadev reste un client leger. Il ne gere ni l'authentification Maivou ni la
configuration de l'API. Tous les appels sont delegues a la fonction
`acl_flows_api_call()` fournie par ACL WordPress API Bridge.

== Prerequis ==

* WordPress 6.0 ou superieur ;
* PHP 8.0 ou superieur ;
* ACL WordPress API Bridge installe, active et configure ;
* le client machine Maivou autorise a demander le scope `lots.read` ;
* la route Maivou `GET /api/machine/lots` disponible.

Si ACL WordPress API Bridge est absent ou inactif, une notification explicite
est affichee dans l'administration WordPress.

== Installation ==

1. Installer et activer `ACL WordPress API Bridge`.
2. Configurer l'URL de l'API et les identifiants machine Maivou dans le Bridge.
3. Copier le dossier `plugin-apa-agadev` dans `wp-content/plugins`.
4. Activer `APA Agadev` depuis l'administration WordPress.
5. Consulter `Reglages > APA Agadev` pour lancer ou controler la synchronisation.
6. Inserer les shortcodes souhaites dans les pages WordPress.

L'activation enregistre le cron horaire et les permaliens `/lot/{code}/`.

== Configuration ==

La connexion a Maivou est configuree dans :

`Reglages > ACL WordPress API Bridge`

APA Agadev ne duplique pas ces reglages et ne stocke pas de second token API.
Les formulaires et accords utilisent la session de l'utilisateur connecte. La
synchronisation des lots utilise exclusivement les client credentials du Bridge
avec le scope `lots.read` et fonctionne sans utilisateur WordPress connecte.

La page `Reglages > APA Agadev` affiche :

* la date de la derniere tentative de synchronisation ;
* le nombre de lots synchronises ;
* le nombre d'erreurs ;
* le resultat explicite du dernier traitement ;
* le bouton securise `Synchroniser maintenant`.

== Flux fonctionnels ==

= Formulaire APA (`[apa_agadev_form]`) =

1. Le plugin envoie `POST /api/_catalog` avec la cle `agreement`.
2. Maivou retourne le formulaire filtre selon le role de l'utilisateur.
3. Le plugin construit les champs WordPress a partir de ce catalogue.
4. L'utilisateur complete et valide le formulaire.
5. Le plugin normalise uniquement les champs declares par Maivou.
6. Le plugin envoie la demande avec `POST /api/agreements`.
7. Le resultat Maivou est affiche sans inventer de donnees de remplacement.

Les champs de fichier sont signales comme indisponibles tant que Maivou
n'expose pas de contrat de televersement pour ces documents.

= Accords APA (`[apa_agadev_agreements]`) =

1. Le plugin appelle `GET /api/agreements`.
2. Maivou applique l'authentification, les permissions et la visibilite du compte.
3. Le plugin restitue uniquement les accords renvoyes par l'API.

= Synchronisation des lots finalises =

1. WP-Cron declenche le traitement toutes les heures.
2. Le plugin parcourt toutes les pages de `GET /api/machine/lots` par groupes de 100.
3. Le Bridge obtient le jeton machine avec le scope `lots.read`.
4. Le plugin accepte uniquement les lots `sold` ou `cancelled` possedant une request associee.
5. Chaque lot est cree ou mis a jour dans le CPT local `apa_lot` selon son UUID, puis son code.
6. Le payload public et l'association `request.uuid -> lot.code` sont conserves en metadata WordPress.
7. Une erreur API ou de pagination n'efface aucun lot deja synchronise.

Le plugin effectue egalement le controle des statuts cote WordPress. Un element
non final ou sans request est refuse meme si le contrat distant derive.

= Fiche publique d'un lot =

Chaque lot synchronise est publie sous l'URL canonique :

`/lot/{code}/`

La fiche affiche le code, le statut traduit (`Vendu` ou `Annule`), le produit,
la quantite, le conditionnement, la province, le departement et la signature
lorsqu'elle est disponible. Elle restitue egalement le collecteur, le reclamant,
la date de claim, la request associee, son statut et son historique public.

Les emails, telephones, permissions, metadata privees, pieces jointes et
identifiants numeriques internes ne sont jamais affiches sur cette fiche.

Les images du produit, de la zone, du collecteur et du reclamant sont choisies
pour chaque lot dans la mediatheque WordPress. Elles ne sont pas remplacees par
la synchronisation Maivou.

Le CPT est exclu de la recherche WordPress et ne possede aucune archive publique.

= Compatibilite des QR existants =

Une ancienne URL dont le chemin contient uniquement l'UUID de la request est
resolue grace a la metadata locale. Si la request est connue, WordPress effectue
une redirection permanente vers `/lot/{code}/`. Si elle n'est pas synchronisee,
WordPress retourne une erreur 404 explicite.

== Shortcodes ==

* `[apa_agadev_form]` : affiche le formulaire APA filtre et transmet la demande.
* `[apa_agadev_form role="chercheur"]` : demande a Maivou le catalogue correspondant au role indique ; son utilisation reste soumise aux autorisations de l'API.
* `[apa_agadev_agreements]` : affiche les accords APA visibles par l'utilisateur connecte.

Il n'existe plus de shortcode de liste de lots. L'ancien shortcode
`[apa_agadev_public_lots]` n'est plus enregistre.

== Routes Maivou utilisees ==

* `POST /api/_catalog` : recuperation du formulaire filtre avec la cle `agreement` ;
* `POST /api/agreements` : creation d'une demande APA ;
* `GET /api/agreements` : consultation des accords visibles ;
* `GET /api/machine/lots` : synchronisation paginee des lots finalises.

Les erreurs HTTP et les reponses invalides de Maivou sont affichees ou stockees
explicitement dans le resultat de synchronisation.

== Release automatique via GitHub Actions ==

Le workflow `.github/workflows/release.yml` cree une Release GitHub a chaque
push sur la branche `main`.

Procedure :

1. Incrementer la version dans l'en-tete de `plugin-apa-agadev.php`.
2. Aligner la constante `PLUGIN_APA_AGADEV_VERSION` et le `Stable tag`.
3. Pousser le commit sur `main`.
4. GitHub Actions cree automatiquement la Release `vX.Y.Z`.

== Autoupdate via GitHub ==

Le plugin interroge la derniere Release publique du depot
`Alex-Saba/apa-agadev` et s'integre au mecanisme natif de mise a jour WordPress.

Lorsqu'une version plus recente existe :

1. WordPress propose la nouvelle version ;
2. l'installation automatique est autorisee uniquement pour APA Agadev ;
3. le zipball de la Release est telecharge ;
4. le dossier extrait est replace sous le nom stable `plugin-apa-agadev` ;
5. le plugin est reactive apres l'installation.

Le depot etant public, aucun token GitHub n'est requis par APA Agadev.

== Diagnostic ==

= Synchronisation en erreur =

* verifier l'URL et les client credentials dans ACL WordPress API Bridge ;
* verifier que le client Maivou peut demander le scope `lots.read` ;
* verifier que `GET /api/machine/lots` est disponible ;
* consulter le resultat dans `Reglages > APA Agadev` ;
* utiliser `Synchroniser maintenant` pour relancer le meme traitement que le cron.

= Fiche de lot introuvable =

* verifier que le lot possede le statut `sold` ou `cancelled` ;
* verifier qu'une request lui est associee ;
* verifier qu'une synchronisation a reussi depuis sa finalisation ;
* enregistrer a nouveau les permaliens WordPress si les regles de reecriture ont ete videes.

= QR historique en erreur 404 =

Le lot associe a l'UUID de request n'a pas encore ete synchronise. Le plugin ne
fabrique aucune correspondance de remplacement.

= Listes deroulantes du formulaire vides =

Les options sont fournies par le catalogue et les endpoints declares par
Maivou. Une liste vide signifie qu'aucune option exploitable n'a ete renvoyee
pour le champ concerne.

= Aucune mise a jour WordPress proposee =

* verifier que la Release GitHub existe avec un tag de la forme `vX.Y.Z` ;
* verifier que sa version est superieure a la version installee ;
* vider le cache des mises a jour WordPress puis relancer leur verification ;
* verifier que le serveur WordPress peut joindre `api.github.com`.

== Changelog ==

= 2026.7.11 =
* Integration du formulaire APA dans la section des agrements de l'espace utilisateur.
* Remplacement des cartes par une table responsive et un etat vide explicite.
* Ajout d'une modale accessible et d'un parcours de formulaire multi-etapes.
* Conservation du formulaire autonome et prevention des doubles soumissions.

= 2026.7.10 =
* Suppression du pictogramme affiche devant le conditionnement.
* Harmonisation de la typographie des informations publiques du lot.

= 2026.7.9 =
* Suppression de l'identifiant technique de la demande sur la fiche publique.
* Traduction en francais du statut courant et de l'historique de la demande.

= 2026.7.8 =
* Remplacement des icones temporaires de la fiche publique par six pictogrammes PNG dedies.
* Association visuelle des pictogrammes au lot, au conditionnement et a la chaine de valeurs.

= 2026.7.7 =
* Ajout du collecteur et du reclamant sur la fiche publique du lot.
* Ajout de la request de claim, de son statut et de son historique public.
* Ajout d'une chronologie responsive sans exposition des donnees privees.
* Nouvelle presentation inspiree de la fiche de tracabilite Maivou.
* Gestion de quatre images par lot depuis la mediatheque WordPress.

= 2026.7.6 =
* Amelioration verticale et responsive de la fiche publique d'un lot.
* Distinction visuelle des statuts vendu et annule.
* Ajout d'un README GitHub aligne sur le style de ACL WordPress API Bridge.

= 2026.7.5 =
* Remplacement de la liste publique par la synchronisation machine des lots finalises.
* Stockage local des lots vendus ou annules ayant une request associee.
* Ajout des fiches publiques individuelles `/lot/{code}/` sans archive.
* Ajout de la redirection des anciens QR bases sur l'UUID de request.
* Ajout du cron horaire et de la synchronisation manuelle dans le back-office.
* Suppression du shortcode `[apa_agadev_public_lots]`.

= 2026.7.4 =
* Amelioration responsive de la grille des lots publics.
* Protection des cartes contre le debordement des identifiants de lot.
* Documentation fonctionnelle alignee sur ACL WordPress API Bridge.
* Ajout d'une page de documentation des shortcodes dans le back-office WordPress.

= 2026.7.3 =
* Alignement des shortcodes APA sur la structure et les styles frontend ACL.

= 2026.7.2 =
* Ajout du formulaire APA interactif et de sa soumission.
* Ajout de la restitution des accords visibles.
* Ajout des lots publics via ACL WordPress API Bridge.

= 2026.7.1 =
* Creation initiale du plugin.
