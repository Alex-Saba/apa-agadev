=== Plugin APA Agadev ===
Contributors: agadev
Tags: apa, agadev
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 2026.7.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress APA Agadev.

== Description ==

Client WordPress des donnees APA exposees par Maivou via ACL WordPress API Bridge.

Le plugin recupere le formulaire APA filtre, transmet les demandes, restitue
les accords visibles et affiche les lots publics sans dupliquer
l'authentification Maivou.

== Installation ==

1. Installer et configurer `ACL WordPress API Bridge`.
2. Copier le dossier `plugin-apa-agadev` dans `wp-content/plugins`.
3. Activer le plugin depuis l'administration WordPress.

== Shortcodes ==

* `[apa_agadev_form]` recupere le formulaire APA filtre et permet de transmettre une demande.
* `[apa_agadev_form role="chercheur"]` permet a un administrateur de cibler un role.
* `[apa_agadev_agreements]` affiche les accords APA visibles.
* `[apa_agadev_public_lots]` affiche uniquement les lots publics au statut `ready`.

Le formulaire fonctionne en deux temps : `POST /api/_catalog` avec la cle
`agreement`, puis `POST /api/agreements` avec les valeurs saisies. Les champs
de fichier sont signales comme indisponibles tant que Maivou n'expose pas un
contrat de televersement pour ces documents.

Les appels API et l'authentification sont delegues a `acl_flows_api_call()` fourni
par ACL WordPress API Bridge. Une erreur explicite est affichee si le Bridge ou
les donnees Maivou sont indisponibles.

== Automatic updates ==

Les mises a jour sont distribuees depuis les Releases GitHub du depot
`Alex-Saba/apa-agadev`. Chaque push sur `main` cree une Release `vX.Y.Z`, ou
`X.Y.Z` correspond a la version declaree dans `plugin-apa-agadev.php`.
WordPress telecharge directement le zipball de cette Release, puis restaure le
dossier stable `plugin-apa-agadev` apres extraction.

== Changelog ==

= 2026.7.3 =
* Alignement des shortcodes APA sur la structure et les styles frontend ACL.

= 2026.7.2 =
* Ajout du formulaire APA interactif, de sa soumission et des lots publics via ACL WordPress API Bridge.

= 2026.7.1 =
* Creation initiale du plugin.
