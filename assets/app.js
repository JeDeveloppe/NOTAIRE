// assets/app.js

import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */

// ⭐ 1. Ligne Simplifiée pour le JS de Bootstrap
// Cet import suffit à exécuter le code JS de Bootstrap.
import 'bootstrap'; 

// ⭐ 2. Initialisation des Tooltips (déplacée du template)
// Placez ici le code d'initialisation pour que 'bootstrap' soit accessible.
document.addEventListener('DOMContentLoaded', function () {
    console.log('AssetMapper est prêt et Bootstrap est importé.');

    // Les tooltips sont initialisés ici, dans le même scope que l'import 'bootstrap'
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        // 'bootstrap' est accessible ici.
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// 🗑️ Lignes Supprimées ou Commentées :
// import * as bootstrap from 'bootstrap'; // ⬅️ Redondant après l'import simple 'bootstrap'