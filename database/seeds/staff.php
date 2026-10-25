<?php

declare(strict_types=1);

use Sesam\Core\Auth\Role;
use Sesam\Core\Database\Connection;
use Sesam\Core\Env;
use Sesam\Repositories\RoleRepository;
use Sesam\Repositories\UserRepository;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
/** @var array{db: array{host: string, port: string, name: string, user: string, password: string}} $config */
$config = require $root . '/config/app.php';

$email = strtolower(trim(Env::get('SESAM_STAFF_EMAIL')));
$password = Env::get('SESAM_STAFF_PASSWORD');
if ($email === '' || $password === '' || mb_strlen($password) < 12) {
    fwrite(STDERR, "Renseignez SESAM_STAFF_EMAIL et SESAM_STAFF_PASSWORD (12 caractères minimum) dans .env.\n");
    exit(1);
}

$pdo = Connection::make($config['db']);
$users = new UserRepository($pdo);
$roles = new RoleRepository($pdo);
$existing = $users->findByEmail($email);
$hash = password_hash($password, PASSWORD_DEFAULT);
$roleIds = $roles->idsForSlugs([Role::SUPER_ADMIN]);

if ($existing === null) {
    $id = $users->insert([
        'tenant_id' => null,
        'full_name' => 'Équipe SESAM',
        'email' => $email,
        'password_hash' => $hash,
        'is_active' => 1,
    ]);
    $roles->replaceUserRoles($id, $roleIds);
    echo "Compte staff créé : {$email}\n";
    exit(0);
}

$users->update($existing->id(), [
    'full_name' => $existing->fullName() !== '' ? $existing->fullName() : 'Équipe SESAM',
    'email' => $email,
    'is_active' => 1,
    'password_hash' => $hash,
]);
$roles->replaceUserRoles($existing->id(), $roleIds);
echo "Compte staff mis à jour : {$email}\n";
