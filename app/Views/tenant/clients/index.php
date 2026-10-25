<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\ClientTag;

$list = is_array($list ?? null) ? $list : [
    'clients' => [],
    'total' => 0,
    'page' => 1,
    'pages' => 1,
    'query' => '',
    'status' => '',
    'tagId' => null,
    'tags' => [],
];
$clients = $list['clients'] ?? [];
$query = is_string($list['query'] ?? null) ? $list['query'] : '';
$status = is_string($list['status'] ?? null) ? $list['status'] : '';
$tagId = $list['tagId'] ?? null;
$tags = is_array($list['tags'] ?? null) ? $list['tags'] : [];
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$total = (int) ($list['total'] ?? 0);
$canCreate = (bool) ($canCreate ?? false);
$canExport = (bool) ($canExport ?? false);

$queryString = static function (array $extra = []) use ($query, $status, $tagId): string {
    $params = array_filter([
        'q' => $query !== '' ? $query : null,
        'status' => $status !== '' ? $status : null,
        'tag' => $tagId !== null ? (string) $tagId : null,
    ] + $extra, static fn ($value) => $value !== null && $value !== '');

    return $params === [] ? '' : '?' . http_build_query($params);
};

?>
<section>
    <div class="row staff-toolbar">
        <h1>Clients</h1>
        <div class="row">
            <?php if ($canExport): ?>
                <a class="btn btn-secondary" href="<?= Html::url('/clients/export' . $queryString()) ?>">Exporter CSV</a>
            <?php endif; ?>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= Html::url('/clients/nouveau') ?>" data-modal="Clients">Nouveau client</a>
            <?php endif; ?>
        </div>
    </div>
    <form class="row staff-search crm-filters" method="get" action="<?= Html::url('/clients') ?>">
        <div class="field">
            <label for="q"><span>Recherche</span></label>
            <input class="input" id="q" name="q" type="search" maxlength="120" value="<?= Html::e($query) ?>" placeholder="Nom, e-mail, téléphone">
        </div>
        <div class="field">
            <label for="status"><span>Statut</span></label>
            <select class="input" id="status" name="status">
                <option value="">Tous</option>
                <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Actifs</option>
                <option value="archived"<?= $status === 'archived' ? ' selected' : '' ?>>Archivés</option>
            </select>
        </div>
        <div class="field">
            <label for="tag"><span>Tag</span></label>
            <select class="input" id="tag" name="tag">
                <option value="">Tous</option>
                <?php foreach ($tags as $tag): ?>
                    <?php if (!$tag instanceof ClientTag) { continue; } ?>
                    <option value="<?= Html::e((string) $tag->id()) ?>"<?= (int) $tagId === $tag->id() ? ' selected' : '' ?>><?= Html::e($tag->name()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-secondary" type="submit">Filtrer</button>
    </form>
    <?php if ($clients === []): ?>
        <div class="empty">
            <p>Aucun client ne correspond.</p>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= Html::url('/clients/nouveau') ?>" data-modal="Clients">Créer une fiche</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Contact</th>
                        <th>Tags</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                        <?php if (!$client instanceof Client) { continue; } ?>
                        <tr>
                            <td><a href="<?= Html::url('/clients/' . $client->id()) ?>"><?= Html::e($client->fullName()) ?></a></td>
                            <td>
                                <?php if ($client->email() !== null): ?>
                                    <span><?= Html::e($client->email()) ?></span>
                                <?php endif; ?>
                                <?php if ($client->phone() !== null): ?>
                                    <span class="muted"><?= Html::e($client->phone()) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($client->tags() as $tag): ?>
                                    <span class="badge"><?= Html::e($tag->name()) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= $client->isArchived() ? 'Archivé' : 'Actif' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted"><?= Html::e((string) $total) ?> client(s), page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></p>
        <?php if ($pages > 1): ?>
            <nav class="row" aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/clients' . $queryString(['page' => (string) ($page - 1)])) ?>">Précédent</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/clients' . $queryString(['page' => (string) ($page + 1)])) ?>">Suivant</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
