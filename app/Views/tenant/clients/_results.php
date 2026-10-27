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
$page = (int) ($list['page'] ?? 1);
$pages = (int) ($list['pages'] ?? 1);
$total = (int) ($list['total'] ?? 0);
$canCreate = (bool) ($canCreate ?? false);

$queryString = static function (array $extra = []) use ($query, $status, $tagId): string {
    $params = array_filter([
        'q' => $query !== '' ? $query : null,
        'status' => $status !== '' ? $status : null,
        'tag' => $tagId !== null ? (string) $tagId : null,
    ] + $extra, static fn ($value) => $value !== null && $value !== '');

    return $params === [] ? '' : '?' . http_build_query($params);
};

?>
    <?php if ($clients === []): ?>
        <div class="empty">
            <p>Aucun client ne correspond.</p>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= Html::url('/clients/nouveau') ?>" data-modal="Clients">Créer une fiche</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <p class="muted record-index-count"><?= Html::e((string) $total) ?> client(s), page <?= Html::e((string) $page) ?> / <?= Html::e((string) $pages) ?></p>
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
                        <?php $profileUrl = Html::url('/clients/' . $client->id()); ?>
                        <tr class="table-row-link" tabindex="0" data-row-href="<?= $profileUrl ?>">
                            <td><span class="table-row-label"><?= Html::e($client->fullName()) ?></span></td>
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
                                    <?php if (!$tag instanceof ClientTag) { continue; } ?>
                                    <span class="badge"><?= Html::e($tag->name()) ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td><?= $client->isArchived() ? 'Archivé' : 'Actif' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="row crm-pagination" data-live-filter-pages aria-label="Pagination">
                <?php if ($page > 1): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/clients' . $queryString(['page' => (string) ($page - 1)])) ?>">Précédent</a>
                <?php endif; ?>
                <?php if ($page < $pages): ?>
                    <a class="btn btn-secondary" href="<?= Html::url('/clients' . $queryString(['page' => (string) ($page + 1)])) ?>">Suivant</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
