<?php
/**
 * Formulaire de Satisfaction pour Clients Connectés
 * Fichier: pages/client/satisfaction_client.php
 * Version: 1.0.0
 * Date: 2026-01-14
 */

require_once '../../config/database.php';
require_once '../../api/functions.php';

// Vérifier que l'utilisateur est connecté et est un client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = false;
$db = getDB();
$userId = $_SESSION['user_id'];

// Récupérer les tickets du client non encore évalués
$stmt = $db->prepare("
    SELECT id, numero_ticket, sujet, date_creation, statut_id, note_satisfaction
    FROM tickets
    WHERE client_id = ?
    ORDER BY date_creation DESC
");
$stmt->execute([$userId]);
$tickets = $stmt->fetchAll();

// Récupérer les statuts
$statuts = [];
$stmtStatuts = $db->query("SELECT id, nom FROM statuts");
foreach ($stmtStatuts->fetchAll() as $s) {
    $statuts[$s['id']] = $s['nom'];
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = intval($_POST['ticket_id'] ?? 0);
    $note = intval($_POST['note_satisfaction'] ?? 0);
    $commentaire = cleanInput($_POST['commentaire_satisfaction'] ?? '');
    
    if ($ticketId <= 0) {
        $error = 'Veuillez sélectionner un ticket';
    } elseif ($note < 1 || $note > 5) {
        $error = 'Veuillez sélectionner une note de 1 à 5';
    } else {
        // Vérifier que le ticket appartient au client
        $stmt = $db->prepare("SELECT id FROM tickets WHERE id = ? AND client_id = ?");
        $stmt->execute([$ticketId, $userId]);
        $ticketCheck = $stmt->fetch();
        
        if (!$ticketCheck) {
            $error = 'Ticket non trouvé ou ne vous appartient pas';
        } else {
            // Mettre à jour la satisfaction
            try {
                $stmt = $db->prepare("
                    UPDATE tickets
                    SET note_satisfaction = ?, 
                        commentaire_satisfaction = ?,
                        date_modification = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$note, $commentaire, $ticketId]);
                $success = true;
                
                // Recharger les tickets
                $stmt = $db->prepare("
                    SELECT id, numero_ticket, sujet, date_creation, statut_id, note_satisfaction
                    FROM tickets
                    WHERE client_id = ?
                    ORDER BY date_creation DESC
                ");
                $stmt->execute([$userId]);
                $tickets = $stmt->fetchAll();
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Satisfaction Client - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/fontawesome/css/all.min.css">
    <style>
        .satisfaction-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .satisfaction-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        .satisfaction-header h1 {
            margin: 0;
            color: var(--text-dark);
            font-size: 1.8rem;
        }
        .satisfaction-header p {
            color: var(--gray-600);
            margin: 0.5rem 0 0 0;
        }
        .ticket-list {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .ticket-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            border-left: 4px solid var(--primary-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .ticket-item:hover {
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
            transform: translateX(5px);
        }
        .ticket-item.selected {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left-color: #10b981;
        }
        .ticket-number {
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        .ticket-subject {
            margin-top: 0.25rem;
            font-weight: 500;
            color: var(--text-dark);
        }
        .ticket-status {
            font-size: 0.8rem;
            display: inline-block;
            background: var(--gray-200);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            margin-top: 0.5rem;
            color: var(--gray-700);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
        }
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 6px;
            font-family: inherit;
            font-size: 1rem;
        }
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .stars-container {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1rem;
            justify-content: center;
        }
        .star {
            font-size: 2.5rem;
            cursor: pointer;
            color: var(--gray-400);
            transition: all 0.2s ease;
            user-select: none;
        }
        .star:hover,
        .star.active {
            color: #ffc107;
            transform: scale(1.2);
        }
        .rating-text {
            text-align: center;
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            height: 1.5rem;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            color: #1565c0;
        }
        .success-box {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 1.5rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            color: #2e7d32;
            text-align: center;
        }
        .error-box {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            color: #c62828;
        }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: var(--primary-color);
            color: white;
            flex: 1;
        }
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: var(--gray-300);
            color: var(--text-dark);
        }
        .btn-secondary:hover {
            background: var(--gray-400);
        }
        .form-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: 8px;
            display: none;
        }
        .form-section.active {
            display: block;
            animation: slideIn 0.3s ease;
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-600);
        }
        .empty-state i {
            font-size: 3rem;
            opacity: 0.5;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="../../index.php">
                <i class="fas fa-headset"></i> 
                <span><?php echo SITE_NAME; ?></span>
                <span style="margin-left: 1rem; font-size: 0.75rem; background: #10b981; color: white; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600;">
                    Satisfaction
                </span>
            </a>
            <div class="navbar-menu">
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
                <a href="nouvelle_reclamation.php"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
                <a href="satisfaction.php" class="active"><i class="fas fa-smile"></i> <span>Satisfaction</span></a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
            </div>
            <button id="navbarToggler" class="navbar-toggler"><i class="fas fa-bars"></i></button>
        </div>
    </nav>

    <!-- Menu Mobile -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <strong><i class="fas fa-smile"></i> Satisfaction</strong>
            <p class="mobile-menu-subtitle">Mon Avis</p>
        </div>
        <a href="dashboard.php" class="mobile-menu-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="nouvelle_reclamation.php" class="mobile-menu-link"><i class="fas fa-plus-circle"></i> <span>Nouvelle Réclamation</span></a>
        <a href="satisfaction.php" class="mobile-menu-link active"><i class="fas fa-smile"></i> <span>Satisfaction</span></a>
        <a href="logout.php" class="mobile-menu-link" style="color: var(--danger-color) !important;"><i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span></a>
    </div>

    <div class="container-fluid" style="padding: 2rem;">
        <!-- Bouton Retour -->
        <a href="dashboard.php" style="margin-bottom: 1.5rem; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); color: white; text-decoration: none; border-radius: 12px; font-weight: 600; box-shadow: 0 2px 8px rgba(255, 107, 53, 0.3); transition: all 0.3s ease;">
            <i class="fas fa-arrow-left"></i> Retour au Dashboard
        </a>

        <div class="satisfaction-card">
            <div class="satisfaction-header">
                <h1><i class="fas fa-smile-wink"></i> Votre Satisfaction</h1>
                <p>Aidez-nous à améliorer nos services</p>
            </div>

            <?php if ($success): ?>
                <div class="success-box">
                    <i class="fas fa-check-circle"></i> 
                    <strong>Merci !</strong> Votre avis a été enregistré avec succès.
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="error-box">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <i class="fas fa-info-circle"></i> 
                Votre retour est important pour nous. Veuillez noter votre expérience avec nos services.
            </div>

            <?php if (empty($tickets)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune Réclamation</h3>
                    <p>Vous n'avez pas encore créé de réclamation.</p>
                    <a href="nouvelle_reclamation.php" class="btn btn-primary" style="display: inline-block; margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Créer une Réclamation
                    </a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="ticket_id">
                            <i class="fas fa-ticket-alt"></i> Sélectionnez un Ticket *
                        </label>
                        <div class="ticket-list">
                            <?php foreach ($tickets as $ticket): ?>
                                <div class="ticket-item" onclick="selectTicket(<?php echo $ticket['id']; ?>, this)">
                                    <div class="ticket-number">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($ticket['numero_ticket']); ?>
                                    </div>
                                    <div class="ticket-subject">
                                        <?php echo htmlspecialchars($ticket['sujet']); ?>
                                    </div>
                                    <div class="ticket-status">
                                        <?php echo htmlspecialchars($statuts[$ticket['statut_id']] ?? 'Inconnu'); ?>
                                    </div>
                                    <?php if ($ticket['note_satisfaction'] !== null): ?>
                                        <div style="margin-top: 0.5rem; font-size: 1rem;">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i class="fas fa-star" style="color: <?php echo ($i < $ticket['note_satisfaction']) ? '#FFC107' : '#ddd'; ?>; font-size: 0.8rem;"></i>
                                            <?php endfor; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="ticket_id" name="ticket_id" value="">
                    </div>

                    <div class="form-section" id="formSection">
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Votre Note *</label>
                            <div class="stars-container" id="starsContainer">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star" onclick="setRating(<?php echo $i; ?>)" data-value="<?php echo $i; ?>">
                                        <i class="fas fa-star"></i>
                                    </span>
                                <?php endfor; ?>
                            </div>
                            <div class="rating-text" id="ratingText"></div>
                        </div>

                        <div class="form-group">
                            <label for="commentaire_satisfaction">
                                <i class="fas fa-comment"></i> Commentaire (Optionnel)
                            </label>
                            <textarea id="commentaire_satisfaction" 
                                      name="commentaire_satisfaction" 
                                      placeholder="Partagez votre expérience avec nous..."
                                      rows="4"></textarea>
                        </div>

                        <div class="btn-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Envoyer mon Avis
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer" style="margin-top: 3rem;">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> - Tous droits réservés</p>
        </div>
    </footer>

    <script>
        const ratingTexts = ['', 'Très mauvais', 'Mauvais', 'Acceptable', 'Bon', 'Excellent'];

        function selectTicket(ticketId, element) {
            // Retirer la sélection précédente
            document.querySelectorAll('.ticket-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Ajouter la sélection à l'élément cliqué
            element.classList.add('selected');
            
            // Mettre à jour l'input caché
            document.getElementById('ticket_id').value = ticketId;
            
            // Afficher le formulaire
            document.getElementById('formSection').classList.add('active');
            
            // Scroll vers le formulaire
            document.getElementById('formSection').scrollIntoView({ behavior: 'smooth' });
        }

        function setRating(value) {
            const stars = document.querySelectorAll('.star');
            stars.forEach((star, index) => {
                if (index < value) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            document.getElementById('note_satisfaction').value = value;
            document.getElementById('ratingText').textContent = ratingTexts[value];
        }

        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenu = document.getElementById('mobileMenu');
            const toggler = document.getElementById('navbarToggler');
            const togglerIcon = toggler ? toggler.querySelector('i') : null;

            // Function to toggle menu state
            function setMenuState(isOpen) {
                if (isOpen) {
                    mobileMenu.classList.add('show');
                    if (togglerIcon) {
                        togglerIcon.classList.remove('fa-bars');
                        togglerIcon.classList.add('fa-times');
                    }
                } else {
                    mobileMenu.classList.remove('show');
                    if (togglerIcon) {
                        togglerIcon.classList.remove('fa-times');
                        togglerIcon.classList.add('fa-bars');
                    }
                }
            }

            // Toggle button click handler
            if (toggler) {
                toggler.addEventListener('click', function(e) {
                    e.stopPropagation(); 
                    const isCurrentlyOpen = mobileMenu.classList.contains('show');
                    setMenuState(!isCurrentlyOpen);
                });
            }

            // Document click handler
            document.addEventListener('click', function(event) {
                if (mobileMenu && mobileMenu.classList.contains('show')) {
                    const isClickOnOverlay = (event.target === mobileMenu);
                    const isClickOutside = !mobileMenu.contains(event.target);
                    const isClickOnToggler = (toggler && toggler.contains(event.target));

                    if ((isClickOutside || isClickOnOverlay) && !isClickOnToggler) {
                        setMenuState(false);
                    }
                }
            });
        });

        // Ajouter un input caché pour la note
        const noteInput = document.createElement('input');
        noteInput.type = 'hidden';
        noteInput.id = 'note_satisfaction';
        noteInput.name = 'note_satisfaction';
        noteInput.value = '0';
        document.querySelector('form').appendChild(noteInput);
    </script>
</body>
</html>
