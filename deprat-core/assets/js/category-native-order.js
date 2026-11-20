/* Groupement visuel de la liste WooCommerce des catégories.
 * L'ordre réel est appliqué côté serveur. Ici on ajoute uniquement
 * des séparateurs de section, sans toucher aux lignes WordPress.
 */
(function () {
 'use strict';
 function init() {
   var config=window.depratNativeCategoryOrder;
   var table=document.querySelector('.wp-list-table.tags, .wp-list-table');
   if(!config || !Array.isArray(config.rows) || !table){return;}
   var tbody=table.querySelector('tbody#the-list');
   if(!tbody){return;}
   var byId={};
   config.rows.forEach(function(item){byId['tag-'+item.id]=item;});
   tbody.querySelectorAll('.deprat-native-category-group').forEach(function(node){node.remove();});
   var prior='';
   Array.from(tbody.children).forEach(function(row){
     var item=byId[row.id];
     if(!item){return;}
     if(item.section!==prior){
       prior=item.section;
       var divider=document.createElement('tr');
       divider.className='deprat-native-category-group';
       var cell=document.createElement('th');
       cell.colSpan=Math.max(table.querySelectorAll('thead th').length,4);
       cell.scope='rowgroup';
       cell.textContent=item.sectionTitle;
       divider.appendChild(cell);
       tbody.insertBefore(divider,row);
     }
   });
   var wrap=table.closest('.wrap');
   if(wrap){
     var title=wrap.querySelector('h1.wp-heading-inline,h1');
     if(title){title.textContent='Catégories des produits';}
   }
 }
 if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}
 else{init();}
}());
