=== Plugin APA Agadev ===
Contributors: agadev
Tags: apa, agadev, maivou, agreements, lots
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 2026.7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Client WordPress des formulaires APA, des accords et des lots publics exposes par Maivou via ACL WordPress API Bridge.

== Description ==

APA Agadev restitue dans WordPress les donnees APA fournies par Maivou.

Le plugin permet :

* de recuperer le formulaire APA filtre pour l'utilisateur connecte ;
* de transmettre une demande APA a Maivou ;
* d'afficher les accords visibles par l'utilisateur ;
* d'afficher les lots publics disponibles au statut `ready`.

APA Agadev reste un client leger. Il ne gere ni l'authentification Maivou ni la
configuration de l'API. Tous les appels sont delegues a la fonction
`acl_flows_api_call()` fournie par ACL WordPress API Bridge.

== Prerequis ==

* WordPress 6.0 ou superieur ;
* PHP 8.0 ou superieur ;
* ACL WordPress API Bridge installe, active et configure ;
* un utilisateur WordPress connecte et associe a une session Maivou valide ;
* les permissions et scopes Maivou requis pour les donnees demandees.

Si ACL WordPress API Bridge est absent ou inactif, une notification explicite
est affichee dans l'administration WordPress.

== Installation ==

1. Installer et activer `ACL WordPress API Bridge`.
2. Configurer l'URL de l'API et les identifiants Maivou dans le Bridge.
3. Copier le dossier `plugin-apa-agadev` dans `wp-content/plugins`.
4. Activer `APA Agadev` depuis l'administration WordPress.
5. Consulter `Reglages > APA Agadev` pour connaitre les shortcodes disponibles.
6. Inserer les shortcodes souhaites dans les pages WordPress.

Le plugin possede un chargeur PSR-4 interne de secours. L'installation de
Composer n'est donc pas obligatoire dans le paquet distribue.

== Configuration ==

La connexion a Maivou est configuree dans :

`Reglages > ACL WordPress API Bridge`

APA Agadev ne duplique pas ces reglages et ne stocke pas de second token API.
L'utilisateur doit etre connecte pour acceder aux routes protegees. Les donnees
retournees dependent de son role, de ses scopes et des regles de visibilite
appliquees par Maivou.

La page `Reglages > APA Agadev` fournit dans le back-office une reference des
shortcodes, de leurs parametres et de leurs conditions de fonctionnement.

== Flux fonctionnels ==

= Formulaire APA (`[apa_agadev_form]`) =

Le formulaire fonctionne en deux temps :

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

Un code HTTP `403` indique que le compte connecte ne dispose pas du scope ou de
la permission necessaire. Le plugin ne contourne pas cette autorisation.

= Lots publics (`[apa_agadev_public_lots]`) =

1. Le plugin appelle toutes les pages de `GET /api/lots`, par lots de 100 elements.
2. Maivou limite deja les resultats aux lots visibles par l'utilisateur.
3. Le plugin conserve uniquement les lots dont le statut est exactement `ready`.
4. Les cartes affichent le code, le produit, la quantite disponible et la province.

Les statuts `draft`, `pending`, `reserved`, `sold` et `cancelled` ne sont pas
affiches dans cette liste. `Aucun lot public disponible.` signifie que l'appel a
reussi mais qu'aucun lot visible au statut `ready` n'a ete retourne.

== Shortcodes ==

* `[apa_agadev_form]` : affiche le formulaire APA filtre et transmet la demande.
* `[apa_agadev_form role="chercheur"]` : demande a Maivou le catalogue correspondant au role indique ; son utilisation reste soumise aux autorisations de l'API.
* `[apa_agadev_agreements]` : affiche les accords APA visibles par l'utilisateur connecte.
* `[apa_agadev_public_lots]` : affiche les lots visibles dont le statut est `ready`.

== Routes Maivou utilisees ==

* `POST /api/_catalog` : recuperation du formulaire filtre avec la cle `agreement` ;
* `POST /api/agreements` : creation d'une demande APA ;
* `GET /api/agreements` : consultation des accords visibles ;
* `GET /api/lots` : consultation paginee des lots visibles.

Les erreurs HTTP et les reponses invalides de Maivou sont affichees explicitement.

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

= Erreur API HTTP 403 =

* verifier que l'utilisateur est connecte dans WordPress ;
* verifier que sa session Maivou est encore valide ;
* verifier les scopes et permissions de son role dans Maivou ;
* se deconnecter puis se reconnecter apres une modification de role ou de scope.

= Aucun lot public disponible =

* verifier qu'au moins un lot visible possede le statut `ready` ;
* verifier que le compte peut consulter les lots publics ;
* verifier que WordPress pointe vers le bon environnement Maivou.

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
