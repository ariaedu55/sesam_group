<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

/** @var array{agent_embed_src: string} $jotform */
$jotform = require dirname(__DIR__, 3) . '/config/jotform.php';
$jotformSrc = trim($jotform['agent_embed_src'] ?? '');

if ($jotformSrc === '') {
    return;
}

?>
<script src="<?= Html::e($jotformSrc) ?>"></script>
