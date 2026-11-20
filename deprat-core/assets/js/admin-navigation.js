/* Administration DEPRAT — navigation métier sans toucher aux menus WordPress.
 * Tous les liens inconnus ou provenant de nouvelles extensions restent accessibles
 * dans Réglages ADMINISTRATEUR. Un lien en haut revient à WordPress classique.
 */
(function () {
    'use strict';
    function init() {
        var original = document.getElementById('adminmenu');
        var wrap = document.getElementById('adminmenuwrap');
        var config = window.depratAdminNavigation;
        if (!original || !wrap || !config || wrap.querySelector('.deprat-admin-menu')) { return; }
        var entries = [];
        var used = new Set();
        function clean(str) { return (str || '').replace(/\s+/g, ' ').trim(); }
        function normalize(url) {
            try {
                var u = new URL(url, window.location.href);
                if (u.origin !== location.origin) { return ''; }
                return u.pathname.replace(/\/$/, '') + u.search;
            } catch(e) { return ''; }
        }
        function match(url, pattern) { return pattern.test(normalize(url)); }
        Array.from(original.children).forEach(function (li) {
            if (!li.classList.contains('menu-top') || li.classList.contains('wp-menu-separator')) { return; }
            var top = li.querySelector(':scope > a.menu-top');
            if (!top) { return; }
            var children = Array.from(li.querySelectorAll(':scope > .wp-submenu a[href]')).map(function (a) {
                return {title:clean(a.textContent),url:a.href};
            }).filter(function (item) { return item.url && item.title; });
            var nativeIcon = li.querySelector(':scope > a.menu-top .wp-menu-image');
            entries.push({
                id:li.id, title:clean(li.querySelector('.wp-menu-name') && li.querySelector('.wp-menu-name').textContent || top.textContent),
                url:top.href, children:children,
                // Garder les véritables pictogrammes fournis par WordPress et les extensions.
                // Les classes dashicons, images et styles de l'icône native sont copiés
                // dans un conteneur isolé pour ne pas déplacer le menu original.
                nativeIcon: nativeIcon ? nativeIcon.cloneNode(true) : null
            });
        });
        function findEntry(regex) { return entries.find(function(e){ return regex.test(e.id+' '+normalize(e.url)); }); }
        function take(regex) {
            var e = findEntry(regex);
            if (e) { used.add(e); }
            return e;
        }
        function item(title,url,icon) { return {title:title,url:url,icon:icon||'circle'}; }
        function nodeFromEntry(entry,title,icon) {
            return entry ? item(title||entry.title,entry.url,icon) : null;
        }
        function childrenAsNodes(entry) {
            return entry ? entry.children.map(function (sub) { return item(sub.title,sub.url,'chevron-right'); }) : [];
        }
        function addUnique(arr,node) {
            if (!node || !node.url) { return; }
            if (!arr.some(function(v){ return normalize(v.url)===normalize(node.url); })) { arr.push(node); }
        }
        function shopTab(title, slug) {
            return item(title, config.settingsUrl + '&tab=' + slug, 'chevron-right');
        }

        var dashboard=take(/menu-dashboard|\/index\.php/);
        var products=take(/menu-posts-product|edit\.php\?post_type=product(?:$|&)/);
        var orders=take(/woocommerce_page_wc-orders|admin\.php\?page=wc-orders|edit\.php\?post_type=shop_order/);
        var woo=take(/toplevel_page_woocommerce|menu-woocommerce|admin\.php\?page=wc-admin/);
        var settings=take(/toplevel_page_deprat-shop-settings|admin\.php\?page=deprat-shop-settings/);
        var users=take(/menu-users|\/users\.php/);
        var marketing=take(/toplevel_page_woocommerce-marketing|wc-admin.*path=.*marketing|menu-posts-shop_coupon|edit\.php\?post_type=shop_coupon/);
        // Les slides d'accueil ont leur propre post_type, séparé des Articles WP.
        var home=take(/menu-posts-deprat_slide|edit\.php\?post_type=deprat_slide/);
        var posts=take(/menu-posts(?:\s|$)|\/edit\.php$/);
        var media=take(/menu-media|\/upload\.php/);

        // Les commandes sont généralement logées sous WooCommerce ;
        // nous reprenons les URL réelles au lieu de deviner un slug.
        function subFrom(entriesToSearch, pattern) {
            for (var i=0;i<entriesToSearch.length;i++) {
                if (!entriesToSearch[i]) { continue; }
                var hit=entriesToSearch[i].children.find(function(c){ return match(c.url,pattern) || pattern.test(c.title.toLowerCase()); });
                if (hit) { return hit.url; }
            }
            return '';
        }
        var ordersUrl=orders && orders.url ||
            subFrom([woo],/page=wc-orders|post_type=shop_order|commandes/) ||
            config.ordersUrl;
        var marketingUrl=marketing && marketing.url ||
            subFrom([woo],/path=.*marketing|post_type=shop_coupon|codes promo/) || '';
        var shopChildren=[
            item('Commandes',ordersUrl,'clipboard-list'),
            item('Produits',products ? products.url : config.productUrl,'package')
        ];
        var shopSettings={
            title:'Paramètres de la boutique',
            url:settings ? settings.url : config.settingsUrl,
            icon:'settings',
            hideOpenLink:true,
            children:[
                shopTab('Organiser les catégories', 'categories'),
                shopTab('Organiser les icônes', 'icons'),
                shopTab('Organiser les slides', 'slides'),
                shopTab('Organiser les caractéristiques techniques', 'characteristics')
            ]
        };
        // Caractéristiques techniques : lorsque leur écran dédié sera ajouté,
        // il sera classé ici ; pas de lien factice vers un outil inexistant.
        shopChildren.push(shopSettings);
        var clientChildren=[
            item('Liste des comptes ouverts',users?users.url:config.clientUrl,'users'),
            item('Nouvelle ouverture / autorisation',config.pendingUrl,'user-check')
        ];
        shopChildren.push({title:'Clients',url:users?users.url:config.clientUrl,icon:'users',children:clientChildren});
        var marketingChildren = [];
        marketingChildren.push(item('Promotions / Codes',marketingUrl || config.marketingUrl,'ticket-percent'));
        // Toujours pointer vers le vrai type de contenu de slides DEPRAT,
        // pas vers edit.php (liste des articles WordPress).
        var homeChildren=[item('Slides d’accueil',config.homeSlideUrl,'images')];
        var postChildren=[item('Tous les articles',posts?posts.url:config.blogUrl,'newspaper')];
        if (posts) {
            var addPost=posts.children.find(function(c){return /ajouter|add new/i.test(c.title);});
            if (addPost) { postChildren.push(item('Ajouter un article',addPost.url,'plus')); }
            posts.children.forEach(function(c){
                if (/catégor|categor|étiquette|tag/i.test(c.title)) {
                    addUnique(postChildren,item(c.title,c.url,'chevron-right'));
                }
            });
        }

        var adminChildren=[];
        entries.forEach(function (e) {
            if (!used.has(e)) {
                // Une extension future apparaît automatiquement ici avec tous ses sous-menus.
                adminChildren.push({title:e.title,url:e.url,icon:'native',nativeIcon:e.nativeIcon,children:childrenAsNodes(e)});
            }
        });
        // Les réglages WooCommerce ne doivent pas disparaître parce que la
        // page « Commandes » est déplacée sous Boutique.
        if (woo) {
            var wooSettings=woo.children.filter(function(c){return !/wc-orders|shop_order|commandes/i.test(c.url+' '+c.title);});
            if (wooSettings.length) {
                adminChildren.unshift({
                    title:'Réglages WooCommerce',url:woo.url,icon:'native',nativeIcon:woo.nativeIcon,
                    children:wooSettings.map(function(c){return item(c.title,c.url,'chevron-right');})
                });
            }
        }
        // Ne jamais dupliquer des menus métier sous Réglages ADMINISTRATEUR.
        // Les sous-menus liés aux produits, aux slides, au marketing et aux
        // catégories restent accessibles depuis leurs espaces respectifs.
        if (users) {
            var userExtras=users.children.filter(function(c){
                return /role|capabilit|profil|permission/i.test(c.title)
                    && !/liste|compte|utilisateur/i.test(c.title);
            });
            if (userExtras.length) {
                adminChildren.push({title:'Comptes et permissions WP',url:users.url,icon:'shield',
                    children:userExtras.map(function(c){return item(c.title,c.url,'chevron-right');})});
            }
        }
        // Ne pas réinsérer Paramètres boutique dans la partie technique.

        // La page native WordPress de maintenance et d'extensions à mettre à jour.
        // Accès immédiat en tête du menu, réservé à l'administrateur.
        var groups=[
            {title:'Mises à jour',tone:'updates',icon:'refresh',url:config.updatesUrl},
            {title:'Tableau de bord',tone:'dashboard',icon:'house',url:dashboard?dashboard.url:config.dashboardUrl},
            {title:'Espace Boutique',tone:'boutique',icon:'shopping-cart',children:shopChildren},
             {title:'Espace Marketing',tone:'marketing',icon:'megaphone',children:marketingChildren},
            {title:'Espace Page d’accueil',tone:'accueil',icon:'house',children:homeChildren},
            {title:'Espace Articles',tone:'articles',icon:'newspaper',children:postChildren},
            {title:'Médias',tone:'medias',icon:'images',url:media?media.url:config.mediaUrl},
            {title:'Réglages ADMINISTRATEUR',tone:'admin',icon:'settings',children:adminChildren}
        ];

        var svgPaths={
            refresh:'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M5.5 9a7 7 0 0 1 12-2l2.5 5M18.5 15a7 7 0 0 1-12 2L4 12"/>',
            house:'<path d="m3 10 9-7 9 7v11h-6v-7H9v7H3z"/>',
            'shopping-cart':'<circle cx="9" cy="20" r="1"/><circle cx="19" cy="20" r="1"/><path d="M2 3h3l3 13h12l2-9H6"/>',
            users:'<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2"/><path d="M17 5a3 3 0 0 1 0 6m1 5a5 5 0 0 1 3 5"/>',
            'user-check':'<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m2-9 2 2 4-4"/>',
            'clipboard-list':'<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4h6m-7 6h8m-8 4h8"/>',
            package:'<path d="m12 2 9 5v10l-9 5-9-5V7zM3 7l9 5 9-5M12 12v10"/>',
            settings:'<circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3M4.9 4.9 7 7m10 10 2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
            images:'<rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="9" r="2"/><path d="m3 18 6-5 4 3 3-4 5 6"/>',
            megaphone:'<path d="M3 10v4h4l12 6V4L7 10zM6 14l2 6h4"/>',
            newspaper:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 7h5v5H7zm8 0h3m-3 4h3M7 16h11"/>',
            puzzle:'<path d="M9 3h6v4a2 2 0 1 1 4 0h2v6h-4a2 2 0 1 0 0 4v4h-6v-4a2 2 0 1 0-4 0H3v-6h4a2 2 0 1 0 0-4H3V3z"/>',
            shield:'<path d="M12 2 3 6v6c0 6 4 9 9 10 5-1 9-4 9-10V6z"/>',
            'ticket-percent':'<path d="M3 7h18v4a2 2 0 0 0 0 4v3H3v-3a2 2 0 0 0 0-4z"/><path d="m9 15 6-6m-6 0h.1m6 6h.1"/>',
            plus:'<path d="M12 5v14m-7-7h14"/>',
            'chevron-right':'<path d="m9 5 7 7-7 7"/>',
            circle:'<circle cx="12" cy="12" r="7"/>'
        };
        function icon(name) {
            var span=document.createElement('span');
            span.className='deprat-menu-icon';
            span.setAttribute('aria-hidden','true');
            span.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'+(svgPaths[name]||svgPaths.circle)+'</svg>';
            return span;
        }
        function nativeIcon(nativeElement) {
            var span=document.createElement('span');
            span.className='deprat-menu-icon deprat-native-menu-icon';
            span.setAttribute('aria-hidden','true');
            if (nativeElement) {
                var cloned=nativeElement.cloneNode(true);
                span.appendChild(cloned);
            }
            return span;
        }
        var sidebar=document.createElement('nav');
        sidebar.className='deprat-admin-menu';
        sidebar.setAttribute('aria-label','Navigation administration DEPRAT');
        var brand=document.createElement('div');
        brand.className='deprat-admin-brand';
        brand.innerHTML='<span class="deprat-admin-brand-mark">D</span><strong>DEPRAT</strong><small>ADMINISTRATION</small>';
        if (config.logoUrl) {
            var mark=brand.querySelector('.deprat-admin-brand-mark');
            var logo=document.createElement('img');
            logo.src=config.logoUrl;
            logo.alt='';
            logo.loading='lazy';
            mark.textContent='';
            mark.appendChild(logo);
        }
        sidebar.appendChild(brand);

        function isActiveUrl(url) {
            try {
                var current=new URL(window.location.href);
                var target=new URL(url,window.location.href);
                if (current.origin!==target.origin || current.pathname!==target.pathname) {return false;}

                // Avant, un lien edit.php sans post_type était considéré actif
                // pour TOUTES les listes (slides, produits, articles...).
                // De même admin.php sans page pouvait activer plusieurs menus.
                // Comparer les deux sens, y compris l'absence du paramètre.
                var keys=['page','post_type','tab','role','path'];
                return keys.every(function (key) {
                    var actual=current.searchParams.get(key);
                    var expected=target.searchParams.get(key);
                    if (key==='post_type' && /\/edit\.php$/.test(current.pathname)) {
                        actual=actual || 'post';
                        expected=expected || 'post';
                    }
                    if (key==='tab' &&
                        current.searchParams.get('page')==='deprat-shop-settings' &&
                        target.searchParams.get('page')==='deprat-shop-settings') {
                        actual=actual || 'categories';
                        expected=expected || 'categories';
                    }
                    return actual===expected;
                });
            }catch(e){return false;}
        }
        function renderLink(n,level) {
            var link=document.createElement('a');
            link.className='deprat-admin-link level-'+level;
            link.href=n.url;
            link.appendChild(n.nativeIcon ? nativeIcon(n.nativeIcon) : icon(n.icon));
            var label=document.createElement('span');
            label.textContent=n.title;
            link.appendChild(label);
            if (isActiveUrl(n.url)) {link.classList.add('is-active');link.setAttribute('aria-current','page');}
            return link;
        }
        function renderSection(n,level,rootTone) {
            var hasChildren=n.children && n.children.length;
            if (!hasChildren) {return renderLink(n,level);}
            var details=document.createElement('details');
            details.className='deprat-menu-details level-'+level;
            var summary=document.createElement('summary');
            summary.appendChild(n.nativeIcon ? nativeIcon(n.nativeIcon) : icon(n.icon));
            var label=document.createElement('span');label.textContent=n.title;summary.appendChild(label);
            var arrow=document.createElement('span');
            arrow.className='deprat-menu-chevron';
            arrow.setAttribute('aria-hidden','true');
            arrow.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
            summary.appendChild(arrow);
            details.appendChild(summary);
            var body=document.createElement('div');body.className='deprat-menu-children';
            if (n.url && level>0 && !n.hideOpenLink) {
                body.appendChild(renderLink(item('Ouvrir '+n.title,n.url,'chevron-right'),level+1));
            }
            n.children.forEach(function(child){ body.appendChild(renderSection(child,level+1,rootTone)); });
            details.appendChild(body);
            if (body.querySelector('.is-active') || (level===0 && rootTone==='boutique' && /deprat-shop-settings/.test(location.search))) { details.open=true; }
            return details;
        }
        groups.forEach(function(group){
            var sec=document.createElement('div');
            sec.className='deprat-admin-space space-'+group.tone;
            sec.appendChild(renderSection(group,0,group.tone));
            sidebar.appendChild(sec);
        });
        var classic=document.createElement('a');
        classic.className='deprat-admin-classic';
        classic.href=config.classicUrl;
        classic.appendChild(icon('chevron-right'));
        var classicText=document.createElement('span');classicText.textContent='Revenir à WordPress classique';classic.appendChild(classicText);
        sidebar.appendChild(classic);
        // Ajouter la navigation à l'intérieur de la zone WordPress, pas au body :
        // la compatibilité avec le bouton réduire le menu est préservée.
        wrap.appendChild(sidebar);
        document.body.classList.add('deprat-admin-mode');
        // L'original demeure entièrement dans le DOM et revient à tout moment
        // en quittant le mode DEPRAT depuis la barre d'administration.
    }
    if (document.readyState==='loading') { document.addEventListener('DOMContentLoaded',init); }
    else { init(); }
}());
