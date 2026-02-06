# Analyse Fonctionnelle et Technique de l'Application de Monitoring

Ce document détaille le fonctionnement de l'application du point de vue de l'athlète et de l'entraîneur, en explicitant les données collectées, les calculs précis effectués par le système, et la valeur ajoutée pour chaque utilisateur.

## 1. Parcours Athlète : La Collecte de Données

L'athlète interagit avec l'application à trois moments clés de sa journée. Ces interactions nourrissent les algorithmes de décision.

### A. Le Matin (Morning Check-in)
Au réveil, l'athlète remplit un formulaire rapide pour évaluer son état de récupération.

**Données collectées :**
*   **VFC Matinale (HRV)** : Entrée numérique (ms). *Optionnel selon préférences.*
*   **Sommeil** :
    *   Heure de coucher et Heure de lever -> **Durée calculée automatiquement**.
    *   **Qualité** (1-10).
*   **Fatigue Générale** (1-10) : 1 = Très frais, 10 = Épuisé.
*   **Humeur / Bien-être** (1-10).
*   **Douleur** (1-10) + **Localisation** (si douleur > 3).
*   **Cycle Menstruel** (Femmes uniquement) : Déclaration du premier jour des règles.

### B. Avant la Séance (Pre-Session)
Juste avant l'entraînement, l'athlète renseigne son état de disponibilité immédiate.

**Données collectées :**
*   **Niveau d'Énergie** (1-10).
*   **Sensation Jambes** (1-10).
*   **Objectifs de séance** (Texte libre) : Ce qu'il compte travailler.

### C. Après la Séance (Post-Session)
Une fois l'entraînement terminé, l'athlète évalue la charge interne.

**Données collectées :**
*   **RPE (Session Load)** (1-10) : Perception de l'effort.
*   **Fatigue Subjective** (1-10).
*   **Ressenti Performance** (1-10).
*   **Douleur Post-Séance** (1-10).
*   **Sensations / Commentaires** (Texte libre).

---

## 2. Le Cœur du Système : Calculs et Algorithmes

Voici les formules précises utilisées par l'application pour transformer les données brutes en indicateurs décisionnels.

### A. Score de Bien-être Matinal (SBM)
Un indicateur composite sur 10 qui agrège la qualité de récupération.

*   **Formule** :
    $$SBM = \frac{\text{Somme des points}}{\text{Points max possibles}} \times 10$$
*   **Composantes de la somme** :
    1.  **Qualité Sommeil** (Note 0-10).
    2.  **Fatigue** (Inversé : $10 - \text{Fatigue}$).
    3.  **Douleur** (Inversé : $10 - \text{Douleur}$).
    4.  **Humeur** (Note 0-10).
    5.  **Durée Sommeil (Normalisée)** :
        *   8h = 10 pts, 4h = 0 pts.
        *   Formula : $\max(0, \min(10, (\text{Durée} - 4) \times 2.5))$.
*   **Pénalités Sommeil** (déduites du total final) :
    *   < 5h : -4 pts
    *   < 6h : -2 pts
    *   < 7h : -1 pt
    *   < 8h : -0.5 pt

### B. Charge d'Entraînement (Load)

1.  **CIH (Charge Interne Hebdomadaire)** :
    *   Somme des RPE (Effort perçu) de toutes les séances de la semaine.
2.  **CIH Normalisée** :
    *   Moyenne quotidienne : $\frac{\text{Somme RPE}}{\text{Jours avec données}}$.
3.  **CPH (Charge Planifiée Hebdomadaire)** :
    *   Estimation de la charge prévue par le coach.
    *   Calcul précis : $\text{Volume} + 1 + \max(0, \sqrt{\text{Intensité} - 50} \times 0.25)$.
4.  **Ratio CIH/CPH** :
    *   Indique si l'athlète respecte le plan ( > 1 = Surcharge, < 1 = Sous-charge).

### C. Ratio Aiguë:Chronique (ACWR) - Prévention des blessures
Mesure si l'augmentation de la charge est trop brutale.

*   **Charge Aiguë (Fatigue)** : Charge cumulée des 7 derniers jours.
*   **Charge Chronique (Fitness)** : Moyenne glissante de la charge des 4 dernières semaines.
*   **ACWR** : $\frac{\text{Charge Aiguë}}{\text{Charge Chronique}}$.
*   *Interprétation* : Un ratio > 1.3 - 1.5 indique un risque élevé de blessure (pic de charge).

### D. Score de Readiness (Disponibilité)
Score sur 100 indiquant si l'athlète est prêt à s'entraîner aujourd'hui.
Base de départ : 100 points.

**Pénalités appliquées (Déductions) :**
1.  **SBM Mauvais** : Si SBM < 10, pénalité proportionnelle : $(10 - SBM) \times 5$.
2.  **Chute HRV** :
    *   Baisse > 10% vs moyenne 7j : **-20 pts**.
    *   Baisse > 5% vs moyenne 7j : **-10 pts**.
3.  **Douleur** : $Points \times 4$ (ex: Douleur 3/10 = -12 pts).
4.  **Énergie Pré-Session / Jambes** :
    *   Faible (Note <= 4) : **-15 pts**.
    *   Moyen (Note <= 6) : **-5 pts**.
5.  **Surcharge (Ratio Charge)** :
    *   Si Ratio CIH/CPH > 1.3 : Pénalité forte ($15 \times \text{Dépassement}$).

**Règles Spéciales (Alertes) :**
*   **Niveau ROUGE** : Si Douleur > 7/10.
*   **Niveau ORANGE** : Si 1er jour de règles **ET** Énergie basse.
*   **Non Calculable** : Si plus de 3 métriques clés sont manquantes.

---

## 3. Vue Entraîneur : Pilotage et Décision

L'entraîneur ne navigue pas à vue. Il dispose d'un tableau de bord consolidé.

### Ce qu'il voit
1.  **Dashboard Global** :
    *   Vue d'ensemble de tous ses athlètes.
    *   Colonnes critiques : Readiness du jour, Fatigue, Sommeil, Douleur, ACWR.
    *   **Système d'Alertes** : Des pastilles (Général, Charge, Menstruel) signalent immédiatement les cas nécessitant attention.
2.  **Vue Détaillée Athlète** :
    *   Graphiques d'évolution (Charge vs Forme).
    *   Historique des blessures.
    *   Rapports générés (Hebdomadaire, Mensuel).
3.  **Feedbacks** :
    *   Lecture des retours athlètes post-séance.
    *   Possibilité de répondre ou de fixer des objectifs pré-séance/compétition.

### Intérêt pour l'Entraîneur
*   **Gain de temps** : Plus besoin d'appeler chaque athlète pour savoir "comment ça va". Le tri est fait par les alertes.
*   **Objectivation** : La décision d'alléger une séance n'est plus basée sur une intuition mais sur des données tangibles (VFC en baisse, Sommeil dégradé, ACWR critique).
*   **Prévention** : L'alerte "Surcharge" (ACWR > 1.3) permet d'intervenir *avant* la blessure.
*   **Suivi Individualisé** : Adaptation aux cycles menstruels et aux contraintes personnelles.

---

## 4. Intérêt pour l'Athlète
*   **Écoute de soi** : Remplir le questionnaire oblige à une introspection quotidienne (Scan corporel).
*   **Sécurité** : Le système "lève la main" pour lui quand il est temps de ralentir, validant ses sensations de fatigue.
*   **Communication** : Canal direct pour exprimer des douleurs ou des doutes sans confrontation directe.
*   **Optimisation** : S'entraîner au bon moment, à la bonne intensité (e.g., adaptation phases cycle menstruel).
