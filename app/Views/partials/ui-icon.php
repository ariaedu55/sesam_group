<?php

declare(strict_types=1);

$name = is_string($name ?? null) ? $name : '';
$paths = match ($name) {
    'home' => '<path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/>',
    'users' => '<path d="M16 19v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"/><circle cx="10" cy="8" r="3"/><path d="M20 19v-1a3.5 3.5 0 0 0-3-3.45"/><circle cx="17" cy="8.5" r="2.5"/>',
    'chart' => '<path d="M4 20h16"/><path d="M7 16v-5M12 16V7M17 16v-8"/>',
    'card' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18"/>',
    'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
    'spark' => '<path d="M12 3v4M12 17v4M5 12H3M21 12h-2M6.2 6.2 8.5 8.5M15.5 15.5l2.3 2.3M17.8 6.2 15.5 8.5M8.5 15.5 6.2 17.8"/><circle cx="12" cy="12" r="3"/>',
    'person' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 19a7 7 0 0 1 14 0"/>',
    'bag' => '<path d="M6 8h12l-1 12H7L6 8z"/><path d="M9 8V7a3 3 0 0 1 6 0v1"/>',
    'tag' => '<path d="M20 13.5 12.5 21 3 11.5V3h8.5L20 11.5z"/><circle cx="7.5" cy="7.5" r="1.2"/>',
    'clock' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    'file' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
    'building' => '<path d="M5 21V5h14v16"/><path d="M9 9h2M13 9h2M9 13h2M13 13h2M9 21v-4h6v4"/>',
    'folder' => '<path d="M3 7h6l2 2h10v10H3z"/>',
    'book' => '<path d="M5 4h10a3 3 0 0 1 3 3v13H8a3 3 0 0 0-3 3z"/><path d="M5 4a3 3 0 0 0-3 3v13a3 3 0 0 1 3-3h13"/>',
    'coin' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v8M9.5 10.5c.6-.7 1.5-1 2.5-1s2 .4 2.5 1M9.5 13.5c.6.7 1.5 1 2.5 1s2-.4 2.5-1"/>',
    'bell' => '<path d="M6 16h12l-1.2-2a6 6 0 0 1-.8-3v-1a4 4 0 0 0-8 0v1a6 6 0 0 1-.8 3z"/><path d="M10 19a2 2 0 0 0 4 0"/>',
    'team' => '<circle cx="9" cy="8" r="3"/><circle cx="16" cy="9" r="2.4"/><path d="M3.5 19a5.5 5.5 0 0 1 11 0M14 19a4.5 4.5 0 0 1 6.5 0"/>',
    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M4.9 6.5l1.6 1.6M17.5 16.9l1.6 1.6M3.5 12h2.2M18.3 12h2.2M4.9 17.5l1.6-1.6M17.5 7.1l1.6-1.6"/>',
    'search' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-3.5-3.5"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13.5 6.5 4 4"/>',
    'store' => '<path d="M4 9.5 5.5 4h13L20 9.5"/><path d="M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M5.5 12v8h13v-8M10 20v-4.5h4V20"/>',
    'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 12.5h18"/>',
    'logout' => '<path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2"/><path d="M4 12h10M7 9l-3 3 3 3"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
    'star' => '<path d="m12 3 2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/>',
    'box' =>'<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="M3.5 7.5 12 12l8.5-4.5M12 12v9"/>',
    'chat' => '<path d="M4 19.5 5.3 16A8 8 0 1 1 8 18.7z"/><path d="M9 10.5h6M9 13.5h4"/>',
    'link' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    'share' => '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="m8.2 10.8 7.6-4.1M8.2 13.2l7.6 4.1"/>',
    'image' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="m20.5 16-5-5-8 8.5"/>',
    'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'truck' => '<path d="M3 6.5h11v10H3zM14 10h4l3 3.2v3.3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
    default => '<circle cx="12" cy="12" r="3"/>',
};

?>
<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $paths ?></svg>
