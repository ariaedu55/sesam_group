<?php

declare(strict_types=1);

namespace Sesam\Core\Immo;

/**
 * Encaissements d'une échéance de loyer : chaque paiement (complet ou partiel, en main propre ou en ligne)
 * est une ligne de immo_payments ; le montant payé et le statut de l'échéance en découlent toujours.
 */
final class ImmoLedger
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param array{amount: int, method: string, paid_on: string, reference?: ?string, note?: ?string, payment_id?: ?int, actor_user_id?: ?int} $data
     */
    public function record(int $tenantId, int $dueId, array $data): int
    {
        $due = $this->pdo->prepare('SELECT id, lease_id FROM loyer_rent_dues WHERE id = ? AND tenant_id = ?');
        $due->execute([$dueId, $tenantId]);
        $row = $due->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('Échéance introuvable.');
        }
        $this->pdo->prepare(
            'INSERT INTO immo_payments (tenant_id, due_id, lease_id, amount_minor, method, reference, paid_on, note, payment_id, receipt_number, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, UTC_TIMESTAMP())'
        )->execute([
            $tenantId, $dueId, (int) $row['lease_id'], $data['amount'], $data['method'], $data['reference'] ?? null,
            $data['paid_on'], $data['note'] ?? null, $data['payment_id'] ?? null, $data['actor_user_id'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE immo_payments SET receipt_number = ? WHERE id = ?')
            ->execute(['Q-' . substr($data['paid_on'], 0, 4) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT), $id]);
        $this->refresh($tenantId, $dueId);

        return $id;
    }

    /**
     * Paiement en ligne confirmé (NabooPay ou simulation) : il devient un encaissement de l'échéance liée.
     * Sans effet si le paiement ne règle pas un loyer ou s'il est déjà enregistré.
     */
    public function applyOnlinePayment(int $tenantId, int $paymentId, int $amount, string $paidAt): void
    {
        $due = $this->pdo->prepare("SELECT id FROM loyer_rent_dues WHERE tenant_id = ? AND payment_id = ? AND status IN ('pending', 'partial')");
        $due->execute([$tenantId, $paymentId]);
        $dueId = $due->fetchColumn();
        if ($dueId === false) {
            return;
        }
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM immo_payments WHERE payment_id = ?');
        $exists->execute([$paymentId]);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }
        $this->record($tenantId, (int) $dueId, [
            'amount' => $amount,
            'method' => 'en-ligne',
            'paid_on' => substr($paidAt, 0, 10),
            'reference' => null,
            'note' => 'Lien de paiement',
            'payment_id' => $paymentId,
        ]);
    }

    /** Recalcule le montant payé et le statut de l'échéance à partir de ses encaissements. */
    public function refresh(int $tenantId, int $dueId): void
    {
        $sum = $this->pdo->prepare('SELECT COALESCE(SUM(amount_minor), 0), MAX(paid_on) FROM immo_payments WHERE due_id = ? AND tenant_id = ?');
        $sum->execute([$dueId, $tenantId]);
        [$paid, $last] = $sum->fetch(\PDO::FETCH_NUM) ?: [0, null];
        $paid = (int) $paid;
        $this->pdo->prepare(
            "UPDATE loyer_rent_dues
             SET paid_minor = :paid,
                 status = CASE WHEN status = 'cancelled' THEN status WHEN :paid_a >= amount_minor THEN 'paid' WHEN :paid_b > 0 THEN 'partial' ELSE 'pending' END,
                 paid_at = CASE WHEN :paid_c >= amount_minor THEN COALESCE(:last, UTC_TIMESTAMP()) ELSE NULL END,
                 updated_at = UTC_TIMESTAMP()
             WHERE id = :id AND tenant_id = :tenant_id"
        )->execute([
            'paid' => $paid, 'paid_a' => $paid, 'paid_b' => $paid, 'paid_c' => $paid,
            'last' => $last !== null ? $last . ' 12:00:00' : null,
            'id' => $dueId, 'tenant_id' => $tenantId,
        ]);
    }
}
