<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$categories = is_array($categories ?? null) ? $categories : [];
$preferences = is_array($preferences ?? null) ? $preferences : [];
$devices = is_array($devices ?? null) ? $devices : [];
$live = (bool) ($live ?? false);
$date = static fn (?string $value): string => $value === null || $value === '' ? '—' : gmdate('d/m/Y à H:i', (int) strtotime($value . ' UTC'));

?>
<section class="btq notif-page">
    <p><a class="muted" href="<?= Html::url('/notifications') ?>">← Notifications</a></p>
    <header class="btq-head">
        <div>
            <h1>Réglages des notifications</h1>
            <p class="muted">Choisissez ce que vous voulez recevoir, dans l'application et sur vos appareils (téléphone, ordinateur).</p>
        </div>
    </header>

    <?php if (!$live): ?>
        <p class="alert" role="status">Envoi push en mode journal : les notifications apparaissent dans la cloche mais ne partent pas vers les appareils (PUSH_DRIVER=log).</p>
    <?php endif; ?>

    <div class="btq-two">
        <section class="btq-card">
            <h2>Cet appareil</h2>
            <p class="push-status" data-push-status data-tone="info">Vérification…</p>
            <div class="btq-actions">
                <button class="btn btn-primary" type="button" data-push-enable>Activer sur cet appareil</button>
                <button class="btn btn-ghost" type="button" data-push-disable>Désactiver ici</button>
            </div>
            <form method="post" action="<?= Html::url('/notifications/test') ?>" style="margin-top: 12px">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <button class="btn btn-secondary btn-sm" type="submit">Envoyer une notification de test</button>
            </form>
            <p class="btq-help">Sur iPhone : ouvrez SESAM dans Safari, « Partager » puis « Sur l'écran d'accueil », et activez depuis l'icône ajoutée.</p>

            <h3 class="console-subhead">Appareils qui reçoivent vos notifications</h3>
            <?php if ($devices === []): ?>
                <p class="muted">Aucun appareil pour l'instant.</p>
            <?php else: ?>
                <ul class="btq-list">
                    <?php foreach ($devices as $device): ?>
                        <li><div>
                            <span><strong><?= Html::e((string) ($device['label'] ?: 'Appareil')) ?></strong><small>Ajouté le <?= Html::e($date((string) $device['created_at'])) ?> · dernier envoi <?= Html::e($date($device['last_sent_at'] ?? null)) ?><?= $device['last_error'] ? ' · erreur : ' . Html::e((string) $device['last_error']) : '' ?></small></span>
                            <form method="post" action="<?= Html::url('/notifications/appareils/' . (int) $device['id'] . '/supprimer') ?>">
                                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                                <button class="btn btn-ghost btn-sm" type="submit">Retirer</button>
                            </form>
                        </div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="btq-card">
            <h2>Ce que vous recevez</h2>
            <form method="post" action="<?= Html::url('/notifications/reglages') ?>">
                <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
                <div class="table-wrap">
                    <table class="data-table notif-prefs">
                        <thead><tr><th scope="col">Notification</th><th scope="col">Dans l'appli</th><th scope="col">Sur l'appareil</th></tr></thead>
                        <tbody>
                            <?php foreach ($categories as $key => $category): ?>
                                <?php $field = str_replace('.', '_', $key); $pref = $preferences[$key] ?? ['in_app' => 1, 'push' => 1]; ?>
                                <tr>
                                    <th scope="row"><strong><?= Html::e($category['label']) ?></strong><br><small class="muted"><?= Html::e($category['hint']) ?></small></th>
                                    <td><label class="sr-only" for="in_app_<?= Html::e($field) ?>">Dans l'appli</label><input type="checkbox" id="in_app_<?= Html::e($field) ?>" name="in_app_<?= Html::e($field) ?>" value="1"<?= (int) $pref['in_app'] === 1 ? ' checked' : '' ?>></td>
                                    <td><label class="sr-only" for="push_<?= Html::e($field) ?>">Sur l'appareil</label><input type="checkbox" id="push_<?= Html::e($field) ?>" name="push_<?= Html::e($field) ?>" value="1"<?= (int) $pref['push'] === 1 ? ' checked' : '' ?>></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="margin-top: 12px"><button class="btn btn-primary" type="submit">Enregistrer</button></div>
            </form>
        </section>
    </div>
</section>
