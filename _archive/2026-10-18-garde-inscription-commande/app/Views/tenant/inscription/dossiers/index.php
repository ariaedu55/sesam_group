<?php

declare(strict_types=1);

use Sesam\Core\Inscription\EnrollmentStatus;
use Sesam\Helpers\Html;
use Sesam\Models\InscriptionEnrollment;

$list = is_array($list ?? null) ? $list : [];
$rows = is_array($list['enrollments'] ?? null) ? $list['enrollments'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$canManage = (bool) ($canManage ?? false);

?>
<section>
    <div class="row staff-toolbar">
        <h1>Dossiers d'inscription</h1>
        <?php if ($canManage): ?>
            <a class="btn btn-primary" href="<?= Html::url('/inscription/dossiers/nouveau') ?>" data-modal="Inscription">Nouvelle inscription</a>
        <?php endif; ?>
    </div>
    <?php if ($rows === []): ?>
        <div class="empty"><p>Aucun dossier.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th scope="col">Élève</th>
                    <th scope="col">Classe</th>
                    <th scope="col">Responsable</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Créé le</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php if (!$row instanceof InscriptionEnrollment) { continue; } ?>
                    <tr>
                        <td><a href="<?= Html::url('/inscription/dossiers/' . $row->id()) ?>"><?= Html::e($row->studentName()) ?></a></td>
                        <td><?= Html::e($row->className()) ?></td>
                        <td><?= Html::e($row->guardianName()) ?></td>
                        <td><?= Html::e(EnrollmentStatus::label($row->status())) ?></td>
                        <td><?= Html::e(substr($row->createdAt(), 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <p class="muted">Page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>
