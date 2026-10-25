<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\User;

$list = is_array($list ?? null) ? $list : ['users' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'query' => ''];
$users = $list['users'] ?? [];
$query = is_string($list['query'] ?? null) ? $list['query'] : '';
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$total = (int) ($list['total'] ?? 0);
$canCreate = (bool) ($canCreate ?? false);
$roleNames = [
    'super-admin' => 'Super Admin',
    'admin' => 'Admin',
    'commercial' => 'Commercial',
    'support' => 'Support',
    'finance' => 'Finance',
    'tenant-owner' => 'Tenant Owner',
    'manager' => 'Manager',
    'staff' => 'Staff',
];

?>
<section>
    <div class="row staff-toolbar">
        <h1>Utilisateurs</h1>
        <?php if ($canCreate): ?>
            <a class="btn btn-primary" href="<?= Html::url('/utilisateurs/nouveau') ?>" data-modal="Équipe">Nouvel utilisateur</a>
        <?php endif; ?>
    </div>
    <form class="row staff-search" method="get" action="<?= Html::url('/utilisateurs') ?>">
        <div class="field">
            <label for="q"><span>Recherche</span></label>
            <input class="input" id="q" name="q" type="search" value="<?= Html::e($query) ?>" maxlength="120">
        </div>
        <button class="btn btn-secondary" type="submit">Filtrer</button>
    </form>
    <?php if ($users === []): ?>
        <div class="empty">
            <p>Aucun utilisateur ne correspond.</p>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= Html::url('/utilisateurs/nouveau') ?>" data-modal="Équipe">Créer un compte</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>E-mail</th>
                        <th>Rôles</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php if (!$user instanceof User) { continue; } ?>
                        <tr>
                            <td><a href="<?= Html::url('/utilisateurs/' . $user->id()) ?>" data-modal="Équipe"><?= Html::e($user->fullName()) ?></a></td>
                            <td><?= Html::e($user->email()) ?></td>
                            <td>
                                <?php foreach ($user->roles() as $role): ?>
                                    <span class="badge"><?= Html::e($roleNames[$role] ?? $role) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= $user->isActive() ? 'Actif' : 'Inactif' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted"><?= Html::e((string) $total) ?> compte(s), page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></p>
        <?php if ($pages > 1): ?>
            <nav class="row" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/utilisateurs') ?>?q=<?= rawurlencode($query) ?>&amp;page=<?= $page - 1 ?>">Précédent</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/utilisateurs') ?>?q=<?= rawurlencode($query) ?>&amp;page=<?= $page + 1 ?>">Suivant</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
