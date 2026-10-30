<?php

declare(strict_types=1);

/*
 * Produits de démonstration de la page /services/boutique.
 * Chaque produit : nom, rayon, prix, ancien prix, badge, fond, fichier photo (public/assets/images/boutique/).
 */

return [
    'categories' => [
        'mode' => 'Mode',
        'beaute' => 'Beauté',
        'tech' => 'Électronique',
        'maison' => 'Maison',
        'epicerie' => 'Épicerie',
        'accessoires' => 'Accessoires',
    ],
    'products' => [
        ['robe', 'Robe wax Teranga', 'mode', 25000, 0, 'Nouveau', '#ffe9e1', 'robe.jpg'],
        ['baskets', 'Baskets urbaines', 'mode', 32000, 38000, 'Promo', '#e3efff', 'baskets.jpg'],
        ['chemise', 'Chemise en lin', 'mode', 18000, 0, '', '#e6f7f3', 'chemise.jpg'],
        ['parfum', 'Parfum Oud Royal', 'beaute', 21000, 0, 'Best-seller', '#f1ecff', 'parfum.jpg'],
        ['karite', 'Crème au karité', 'beaute', 6500, 0, '', '#fff5d6', 'karite.jpg'],
        ['rouge', 'Rouge à lèvres mat', 'beaute', 4500, 5500, 'Promo', '#ffe4ec', 'rouge.jpg'],
        ['ecouteurs', 'Écouteurs sans fil', 'tech', 15000, 19000, '-20 %', '#e3efff', 'ecouteurs.jpg'],
        ['smartphone', 'Smartphone 128 Go', 'tech', 95000, 0, '', '#eef1f6', 'smartphone.jpg'],
        ['montre', 'Montre connectée', 'tech', 28000, 0, 'Plus que 3', '#e6f7f3', 'montre.jpg'],
        ['bougie', 'Bougie parfumée', 'maison', 8000, 0, '', '#fff5d6', 'bougie.jpg'],
        ['plante', 'Plante en pot', 'maison', 11000, 0, 'Nouveau', '#e6f7f3', 'plante.jpg'],
        ['coussin', 'Coussin wax', 'maison', 9500, 12000, 'Promo', '#e3efff', 'coussin.jpg'],
        ['cafe', 'Café Touba 500 g', 'epicerie', 3500, 0, 'Best-seller', '#f3e9e1', 'cafe.jpg'],
        ['bissap', 'Jus de bissap 1 L', 'epicerie', 1500, 0, '', '#ffe4ec', 'bissap.jpg'],
        ['mangues', 'Panier de mangues', 'epicerie', 5000, 6000, 'Promo', '#fff5d6', 'mangues.jpg'],
        ['sac', 'Sac en raphia', 'accessoires', 12000, 0, 'Nouveau', '#f3e9e1', 'sac.jpg'],
        ['lunettes', 'Lunettes de soleil', 'accessoires', 7000, 0, '', '#eef1f6', 'lunettes.jpg'],
        ['bijoux', 'Parure dorée', 'accessoires', 7500, 9000, '-15 %', '#fff5d6', 'bijoux.jpg'],
    ],
];
