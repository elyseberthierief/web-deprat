/**
 * Affichage CAD : véritables arêtes géométriques issues du GLB, sans textures.
 * Chargé à la demande. Les autres matériaux du produit restent intacts.
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

export async function createContourViewer( host, url, getBackground ) {
    if ( ! host || ! url ) { throw new Error( 'Modèle 3D absent.' ); }
    const canvas = document.createElement( 'canvas' );
    canvas.setAttribute( 'aria-label', 'Vue 3D technique en contours, manipulable à la souris' );
    host.appendChild( canvas );
    const renderer = new THREE.WebGLRenderer( { canvas, antialias: true, alpha: false } );
    renderer.setPixelRatio( Math.min( window.devicePixelRatio || 1, 2 ) );
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera( 40, 1, 0.01, 10000 );
    let lines = [];
    let group = new THREE.Group();
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
    gltf.scene.traverse( object => {
        if ( ! object.isMesh || ! object.geometry ) { return; }
        // Angle seuil : les arêtes du profil sont conservées, pas le maillage triangulé plat.
        const edges = new THREE.EdgesGeometry( object.geometry, 20 );
        const line = new THREE.LineSegments( edges, new THREE.LineBasicMaterial( { color: 0x153c72 } ) );
        line.applyMatrix4( object.matrixWorld );
        group.add( line );
        lines.push( line );
        bounds.expandByObject( line );
    } );
    if ( ! lines.length || bounds.isEmpty() ) {
        renderer.dispose();
        canvas.remove();
        throw new Error( 'Ce fichier ne contient aucune géométrie exploitable en contours.' );
    }

    const center = bounds.getCenter( new THREE.Vector3() );
    const dimensions = bounds.getSize( new THREE.Vector3() );
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

    let visible = true, disposed = false, raf = 0;
    function resize() {
        const width = Math.max( 2, Math.round( host.clientWidth ) );
        const height = Math.max( 2, Math.round( host.clientHeight ) );
        renderer.setSize( width, height, false );
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    }
    function theme() {
        const background = typeof getBackground === 'function' ? getBackground() : 'light';
        const dark = background === 'dark';
        const bg = new THREE.Color( dark ? 0x526173 : 0xf0f5fa );
        renderer.setClearColor( bg, 1 );
        lines.forEach( line => line.material.color.setHex( dark ? 0xffffff : 0x173b72 ) );
    }
    function loop() {
        if ( disposed || ! visible ) { return; }
        raf = window.requestAnimationFrame( loop );
        controls.update();
        renderer.render( scene, camera );
    }
    let observer;
    if ( typeof ResizeObserver !== 'undefined' ) {
        observer = new ResizeObserver( () => { if ( ! disposed ) { resize(); } } );
        observer.observe( host );
    }
    return {
        show() {
            if ( disposed ) { return; }
            visible = true;
            host.hidden = false;
            resize(); theme(); loop();
        },
        hide() {
            visible = false;
            window.cancelAnimationFrame( raf );
            host.hidden = true;
        },
        recolor: theme,
        destroy() {
            disposed = true;
            window.cancelAnimationFrame( raf );
            if ( observer ) { observer.disconnect(); }
            controls.dispose();
            lines.forEach( line => { line.geometry.dispose(); line.material.dispose(); } );
            renderer.dispose();
            canvas.remove();
            lines = [];
        },
    };
}
