<?php

declare(strict_types=1);

namespace Sesam\Services;

/**
 * Envoie les rappels programmés (rendez-vous la veille, etc.) arrivés à échéance.
 *
 * Lancé par la tâche planifiée « php bin/rappels-clients.php » (toutes les 5 à 15 minutes), ou à défaut
 * au fil des visites de l'application, au plus une fois toutes les 5 minutes.
 */
final class ReminderPump
{
    public const INTERVAL = 300;

    private static ?self $current = null;

    public function __construct(
        private readonly MessagingService $messaging,
        private readonly string $stampFile,
    ) {
    }

    public static function setCurrent(?self $pump): void
    {
        self::$current = $pump;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public function run(): int
    {
        return $this->messaging->processDueReminders();
    }

    /**
     * Sans tâche planifiée : une requête toutes les 5 minutes au plus envoie les rappels dus. Jamais bloquant.
     */
    public function runIfDue(): int
    {
        try {
            $last = is_file($this->stampFile) ? (int) @file_get_contents($this->stampFile) : 0;
            if (time() - $last < self::INTERVAL) {
                return 0;
            }
            $dir = dirname($this->stampFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $handle = @fopen($this->stampFile, 'c+');
            if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
                return 0;
            }
            try {
                if (time() - (int) stream_get_contents($handle) < self::INTERVAL) {
                    return 0;
                }
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, (string) time());
                fflush($handle);

                return $this->run();
            } finally {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        } catch (\Throwable) {
            return 0;
        }
    }
}
