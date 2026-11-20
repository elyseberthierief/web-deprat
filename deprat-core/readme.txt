DEPRAT Core 0.1.54

Le plugin reprend les extraits PHP actifs dans la sauvegarde du 14 novembre 2026.
Extraits globaux : 5 à 11 et 15.
Extraits d’administration : 12 et 14.
Les CSS 678 (historique client) et 679 (typographie) sont inclus et chargés dans l’en-tête public.

Installation sur une copie de deprat.eu :
1. Téléverser le ZIP depuis Extensions > Ajouter > Téléverser une extension.
2. Comparer les extraits installés avec les versions de Code Snippets sur le site.
3. Désactiver les dix extraits listés, désactiver aussi les CSS 678 et 679 dans Simple Custom CSS and JS, puis activer DEPRAT Core.
4. Tester compte pro, prix masqués, demande de devis, historique et écran ADV.
5. Pour revenir en arrière, désactiver DEPRAT Core et réactiver les dix extraits et les deux CSS.

Page d’accueil — version 0.1.45 :
- Un diaporama pleine largeur s'ajoute au début du contenu de la page d’accueil, sous le menu Astra, avec une bande de quatre avantages.
- Dans WordPress, menu « Slides d’accueil » > Ajouter un slide : titre, texte, bouton, lien, photo mise en avant, ordre.
- Image recommandée : 1920 × 650 px (JPG/WebP, idéalement moins de 500 Ko) ; visuel à droite et espace libre à gauche.
- Plusieurs slides publiés et visibles défilent automatiquement (7 s), flèches et pastilles. Une seule slide ne montre pas de commandes inutiles.
- Pour conserver une campagne sans l’afficher : cocher « Masquer temporairement ce slide » ou cliquer sur « Masquer de l’accueil » depuis la liste. Le lien « Afficher sur l’accueil » la réactive immédiatement.
- Le shortcode [deprat_accueil_hero] sert uniquement aux configurations de page Elementor particulières et évite d’ajouter le module en double.
- Les liens relatifs des boutons (ex. /boutique/) restent valables après la migration deprat.eu → deprat.fr.

Slides d’accueil — version 0.1.46 :
- Image du slide toujours accessible via le bouton « Ajouter une image » (médiathèque WordPress), même si le thème ne propose pas d’image mise en avant.
- Une entrée de slide dispose d’un nom interne, d’un titre visible sur le site (facultatif), d’un texte (facultatif), d’un bouton (facultatif) et d’une image.
- Sans aucun texte ni bouton, seule l’image est affichée, sans bandeau de couleur par-dessus.
- Ordre configurable dans l’éditeur et modifiable par glisser-déposer dans la liste complète des slides.
- Administration harmonisée en bleu #103B80 et doré #B68B24.
- Catégories réelles WooCommerce en chevauchement sous le diaporama, icônes existantes et défilement horizontal.
- Navigation principale : court trait doré centré au survol et sur la page active, sans toucher à la flèche du méga-menu.

Version 0.1.47 :
- Le format recommandé 1920 × 650 px est enfin respecté sur grand écran : ratio natif au lieu d'une hauteur plafonnée à 440 px.
- Le bloc de catégories conserve son effet de chevauchement en masquant moins le bas de la bannière.
- Chaque catégorie et sous-catégorie WooCommerce peut avoir son propre pictogramme ou une image JPG/PNG/WebP, modifiables dans la fenêtre de création et d'édition de l'arborescence.
- Ces visuels sont utilisés dans le carrousel des catégories sur l'accueil, le méga-menu et sa prévisualisation d’administration.
- Le libellé « CATALOGUE DEPRAT » est retiré de la page d'organisation des catégories et de ses boîtes de dialogue.

Version 0.1.48 — Bibliothèque d'icônes :
- Produits > Organiser les catégories > Bibliothèque d'icônes, affichage en carrés.
- Les dix pictogrammes DEPRAT historiques sont préinstallés et restent utilisables.
- Ajouter une icône avec son nom et son image PNG/JPG/WebP (pas de SVG importé).
- Modifier le nom ou remplacer l'image via la médiathèque WordPress.
- Retirer une icône uniquement si elle n'est utilisée par aucune section ou catégorie.
- Les sections, catégories et sous-catégories partagent les mêmes icônes. Un remplacement se répercute partout sans modifier chaque catégorie.
- Enregistrement AJAX protégé par nonce et autorisations WordPress ; aucun SVG utilisateur exécuté.

Slider compact — format 1920 × 405 px :
- Format unique des prochaines bannières : 1920 × 405 px (JPG/WebP, moins de 500 Ko conseillé).
- Hauteur proportionnelle à l'écran, visuel recouvrant tout le bandeau et catégories remontées avec léger chevauchement.
- Les anciens visuels 1920 × 650 peuvent être recadrés verticalement ; préparer les prochaines images au format exact.
- Sur mobile, hauteur compacte et adaptation responsive.

Version 0.1.49 — Pictogrammes techniques officiels du PDF :
- Remplacement DIRECT des 8 pictogrammes existants Tubes, Liaison, Treuil, Sangle, Tirage, Coulisses/lames, Bras et Persiennes par les dessins fournis.
- Ajout de 3 icônes dans la bibliothèque : Embouts, Flasques guidage et bagues, Supports d’arbres.
- Chaque dessin est intégré au plugin en monochrome bleu DEPRAT, normalisé sur 80 × 80 ; aucun plugin séparé, média ou requête réseau.
- Tirage direct : illustration recadrée sur la poignée et une courte longueur de tige.
- Les icônes Porte et Composants restent disponibles sans changement.
- Les médias personnalisés déjà sélectionnés par l’ADV restent prioritaires ; les affectations par identifiants sont préservées.

Version 0.1.50 — catégories lisibles :
- Pictogrammes des titres de sections rendus plus visibles (dessins PDF renforcés et icônes agrandies).
- Méga-menu « Nos composants » : titres de sections illustrés, catégories et sous-catégories en texte seul.
- Prévisualisation admin du méga-menu : aucun pictogramme sur les lignes de catégories, mais les icônes restent dans la bibliothèque et dans la modification des catégories.
- Page d’une catégorie WooCommerce (ex. « Tubes ») : cartes cliquables pour ses seules sous-catégories directes, avec icônes conservées.
- Les filtres, références produits, catégories WooCommerce et leurs associations sont inchangés.

Version 0.1.52 — Icônes réalistes et en-tête :
- 6 images techniques 3D sur fond transparent, optimisées et centrées : supports d'arbres, flasque/guidage, porte de garage, persienne/jalousie, bras de projection, guide-sangle.
- Remplacement de 5 pictogrammes existants par identifiants stables. Guide-sangle est une NOUVELLE entrée de bibliothèque, distincte de la section Manœuvre par sangle.
- Les catégories/sous-catégories de type Guide-sangle adoptent automatiquement cette icône lorsqu'aucun choix explicite n'existe ; images personnalisées prioritaires.
- Le méga-menu conserve des listes de catégories textuelles ; leurs icônes restent présentes sur les cartes des pages catégories.
- En-tête Astra harmonisé en bleu DEPRAT / or, sans recréer de logo ; l'image officielle de la médiathèque (cropped-Logo-Deprat.png) est utilisée si disponible.
- Le menu existant « Liste de devis » est renommé « Ma demande de devis », sans changer son URL ou ses restrictions d'accès.
- Contrôler sur mobile que le logo et la navigation restent adaptés.

Version 0.1.53 — Refonte réelle de l'en-tête Astra :
- Trois lignes sur ordinateur : coordonnées / mention professionnels, logo DEPRAT et recherche WooCommerce / accès pro, puis menu Astra existant.
- Le logo réellement chargé par le site est utilisé, avec son nom complet et sa signature à droite.
- La recherche est opérationnelle et filtre les produits WooCommerce.
- Connexion, déconnexion et notifications s'appuient sur les shortcodes DEPRAT existants.
- Menu Astra et méga-menu conservés, sans remplacer les liens WooCommerce.
- Entrée « Ma demande de devis » dans le menu principal, vers le panier WooCommerce, avec compteur d'articles.
- En mobile, l'en-tête Astra natif et le menu hamburger sont conservés.
- Contrôles après déploiement : trois lignes desktop, bon logo, recherche, connexion/notifications, ouverture du méga-menu, lien devis, et affichage téléphone.

Version 0.1.54 — Paramètres de la boutique / Bandeaux catégories / Bibliothèque :
- Nouvelle entrée admin « Paramètres de la boutique » sous Produits avec 3 onglets : Organiser les catégories, Les icônes et Les slides. L'ancienne arborescence reste accessible pour compatibilité.
- Les slides catégories sont un type de contenu dédié : catégorie WooCommerce obligatoire, titre et description facultatifs, image issue de la médiathèque, ordre, brouillon/masquage. Chaque catégorie ne voit que ses propres slides, aucune transmission à ses sous-catégories.
- En l’absence de slide, bandeau dégradé avec nom et description du terme WooCommerce. Sur les pages catégories uniquement, en pleine largeur ; le catalogue général et les fiches produits ne changent pas.
- Les transitions des slides d’accueil et des catégories passent à un fondu de 850 ms, avec pause au survol, navigation manuelle et respect de « réduire les animations ».
- « Les icônes » : réparation du champ Nom mal sélectionné par le script ; modification et enregistrement de l'intitulé.
- Images non carrées : prévisualisation et cadrage 1:1, déplacement à la souris / au toucher / aux flèches, position horizontale et verticale sauvegardées dans la bibliothèque et réutilisées sur les cartes.
- En-tête : connecté = cloche + un seul contrôle compte / nom ; la déconnexion est dans le menu déroulant. Déconnecté = « Se connecter » + « Créer un compte pro » centrés sans titre redondant. Bouton bleu unique « Ma demande de devis », icône et texte cliquables ensemble, compteur doré quand le panier contient des articles.
- Navigation agrandie et soulignement actif rapproché.
- Vérifications conseillées sur desktop et mobile pour chaque modification (voir PR).

Version 0.1.56 — Bibliothèque d'icônes : déplacement visuel du recadrage.
- À l'édition, la photo source entière est chargée dans l'aperçu, pas la vignette WordPress déjà recadrée.
- Le carré de découpe est agrandi et possède des lignes de tiers ; la photo se déplace réellement et en direct au glissement.
- Un glissement lent est pris en compte grâce à un calcul cumulatif ; les % correspondent à la position visible.
- Le cadrage reste déplaçable à la souris, au toucher et aux flèches du clavier, puis enregistré.
- Ne change pas les icônes existantes tant que l'éditeur n'enregistre pas leurs modifications.
