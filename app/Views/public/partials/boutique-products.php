<?php

declare(strict_types=1);

/*
 * Produits de démonstration de la page /services/boutique : 6 rayons, 18 produits dessinés en SVG (aplats, sans dégradé).
 * Chaque produit : nom, rayon, prix, ancien prix (promotion), badge, fond de vignette, dessin (viewBox 0 0 120 120).
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
        ['robe', 'Robe wax Teranga', 'mode', 25000, 0, 'Nouveau', '#ffe9e1',
            '<path d="M48 22h24l4 14-8 6 22 58H30l22-58-8-6z" fill="#ff7a59"/><path d="M52 22l8 10 8-10" fill="none" stroke="#071a3d" stroke-width="3" stroke-linejoin="round"/><circle cx="50" cy="66" r="5" fill="#ffd84d"/><circle cx="68" cy="78" r="5" fill="#ffd84d"/><circle cx="58" cy="90" r="4" fill="#0a6cf0"/><circle cx="72" cy="60" r="3.5" fill="#0a6cf0"/><circle cx="44" cy="86" r="3.5" fill="#071a3d"/>'],
        ['baskets', 'Baskets urbaines', 'mode', 32000, 38000, 'Promo', '#e3efff',
            '<path d="M18 74c10 0 18-14 26-14 6 0 8 8 18 10 14 3 30 2 40 10v10H18z" fill="#fff" stroke="#071a3d" stroke-width="3" stroke-linejoin="round"/><path d="M18 84h84v8H18z" fill="#071a3d"/><path d="M48 66l6 8M56 64l6 9M64 66l5 7" stroke="#0a6cf0" stroke-width="3" stroke-linecap="round"/><path d="M80 72c6 0 14 2 18 6" stroke="#ff7a59" stroke-width="4" stroke-linecap="round" fill="none"/>'],
        ['chemise', 'Chemise en lin', 'mode', 18000, 0, '', '#e6f7f3',
            '<path d="M40 26l20 8 20-8 22 16-10 14-8-5v49H36V51l-8 5-10-14z" fill="#ccf1ec" stroke="#0f9f6e" stroke-width="3" stroke-linejoin="round"/><path d="M60 34v66" stroke="#0f9f6e" stroke-width="2.5"/><circle cx="60" cy="48" r="2.5" fill="#0f9f6e"/><circle cx="60" cy="62" r="2.5" fill="#0f9f6e"/><circle cx="60" cy="76" r="2.5" fill="#0f9f6e"/><path d="M50 30l10 10 10-10" fill="none" stroke="#0f9f6e" stroke-width="3" stroke-linejoin="round"/>'],
        ['parfum', 'Parfum Oud Royal', 'beaute', 21000, 0, 'Best-seller', '#f1ecff',
            '<rect x="50" y="18" width="20" height="12" rx="3" fill="#071a3d"/><rect x="55" y="30" width="10" height="8" fill="#ffd84d"/><rect x="34" y="38" width="52" height="62" rx="14" fill="#7b5cff"/><rect x="42" y="48" width="12" height="40" rx="6" fill="#fff" opacity=".35"/><rect x="48" y="62" width="30" height="16" rx="4" fill="#fff"/><path d="M54 70h18" stroke="#7b5cff" stroke-width="3" stroke-linecap="round"/>'],
        ['karite', 'Crème au karité', 'beaute', 6500, 0, '', '#fff5d6',
            '<rect x="28" y="40" width="64" height="16" rx="6" fill="#0f9f6e"/><path d="M30 56h60l-4 40a6 6 0 0 1-6 5H40a6 6 0 0 1-6-5z" fill="#fff" stroke="#071a3d" stroke-width="3" stroke-linejoin="round"/><path d="M48 72c4-8 20-8 24 0-4 10-20 10-24 0z" fill="#ffd84d"/><path d="M60 64v16" stroke="#0f9f6e" stroke-width="3" stroke-linecap="round"/>'],
        ['rouge', 'Rouge à lèvres mat', 'beaute', 4500, 5500, 'Promo', '#ffe4ec',
            '<rect x="44" y="62" width="32" height="40" rx="5" fill="#071a3d"/><rect x="46" y="50" width="28" height="14" rx="3" fill="#ffd84d"/><path d="M50 50V30c0-6 6-10 14-12 4 0 6 4 6 8v24z" fill="#e0245e"/><rect x="50" y="74" width="20" height="4" rx="2" fill="#ffd84d"/>'],
        ['ecouteurs', 'Écouteurs sans fil', 'tech', 15000, 19000, '-20 %', '#e3efff',
            '<path d="M30 70V58a30 30 0 0 1 60 0v12" fill="none" stroke="#071a3d" stroke-width="6" stroke-linecap="round"/><rect x="22" y="62" width="20" height="32" rx="9" fill="#0a6cf0"/><rect x="78" y="62" width="20" height="32" rx="9" fill="#0a6cf0"/><rect x="27" y="68" width="10" height="20" rx="5" fill="#3cc4f5"/><rect x="83" y="68" width="10" height="20" rx="5" fill="#3cc4f5"/>'],
        ['smartphone', 'Smartphone 128 Go', 'tech', 95000, 0, '', '#eef1f6',
            '<rect x="38" y="16" width="44" height="88" rx="9" fill="#071a3d"/><rect x="42" y="24" width="36" height="68" rx="4" fill="#3cc4f5"/><rect x="47" y="30" width="12" height="12" rx="3" fill="#fff"/><rect x="61" y="30" width="12" height="12" rx="3" fill="#ffd84d"/><rect x="47" y="45" width="12" height="12" rx="3" fill="#ff7a59"/><rect x="61" y="45" width="12" height="12" rx="3" fill="#1fa855"/><rect x="52" y="96" width="16" height="3" rx="1.5" fill="#fff"/><rect x="54" y="19" width="12" height="2.5" rx="1.25" fill="#3cc4f5"/>'],
        ['montre', 'Montre connectée', 'tech', 28000, 0, 'Plus que 3', '#e6f7f3',
            '<rect x="48" y="12" width="24" height="26" rx="6" fill="#1d2f55"/><rect x="48" y="82" width="24" height="26" rx="6" fill="#1d2f55"/><rect x="36" y="34" width="48" height="52" rx="14" fill="#071a3d"/><rect x="42" y="40" width="36" height="40" rx="9" fill="#0f9f6e"/><path d="M50 62h6l3-8 4 14 3-6h6" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><rect x="84" y="52" width="4" height="12" rx="2" fill="#1d2f55"/>'],
        ['bougie', 'Bougie parfumée', 'maison', 8000, 0, '', '#fff5d6',
            '<path d="M60 18c-6 8-6 14 0 18 6-4 6-10 0-18z" fill="#ff7a59"/><path d="M60 26c-2 3-2 6 0 8 2-2 2-5 0-8z" fill="#ffd84d"/><path d="M60 36v8" stroke="#071a3d" stroke-width="2.5"/><rect x="36" y="44" width="48" height="58" rx="10" fill="#fff" stroke="#071a3d" stroke-width="3"/><rect x="36" y="64" width="48" height="18" fill="#ffd84d"/><path d="M48 73h24" stroke="#071a3d" stroke-width="2.5" stroke-linecap="round"/>'],
        ['plante', 'Plante en pot', 'maison', 11000, 0, 'Nouveau', '#e6f7f3',
            '<path d="M60 64C46 60 36 48 38 30c14 2 22 14 22 34z" fill="#1fa855"/><path d="M60 64c12-6 20-18 18-34-14 4-20 16-18 34z" fill="#0f9f6e"/><path d="M60 64c-2-14 2-30 10-38" fill="none" stroke="#0f9f6e" stroke-width="3"/><path d="M38 66h44l-6 36H44z" fill="#ff7a59"/><rect x="34" y="62" width="52" height="10" rx="3" fill="#e0603d"/>'],
        ['coussin', 'Coussin wax', 'maison', 9500, 12000, 'Promo', '#e3efff',
            '<path d="M24 34c12 4 60 4 72 0-4 18-4 34 0 52-12-4-60-4-72 0 4-18 4-34 0-52z" fill="#0a6cf0"/><circle cx="46" cy="52" r="7" fill="#ffd84d"/><circle cx="74" cy="68" r="7" fill="#ffd84d"/><circle cx="74" cy="48" r="4" fill="#ff7a59"/><circle cx="46" cy="72" r="4" fill="#ff7a59"/><circle cx="60" cy="60" r="5" fill="#fff"/>'],
        ['cafe', 'Café Touba 500 g', 'epicerie', 3500, 0, 'Best-seller', '#f3e9e1',
            '<path d="M38 26h44l6 76H32z" fill="#8a5a3c"/><path d="M38 26l6-8h32l6 8" fill="#6b4329"/><rect x="40" y="50" width="40" height="28" rx="4" fill="#fff"/><ellipse cx="54" cy="64" rx="5" ry="7" fill="#8a5a3c"/><ellipse cx="67" cy="64" rx="5" ry="7" fill="#8a5a3c"/><path d="M54 58v12M67 58v12" stroke="#fff" stroke-width="1.5"/>'],
        ['bissap', 'Jus de bissap 1 L', 'epicerie', 1500, 0, '', '#ffe4ec',
            '<rect x="52" y="14" width="16" height="10" rx="2" fill="#1fa855"/><path d="M54 24h12v12l8 10v52a6 6 0 0 1-6 6H52a6 6 0 0 1-6-6V46l8-10z" fill="#b0124a"/><rect x="46" y="58" width="28" height="24" fill="#fff"/><path d="M60 64c-6 0-8 8 0 12 8-4 6-12 0-12z" fill="#e0245e"/><rect x="58" y="28" width="4" height="10" rx="2" fill="#fff" opacity=".4"/>'],
        ['mangues', 'Panier de mangues', 'epicerie', 5000, 6000, 'Promo', '#fff5d6',
            '<ellipse cx="46" cy="52" rx="14" ry="12" fill="#ffb020"/><ellipse cx="72" cy="50" rx="14" ry="12" fill="#ff7a59"/><ellipse cx="60" cy="40" rx="13" ry="11" fill="#ffd84d"/><path d="M60 29c2-4 6-6 10-5" stroke="#1fa855" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M26 58h68l-8 40H34z" fill="#c98a4b"/><path d="M30 70h60M32 82h56" stroke="#8a5a3c" stroke-width="3"/><path d="M44 58v40M60 58v40M76 58v40" stroke="#8a5a3c" stroke-width="2"/>'],
        ['sac', 'Sac en raphia', 'accessoires', 12000, 0, 'Nouveau', '#f3e9e1',
            '<path d="M42 44c0-16 36-16 36 0" fill="none" stroke="#8a5a3c" stroke-width="5" stroke-linecap="round"/><path d="M26 46h68l-6 54H32z" fill="#e2b77a"/><path d="M30 60h60M31 74h58M32 88h56" stroke="#c98a4b" stroke-width="3"/><circle cx="60" cy="60" r="6" fill="#ff7a59"/>'],
        ['lunettes', 'Lunettes de soleil', 'accessoires', 7000, 0, '', '#eef1f6',
            '<path d="M14 48h92" stroke="#071a3d" stroke-width="5" stroke-linecap="round"/><path d="M20 48h32l-3 22a10 10 0 0 1-10 9h-6a10 10 0 0 1-10-9z" fill="#071a3d"/><path d="M68 48h32l-3 22a10 10 0 0 1-10 9h-6a10 10 0 0 1-10-9z" fill="#071a3d"/><path d="M28 56l8-4M76 56l8-4" stroke="#3cc4f5" stroke-width="3" stroke-linecap="round"/>'],
        ['bijoux', 'Parure dorée', 'accessoires', 7500, 9000, '-15 %', '#fff5d6',
            '<path d="M28 30c4 30 60 30 64 0" fill="none" stroke="#e0a800" stroke-width="4"/><circle cx="60" cy="58" r="9" fill="#ffd84d" stroke="#e0a800" stroke-width="3"/><circle cx="60" cy="58" r="3.5" fill="#e0245e"/><circle cx="36" cy="88" r="9" fill="none" stroke="#e0a800" stroke-width="4"/><circle cx="84" cy="88" r="9" fill="none" stroke="#e0a800" stroke-width="4"/><circle cx="36" cy="97" r="3" fill="#ffd84d"/><circle cx="84" cy="97" r="3" fill="#ffd84d"/>'],
    ],
];
