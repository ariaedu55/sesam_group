<?php

declare(strict_types=1);

namespace Sesam\Core\Security;

use Sesam\Core\Session\Session;

/**
 * Pièges anti-robots des formulaires publics (inscription, connexion, devis, réservations, commandes…).
 *
 *  1. Champ piège « website » : invisible pour un humain (hors écran, ignoré par les lecteurs d'écran et
 *     l'autoremplissage), mais les robots remplissent tous les champs. Rempli = robot.
 *  2. Piège du temps : un jeton signé note l'heure d'affichage du formulaire. Envoyé trop vite = robot ;
 *     jeton absent ou falsifié = robot qui poste sans afficher la page.
 *
 * Aucun captcha : rien ne change pour les visiteurs.
 */
final class Honeypot
{
    /** Nom du champ piège : volontairement « appétissant » pour un robot. */
    public const FIELD = 'website';
    public const TIME_FIELD = '_hp_t';
    private const SESSION_KEY = '_hp_key';
    /** Au-delà, le jeton est trop vieux (page restée ouverte plus d'un jour) : on demande de renvoyer. */
    private const MAX_AGE = 86400;

    private static ?self $current = null;

    public function __construct(private readonly Session $session)
    {
    }

    public static function setCurrent(?self $honeypot): void
    {
        self::$current = $honeypot;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * Champs à placer dans le formulaire (partials/honeypot.php).
     */
    public function fields(): string
    {
        $time = (string) time();
        $id = 'hp-website-' . bin2hex(random_bytes(4));

        // Hors écran (et non display:none, que certains robots repèrent) ; style en ligne pour marcher sur toutes les pages.
        return '<div class="hp-field" aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">'
            . '<label for="' . $id . '">Ne pas remplir ce champ</label>'
            . '<input type="text" id="' . $id . '" name="' . self::FIELD . '" value="" tabindex="-1" autocomplete="off">'
            . '</div>'
            . '<input type="hidden" name="' . self::TIME_FIELD . '" value="' . htmlspecialchars($time . '.' . $this->sign($time), ENT_QUOTES) . '">';
    }

    /**
     * « ok », ou la raison du blocage : « filled » (champ piège rempli), « missing » / « forged » (jeton absent
     * ou faux), « fast » (envoyé trop vite), « expired » (page trop ancienne).
     */
    public function check(mixed $trap, mixed $token, int $minSeconds, ?int $now = null): string
    {
        if (is_string($trap) && trim($trap) !== '') {
            return 'filled';
        }
        if (!is_string($token) || !str_contains($token, '.')) {
            return 'missing';
        }
        [$time, $signature] = explode('.', $token, 2);
        if (!ctype_digit($time) || !hash_equals($this->sign($time), $signature)) {
            return 'forged';
        }
        $elapsed = ($now ?? time()) - (int) $time;
        if ($elapsed < $minSeconds) {
            return 'fast';
        }
        if ($elapsed > self::MAX_AGE) {
            return 'expired';
        }

        return 'ok';
    }

    private function sign(string $time): string
    {
        return hash_hmac('sha256', $time, $this->key());
    }

    private function key(): string
    {
        $key = $this->session->get(self::SESSION_KEY);
        if (!is_string($key) || strlen($key) < 32) {
            $key = bin2hex(random_bytes(32));
            $this->session->put(self::SESSION_KEY, $key);
        }

        return $key;
    }
}
