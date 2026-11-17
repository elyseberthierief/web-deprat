# DEPRAT — revue de sécurité du code (17 novembre 2026)

## Portée

Analyse statique du code PHP et JavaScript versionné dans `deprat-core/` et `wp-content/code-snippets/` :
Semgrep Community Edition avec les règles `p/security-audit` et `p/owasp-top-ten`.
Gitleaks vérifie les secrets dans l'historique Git. L'audit npm s'applique uniquement si un `package-lock.json` est présent.

**Important** : les résultats de Semgrep sont des *signalements potentiels*, pas des preuves d'exploitation.
Le contrôle ne remplace ni les tests de permissions réels ni l'audit de l'installation WordPress en production.

## Résultat initial : 9 signalements

| Signalement initial | Gravité scanner | Analyse / modification |
|---|---|---|
| `global-09.php:631,634` — chemin de stockage PDF | 2 WARNING | Les noms sont générés côté serveur avec un ID entier, un type autorisé (`devis` ou `facture`) et 16 caractères hexadécimaux aléatoires ; une regex stricte a été ajoutée avant l'écriture. Stockage chiffré dans le dossier privé WordPress, exception ciblée au scanner après validation. |
| `global-09.php:842` — sortie PDF binaire | 1 ERROR | Authentification, autorisation liée au propriétaire, nonce lié au document et à sa version, signature `%PDF-`, réponse `application/pdf`, `nosniff` et `no-store`. On ne peut pas appliquer `esc_html()` à un PDF sans corrompre le flux : exception ciblée. |
| `global-09.php:1287,1291` — choix du statut/période | 2 ERROR | `<option>` généré à partir d'options contrôlées ; attribut de sélection désormais une chaîne littérale plutôt qu'un résultat de fonction conditionnelle que Semgrep interprétait comme taint. `esc_attr` / `esc_html` sont conservés. |
| `global-09.php:1388,1419` — liens devis et facture | 2 ERROR | Lien construit par le serveur pour une demande soumise aux contrôles d'accès et aux nonces. `esc_url` et `wp_kses_post` appliqués ; exceptions localisées et commentées, sans désactiver la règle générale. |
| `global-09.php:1454` — pagination de l'historique | 1 ERROR | `add_query_arg`, `esc_url`, `esc_html` et `wp_kses_post` ; exception localisée après revue du code, pas d'HTML provenant directement de GET. |
| `catalogue-archive.php:219` — pagination du catalogue | 1 ERROR | Les paramètres sont sélectionnés parmi une liste autorisée et nettoyés ; la base URL est échappée, le HTML `paginate_links()` filtré par `wp_kses_post`. Exception localisée et commentée. |

Les exceptions `nosemgrep` sont spécifiques à un identifiant de règle, sur une ligne précise, et documentées à côté du code. Elles **ne désactivent pas** les scanners sur d'autres lignes ou de nouvelles fonctionnalités.

## Tests de non-régression ajoutés

- Paramètres GET malveillants de recherche, statut et période : vérifier qu'aucune balise HTML exécutable n'apparaît dans l'historique.
- Devis rattaché à un client : vérifier qu'un autre compte ne peut pas le consulter.
- Accès HTTP anonyme à `?deprat_document=1` : réponse attendue **403**.
- La suite actuelle de tests techniques et navigateur WordPress reste obligatoire.

## Contrôles restants et limites

- Auditer une installation **réelle** (versions WordPress, WooCommerce, extensions tierces, thème, PHP, serveur, headers HTTP et configuration des sauvegardes) : hors de portée du dépôt seul.
- Faire contrôler le cycle de devis/commande par un test de sécurité manuel avec comptes client et ADV séparés, notamment les autorisations et la gestion des fichiers.
- Maintenir les dépendances, renouveler les clés compromises, configurer des sauvegardes et un monitoring des connexions.
- Une CI verte signifie seulement qu'aucun nouveau signalement de gravité ERROR n'a été trouvé *dans les règles et fichiers analysés*.

Référence : PR de remédiation n°23.
