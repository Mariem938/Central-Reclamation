<?php
/**
 * Gestionnaire d'Emails V15 (Restauration) - Visual Sync
 * Design Conforme aux Captures | Port 465 SSL | Identité Hammami-groupe
 */




use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';

class EmailManager {
    private $db;
   
    const SMTP_HOST = 'ssl0.ovh.net';
    const SMTP_PORT = 465; 
    const SMTP_USER = 'software@groupe-hammami.com.tn';
    const SMTP_PASS = 'S4fpY2DmA*';
   
    public function __construct($db) {
        $this->db = $db;
    }

    private function getMailer() {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = self::SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = self::SMTP_USER;
        $mail->Password   = self::SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = self::SMTP_PORT;
        
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(self::SMTP_USER, 'Hammami-groupe'); 
        $mail->addReplyTo(self::SMTP_USER, 'Hammami-groupe');
        $mail->Sender = self::SMTP_USER;
        
        $mail->Hostname = 'groupe-hammami.com.tn';
        $mail->XMailer = ' '; 
        
        $mail->SMTPOptions = [
            'ssl' => ['verify_peer'=>false, 'verify_peer_name'=>false, 'allow_self_signed'=>true]
        ];
        
        return $mail;
    }

    private function execute($tid, $to, $sub, $html) {
        try {
            $mail = $this->getMailer();
            if (is_array($to)) {
                foreach ($to as $email) $mail->addAddress(trim($email));
            } else {
                $mail->addAddress(trim($to));
            }
            
            $mail->isHTML(true);
            $mail->Subject = $sub;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</div>', '</p>'], ["\n", "\n", "\n\n"], $html)));
            
            $status = $mail->send();
            @file_put_contents(__DIR__ . '/../logs/email_logs.txt', "[" . date('Y-m-d H:i:s') . "] V15 REVERT ID:$tid | To:" . (is_array($to) ? 'Multi' : $to) . " | Stat:sent | Sub:$sub\n", FILE_APPEND);
            return $status;
        } catch (Exception $e) {
            @file_put_contents(__DIR__ . '/../logs/email_errors.log', "[" . date('Y-m-d H:i:s') . "] V15 ERROR: " . $mail->ErrorInfo . "\n", FILE_APPEND);
            return false;
        }
    }
   
    // --- TEMPLATE PREMIUM (V15 Visual Sync) ---
    private function getPremiumLayout($headerTitle, $mainContent, $ticketNumero, $showFeedback = false) {
        $colorOrange = "#FF8C00"; 
        $colorOrangeLight = "#FFA500";
        $colorOrangeBtn = "#FF8C00";
        
        $linkSuivi = "http://localhost/Central_Reclamation/pages/client/suivi_reclamation.php?ticket=" . $ticketNumero;
        
        $feedbackBlock = "";
        if ($showFeedback) {
            $feedbackBlock = "
            <div style='margin-top: 30px; border: 1px solid #BFDBFE; border-radius: 8px; padding: 25px; text-align: center; background-color: #EFF6FF;'>
                <p style='color: #1E40AF; font-weight: bold; margin-top: 0; margin-bottom: 20px; font-size: 17px;'>Donnez votre avis sur le traitement de votre réclamation</p>
                <a href='http://localhost/Central_Reclamation/evaluation.php?t=$ticketNumero' style='background-color: #1D4ED8; color: #ffffff !important; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block; font-size: 15px;'>
                    ⭐ Évaluer le traitement
                </a>
            </div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='margin: 0; padding: 0; background-color: #fcfcfc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #fcfcfc;'>
                <tr>
                    <td align='center' style='padding: 20px;'>
                        <div style='max-width: 650px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;'>
                            
                            <div style='background: linear-gradient(to right, $colorOrange, $colorOrangeLight); padding: 35px 20px; text-align: center;'>
                                <h1 style='color: white; margin: 0; font-size: 32px; font-weight: 700;'>
                                    📢 $headerTitle
                                </h1>
                            </div>

                            <div style='padding: 40px 30px;'>
                                <div style='color: #4B5563; font-size: 16px; line-height: 1.6;'>
                                    $mainContent
                                </div>
                                
                                <div style='text-align: center; margin: 30px 0;'>
                                    <a href='$linkSuivi' style='background-color: $colorOrangeBtn; color: #ffffff !important; padding: 14px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>
                                        📊 Suivre ma réclamation
                                    </a>
                                </div>

                                <p style='color: #4B5563; font-size: 15px;'>Notre équipe traite votre demande et vous tiendra informé de son évolution.</p>

                                <div style='background-color: #fcfcfc; border-left: 4px solid #F59E0B; padding: 15px; margin: 25px 0; color: #4B5563; font-size: 15px;'>
                                    <strong>💡 Astuce :</strong> Connectez-vous à votre espace client pour voir l'historique complet de votre réclamation.
                                </div>

                                $feedbackBlock
                                
                                <div style='margin-top: 40px; padding-top: 25px; border-top: 1px solid #F3F4F6;'>
                                    <p style='color: #1F2937; font-weight: 700; margin-bottom: 8px; font-size: 16px;'>Nous vous remercions de votre confiance et de votre fidélité.</p>
                                    <p style='color: #4B5563; font-size: 14px; line-height: 1.5; margin-bottom: 20px;'>Votre satisfaction est au cœur de nos préoccupations et vos retours nous permettent d'améliorer continuellement nos services.</p>
                                    <p style='color: #4B5563; font-size: 15px; margin: 0;'>Nous restons à votre entière disposition pour toute information complémentaire.</p>
                                </div>
                                
                                <div style='margin-top: 20px; color: #9CA3AF; font-size: 18px;'>•••</div>
                            </div>

                            <div style='background-color: #F9FAFB; padding: 25px; text-align: center; color: #9CA3AF; font-size: 12px; border-top: 1px solid #F3F4F6;'>
                                <strong>Groupe Hammami - SCPC</strong><br>
                                Service Relation Clientèle<br>
                                <span style='font-size: 10px; margin-top: 10px; display: block;'>Ceci est une notification automatique. Merci de ne pas répondre.</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    }

    // --- CONSTRUCTION DES DETAILS (V15 Visual Sync) ---
    private function buildTicketDetails($ticket, $rows) {
        $html = "
        <div style='background-color: #ffffff; border: 1px solid #E5E7EB; border-left: 5px solid #FF6200; border-radius: 8px; margin: 30px 0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);'>
            <div style='background-color: #ffffff; padding: 25px 15px; text-align: center;'>
                <div style='color: #6B7280; font-size: 13px; text-transform: none; font-weight: 500; margin-bottom: 5px;'>Numéro de ticket</div>
                <div style='color: #FF6200; font-size: 42px; font-weight: 900; margin: 0; letter-spacing: -1px;'>" . $ticket['numero_ticket'] . "</div>
            </div>
            <div style='padding: 0 25px 25px 25px;'>
        ";
        
        foreach ($rows as $label => $value) {
            $html .= "
            <div style='border-bottom: 1px solid #F3F4F6; padding: 15px 0; font-size: 16px;'>
                <span style='color: #1F2937; font-weight: bold;'>$label :</span>
                <span style='color: #4B5563; margin-left: 5px;'>$value</span>
            </div>";
        }
        
        $html .= "
            <div style='padding-top: 20px; font-size: 14px; color: #4B5563; text-align: left;'>
                <strong>Mis à jour le :</strong> " . date('d/m/Y') . " à " . date('H:i') . "
            </div>
            </div>
        </div>";
        return $html;
    }

    public function envoyerConfirmationClientAuth($ticket, $client) {
        $details = $this->buildTicketDetails($ticket, [
            'Sujet' => htmlspecialchars($ticket['sujet']),
            'Catégorie' => 'Réclamation client',
            'Statut' => '<span style="color: #F97316; font-weight: bold;">Nouveau</span>'
        ]);
        $msg = "<p>Bonjour <strong>" . htmlspecialchars($client['nom_complet']) . "</strong>,</p>
                <p>Votre réclamation a bien été enregistrée. Voici le récapitulatif :</p>
                $details";
        return $this->execute($ticket['id'], $client['email'], "Confirmation " . $ticket['numero_ticket'], 
            $this->getPremiumLayout("Réclamation Enregistrée", $msg, $ticket['numero_ticket']));
    }
   
    public function envoyerCredentialsNouveauClient($ticket, $client, $pass) {
        $numeroTicket = $ticket['numero_ticket'] ?? 'N/A';
        $clientEmail = $client['email'] ?? $ticket['email_client'] ?? 'Non spécifié';
        $nomComplet = $client['nom_complet'] ?? trim(($ticket['prenom_client'] ?? '') . ' ' . ($ticket['nom_client'] ?? ''));
        $sujet = $ticket['sujet'] ?? 'Réclamation';
        $dateCreation = isset($ticket['date_creation']) ? date('d/m/Y à H:i', strtotime($ticket['date_creation'])) : date('d/m/Y à H:i');
        
        $loginLink = "http://localhost/Central_Reclamation/pages/client/login.php";
        $suiviLink = "http://localhost/Central_Reclamation/pages/client/suivi_reclamation.php?ticket=" . $numeroTicket;
        
        $colorOrange = "#FF8C00";
        $colorOrangeLight = "#FFA500";
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #f4f4f4;'>
                <tr>
                    <td align='center' style='padding: 30px 20px;'>
                        <div style='max-width: 650px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e0e0e0;'>
                            
                            <!-- Header -->
                            <div style='background: linear-gradient(135deg, $colorOrange, $colorOrangeLight); padding: 35px 30px; text-align: center;'>
                                <h1 style='color: white; margin: 0; font-size: 26px; font-weight: 700;'>
                                    🎉 Bienvenue chez SCPC !
                                </h1>
                                <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 15px;'>Votre compte client a été créé avec succès</p>
                            </div>

                            <!-- Content -->
                            <div style='padding: 35px 30px;'>
                                
                                <p style='color: #1F2937; font-size: 16px; line-height: 1.6; margin-bottom: 25px;'>
                                    Bonjour <strong>" . htmlspecialchars($nomComplet) . "</strong>,
                                </p>
                                
                                <p style='color: #4B5563; font-size: 15px; line-height: 1.6; margin-bottom: 25px;'>
                                    Votre réclamation a bien été enregistrée et un compte client a été créé automatiquement pour vous permettre de suivre son traitement.
                                </p>

                                <!-- Ticket Info -->
                                <div style='background: #FFFBF5; border: 1px solid #FFE4C4; border-left: 5px solid $colorOrange; border-radius: 8px; padding: 20px; margin-bottom: 25px;'>
                                    <h3 style='color: #1F2937; margin: 0 0 15px 0; font-size: 15px;'>
                                        📋 Votre réclamation
                                    </h3>
                                    <table width='100%' cellpadding='5' cellspacing='0' style='font-size: 14px;'>
                                        <tr>
                                            <td style='color: #6B7280; width: 120px;'><strong>N° Ticket</strong></td>
                                            <td style='color: #FF6B35; font-weight: bold; font-size: 16px;'>$numeroTicket</td>
                                        </tr>
                                        <tr>
                                            <td style='color: #6B7280;'><strong>Sujet</strong></td>
                                            <td style='color: #1F2937;'>" . htmlspecialchars($sujet) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='color: #6B7280;'><strong>Date</strong></td>
                                            <td style='color: #1F2937;'>$dateCreation</td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Credentials Block - IMPORTANT -->
                                <div style='background: linear-gradient(135deg, #ECFDF5, #D1FAE5); border: 2px solid #10B981; border-radius: 10px; padding: 25px; margin-bottom: 25px;'>
                                    <h3 style='color: #065F46; margin: 0 0 5px 0; font-size: 16px; text-align: center;'>
                                        🔐 Vos identifiants de connexion
                                    </h3>
                                    <p style='color: #047857; font-size: 13px; text-align: center; margin: 0 0 20px 0;'>
                                        Conservez ces informations précieusement
                                    </p>
                                    
                                    <div style='background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);'>
                                        <table width='100%' cellpadding='10' cellspacing='0'>
                                            <tr>
                                                <td style='color: #374151; font-weight: 600; width: 120px; border-bottom: 1px solid #E5E7EB;'>
                                                    📧 Email
                                                </td>
                                                <td style='color: #1F2937; font-size: 16px; border-bottom: 1px solid #E5E7EB;'>
                                                    <strong>" . htmlspecialchars($clientEmail) . "</strong>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style='color: #374151; font-weight: 600;'>
                                                    🔑 Mot de passe
                                                </td>
                                                <td style='color: #059669; font-size: 18px; font-family: monospace; letter-spacing: 1px;'>
                                                    <strong>" . htmlspecialchars($pass) . "</strong>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>

                                <!-- Instructions -->
                                <div style='background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; padding: 15px 20px; margin-bottom: 25px;'>
                                    <p style='margin: 0; color: #0369A1; font-size: 14px; line-height: 1.6;'>
                                        <strong>💡 Comment accéder à votre espace client ?</strong><br>
                                        1. Cliquez sur le bouton ci-dessous<br>
                                        2. Entrez votre email et mot de passe<br>
                                        3. Suivez l'état de votre réclamation en temps réel
                                    </p>
                                </div>

                                <!-- Boutons -->
                                <div style='text-align: center; margin-bottom: 25px;'>
                                    <a href='$loginLink' style='display: inline-block; background: linear-gradient(135deg, #10B981, #059669); color: #ffffff !important; padding: 16px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(16,185,129,0.4); margin-right: 10px;'>
                                        🔓 Me connecter
                                    </a>
                                    <a href='$suiviLink' style='display: inline-block; background: linear-gradient(135deg, #FF6B35, #FF8C00); color: #ffffff !important; padding: 16px 35px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(255,107,53,0.4);'>
                                        📊 Suivre ma réclamation
                                    </a>
                                </div>

                                <p style='color: #9CA3AF; font-size: 12px; text-align: center; margin-top: 20px;'>
                                    ⚠️ Par mesure de sécurité, nous vous recommandons de modifier votre mot de passe après votre première connexion.
                                </p>
                            </div>

                            <!-- Footer -->
                            <div style='background-color: #F9FAFB; padding: 25px 30px; text-align: center; border-top: 1px solid #E5E7EB;'>
                                <p style='margin: 0 0 5px 0; color: #374151; font-weight: 600; font-size: 14px;'>Groupe Hammami - SCPC</p>
                                <p style='margin: 0 0 10px 0; color: #6B7280; font-size: 13px;'>Service Relation Clientèle</p>
                                <p style='margin: 0; color: #9CA3AF; font-size: 11px; font-style: italic;'>
                                    Cet email est généré automatiquement, merci de ne pas y répondre.
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
        
        return $this->execute($ticket['id'], $clientEmail, "Bienvenue chez SCPC - Vos identifiants de connexion (Ticket $numeroTicket)", $html);
    }
   
    public function notifierAgents($ticket, $client) {
        $notifications = [];

        // 1. RMQ : Toujours notifié (Supervision)
        try {
            $stmt = $this->db->query("SELECT email FROM utilisateurs WHERE role = 'rmq' AND actif = 1");
            $rmqEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($rmqEmails) {
                $notifications['RMQ'] = [
                    'emails' => $rmqEmails,
                    'role_label' => 'RMQ – Responsable Management Qualité',
                    'role_context' => 'Validation Requise',
                    'detail_url' => 'pages/rmq/detail_ticket.php'
                ];
            }
        } catch (Exception $e) {}

        // 2. AQF : Notifié uniquement si Fournisseur/Transport impliqué ou Type NC produit
        $isAqfConcern = false;
        if (!empty($ticket['fournisseur_nom']) || !empty($ticket['fournisseur_prenom']) || !empty($ticket['societe_transport'])) {
            $isAqfConcern = true;
        }
        if (stripos($ticket['type_ticket'] ?? '', 'produit') !== false || stripos($ticket['type_nc'] ?? '', 'produit') !== false) {
            $isAqfConcern = true;
        }

        if ($isAqfConcern) {
            try {
                $stmt = $this->db->query("SELECT email FROM utilisateurs WHERE role = 'aqf' AND actif = 1");
                $aqfEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if ($aqfEmails) {
                    $notifications['AQF'] = [
                        'emails' => $aqfEmails,
                        'role_label' => 'AQF – Assurance Qualité Fournisseur',
                        'role_context' => 'Action Fournisseur/Produit Requise',
                        'detail_url' => 'pages/aqf/detail_ticket.php'
                    ];
                }
            } catch (Exception $e) {}
        }

        // 3. Commercial : Notifié si c'est son client
        if (!empty($ticket['commercial_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT email FROM utilisateurs WHERE id = ? AND actif = 1");
                $stmt->execute([$ticket['commercial_id']]);
                $commEmail = $stmt->fetchColumn();
                if ($commEmail) {
                    $notifications['Commercial'] = [
                        'emails' => [$commEmail],
                        'role_label' => 'Commercial – Suivi Client',
                        'role_context' => 'Suivi Client',
                        'detail_url' => 'pages/commercial/view_ticket.php'
                    ];
                }
            } catch (Exception $e) {}
        }

        // Préparation des données pour l'email
        $ticketId = $ticket['id'] ?? 0;
        $numeroTicket = $ticket['numero_ticket'] ?? 'N/A';
        $nomClient = trim(($ticket['prenom_client'] ?? '') . ' ' . ($ticket['nom_client'] ?? ''));
        if (empty($nomClient)) $nomClient = $client['nom_complet'] ?? 'Non spécifié';
        $emailClient = $ticket['email_client'] ?? $client['email'] ?? 'Non spécifié';
        $telephoneClient = $ticket['telephone_client'] ?? $client['telephone'] ?? 'Non spécifié';
        $siteRaw = $ticket['site_scpc'] ?? $ticket['site_autre'] ?? 'Non spécifié';
        $description = $ticket['description'] ?? $ticket['sujet'] ?? 'Non spécifiée';
        $dateCreation = isset($ticket['date_creation']) ? date('d/m/Y à H:i', strtotime($ticket['date_creation'])) : date('d/m/Y à H:i');

        // Envoi des notifications ciblées avec template professionnel
        foreach ($notifications as $role => $data) {
            $subject = "Nouvelle réclamation créée – Action requise (Ticket n° $numeroTicket)";
            $detailLink = "http://localhost/Central_Reclamation/" . $data['detail_url'] . "?id=" . $ticketId;
            
            $emailHtml = $this->getAgentNotificationLayout(
                $numeroTicket,
                $nomClient,
                $emailClient,
                $telephoneClient,
                $siteRaw,
                $description,
                $dateCreation,
                $data['role_label'],
                $detailLink
            );
            
            $this->execute($ticketId, $data['emails'], $subject, $emailHtml);
        }

        return true;
    }

    /**
     * Template HTML professionnel pour les notifications agents
     */
    private function getAgentNotificationLayout($numeroTicket, $nomClient, $emailClient, $telephoneClient, $site, $description, $dateCreation, $roleAgent, $detailLink) {
        $colorOrange = "#FF8C00";
        $colorOrangeLight = "#FFA500";
        
        // Tronquer la description si trop longue
        $descriptionDisplay = (strlen($description) > 300) ? substr($description, 0, 297) . '...' : $description;
        
        return "
        <!DOCTYPE html>
        <html>
        <head><meta charset='UTF-8'></head>
        <body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif;'>
            <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #f4f4f4;'>
                <tr>
                    <td align='center' style='padding: 30px 20px;'>
                        <div style='max-width: 650px; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid #e0e0e0;'>
                            
                            <!-- Header -->
                            <div style='background: linear-gradient(135deg, $colorOrange, $colorOrangeLight); padding: 35px 30px; text-align: center;'>
                                <h1 style='color: white; margin: 0; font-size: 26px; font-weight: 700;'>
                                    📢 Nouvelle Réclamation Créée
                                </h1>
                                <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0; font-size: 15px;'>Action requise de votre part</p>
                            </div>

                            <!-- Content -->
                            <div style='padding: 35px 30px;'>
                                
                                <!-- Ticket Number Badge -->
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <div style='display: inline-block; background: linear-gradient(135deg, #FF6B35, #FF8C00); color: white; padding: 15px 35px; border-radius: 50px; font-size: 20px; font-weight: bold; box-shadow: 0 4px 15px rgba(255,107,53,0.3);'>
                                        🎫 Ticket n° $numeroTicket
                                    </div>
                                </div>

                                <!-- Informations Réclamation -->
                                <div style='background: #FFFBF5; border: 1px solid #FFE4C4; border-left: 5px solid $colorOrange; border-radius: 8px; padding: 25px; margin-bottom: 25px;'>
                                    <h3 style='color: #1F2937; margin: 0 0 20px 0; font-size: 16px; border-bottom: 1px solid #FFE4C4; padding-bottom: 10px;'>
                                        📋 Informations de la Réclamation
                                    </h3>
                                    
                                    <table width='100%' cellpadding='8' cellspacing='0' style='font-size: 15px;'>
                                        <tr>
                                            <td style='color: #6B7280; width: 140px; vertical-align: top;'><strong>Numéro de ticket</strong></td>
                                            <td style='color: #1F2937;'>$numeroTicket</td>
                                        </tr>
                                        <tr style='background: rgba(255,140,0,0.05);'>
                                            <td style='color: #6B7280; vertical-align: top;'><strong>Nom du client</strong></td>
                                            <td style='color: #1F2937;'>" . htmlspecialchars($nomClient) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='color: #6B7280; vertical-align: top;'><strong>Email du client</strong></td>
                                            <td style='color: #1F2937;'><a href='mailto:" . htmlspecialchars($emailClient) . "' style='color: #FF6B35;'>" . htmlspecialchars($emailClient) . "</a></td>
                                        </tr>
                                        <tr style='background: rgba(255,140,0,0.05);'>
                                            <td style='color: #6B7280; vertical-align: top;'><strong>Téléphone</strong></td>
                                            <td style='color: #1F2937;'>" . htmlspecialchars($telephoneClient) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='color: #6B7280; vertical-align: top;'><strong>Site concerné</strong></td>
                                            <td style='color: #1F2937;'>" . htmlspecialchars($site) . "</td>
                                        </tr>
                                        <tr style='background: rgba(255,140,0,0.05);'>
                                            <td style='color: #6B7280; vertical-align: top;'><strong>Description</strong></td>
                                            <td style='color: #1F2937; line-height: 1.5;'>" . nl2br(htmlspecialchars($descriptionDisplay)) . "</td>
                                        </tr>
                                        <tr>
                                            <td style='color: #6B7280; vertical-align: top;'><strong>Date de création</strong></td>
                                            <td style='color: #1F2937;'>$dateCreation</td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Agent Concerné -->
                                <div style='background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; padding: 15px 20px; margin-bottom: 25px;'>
                                    <p style='margin: 0; color: #0369A1; font-size: 15px;'>
                                        <strong>👤 Agent concerné :</strong> " . htmlspecialchars($roleAgent) . "
                                    </p>
                                </div>

                                <!-- Action Requise -->
                                <div style='background: linear-gradient(135deg, #FEF3C7, #FDE68A); border: 2px solid #F59E0B; border-radius: 10px; padding: 20px 25px; margin-bottom: 30px; text-align: center;'>
                                    <p style='margin: 0 0 5px 0; color: #92400E; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;'>⚠️ Action Requise</p>
                                    <p style='margin: 0; color: #78350F; font-size: 15px; line-height: 1.6;'>
                                        Veuillez consulter et traiter cette réclamation dans les plus brefs délais afin d'assurer un suivi efficace et conforme aux procédures qualité.
                                    </p>
                                </div>

                                <!-- Bouton Action -->
                                <div style='text-align: center; margin-bottom: 20px;'>
                                    <a href='$detailLink' style='display: inline-block; background: linear-gradient(135deg, #FF6B35, #FF8C00); color: #ffffff !important; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(255,107,53,0.4); transition: all 0.3s;'>
                                        👁️ Voir le ticket
                                    </a>
                                </div>
                                
                                <p style='text-align: center; color: #9CA3AF; font-size: 13px; margin-top: 25px;'>
                                    Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
                                    <a href='$detailLink' style='color: #FF6B35; word-break: break-all;'>$detailLink</a>
                                </p>
                            </div>

                            <!-- Footer -->
                            <div style='background-color: #F9FAFB; padding: 25px 30px; text-align: center; border-top: 1px solid #E5E7EB;'>
                                <p style='margin: 0 0 5px 0; color: #374151; font-weight: 600; font-size: 14px;'>Groupe Hammami - SCPC</p>
                                <p style='margin: 0 0 10px 0; color: #6B7280; font-size: 13px;'>Plateforme de Gestion des Réclamations</p>
                                <p style='margin: 0; color: #9CA3AF; font-size: 11px; font-style: italic;'>
                                    Cet email est généré automatiquement, merci de ne pas y répondre.
                                </p>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    }
   
    public function envoyerChangementStatut($ticket, $email, $statut) {
        $details = $this->buildTicketDetails($ticket, [
            'Sujet' => htmlspecialchars($ticket['sujet']),
            'Catégorie' => 'Réclamation client',
            'Statut' => "<span style='color: #F97316; font-weight: bold;'>" . htmlspecialchars($statut) . "</span>"
        ]);
        $msg = "<p>Bonjour,</p>
                <p>Le statut de votre réclamation a été mis à jour. Veuillez consulter les détails ci-dessous.</p>
                $details";
        $showFeedback = (stripos($statut, 'solu') !== false || stripos($statut, 'vérification') !== false);
        return $this->execute($ticket['id'], $email, "Mise à Jour - " . $ticket['numero_ticket'], 
            $this->getPremiumLayout("Mise à Jour - Réclamation", $msg, $ticket['numero_ticket'], $showFeedback));
    }

    public function envoyerEmailRefus($ticket, $motif) {
        $details = $this->buildTicketDetails($ticket, [
            'Sujet' => htmlspecialchars($ticket['sujet']),
            'Statut' => '<span style="color: #64748B; font-weight: bold;">Clôturé</span>'
        ]);
        $msg = "<p>Votre dossier a été clôturé.</p>
                $details
                <div style='background: #FEF2F2; border-left: 4px solid #DC2626; padding: 15px; margin-top: 15px; color: #991B1B;'>
                    <strong>Motif :</strong><br>" . nl2br(htmlspecialchars($motif)) . "
                </div>";
        return $this->execute($ticket['id'], $ticket['email_client'], "Information Dossier", 
            $this->getPremiumLayout("Clôture Réclamation", $msg, $ticket['numero_ticket']));
    }

    public function envoyerNotificationCommentaire($ticket, $contenu) {
        $msg = "<p>Nouveau message disponible :</p>
                <div style='background: #F0F9FF; border-left: 4px solid #2563EB; padding: 15px; color: #1E40AF;'>
                    \"" . nl2br(htmlspecialchars($contenu)) . "\"
                </div>";
        return $this->execute($ticket['id'], $ticket['email_client'], "Nouveau message", 
            $this->getPremiumLayout("Nouvel Échange", $msg, $ticket['numero_ticket']));
    }

    public function send($to, $sub, $body, $tid = 0) {
        return $this->execute($tid, $to, $sub, $this->getPremiumLayout($sub, $body, "GÉNÉRIQUE"));
    }
}
?>