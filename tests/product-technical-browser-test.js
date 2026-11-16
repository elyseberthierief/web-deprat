// Exercice des actions du formulaire avec un DOM minimal, sans dépendance externe.
const fs = require( 'fs' );
const vm = require( 'vm' );
const assert = require( 'assert' );
const source = fs.readFileSync( __dirname + '/../deprat-core/assets/js/product-technical-admin.js', 'utf8' );
let clickHandler;
let nextMedia;
let lastAlert = '';
const containers = {};

function makeRow( type, index ) {
    const names = type === 'norms'
        ? [ 'deprat_product_tech[norms][' + index + ']' ]
        : [ 'deprat_product_tech[' + type + '][' + index + '][label]', 'deprat_product_tech[' + type + '][' + index + '][value]' ];
    const row = {
        inputs: names.map( name => ( { name, focus() {} } ) ),
        querySelector( selector ) { return selector === 'input' ? this.inputs[ 0 ] : null; },
        remove() { const rows = containers[ type ].rows; rows.splice( rows.indexOf( this ), 1 ); }
    };
    return row;
}

function makeContainer( type ) {
    return {
        rows: [],
        querySelectorAll() { return this.rows.flatMap( row => row.inputs ); },
        appendChild( fragment ) { this.rows.push( fragment.row ); },
        get lastElementChild() { return this.rows[ this.rows.length - 1 ]; }
    };
}

const templates = {};
for ( const type of [ 'features', 'norms', 'tests' ] ) {
    containers[ type ] = makeContainer( type );
    templates[ type ] = {
        content: {
            cloneNode() {
                const row = makeRow( type, '__index__' );
                return { row, querySelectorAll() { return row.inputs; } };
            }
        }
    };
}
containers.features.rows.push( makeRow( 'features', '0' ) );

const media = {
    id: { value: '' },
    preview: { textContent: '', children: [], appendChild( child ) { this.children.push( child ); } },
    select: { textContent: 'Ajouter' },
    remove: { hidden: true },
    getAttribute() { return 'pdf'; },
    querySelector( selector ) {
        return { '[data-media-id]': this.id, '[data-media-preview]': this.preview, '[data-media-select]': this.select, '[data-media-remove]': this.remove }[ selector ];
    }
};
media.select.closest = () => media;
media.remove.closest = () => media;

const editor = {
    addEventListener( event, callback ) { if ( event === 'click' ) clickHandler = callback; },
    contains() { return true; },
    querySelector( selector ) {
        const row = selector.match( /^\[data-rows="(.*?)"\]$/ );
        const template = selector.match( /^\[data-row-template="(.*?)"\]$/ );
        return row ? containers[ row[ 1 ] ] : template ? templates[ template[ 1 ] ] : null;
    }
};
const document = {
    readyState: 'complete',
    querySelector() { return editor; },
    createElement() { return {}; }
};
const window = {
    alert( text ) { lastAlert = text; },
    wp: {
        media() {
            let onSelect;
            return {
                on( name, callback ) { if ( name === 'select' ) onSelect = callback; },
                open() { onSelect(); },
                state() { return { get() { return { first() { return nextMedia ? { toJSON() { return nextMedia; } } : null; } }; } }; }
            };
        }
    }
};
vm.runInNewContext( source, { document, window, Number, Math, String } );
function click( selector, element ) {
    clickHandler( { target: { closest( query ) { return selector === query ? element : null; } } } );
}
function add( type ) {
    click( '[data-add-row]', { getAttribute() { return type; } } );
}

add( 'features' );
add( 'features' );
assert.deepStrictEqual( containers.features.rows.map( row => row.inputs[0].name ), [
    'deprat_product_tech[features][0][label]',
    'deprat_product_tech[features][1][label]',
    'deprat_product_tech[features][2][label]'
] );
const middle = containers.features.rows[1];
click( '[data-remove-row]', { closest() { return middle; } } );
add( 'features' );
assert.strictEqual( new Set( containers.features.querySelectorAll().map( input => input.name ) ).size, 6 );
add( 'norms' );
assert.strictEqual( containers.norms.rows[0].inputs[0].name, 'deprat_product_tech[norms][0]' );

nextMedia = { id: 99, type: 'text', mime: 'text/html', url: 'https://example.test/bad' };
click( '[data-media-select]', media.select );
assert.strictEqual( media.id.value, '' );
assert.strictEqual( lastAlert, 'Sélectionnez un fichier PDF.' );
nextMedia = { id: 12, mime: 'application/pdf', filename: 'fiche.pdf', url: 'https://example.test/fiche.pdf' };
click( '[data-media-select]', media.select );
assert.strictEqual( media.id.value, 12 );
assert.strictEqual( media.preview.children[0].textContent, 'fiche.pdf' );
assert.strictEqual( media.remove.hidden, false );
click( '[data-media-remove]', media.remove );
assert.strictEqual( media.id.value, '' );
assert.strictEqual( media.remove.hidden, true );
console.log( 'Ajout et suppression de champs, sélection et retrait du PDF vérifiés.' );
