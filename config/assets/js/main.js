/**
 * Script principal - Central de Réclamation
 * Fichier: assets/js/main.js
 */

// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Animation des éléments fade-in
    const fadeElements = document.querySelectorAll('.fade-in');
    fadeElements.forEach((el, index) => {
        setTimeout(() => {
            el.style.opacity = '1';
            el.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Animation des cartes d'options
    const optionCards = document.querySelectorAll('.option-card');
    optionCards.forEach((card, index) => {
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 200 + (index * 150));
    });

    // Auto-fermeture des alertes
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Validation du formulaire de réclamation
function validateReclamationForm() {
    const nom = document.getElementById('nom');
    const email = document.getElementById('email');
    const telephone = document.getElementById('telephone');
    const sujet = document.getElementById('sujet');
    const description = document.getElementById('description');
    
    let errors = [];

    if (nom && nom.value.trim().length < 3) {
        errors.push('Le nom doit contenir au moins 3 caractères.');
    }

    if (email && !isValidEmail(email.value)) {
        errors.push('Veuillez entrer une adresse email valide.');
    }

    if (telephone && telephone.value.trim().length < 8) {
        errors.push('Le numéro de téléphone doit contenir au moins 8 chiffres.');
    }

    if (sujet && sujet.value.trim().length < 5) {
        errors.push('Le sujet doit contenir au moins 5 caractères.');
    }

    if (description && description.value.trim().length < 20) {
        errors.push('La description doit contenir au moins 20 caractères.');
    }

    if (errors.length > 0) {
        alert(errors.join('\n'));
        return false;
    }

    return true;
}

// Validation de l'email
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// Validation du formulaire de connexion
function validateLoginForm() {
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    
    let errors = [];

    if (username && username.value.trim().length < 3) {
        errors.push('Le nom d\'utilisateur doit contenir au moins 3 caractères.');
    }

    if (password && password.value.length < 4) {
        errors.push('Le mot de passe doit contenir au moins 4 caractères.');
    }

    if (errors.length > 0) {
        alert(errors.join('\n'));
        return false;
    }

    return true;
}

// Recherche de ticket
function searchTicket() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toUpperCase();
    const table = document.querySelector('.table');
    
    if (!table) return;
    
    const rows = table.getElementsByTagName('tr');

    for (let i = 1; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        let found = false;

        for (let j = 0; j < cells.length; j++) {
            const cell = cells[j];
            if (cell) {
                const textValue = cell.textContent || cell.innerText;
                if (textValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }

        rows[i].style.display = found ? '' : 'none';
    }
}

// Copier le numéro de ticket
function copyTicketNumber(ticketNumber) {
    const textarea = document.createElement('textarea');
    textarea.value = ticketNumber;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    
    showNotification('Numéro de ticket copié : ' + ticketNumber, 'success');
}

// Afficher une notification
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        animation: slideIn 0.3s ease;
    `;
    notification.innerHTML = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Filtrer par statut
function filterByStatus(statusId) {
    const rows = document.querySelectorAll('.table tbody tr');
    
    rows.forEach(row => {
        if (statusId === 'all') {
            row.style.display = '';
        } else {
            const statusCell = row.querySelector('[data-status]');
            if (statusCell && statusCell.dataset.status === statusId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Filtrer par priorité
function filterByPriority(priorityId) {
    const rows = document.querySelectorAll('.table tbody tr');
    
    rows.forEach(row => {
        if (priorityId === 'all') {
            row.style.display = '';
        } else {
            const priorityCell = row.querySelector('[data-priority]');
            if (priorityCell && priorityCell.dataset.priority === priorityId) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// Gestion des étoiles de satisfaction
function setRating(rating) {
    const stars = document.querySelectorAll('.star-rating i');
    const input = document.getElementById('note_satisfaction');
    
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.remove('far');
            star.classList.add('fas');
            star.style.color = '#ffc107';
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
            star.style.color = '#dee2e6';
        }
    });
    
    if (input) {
        input.value = rating;
    }
}

// Confirmation de suppression
function confirmDelete(message = 'Êtes-vous sûr de vouloir supprimer cet élément ?') {
    return confirm(message);
}

// Confirmation de déconnexion
function confirmLogout() {
    return confirm('Êtes-vous sûr de vouloir vous déconnecter ?');
}

// Toggle sidebar pour mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

// Chargement
function showLoading() {
    const loader = document.createElement('div');
    loader.id = 'globalLoader';
    loader.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.5); display: flex; align-items: center; 
                    justify-content: center; z-index: 9999;">
            <div style="background: white; padding: 2rem; border-radius: 1rem; text-align: center;">
                <div class="spinner-border text-primary" role="status"></div>
                <p style="margin-top: 1rem;">Chargement en cours...</p>
            </div>
        </div>
    `;
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('globalLoader');
    if (loader) loader.remove();
}

// Impression
function printPage() {
    window.print();
}

// Formatage des dates
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('fr-FR', options);
}