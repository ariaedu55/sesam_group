<?php

declare(strict_types=1);

namespace Sesam\Core\Crm;

final class ClientActivityKind
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const NOTE_ADDED = 'note_added';
    public const TAG_ADDED = 'tag_added';
    public const TAG_REMOVED = 'tag_removed';
    public const ARCHIVED = 'archived';
    public const RESTORED = 'restored';
}
