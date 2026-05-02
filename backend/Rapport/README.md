# Rapport de Stage - Instructions de Compilation

Ce dossier contient le code source LaTeX du rapport de stage "Central de Réclamation".

## Prérequis
Vous devez disposer d'une distribution LaTeX installée sur votre machine :
- **Windows** : MiKTeX ou TeX Live
- **macOS** : MacTeX
- **Linux** : TeX Live

## Structure
- `main.tex` : Fichier principal à compiler.
- `chapters/` : Contient le texte des différents chapitres.
- `images/` : (À créer) Doit contenir vos captures d'écran.

## Compilation
1.  Ouvrez `main.tex` dans votre éditeur LaTeX préféré (Texmaker, TeXShop, VS Code).
2.  Exécutez la compilation **PDFLaTeX**.
    - *Note : Il peut être nécessaire de compiler deux fois pour que la Table des Matières (Sommaire) se génère correctement.*

## Personnalisation
-   **Captures d'écran** : Le rapport utilise actuellement des cadres vides (`\fbox`) comme placeholders. Pour insérer vos vraies images :
    1.  Placez vos images (png/jpg) dans un dossier.
    2.  Dans `chapters/06_realisation.tex`, remplacez :
        ```latex
        \fbox{ ... [INSÉRER CAPTURE D'ÉCRAN ...] ... }
        ```
        par :
        ```latex
        \includegraphics[width=0.9\textwidth]{images/nom_de_votre_image.png}
        ```
-   **Nom de l'étudiant** : N'oubliez pas de modifier `[VOTRE NOM]` dans la page de garde (`main.tex`).
