<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\Client;
use Sesam\Models\Tenant;
use Sesam\Models\User;

$results = is_array($results ?? null) ? $results : ['query' => '', 'users' => [], 'tenants' => [], 'clients' => []];
$query = is_string($results['query'] ?? null) ? $results['query'] : '';
$users = is_array($results['users'] ?? null) ? $results['users'] : [];
$tenants = is_array($results['tenants'] ?? null) ? $results['tenants'] : [];
$clients = is_array($results['clients'] ?? null) ? $results['clients'] : [];
$isTenantUser = ($actor ?? null) instanceof User && $actor->tenantId() !== null;

?>
<section>
    <h1>Recherche</h1>
    <form class="row staff-search" method="get" action="<?= Html::url('/recherche') ?>">
        <div class="field">
            <label for="q"><span>Rechercher</span></label>
            <input class="input" id="q" name="q" type="search" maxlength="120" value="<?= Html::e($query) ?>" autofocus>
        </div>
        <button class="btn btn-primary" type="submit">Rechercher</button>
    </form>
    <?php if ($query === ''): ?>
        <p class="muted">Saisissez un nom, un e-mail ou un téléphone.</p>
    <?php elseif ($users === [] && $tenants === [] && $clients === []): ?>
        <div class="empty">
            <p>Aucun résultat pour « <?= Html::e($query) ?> ».</p>
        </div>
    <?php else: ?>
        <?php if ($clients !== []): ?>
            <h2>Clients</h2>
            <ul class="search-results">
                <?php foreach ($clients as $client): ?>
                    <?php if (!$client instanceof Client) { continue; } ?>
                    <li><a href="<?= Html::url('/clients/' . $client->id()) ?>"><?= Html::e($client->fullName()) ?><?php if ($client->email() !== null): ?> — <?= Html::e($client->email()) ?><?php endif; ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($users !== []): ?>
            <h2><?= $isTenantUser ? 'Membres de l\'équipe' : 'Utilisateurs SESAM' ?></h2>
            <ul class="search-results">
                <?php foreach ($users as $user): ?>
                    <?php if (!$user instanceof User) { continue; } ?>
                    <li><a href="<?= Html::url('/utilisateurs/' . $user->id()) ?>"><?= Html::e($user->fullName()) ?> — <?= Html::e($user->email()) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($tenants !== []): ?>
            <h2>Commerces</h2>
            <ul class="search-results">
                <?php foreach ($tenants as $tenant): ?>
                    <?php if (!$tenant instanceof Tenant) { continue; } ?>
                    <li><a href="<?= Html::url('/commerces/' . $tenant->id()) ?>"><?= Html::e($tenant->displayName()) ?> — <?= Html::e($tenant->slug()) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
