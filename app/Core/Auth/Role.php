<?php

declare(strict_types=1);

namespace Sesam\Core\Auth;

final class Role
{
    public const SUPER_ADMIN = 'super-admin';
    public const ADMIN = 'admin';
    public const COMMERCIAL = 'commercial';
    public const SUPPORT = 'support';
    public const FINANCE = 'finance';
    public const TENANT_OWNER = 'tenant-owner';
    public const MANAGER = 'manager';
    public const STAFF = 'staff';

    /** @var list<string> */
    public const ALL = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::COMMERCIAL,
        self::SUPPORT,
        self::FINANCE,
        self::TENANT_OWNER,
        self::MANAGER,
        self::STAFF,
    ];

    /** @var list<string> */
    public const SESAM = [
        self::SUPER_ADMIN,
        self::ADMIN,
        self::COMMERCIAL,
        self::SUPPORT,
        self::FINANCE,
    ];

    /** @var list<string> */
    public const TENANT = [
        self::TENANT_OWNER,
        self::MANAGER,
        self::STAFF,
    ];
}
