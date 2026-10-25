<?php

declare(strict_types=1);

use Sesam\Helpers\Html;

$icon = static function (string $path): string {
    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $path . '"></path></svg>';
};

?>
<div class="app-shell">
    <aside class="sidebar" id="navigation" data-sidebar>
        <a class="wordmark" href="<?= Html::url('') ?>">SESAM</a>
        <nav class="side-nav" aria-label="Sections du système visuel">
            <a href="#apercu" aria-current="page"><?= $icon('M4 10h6V4H4v6zm10 0h6V4h-6v6zM4 20h6v-6H4v6zm10 0h6v-6h-6v6z') ?> Aperçu</a>
            <a href="#actions"><?= $icon('M5 12h14M12 5v14') ?> Actions</a>
            <a href="#formulaires"><?= $icon('M5 6h14M5 12h14M5 18h8') ?> Formulaires</a>
            <a href="#donnees"><?= $icon('M4 6h16v12H4z') ?> Données</a>
            <a href="#retours"><?= $icon('M12 7v6M10 17h4') ?> Retours</a>
        </nav>
    </aside>
    <div class="app-main">
        <header class="topbar">
            <button class="btn btn-secondary sidebar-toggle" type="button" data-sidebar-toggle aria-expanded="false" aria-controls="navigation">
                <?= $icon('M4 7h16M4 12h16M4 17h16') ?> Menu
            </button>
            <p class="topbar-title">Système visuel</p>
        </header>
        <main class="content" id="contenu">
            <p class="muted">Démonstration statique. Aucune donnée métier, aucun enregistrement.</p>

            <section class="hero" id="apercu" data-reveal>
                <h1>SESAM, clair et numérique</h1>
                <p>Une interface lisible, pensée d'abord pour le mobile, avec une identité bleu nuit, bleu électrique et cyan.</p>
                <a class="btn btn-ghost" href="#actions"><?= $icon('M5 12h14M13 6l6 6-6 6') ?> Voir les actions</a>
            </section>

            <section class="section" data-reveal>
                <h2>Activité d'exemple</h2>
                <div class="grid-4">
                    <article class="stat"><span class="muted">Rendez-vous</span><strong data-count="12">12</strong></article>
                    <article class="stat"><span class="muted">Commandes</span><strong data-count="3">03</strong></article>
                    <article class="stat"><span class="muted">Paiements en attente</span><strong data-count="2">02</strong></article>
                    <article class="stat"><span class="muted">Échéances</span><strong data-count="4">04</strong></article>
                </div>
            </section>

            <section class="section" id="actions" data-reveal>
                <h2>Actions</h2>
                <div class="row">
                    <button class="btn btn-primary" type="button"><?= $icon('M12 5v14M5 12h14') ?> Action principale</button>
                    <button class="btn btn-secondary" type="button">Action secondaire</button>
                    <button class="btn btn-primary" type="button" disabled>Indisponible</button>
                    <div class="dropdown" data-dropdown>
                        <button class="btn btn-secondary" type="button" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
                            Choisir <?= $icon('M6 9l6 6 6-6') ?>
                        </button>
                        <div class="dropdown-menu" data-dropdown-menu hidden>
                            <button type="button">Voir le détail</button>
                            <button type="button">Modifier</button>
                        </div>
                    </div>
                    <button class="btn btn-secondary" type="button" data-open-dialog="confirm-dialog">Supprimer l'exemple</button>
                </div>
            </section>

            <section class="section" id="formulaires" data-reveal>
                <h2>Formulaire</h2>
                <div class="grid-2">
                    <form class="card" action="#" method="get">
                        <div class="field">
                            <label for="demo-nom"><span>Nom</span></label>
                            <input class="input is-invalid" id="demo-nom" name="nom" type="text" aria-describedby="demo-nom-error" aria-invalid="true">
                            <p class="field-error" id="demo-nom-error">Le champ nom est obligatoire.</p>
                        </div>
                        <div class="field">
                            <label for="demo-email"><span>E-mail</span></label>
                            <input class="input" id="demo-email" name="email" type="email" autocomplete="email">
                        </div>
                        <div class="field">
                            <label for="demo-role"><span>Rôle</span></label>
                            <select class="select" id="demo-role" name="role">
                                <option>Équipe</option>
                                <option>Direction</option>
                            </select>
                        </div>
                        <button class="btn btn-primary" type="button">Enregistrer l'exemple</button>
                    </form>
                    <div class="card">
                        <h3>Champ désactivé</h3>
                        <div class="field">
                            <label for="demo-off"><span>Référence</span></label>
                            <input class="input" id="demo-off" type="text" value="SESAM-001" disabled>
                        </div>
                    </div>
                </div>
            </section>

            <section class="section" id="donnees" data-reveal>
                <h2>Listes et repères</h2>
                <nav aria-label="Fil d'Ariane">
                    <ol class="breadcrumb">
                        <li><a href="<?= Html::url('') ?>">Accueil</a></li>
                        <li aria-current="page">Système visuel</li>
                    </ol>
                </nav>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Élément</th>
                                <th>État</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Carte</td>
                                <td><span class="badge">Actif</span></td>
                                <td>Exemple</td>
                            </tr>
                            <tr>
                                <td>Accent</td>
                                <td><span class="badge badge-accent">Cyan</span></td>
                                <td>Pas pour le petit texte</td>
                            </tr>
                            <tr>
                                <td>Ligne</td>
                                <td><span class="badge">Lecture</span></td>
                                <td>Tableau défilable</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="grid-2 split">
                    <article class="panel">
                        <h3>Calendrier d'exemple</h3>
                        <p class="muted">Octobre 2026</p>
                        <div class="calendar" aria-label="Octobre 2026">
                            <span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span>
                            <span></span><span></span><span></span><span>1</span><span>2</span><span>3</span><span class="is-today">4</span>
                            <span>5</span><span>6</span><span>7</span><span>8</span><span>9</span><span>10</span><span>11</span>
                        </div>
                    </article>
                    <article class="panel">
                        <h3>Activité récente</h3>
                        <ol class="timeline">
                            <li><strong>Réservation confirmée</strong><br><span class="muted">Exemple</span></li>
                            <li><strong>Paiement reçu</strong><br><span class="muted">Exemple</span></li>
                            <li><strong>Échéance demain</strong><br><span class="muted">Exemple</span></li>
                        </ol>
                    </article>
                </div>
            </section>

            <section class="section" id="retours" data-reveal>
                <h2>Retours</h2>
                <div class="stack">
                    <div class="alert" role="status"><strong>Information.</strong> Le message reste lisible, sans jargon.</div>
                    <div class="alert alert-strong" role="alert"><strong>Erreur.</strong> Le texte explique quoi corriger.</div>
                    <div class="empty">
                        <h3>Aucun élément pour le moment</h3>
                        <p>Ajoutez un premier élément quand le module correspondant sera disponible.</p>
                        <a class="btn btn-primary" href="#formulaires">Voir le formulaire</a>
                    </div>
                    <div class="row" aria-busy="true" aria-live="polite">
                        <span class="loader" role="img" aria-label="Chargement"></span>
                        <span>Chargement</span>
                    </div>
                    <div class="card" aria-hidden="true">
                        <div class="skeleton-line skeleton-short"></div>
                        <div class="skeleton-block"></div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<dialog class="modal" id="confirm-dialog" aria-labelledby="confirm-title">
    <h2 id="confirm-title">Confirmer la suppression</h2>
    <p>Cette action est un exemple. Rien ne sera supprimé.</p>
    <div class="row">
        <button class="btn btn-secondary" type="button" data-close-dialog>Annuler</button>
        <button class="btn btn-primary" type="button" data-close-dialog>Supprimer l'exemple</button>
    </div>
</dialog>
