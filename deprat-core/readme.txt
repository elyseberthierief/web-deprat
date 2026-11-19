DEPRAT Core 0.1.48

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

Slider compact — format 1920 × 440 px :
- Format unique des prochaines bannières : 1920 × 440 px (JPG/WebP, moins de 500 Ko conseillé).
- Hauteur proportionnelle à l'écran, visuel recouvrant tout le bandeau et catégories remontées avec léger chevauchement.
- Les anciens visuels 1920 × 650 peuvent être recadrés verticalement ; préparer les prochaines images au format exact.
- Sur mobile, hauteur compacte et adaptation responsive.
