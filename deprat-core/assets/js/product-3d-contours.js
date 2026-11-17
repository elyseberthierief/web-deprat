/**
 * Vue technique DEPRAT : arêtes significatives + silhouettes visibles.
 * Contrairement au wireframe brut, les triangles du GLB et les traits derrière
 * la pièce ne sont pas affichés. Le dessin suit la rotation de la caméra.
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { mergeGeometries, mergeVertices } from 'three/addons/utils/BufferGeometryUtils.js';

// 38° garde les angles des profils octogonaux (45°) et les plis marqués,
// sans dessiner les multiples facettes d'un cylindre ou les triangles CAD.
const MAJOR_EDGE_ANGLE = 38;
const SILHOUETTE_FACE_LIMIT = 150000;

export async function createContourViewer( host, url, getBackground ) {
    if ( ! host || ! url ) { throw new Error( 'Modèle 3D absent.' ); }
    const canvas = document.createElement( 'canvas' );
    canvas.setAttribute( 'aria-label', 'Vue 3D technique aux contours simplifiés, manipulable à la souris' );
    host.appendChild( canvas );
    const renderer = new THREE.WebGLRenderer( { canvas, antialias: true, alpha: false } );
    renderer.setPixelRatio( Math.min( window.devicePixelRatio || 1, 2 ) );
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera( 40, 1, 0.01, 10000 );
    const group = new THREE.Group();
    scene.add( group );

    const loader = new GLTFLoader();
    let gltf;
    try {
        gltf = await loader.loadAsync( url );
    } catch ( error ) {
        renderer.dispose();
        canvas.remove();
        throw error;
    }
    gltf.scene.updateMatrixWorld( true );

    const bounds = new THREE.Box3();
    const fragments = [];
    const masks = [];
    const maskMaterial = new THREE.MeshBasicMaterial( {
        // Écriture dans le tampon de profondeur sans masquer le fond :
        // les lignes arrière et intérieures disparaissent.
        colorWrite: false, depthWrite: true, depthTest: true,
        side: THREE.DoubleSide, polygonOffset: true,
        polygonOffsetFactor: 1, polygonOffsetUnits: 1
    } );
    gltf.scene.traverse( object => {
        if ( ! object.isMesh || ! object.geometry || ! object.geometry.getAttribute( 'position' ) ) { return; }
        const fragment = object.geometry.index ? object.geometry.toNonIndexed() : object.geometry.clone();
        Object.keys( fragment.attributes ).forEach( name => {
            if ( name !== 'position' ) { fragment.deleteAttribute( name ); }
        } );
        fragment.applyMatrix4( object.matrixWorld );
        fragment.clearGroups();
        fragments.push( fragment );

        // L'occlusion prend la géométrie d'origine, mais jamais sa texture.
        const mask = new THREE.Mesh( object.geometry, maskMaterial );
        mask.matrix.copy( object.matrixWorld );
        mask.matrixAutoUpdate = false;
        mask.renderOrder = 0;
        masks.push( mask );
        group.add( mask );
        bounds.expandByObject( mask );
    } );
    if ( ! fragments.length || bounds.isEmpty() ) {
        fragments.forEach( fragment => fragment.dispose() );
        maskMaterial.dispose();
        renderer.dispose();
        canvas.remove();
        throw new Error( 'Ce fichier ne contient aucune géométrie exploitable en contours.' );
    }

    // Fusionner les sous-meshes et souder leurs points communs avant de tracer
    // les arêtes : sinon chaque séparation de matériau ressemble à une arête.
    const merged = fragments.length === 1 ? fragments[0] : mergeGeometries( fragments, false );
    fragments.forEach( fragment => {
        if ( fragment !== merged ) { fragment.dispose(); }
    } );
    if ( ! merged ) {
        maskMaterial.dispose();
        renderer.dispose();
        canvas.remove();
        throw new Error( 'Les géométries 3D ne peuvent pas être assemblées.' );
    }
    const dimensions = bounds.getSize( new THREE.Vector3() );
    const tolerance = Math.max( 0.000001, dimensions.length() * 0.0000001 );
    const welded = mergeVertices( merged, tolerance );
    merged.dispose();

    const majorGeometry = new THREE.EdgesGeometry( welded, MAJOR_EDGE_ANGLE );
    const majorLines = new THREE.LineSegments( majorGeometry, new THREE.LineBasicMaterial( {
        color: 0x173b72, depthTest: true, depthWrite: false, transparent: false
    } ) );
    majorLines.renderOrder = 1;
    group.add( majorLines );

    // Surfaces courbes : la silhouette évolue avec le point de vue.
    // Les arêtes structurelles sont déjà tracées ; on n'y ajoute pas de doublons.
    const silhouetteEdges = [];
    const triangleCount = welded.index ? welded.index.count / 3 : 0;
    if ( triangleCount > 0 && triangleCount <= SILHOUETTE_FACE_LIMIT ) {
        const positions = welded.attributes.position.array;
        const indices = welded.index.array;
        const adjacency = new Map();
        const p1 = new THREE.Vector3(), p2 = new THREE.Vector3(), p3 = new THREE.Vector3();
        const v1 = new THREE.Vector3(), v2 = new THREE.Vector3();
        const normal = new THREE.Vector3();
        const angleLimit = Math.cos( THREE.MathUtils.degToRad( MAJOR_EDGE_ANGLE ) );

        for ( let index = 0; index < indices.length; index += 3 ) {
            const a = indices[index], b = indices[index + 1], c = indices[index + 2];
            if ( a === b || b === c || c === a ) { continue; }
            p1.fromArray( positions, a * 3 );
            p2.fromArray( positions, b * 3 );
            p3.fromArray( positions, c * 3 );
            normal.crossVectors( v1.subVectors( p2, p1 ), v2.subVectors( p3, p1 ) );
            if ( normal.lengthSq() < 1e-16 ) { continue; }
            normal.normalize();
            for ( const [u, v] of [[a,b], [b,c], [c,a]] ) {
                const key = u < v ? u + ':' + v : v + ':' + u;
                let edge = adjacency.get( key );
                if ( !edge ) {
                    edge = { a: u, b: v, normals: [] };
                    adjacency.set( key, edge );
                }
                // Une soudure peut relier des triangles de pièces distinctes :
                // conserver deux faces suffit pour la silhouette.
                if ( edge.normals.length < 3 ) { edge.normals.push( [normal.x, normal.y, normal.z] ); }
            }
        }
        adjacency.forEach( edge => {
            if ( edge.normals.length !== 2 ) { return; }
            const n1 = edge.normals[0], n2 = edge.normals[1];
            const cosine = n1[0]*n2[0] + n1[1]*n2[1] + n1[2]*n2[2];
            if ( cosine <= angleLimit ) { return; } // arête nette déjà présente
            const a = edge.a * 3, b = edge.b * 3;
            silhouetteEdges.push( {
                a: [positions[a], positions[a+1], positions[a+2]],
                b: [positions[b], positions[b+1], positions[b+2]],
                n1, n2
            } );
        } );
        adjacency.clear();
    }
    const silhouetteArray = new Float32Array( Math.max( 6, silhouetteEdges.length * 6 ) );
    const silhouetteGeometry = new THREE.BufferGeometry();
    silhouetteGeometry.setAttribute( 'position', new THREE.BufferAttribute( silhouetteArray, 3 ).setUsage( THREE.DynamicDrawUsage ) );
    silhouetteGeometry.setDrawRange( 0, 0 );
    const silhouetteLines = new THREE.LineSegments( silhouetteGeometry, new THREE.LineBasicMaterial( {
        color: 0x173b72, depthTest: true, depthWrite: false
    } ) );
    silhouetteLines.renderOrder = 2;
    group.add( silhouetteLines );

    const center = bounds.getCenter( new THREE.Vector3() );
    const radius = Math.max( dimensions.length() * .5, .01 );
    group.position.sub( center );
    camera.position.set( radius * 1.5, radius * .9, radius * 1.5 );
    camera.near = radius / 1000;
    camera.far = radius * 100;
    camera.updateProjectionMatrix();
    const controls = new OrbitControls( camera, renderer.domElement );
    controls.enableDamping = true;
    controls.dampingFactor = .08;
    controls.enablePan = true;
    controls.target.set( 0, 0, 0 );
    controls.minDistance = radius * .1;
    controls.maxDistance = radius * 20;
    controls.update();

    let visible = true, disposed = false, raf = 0, dirtySilhouette = true;
    controls.addEventListener( 'change', () => { dirtySilhouette = true; } );
    function updateSilhouette() {
        if ( !dirtySilhouette || !silhouetteEdges.length ) { return; }
        dirtySilhouette = false;
        let offset = 0;
        const cx = camera.position.x + center.x;
        const cy = camera.position.y + center.y;
        const cz = camera.position.z + center.z;
        for ( const edge of silhouetteEdges ) {
            const mx = ( edge.a[0] + edge.b[0] ) * .5;
            const my = ( edge.a[1] + edge.b[1] ) * .5;
            const mz = ( edge.a[2] + edge.b[2] ) * .5;
            const dx = cx - mx, dy = cy - my, dz = cz - mz;
            const dot1 = edge.n1[0]*dx + edge.n1[1]*dy + edge.n1[2]*dz;
            const dot2 = edge.n2[0]*dx + edge.n2[1]*dy + edge.n2[2]*dz;
            if ( dot1 * dot2 >= 0 ) { continue; }
            silhouetteArray.set( edge.a, offset );
            silhouetteArray.set( edge.b, offset + 3 );
            offset += 6;
        }
        silhouetteGeometry.setDrawRange( 0, offset / 3 );
        silhouetteGeometry.attributes.position.needsUpdate = true;
        silhouetteGeometry.computeBoundingSphere();
    }
    function resize() {
        const width = Math.max( 2, Math.round( host.clientWidth ) );
        const height = Math.max( 2, Math.round( host.clientHeight ) );
        renderer.setSize( width, height, false );
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        dirtySilhouette = true;
    }
    function theme() {
        const background = typeof getBackground === 'function' ? getBackground() : 'light';
        const dark = background === 'dark';
        renderer.setClearColor( new THREE.Color( dark ? 0x526173 : 0xf0f5fa ), 1 );
        majorLines.material.color.setHex( dark ? 0xffffff : 0x173b72 );
        silhouetteLines.material.color.setHex( dark ? 0xffffff : 0x173b72 );
    }
    function loop() {
        if ( disposed || !visible ) { return; }
        raf = window.requestAnimationFrame( loop );
        controls.update();
        updateSilhouette();
        renderer.render( scene, camera );
    }
    let observer;
    if ( typeof ResizeObserver !== 'undefined' ) {
        observer = new ResizeObserver( () => { if ( !disposed ) { resize(); } } );
        observer.observe( host );
    }
    return {
        show() {
            if ( disposed ) { return; }
            if ( visible && raf ) { return; }
            visible = true;
            host.hidden = false;
            resize(); theme(); dirtySilhouette = true; loop();
        },
        hide() {
            visible = false;
            window.cancelAnimationFrame( raf );
            raf = 0;
            host.hidden = true;
        },
        recolor: theme,
        destroy() {
            disposed = true;
            window.cancelAnimationFrame( raf );
            if ( observer ) { observer.disconnect(); }
            controls.dispose();
            majorGeometry.dispose();
            majorLines.material.dispose();
            silhouetteGeometry.dispose();
            silhouetteLines.material.dispose();
            maskMaterial.dispose();
            welded.dispose();
            renderer.dispose();
            canvas.remove();
        },
    };
}
