<?php
/**
 * Pied de page commun pour toutes les pages
 * Fichier: includes/footer.php
 */
?>
        </div> <!-- Fin container -->
    </main> <!-- Fin main-content -->

    <!-- Pied de page -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <!-- Section À propos -->
                <div class="footer-section">
                    <h4><i class="fas fa-info-circle"></i> À propos</h4>
                    <p>
                        <?php echo SITE_NAME; ?> est votre plateforme de gestion 
                        et de suivi des réclamations en ligne. Nous nous engageons 
                        à traiter vos demandes rapidement et efficacement.
                    </p>
                </div>

                <!-- Section Liens rapides -->
                <div class="footer-section">
                    <h4><i class="fas fa-link"></i> Liens Rapides</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/index.php"><i class="fas fa-home"></i> Accueil</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/client/nouvelle_reclamation.php"><i class="fas fa-plus"></i> Nouvelle Réclamation</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/client/suivi_reclamation.php"><i class="fas fa-search"></i> Suivre ma Réclamation</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/pages/agent/login.php"><i class="fas fa-user-tie"></i> Espace Agent</a></li>
                    </ul>
                </div>

                <!-- Section Contact -->
                <div class="footer-section">
                    <h4><i class="fas fa-envelope"></i> Contact</h4>
                    <ul>
                        <li><i class="fas fa-phone"></i> +216 XX XXX XXX</li>
                        <li><i class="fas fa-envelope"></i> contact@reclamation.tn</li>
                        <li><i class="fas fa-map-marker-alt"></i> Tunis, Tunisie</li>
                        <li><i class="fas fa-clock"></i> Lun - Ven: 8h - 17h</li>
                    </ul>
                </div>

                <!-- Section Informations -->
                <div class="footer-section">
                    <h4><i class="fas fa-shield-alt"></i> Informations</h4>
                    <p>
                        <i class="fas fa-check-circle" style="color: var(--status-resolu);"></i> Données sécurisées<br>
                        <i class="fas fa-check-circle" style="color: var(--status-resolu);"></i> Traitement rapide<br>
                        <i class="fas fa-check-circle" style="color: var(--status-resolu);"></i> Support 24/7<br>
                        <i class="fas fa-check-circle" style="color: var(--status-resolu);"></i> Satisfaction garantie
                    </p>
                </div>
            </div>

            <!-- Bas du footer -->
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Tous droits réservés.</p>
                
                <div class="footer-links">
                    <a href="#" onclick="alert('Page en construction'); return false;">Politique de confidentialité</a>
                    <a href="#" onclick="alert('Page en construction'); return false;">Conditions d'utilisation</a>
                    <a href="#" onclick="alert('Page en construction'); return false;">Mentions légales</a>
                </div>

                <div class="social-links">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bouton retour en haut -->
    <button class="back-to-top" id="backToTop" onclick="scrollToTop()" aria-label="Retour en haut">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Scripts JavaScript -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
    <!-- Navbar & Mobile Menu Scripts -->
    <script>
        // ========== NAVBAR MOBILE MENU TOGGLE ==========
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobileMenu');
            const toggler = document.getElementById('navbarToggler');
            
            if (mobileMenu && toggler) {
                const isOpen = mobileMenu.classList.toggle('show');
                toggler.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }
        }

        // Fermer le menu mobile en cliquant sur un lien
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const toggler = document.getElementById('navbarToggler');
            
            // Fermer si on clique sur un lien du menu mobile
            if (event.target.closest('.mobile-menu-link')) {
                if (mobileMenu) mobileMenu.classList.remove('show');
                if (toggler) toggler.setAttribute('aria-expanded', 'false');
            }
            
            // Fermer si on clique en dehors
            if (mobileMenu && !mobileMenu.contains(event.target) && toggler && !toggler.contains(event.target)) {
                if (mobileMenu.classList.contains('show')) {
                    mobileMenu.classList.remove('show');
                    toggler.setAttribute('aria-expanded', 'false');
                }
            }
        });

        // Fermer avec la touche ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const mobileMenu = document.getElementById('mobileMenu');
                const toggler = document.getElementById('navbarToggler');
                if (mobileMenu) mobileMenu.classList.remove('show');
                if (toggler) toggler.setAttribute('aria-expanded', 'false');
            }
        });

        // Ajouter event listener au bouton toggler
        document.addEventListener('DOMContentLoaded', function() {
            const toggler = document.getElementById('navbarToggler');
            if (toggler) {
                toggler.addEventListener('click', toggleMobileMenu);
            }
        });
    </script>
    
    <script>
        // ========== BACK TO TOP BUTTON ==========
        // Afficher/cacher le bouton "retour en haut"
        window.addEventListener('scroll', function() {
            const backToTop = document.getElementById('backToTop');
            if (backToTop) {
                if (window.pageYOffset > 300) {
                    backToTop.style.display = 'flex';
                } else {
                    backToTop.style.display = 'none';
                }
            }
        });

        // Fonction pour remonter en haut
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Animation des éléments au scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });
    </script>

    <?php if (isset($extra_scripts)): ?>
        <?php echo $extra_scripts; ?>
    <?php endif; ?>
</body>
</html>