<?php

/**
 * Retourne la classe Bootstrap correspondant au statut
 * @param string $statut Le statut de la commande
 * @return string La classe Bootstrap correspondante
 */
function getStatutClass($statut) {
    $classes = [
        'en_attente' => 'warning',
        'en_cours' => 'info',
        'terminee' => 'success',
        'annulee' => 'danger'
    ];
    
    return $classes[$statut] ?? 'secondary';
}

/**
 * Retourne le libellé formaté du statut
 * @param string $statut Le statut de la commande
 * @return string Le libellé formaté
 */
function getStatutLibelle($statut) {
    $libelles = [
        'en_attente' => 'En attente',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée'
    ];
    
    return $libelles[$statut] ?? ucfirst(str_replace('_', ' ', $statut));
} 