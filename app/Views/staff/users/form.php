<?php

declare(strict_types=1);

use Sesam\Helpers\Html;
use Sesam\Models\User;

$edited = $edited instanceof User ? $edited : null;
$old = is_array($old ?? null) ? $old : [];
$errors = is_array($errors ?? null) ? $errors : [];
$roles = is_array($roles ?? null) ? $roles : [];
$selected = $old['roles'] ?? [];
$selected = is_array($selected) ? $selected : [];
$value = static function (string $key) use ($old): string {
    $item = $old[$key] ?? '';

    return Html::e(is_scalar($item) ? (string) $item : '');
};
$isActive = ($old['is_active'] ?? '1') === '1';
$canAssignRoles = (bool) ($canAssignRoles ?? false);
$canSetPassword = (bool) ($canSetPassword ?? false);
$canDeactivate = (bool) ($canDeactivate ?? false);
$action = $edited === null ? Html::url('/utilisateurs') : Html::url('/utilisateurs/' . $edited->id());

?>
<section class="staff-form">
    <h1><?= $edited === null ? 'Nouvel utilisateur' : 'Modifier l\'utilisateur' ?></h1>
    <form class="card" method="post" action="<?= $action ?>">
        <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
        <div class="field">
            <label for="full_name"><span>Nom</span></label>
            <input class="input<?= isset($errors['full_name']) ? ' is-invalid' : '' ?>" id="full_name" name="full_name" type="text" required maxlength="120" value="<?= $value('full_name') ?>"<?= isset($errors['full_name']) ? ' aria-invalid="true" aria-describedby="full_name-error"' : '' ?>>
            <?php if (isset($errors['full_name'])): ?><p class="field-error" id="full_name-error"><?= Html::e($errors['full_name']) ?></p><?php endif; ?>
        </div>
        <div class="field">
            <label for="email"><span>E-mail</span></label>
            <input class="input<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" type="email" required maxlength="190" autocomplete="off" value="<?= $value('email') ?>"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
            <?php if (isset($errors['email'])): ?><p class="field-error" id="email-error"><?= Html::e($errors['email']) ?></p><?php endif; ?>
        </div>
        <?php if ($canSetPassword): ?>
            <div class="field">
                <label for="password"><span>Mot de passe<?= $edited === null ? '' : ' (laisser vide pour conserver)' ?></span></label>
                <input class="input<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" type="password" <?= $edited === null ? 'required minlength="12"' : '' ?> maxlength="255" autocomplete="new-password"<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                <?php if (isset($errors['password'])): ?><p class="field-error" id="password-error"><?= Html::e($errors['password']) ?></p><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($canDeactivate || $edited === null): ?>
            <div class="field">
                <label class="choice" for="is_active">
                    <input id="is_active" name="is_active" type="checkbox" value="1"<?= $isActive ? ' checked' : '' ?>>
                    <span>Compte actif</span>
                </label>
                <?php if (isset($errors['is_active'])): ?><p class="field-error"><?= Html::e($errors['is_active']) ?></p><?php endif; ?>
            </div>
        <?php else: ?>
            <input type="hidden" name="is_active" value="<?= $isActive ? '1' : '0' ?>">
        <?php endif; ?>
        <?php if ($canAssignRoles): ?>
            <fieldset class="field">
                <legend>Rôles</legend>
                <ul class="choice-list">
                    <?php foreach ($roles as $role): ?>
                        <?php
                        if (!is_array($role)) {
                            continue;
                        }
                        $slug = (string) ($role['slug'] ?? '');
                        $name = (string) ($role['name'] ?? $slug);
                        $id = 'role-' . preg_replace('/[^a-z0-9-]/', '', $slug);
                        ?>
                        <li>
                            <label class="choice" for="<?= Html::e($id) ?>">
                                <input id="<?= Html::e($id) ?>" name="roles[]" type="checkbox" value="<?= Html::e($slug) ?>"<?= in_array($slug, $selected, true) ? ' checked' : '' ?>>
                                <span><?= Html::e($name) ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (isset($errors['roles'])): ?><p class="field-error"><?= Html::e($errors['roles']) ?></p><?php endif; ?>
            </fieldset>
        <?php endif; ?>
        <div class="row">
            <button class="btn btn-primary" type="submit"><?= $edited === null ? 'Créer le compte' : 'Enregistrer' ?></button>
            <a class="btn btn-secondary" href="<?= Html::url('/utilisateurs') ?>">Retour à la liste</a>
        </div>
    </form>
    <?php if ($edited !== null && $canSetPassword): ?>
        <form class="card" method="post" action="<?= Html::url('/utilisateurs/' . $edited->id() . '/lien-reinitialisation') ?>">
            <input type="hidden" name="_csrf" value="<?= Html::e($csrf ?? '') ?>">
            <p>Créer un lien de réinitialisation, valable une heure. Il s'affiche une seule fois.</p>
            <button class="btn btn-secondary" type="submit">Créer un lien de réinitialisation</button>
        </form>
    <?php endif; ?>
</section>
