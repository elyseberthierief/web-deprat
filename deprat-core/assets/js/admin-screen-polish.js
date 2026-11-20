/* Navigation native, onglets rapides pour les écrans sans filtres de statut.
 * Les écrans Articles, Pages et Slides conservent leurs vrais onglets WP.
 * Sur Médias, WordPress n'a pas toujours de subsubsub : ajouter uniquement
 * les raccourcis Tous les médias / Ajouter un média. */
(function(){
 'use strict';
 function init(){
   if(!document.body.classList.contains('deprat-admin-mode')){return;}
   // L'intitulé de l'écran suit maintenant la vraie liste visible.
   if(/\/users\.php$/.test(location.pathname)){
      var usersTitle=document.querySelector('#wpbody-content>.wrap>h1');
      if(usersTitle){
         usersTitle.textContent=new URLSearchParams(location.search).get('deprat_view')==='admins'
            ? 'Comptes administrateurs' : 'Clients professionnels';
      }
      return;
   }
   if(!/\/upload\.php$/.test(location.pathname)){return;}
   var wrap=document.querySelector('#wpbody-content>.wrap');
   if(!wrap || wrap.querySelector('.deprat-editorial-shortcuts')){return;}
   var nativeTabs=wrap.querySelector('.subsubsub');
   if(nativeTabs){return;}
   var title=wrap.querySelector('h1,.wp-heading-inline');
   var nav=document.createElement('nav');
   nav.className='deprat-editorial-shortcuts';
   nav.setAttribute('aria-label','Navigation de la médiathèque');
   [{text:'Tous les médias',href:'upload.php',active:true},
    {text:'Ajouter un média',href:'media-new.php',active:false}].forEach(function(tab){
       var a=document.createElement('a');
       a.href=tab.href;a.textContent=tab.text;
       if(tab.active){a.classList.add('is-current');a.setAttribute('aria-current','page');}
       nav.appendChild(a);
   });
   if(title){title.insertAdjacentElement('afterend',nav);}else{wrap.prepend(nav);}
 }
 if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}
 else{init();}
}());
