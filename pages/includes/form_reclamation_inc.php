<?php
/**
 * Composant: Formulaire de Réclamation Unifié (Include)
 * Fichier: pages/includes/form_reclamation_inc.php
 * 
 * Variables requises à définir avant l'inclusion:
 * - $sites (Array)
 * - $categories (Array)
 * - $contexte (String: 'client', 'commercial', 'rmq')
 * - $clientInfo (Array|Null)
 */
?>

<div class="glass-card fade-in">
    <?php if (!isset($contexte) || strpos($contexte, 'edit') === false): ?>
    <?php 
        $headerStyle = (isset($contexte) && $contexte === 'admin_modal') 
            ? 'background: #ffffff; border-bottom: 2px solid #e5e7eb; color: #1a1a1a;' 
            : 'background: linear-gradient(135deg, #FF6B35 0%, #FFA500 100%); color: white;';
        $titleColor = (isset($contexte) && $contexte === 'admin_modal') ? '#1a1a1a' : 'white';
    ?>
    <div class="card-header" style="margin-bottom: 1.5rem; padding: 1.25rem; border-radius: 12px 12px 0 0; <?php echo $headerStyle; ?>">
        <h2 style="color: <?php echo $titleColor; ?>; margin: 0; font-size: 1.3rem; font-weight: 700;">
            <i class="fas fa-file-signature"></i> Nouvelle Réclamation
        </h2>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="reclamationForm">
        
        <!-- SECTION 1: Informations Client -->
        <div class="form-section mb-4" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb;">
            <h3 class="section-title" style="color: #1a1a1a; font-size: 1.1rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary-color); padding-left: 10px; font-weight: 700;">
                <i class="fas fa-user" style="color: var(--primary-color);"></i> Informations Client
            </h3>
            
            <input type="hidden" name="client_id" value="<?php echo $ticket['client_id'] ?? $clientInfo['id'] ?? 0; ?>">
            <div class="row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" class="form-control" required 
                           value="<?php echo htmlspecialchars($ticket['nom_client'] ?? $clientInfo['nom'] ?? $_POST['nom'] ?? ''); ?>"
                           <?php echo (isset($clientInfo) && $contexte === 'client') ? 'readonly' : ''; ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="prenom" class="form-control" required
                           value="<?php echo htmlspecialchars($ticket['prenom_client'] ?? $clientInfo['prenom'] ?? $_POST['prenom'] ?? ''); ?>"
                           <?php echo (isset($clientInfo) && $contexte === 'client') ? 'readonly' : ''; ?>>
                </div>
                <!-- Nouveau Champ: Nom de la société -->
                <div class="form-group">
                    <label class="form-label">Nom de la société *</label>
                    <input type="text" name="nom_societe" class="form-control" required
                           value="<?php echo htmlspecialchars($ticket['nom_societe'] ?? $ticket['client_nom_societe'] ?? $clientInfo['nom_societe'] ?? $_POST['nom_societe'] ?? ''); ?>"
                           <?php echo (isset($clientInfo) && !empty($clientInfo['nom_societe']) && $contexte === 'client') ? 'readonly' : ''; ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($ticket['email_client'] ?? $clientInfo['email'] ?? $_POST['email'] ?? ''); ?>"
                           <?php echo (isset($clientInfo) && $contexte === 'client') ? 'readonly' : ''; ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone *</label>
                    <input type="tel" name="telephone" class="form-control" required
                           value="<?php echo htmlspecialchars($ticket['telephone_client'] ?? $clientInfo['telephone'] ?? $_POST['telephone'] ?? ''); ?>"
                           <?php echo (isset($clientInfo) && $contexte === 'client') ? 'readonly' : ''; ?>>
                </div>
                <div class="form-group">
                    <label class="form-label">Code Client *</label>
                    <input type="text" name="code_client" class="form-control" 
                           placeholder="CL-000000 ou CI-000000" required
                           pattern="^(CL|CI)-\d{6}$"
                           title="Format obligatoire : CL-000000 ou CI-000000 (ex: CL-123456)"
                           value="<?php echo htmlspecialchars($ticket['code_client'] ?? $clientInfo['code_client'] ?? $_POST['code_client'] ?? ''); ?>"
                           <?php echo (isset($clientInfo) && isset($clientInfo['code_client']) && $contexte === 'client') ? 'readonly' : ''; ?>>
                </div>
            </div>
        </div>

        <div class="form-section mb-4" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb;">
            <h3 class="section-title" style="color: #1a1a1a; font-size: 1.1rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary-color); padding-left: 10px; font-weight: 700;">
                <i class="fas fa-map-marked-alt" style="color: var(--primary-color);"></i> Contexte
            </h3>

            <div class="row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Site SCPC *</label>
                    <?php $currentSite = $ticket['site_scpc'] ?? $_POST['site_scpc'] ?? ''; ?>
                    <select name="site_scpc" id="siteSCPC" class="form-control" required onchange="toggleSiteAutre()">
                        <option value="">Sélectionner un site...</option>
                        <option value="sfax" <?php echo ($currentSite == 'sfax' || $currentSite == 'Sfax-Sfax') ? 'selected' : ''; ?>>Sfax-Sfax</option>
                        <option value="tunis_mnihla" <?php echo ($currentSite == 'tunis_mnihla' || $currentSite == 'Tunis-Mnihla') ? 'selected' : ''; ?>>Tunis - Mnihla</option>
                        <option value="sousse" <?php echo ($currentSite == 'sousse' || $currentSite == 'Sousse-Sousse') ? 'selected' : ''; ?>>Sousse-Sousse</option>
                        <option value="tunis_mhamdia" <?php echo ($currentSite == 'tunis_mhamdia' || $currentSite == 'Tunis-Mhamdia') ? 'selected' : ''; ?>>Tunis - Mhamdia</option>
                        <option value="zarzis" <?php echo ($currentSite == 'zarzis' || $currentSite == 'Zarzis-Zarzis') ? 'selected' : ''; ?>>Zarzis-Zarzis</option>
                        <option value="site_client" <?php echo ($currentSite == 'site_client' || $currentSite == 'Site client') ? 'selected' : ''; ?>>Site client</option>
                        <option value="autre" <?php echo ($currentSite == 'autre' || (!empty($currentSite) && !in_array($currentSite, ['sfax','Sfax-Sfax','tunis_mnihla','Tunis-Mnihla','sousse','Sousse-Sousse','tunis_mhamdia','Tunis-Mhamdia','zarzis','Zarzis-Zarzis','site_client','Site client']))) ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>

                <div class="form-group" id="siteAutreDiv" style="display: none;">
                    <label class="form-label">Préciser le site *</label>
                    <input type="text" name="site_autre" class="form-control" id="siteAutreInput" placeholder="Entrez le nom du site"
                           value="<?php echo htmlspecialchars($ticket['site_scpc'] ?? $_POST['site_autre'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Type de Réclamation *</label>
                    <?php 
                        $rawTypes = $ticket['type_ticket'] ?? $_POST['categorie'] ?? [];
                        $currentCats = is_array($rawTypes) ? $rawTypes : explode(',', strtolower($rawTypes));
                        $currentCats = array_map('trim', $currentCats);
                    ?>
                    <div class="checkbox-group" style="display: flex; gap: 15px; flex-wrap: wrap; padding-top: 10px;">
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="categorie[]" value="produit" onchange="toggleFormSections()" style="width: 18px; height: 18px;" <?php echo (in_array('produit', $currentCats)) ? 'checked' : ''; ?>> 
                            <span>Produit</span>
                        </label>
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="categorie[]" value="service" onchange="toggleFormSections()" style="width: 18px; height: 18px;" <?php echo (in_array('service', $currentCats)) ? 'checked' : ''; ?>> 
                            <span>Service</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Type de Non-Conformité (NC) *</label>
                    <?php 
                        $rawNC = $ticket['type_nc'] ?? $_POST['type_nc'] ?? [];
                        $currentNC = is_array($rawNC) ? $rawNC : explode(',', $rawNC);
                        $currentNC = array_map('trim', $currentNC);
                        // Extraire "Autre" si présent sous forme "Autre: detail"
                        $hasAutreNC = false;
                        $autreNCDetail = '';
                        foreach($currentNC as $nc) {
                            if (stripos($nc, 'Autre') === 0) {
                                $hasAutreNC = true;
                                if (strpos($nc, ':') !== false) {
                                    $autreNCDetail = trim(substr($nc, strpos($nc, ':') + 1));
                                }
                            }
                        }
                    ?>
                    <div class="checkbox-group" style="display: flex; gap: 15px; flex-wrap: wrap; padding-top: 10px;">
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="type_nc[]" value="Qualité" style="width: 18px; height: 18px;" <?php echo (in_array('Qualité', $currentNC)) ? 'checked' : ''; ?>> 
                            <span>Qualité</span>
                        </label>
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="type_nc[]" value="Quantité" style="width: 18px; height: 18px;" <?php echo (in_array('Quantité', $currentNC)) ? 'checked' : ''; ?>> 
                            <span>Quantité</span>
                        </label>
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="type_nc[]" value="Autre" onchange="toggleTypeNCAutre()" id="typeNCAutreCheckbox" style="width: 18px; height: 18px;" 
                                   <?php echo ($hasAutreNC) ? 'checked' : ''; ?>> 
                            <span>Autre</span>
                        </label>
                    </div>
                </div>

                <div class="form-group" id="typeNCAutreDiv" style="display: none;">
                    <label class="form-label">Préciser l'autre NC *</label>
                    <input type="text" name="type_nc_autre" class="form-control" id="typeNCAutreInput" placeholder="Précisez le type de NC"
                           value="<?php echo htmlspecialchars($autreNCDetail ?: ($_POST['type_nc_autre'] ?? '')); ?>">
                </div>
            </div>
        </div>

        <!-- SECTION 3: Détails du Produit (Dynamique) -->
        <div id="sectionProduit" class="form-section mb-4" style="display: none; background: #fafafa; padding: 1.5rem; border-radius: 8px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 class="section-title" style="color: #4B5563; font-size: 1rem; margin-bottom: 0; font-weight: 600;">
                    <i class="fas fa-box-open"></i> Détails Produit(s)
                </h3>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addProductRow()">
                    <i class="fas fa-plus"></i> Ajouter un produit
                </button>
            </div>
            
            <div id="productsContainer">
                <?php 
                // Récupération des données produits (soit array post, soit simple string DB existante)
                // Pour l'édition, il faudrait exploser les chaînes si elles contiennent des virgules, 
                // mais pour la V1 création, on gère les tableaux du POST.
                $prodVals = $_POST['produit_concerne'] ?? $ticket['produit_concerne'] ?? [''];
                $qteVals = $_POST['qte_nc'] ?? $ticket['qte_nc'] ?? [''];
                $unitVals = $_POST['unite_nc'] ?? $ticket['unite_nc'] ?? [''];
                
                if (!is_array($prodVals)) $prodVals = [$prodVals];
                if (!is_array($qteVals)) $qteVals = [$qteVals];
                if (!is_array($unitVals)) $unitVals = [$unitVals];
                
                // S'assurer qu'on a au moins une ligne
                if (empty($prodVals)) $prodVals = [''];

                foreach($prodVals as $index => $prodVal): 
                    $qteVal = $qteVals[$index] ?? '';
                    $unitVal = $unitVals[$index] ?? '';
                ?>
                <div class="product-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem; align-items: end;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Produit concerné *</label>
                        <input type="text" name="produit_concerne[]" class="form-control" placeholder="Désignation" required
                               value="<?php echo htmlspecialchars($prodVal); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Quantité *</label>
                        <input type="text" name="qte_nc[]" class="form-control" placeholder="Qte" required
                               value="<?php echo htmlspecialchars($qteVal); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Unité *</label>
                        <input type="text" name="unite_nc[]" class="form-control" placeholder="Unité" required
                               value="<?php echo htmlspecialchars($unitVal); ?>">
                    </div>
                    <div style="padding-bottom: 5px;">
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProductRow(this)" title="Supprimer" style="<?php echo ($index === 0) ? 'display:none;' : ''; ?>">
                            <i class="fas fa-minus"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SECTION 4: Détails Service/Transport (Dynamique) -->
        <div id="sectionService" class="form-section mb-4" style="display: none; background: #fafafa; padding: 1.5rem; border-radius: 8px;">
            <h3 class="section-title" style="color: #4B5563; font-size: 1rem; margin-bottom: 1.5rem; font-weight: 600;">
                <i class="fas fa-truck"></i> Détails Service
            </h3>
            
            <div class="row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <!-- Nouveau Champ: Matricule (Obligatoire si Service) -->
                <div class="form-group" id="matriculeGroup" style="display: none;">
                    <label class="form-label">Matricule *</label>
                    <input type="text" name="matricule" id="matriculeInput" class="form-control" 
                           placeholder="Ex: 1234 TU 56"
                           value="<?php echo htmlspecialchars($ticket['matricule'] ?? $_POST['matricule'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Société de Transport</label>
                    <input type="text" name="societe_transport" class="form-control" 
                           placeholder="Ex: SCPC, Société X, Propre moyen..."
                           value="<?php echo htmlspecialchars($ticket['societe_transport'] ?? $_POST['societe_transport'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Nom et Prénom du Chauffeur</label>
                    <input type="text" name="chauffeur" class="form-control" placeholder="Nom et prénom du chauffeur"
                           value="<?php echo htmlspecialchars($ticket['chauffeur'] ?? $_POST['chauffeur'] ?? ''); ?>">
                </div>
            </div>
        </div>

        <div class="form-section mb-4" style="background: white; padding: 1.5rem; border-radius: 12px; border: 1px solid #e5e7eb;">
            <h3 class="section-title" style="color: #1a1a1a; font-size: 1.1rem; margin-bottom: 1.5rem; border-left: 4px solid var(--primary-color); padding-left: 10px; font-weight: 700;">
                <i class="fas fa-file-invoice" style="color: var(--primary-color);"></i> Documents & Analyse
            </h3>
            
            <div class="row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Numéro Facture / BL</label>
                    <input type="text" name="num_facture" class="form-control" placeholder="N° Facture ou Bon de Livraison"
                           value="<?php echo htmlspecialchars($ticket['num_facture'] ?? $_POST['num_facture'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Date Facture</label>
                    <input type="date" name="date_facture" id="dateFacture" class="form-control"
                           value="<?php echo htmlspecialchars($ticket['date_facture'] ?? $_POST['date_facture'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="row mt-3" style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
                 <div class="form-group">
                    <label class="form-label"><i class="fas fa-edit"></i> Description de la Réclamation * (Détails)</label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="Expliquez le problème en détail..."><?php echo htmlspecialchars($ticket['description'] ?? $_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-question-circle"></i> Demande du Client * (Attente explicite)</label>
                    <textarea name="demande_client" class="form-control" rows="2" required placeholder="Que souhaite le client ? (Avoir, Échange, Réparation, Remboursement...)"
                    ><?php echo htmlspecialchars($ticket['demande_client'] ?? $_POST['demande_client'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date de Livraison</label>
                    <input type="date" name="date_livraison" id="dateLivraison" class="form-control"
                           value="<?php echo htmlspecialchars($ticket['date_livraison'] ?? $_POST['date_livraison'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-microscope"></i> Analyse (Causes & Conséquences)</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <textarea name="analyse_causes" class="form-control" rows="3" placeholder="Causes probables..."><?php echo htmlspecialchars($ticket['analyse_causes'] ?? $_POST['analyse_causes'] ?? ''); ?></textarea>
                        <textarea name="analyse_consequences" class="form-control" rows="3" placeholder="Conséquences éventuelles..."><?php echo htmlspecialchars($ticket['analyse_consequences'] ?? $_POST['analyse_consequences'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-sticky-note"></i> Notes / Observations</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="Notes internes..."><?php echo htmlspecialchars($ticket['notes'] ?? $_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Pièces Jointes</label>
                    <div class="file-upload-wrapper" style="border: 2px dashed #cbd5e0; padding: 2rem; text-align: center; border-radius: 8px; cursor: pointer; transition: all 0.2s;" onclick="document.getElementById('fileInput').click()">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: #a0aec0; margin-bottom: 1rem;"></i>
                        <p style="margin: 0; color: #4a5568;">Cliquez pour ajouter des photos ou documents</p>
                        <p style="margin: 0; font-size: 0.8rem; color: #a0aec0;">JPG, PNG, PDF (Max 5Mo)</p>
                        <input type="file" id="fileInput" name="fichiers[]" multiple style="display: none" onchange="updateFileName(this)">
                    </div>
                    <div id="fileList" style="margin-top: 10px; font-size: 0.9rem; color: #4a5568;"></div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <?php if (!isset($contexte) || strpos($contexte, 'edit') === false): ?>
        <div class="form-actions" style="margin-top: 3rem; text-align: right; border-top: 2px solid #f0f0f0; padding-top: 1.5rem;">
            <button type="button" class="btn btn-secondary" onclick="window.history.back()" style="margin-right: 1rem;">Annuler</button>
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirmSubmission('<?php echo $contexte; ?>')">
                <i class="fas fa-paper-plane"></i> Soumettre la Réclamation
            </button>
        </div>
        <?php endif; ?>

        <!-- SECTION ACTIONS POUR ÉDITION (RMQ) -->
        <?php if (isset($contexte) && $contexte === 'rmq_edit'): ?>
            <input type="hidden" name="action" value="update">
            
            <div style="padding: 1.5rem; background: rgba(22, 101, 52, 0.05); border-radius: 8px; border: 1px solid rgba(22, 101, 52, 0.2); margin: 2rem 0;">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 12px; cursor: pointer; color: #166534; font-weight: 600;">
                    <input type="checkbox" name="notify_client" value="1" style="width: 20px; height: 20px; cursor: pointer;"> 
                    <span>Envoyer une notification email au client pour ces modifications ?</span>
                </label>
            </div>

            <div class="form-actions" style="margin-top: 1rem; text-align: right; border-top: 2px solid #f0f0f0; padding-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                 <!-- ID est requis pour le lien retour, on suppose qu'il est dans $ticket['id'] -->
                <a href="detail_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
            </div>
        <?php endif; ?>

    </form>
</div>

<script>
function toggleSiteAutre() {
    const site = document.getElementById('siteSCPC').value;
    const autreDiv = document.getElementById('siteAutreDiv');
    const autreInput = document.getElementById('siteAutreInput');
    
    if (site === 'autre') {
        autreDiv.style.display = 'block';
        autreInput.setAttribute('required', 'required');
    } else {
        autreDiv.style.display = 'none';
        autreInput.removeAttribute('required');
        autreInput.value = '';
    }
}

function toggleTypeNCAutre() {
    const checkbox = document.getElementById('typeNCAutreCheckbox');
    const autreDiv = document.getElementById('typeNCAutreDiv');
    const autreInput = document.getElementById('typeNCAutreInput');
    
    if (checkbox) {
        if (checkbox.checked) {
            autreDiv.style.display = 'block';
            autreInput.setAttribute('required', 'required');
        } else {
            autreDiv.style.display = 'none';
            autreInput.removeAttribute('required');
            autreInput.value = '';
        }
    }
}

function toggleSocieteTransportAutre() {
    const type = document.getElementById('societeTransport')?.value;
    const autreDiv = document.getElementById('societeTransportAutreDiv');
    const autreInput = document.getElementById('societeTransportAutreInput');
    
    if (type && type === 'autre') {
        autreDiv.style.display = 'block';
        autreInput.setAttribute('required', 'required');
    } else if (autreDiv) {
        autreDiv.style.display = 'none';
        autreInput.removeAttribute('required');
        autreInput.value = '';
    }
}

// GESTION PRODUITS MULTIPLES
function addProductRow() {
    const container = document.getElementById('productsContainer');
    const rowCount = container.querySelectorAll('.product-row').length;
    
    const newRow = document.createElement('div');
    newRow.className = 'product-row';
    newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 1rem; margin-bottom: 1rem; align-items: end;';
    
    newRow.innerHTML = `
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Produit concerné *</label>
            <input type="text" name="produit_concerne[]" class="form-control" placeholder="Désignation" required>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Quantité *</label>
            <input type="text" name="qte_nc[]" class="form-control" placeholder="Qte" required>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Unité *</label>
            <input type="text" name="unite_nc[]" class="form-control" placeholder="Unité" required>
        </div>
        <div style="padding-bottom: 5px;">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeProductRow(this)" title="Supprimer">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newRow);
    toggleRemoveButtons();
}

function removeProductRow(btn) {
    const row = btn.closest('.product-row');
    row.remove();
    toggleRemoveButtons();
}

function toggleRemoveButtons() {
    const rows = document.querySelectorAll('.product-row');
    rows.forEach((row, index) => {
        const btn = row.querySelector('.btn-outline-danger');
        if (btn) {
            // Le premier ne peut pas être supprimé s'il est le seul, 
            // MAIS on veut pouvoir en supprimer si on en a ajouté.
            // La logique demandée : "Ajouter un bouton ( – ) pour supprimer un produit ajouté (le premier bloc reste obligatoire)"
            // Donc si index === 0, on cache le bouton, sauf si on veut permettre de vider ? Non, "reste obligatoire".
            if (index === 0) {
                btn.style.display = 'none'; 
            } else {
                btn.style.display = 'block';
            }
        }
    });
}

function toggleFormSections() {
    const cbs = document.querySelectorAll('input[name="categorie[]"]');
    let hasProduit = false;
    let hasService = false;
    
    cbs.forEach(cb => {
        if (cb.checked && cb.value === 'produit') hasProduit = true;
        if (cb.checked && cb.value === 'service') hasService = true;
    });

    const secProd = document.getElementById('sectionProduit');
    const secServ = document.getElementById('sectionService');
    const matriculeGroup = document.getElementById('matriculeGroup');
    const matriculeInput = document.getElementById('matriculeInput');

    if (hasProduit) {
        secProd.style.display = 'block';
        // Activer Required sur les champs du premier produit
        const firstRowInputs = secProd.querySelectorAll('.product-row:first-child input');
        firstRowInputs.forEach(inp => inp.setAttribute('required', 'required'));
    } else {
        secProd.style.display = 'none';
        const allProdInputs = secProd.querySelectorAll('input');
        allProdInputs.forEach(inp => inp.removeAttribute('required'));
    }

    if (hasService) {
        secServ.style.display = 'block';
        // Afficher Matricule et rendre required
        if (matriculeGroup) matriculeGroup.style.display = 'block';
        if (matriculeInput) matriculeInput.setAttribute('required', 'required');
    } else {
        secServ.style.display = 'none';
        if (matriculeGroup) matriculeGroup.style.display = 'none';
        if (matriculeInput) matriculeInput.removeAttribute('required');
    }
}

function updateFileName(input) {
    const list = document.getElementById('fileList');
    list.innerHTML = '';
    if (input.files.length > 0) {
        for (let i = 0; i < input.files.length; i++) {
            list.innerHTML += '<div><i class="fas fa-check"></i> ' + input.files[i].name + '</div>';
        }
    }
}

function confirmSubmission(role) {
    if (role === 'rmq') {
        return confirm("Souhaitez-vous vraiment soumettre cette réclamation ?");
    }
    return true;
}

function validateDates() {
    const dateFacture = document.getElementById('dateFacture').value;
    const dateLivraison = document.getElementById('dateLivraison').value;
    
    if (dateFacture && dateLivraison) {
        if (new Date(dateFacture) > new Date(dateLivraison)) {
            alert('La date de facture ne peut pas être postérieure à la date de livraison.');
            return false;
        }
    }
    return true;
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les dates
    const dateFacture = document.getElementById('dateFacture');
    const dateLivraison = document.getElementById('dateLivraison');
    if (dateFacture) dateFacture.addEventListener('change', validateDates);
    if (dateLivraison) dateLivraison.addEventListener('change', validateDates);
    
    // Initialiser les boutons produits
    toggleRemoveButtons();

    // Lancer les toggles initiaux
    toggleFormSections();
    toggleSiteAutre();
    toggleSocieteTransportAutre();
    toggleTypeNCAutre();
});
</script>
