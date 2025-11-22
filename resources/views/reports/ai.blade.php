<x-layouts.athlete :title="$athlete->name">
    <div class="container mx-auto space-y-6 py-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Demander à votre IA</h1>
            <p class="text-gray-500">Pour la période du {{ \Carbon\Carbon::parse($startDate)->locale('fr_CH')->isoFormat('LL') }} au {{ \Carbon\Carbon::parse($endDate)->locale('fr_CH')->isoFormat('LL') }}</p>
        </div>

        <div x-data="{
            currentStep: 1,
            copyToClipboardAndAdvance(elementId, nextStep) {
                const textarea = document.getElementById(elementId);
                if (!textarea) {
                    console.error('Textarea element not found for ID:', elementId);
                    alert('Erreur: Impossible de trouver le contenu à copier. Veuillez réessayer.');
                    return;
                }
                textarea.select();
                textarea.setSelectionRange(0, 99999); // For mobile devices
                document.execCommand('copy');
                alert(`Copié ! Maintenant, collez dans votre IA et attendez ${nextStep <= 4 ? 'son invitation pour le prochain jeu de données.' : 'le rapport complet.'}`);
                this.currentStep = nextStep;
            }
        }">
            <div class="space-y-4">
                <h2 class="text-2xl font-bold text-gray-700">Guide pour l'IA</h2>

                {{-- Step 1: Instructions and Definitions --}}
                <div x-show="currentStep === 1" class="p-4 border rounded-lg shadow-sm bg-white">
                    <h3 class="text-xl font-semibold mb-2">Étape 1 : Copiez les instructions initiales</h3>
                    <p class="text-gray-600 mb-4">
                        Copiez le texte ci-dessous. C'est le rôle de l'IA, les définitions des métriques et les
                        instructions d'analyse.
                    </p>
                    <textarea id="promptPart1" rows="20"
                        class="w-full p-3 border rounded-md bg-gray-50 font-mono text-sm resize-y">Rôle et Objectif :

"Vous êtes un analyste de données de performance sportive de haut niveau, spécialisé dans la gestion de la charge et de la récupération. Votre objectif est d'analyser les jeu de données CSV 1, CSV 2 et CSV 3 pour fournir un rapport d'expertise complet et actionable à l'entraîneur et à l'athlète professionnel."

## Contexte des données et définitions

* Période couverte : Du {{ \Carbon\Carbon::parse($startDate)->locale('fr_CH')->isoFormat('L') }} au {{ \Carbon\Carbon::parse($endDate)->locale('fr_CH')->isoFormat('L') }}.
* Athlète : {{ $athlete->name }}.

Définition détaillée des métriques :

| Catégorie | Nom CSV (Colonne) | Nom Complet | Description/Unité/Échelle | Tendance Optimale |
| :--- | :--- | :--- | :--- | :--- |
| Au Réveil | MORNING_BODY_WEIGHT_KG | Poids corporel le matin | Poids en kg. | Neutre |
| Au Réveil | MORNING_HRV | Variabilité de la FC | VFC en ms. | Bonne (Hausse = meilleure récup) |
| Au Réveil | MORNING_SLEEP_QUALITY | Qualité du sommeil | Score sur 1-10 (très mauvaise ➝ excellente). | Bonne |
| Au Réveil | MORNING_SLEEP_DURATION | Durée du sommeil | Durée totale en heures (h). | Bonne |
| Au Réveil | MORNING_GENERAL_FATIGUE | Fatigue générale | Score sur 1-10 (pas fatigué ➝ épuisé). | Mauvaise (Hausse = problème) |
| Au Réveil | MORNING_PAIN | Douleurs musculaires/articulaires | Score sur 1-10 (aucune ➝ très fortes). | Mauvaise |
| Au Réveil | MORNING_PAIN_LOCATION | Localisation des douleurs | Texte libre (si douleur > 3). | Neutre |
| Au Réveil | MORNING_MOOD_WELLBEING | Humeur/bien-être | Score sur 1-10 (très mauvaise ➝ excellente). | Bonne |
| Au Réveil | MORNING_FIRST_DAY_PERIOD | Premier jour des règles | Binaire (indicateur). | Neutre |
| Avant Session | PRE_SESSION_ENERGY_LEVEL | Niveau d'énergie | Score sur 1-10 (très bas ➝ très haut). | Bonne |
| Avant Session | PRE_SESSION_LEG_FEEL | Ressenti des jambes | Score sur 1-10 (très lourdes ➝ très légères). | Bonne |
| Après Session | POST_SESSION_SESSION_LOAD | Ressenti de la charge | Score sur 1-10 (basse ➝ maximale). | Mauvaise (Hausse doit être contrôlée) |
| Après Session | POST_SESSION_PERFORMANCE_FEEL | Évaluation de la performance | Score sur 1-10 (mauvais ➝ excellent). | Bonne |
| Après Session | POST_SESSION_SUBJECTIVE_FATIGUE | Évaluation de la fatigue | Score sur 1-10 (aucune ➝ extrême). | Mauvaise |
| Après Session | POST_SESSION_PAIN | Douleurs après séance | Score sur 1-10 (aucune ➝ très fortes). | Mauvaise |

Définition détaillée des métriques calculées :

### 1. Score de Bien-être Matinal (SBM)
* Définition : Indicateur synthétique de la récupération et du bien-être général.
* Calcul (Journalier) : SBM = (SQ + (10 - GF) + (10 - P) + MW / (4 * 10)) * 10
    * Où : SQ (`MORNING_SLEEP_QUALITY`), GF (`MORNING_GENERAL_FATIGUE`), P (`MORNING_PAIN`), MW (`MORNING_MOOD_WELLBEING`).
    * Note : Les scores sont normalisés sur 10.
* Tendance Optimale : Haute (Indicateur de bonne récupération).

### 2. Charge Interne Hebdomadaire (CIH)
* Définition : Charge d'entraînement interne (subjective) totale pour la semaine.
* Calcul (Hebdomadaire) : Somme de la métrique `POST_SESSION_SESSION_LOAD` (RPE) pour toutes les sessions de la semaine.
* Tendance Optimale : Doit être dans la plage planifiée (Contrôlée).

#### 3. Charge Interne Hebdomadaire Normalisée (CIH_NORMALIZED)
* Définition : Charge moyenne des sessions (utile pour comparer l'intensité moyenne des sessions d'une semaine à l'autre).
* Calcul (Hebdomadaire) : CIH_NORMALIZED = SOMME(POST_SESSION_SESSION_LOAD) / Nombre de jours avec session
* Tendance Optimale : Contrôlée, doit correspondre à l'intensité planifiée.

#### 4. Charge Planifiée Hebdomadaire (CPH)
* Définition : Charge planifiée des sessions par semaine (volume et intensité).

### 5. Ratio CIH / CPH normalisé (Charge Interne vs. Charge Planifiée)
* Hypothèse : Assumer que le CSV inclut la colonne `CPH_PLANIFIEE` (Charge Planifiée Hebdomadaire) pour chaque semaine.
* Calcul (Hebdomadaire) : Ratio = CIH / CPH_PLANIFIEE
* Analyse :
    * Ratio > 1.1 (ou seuil défini) : L'athlète subit une charge significativement plus élevée que prévu (risque de surcharge/fatigue).
    * Ratio < 0.9 (ou seuil défini) : L'athlète subit une charge significativement plus faible que prévu (risque de sous-entraînement).

### 6. Ratio ACWR (Acute:Chronic Workload Ratio)
* Définition : Évaluation du risque de blessure lié à une augmentation trop rapide de la charge.
*Calcul : CIH des 7 derniers jours / Moyenne CIH des 28 derniers jours (Charge Chronique).
*Seuils d'Analyse :
    * Optimal : 0.8 < ACWR < 1.3
    * Warning (Risque accru) : 1.3 < ACWR < 1.5
    * $High Risk (Danger) : ACWR > 1.5

## Instructions pour l'analyse :

L'analyse doit OBLIGATOIREMENT être menée en utilisant les métriques dérivées (SBM, CIH, ACWR).

1. Évaluation de la Charge et du Risque (ACWR) : Calculer et rapporter l'évolution de l'ACWR pour chaque semaine. Mettre en évidence les périodes de High Risk (ACWR $\ge 1.5$) et les corréler avec les pics de douleurs (MORNING_PAIN/POST_SESSION_PAIN).
2. Score de Bien-être Matinal (SBM) : Identifier les jours où le SBM est en dessous de 5 et analyser si la cause est un Damping psychologique (VFC basse ET MORNING_MOOD_WELLBEING > 8) ou autre cause. Si Damping est détecté, considérer le jour comme High Risk (Charge interne non reflétée par le moral). Noter les périodes et les dates.
3. Adhésion et Efficacité (CIH/CPH) : Identifier les semaines de Sur-adhésion (> 1.1) et les corréler avec les baisses de Performance ressentie (POST_SESSION_PERFORMANCE_FEEL) et les indicateurs de fatigue (MORNING_GENERAL_FATIGUE). Noter les périodes et les dates.
4. Corrélation Clé J-1 vs J : Calculer la corrélation entre la Charge de Session (POST_SESSION_SESSION_LOAD) et le SBM du lendemain. Identifier si l'athlète est sensible à la charge élevée la veille. Noter les périodes et les dates.
5. Analyse des Hotspots de Douleur : Identifier la zone corporelle la plus fréquemment signalée dans MORNING_PAIN_LOCATION et chercher une corrélation avec une charge sessionnelle élevée. Noter les zone, les périodes et les dates.
6. Analyse du Cycle Menstruel (Si pertinent) : Si des données sont présentes dans MORNING_FIRST_DAY_PERIOD, comparer les moyennes de la Fatigue et de la Performance pendant la phase folliculaire et lutéale. Noter les constats, les périodes et les dates.
7. Synthèse Qualitative Contextuelle :
    * Analyser les périodes identifiées comme problématiques (SBM bas, ACWR High Risk, Performance ressentie basse, etc.) et chercher des causes qualitatives dans le CSV de Feedback (CSV 2). Noter les constats, les périodes et les dates.
    * Identifier les thèmes récurrents dans les feedbacks qui peuvent expliquer les variations des métriques. (Exemples : stress personnel, problème technique, conflit, motivation élevée, fatigue liée au voyage).
    * Utiliser des extraits de feedback dans le rapport pour justifier les tendances ou les anomalies détectées par les chiffres.

## Format du Rapport (Structure Demandée) :

Partie 1 : Résumé & Statut global

* Le Statut global doit être clairement formulé (ex: "Phase de surcharge, Dette de récupération chronique").
* Résumé en termes simples et compréhensibles du rapport de minimum trois paragraphes.
* Les 3 principales conclusions (tendances et problèmes) qui doivent inclure la cause probable (chiffres + contexte).

Partie 2 : Analyse de la charge, des blessures, de la récupération et du bien-être

* Évaluation des semaines de Sur- ou Sous-entraînement.
* Risque de blessure (ACWR) : Semaines avec risque de blessure, et corrélation avec les douleurs.
* Dette de récupération : Quantification de la fatigue chronique.
* Jours de Damping
* Jours de récupération Insuffisante (SBM bas).
* Hotspots des douleur : Zone, fréquence et lien potentiel avec la charge.
* Lister les périodes et les jours concernés et les constats des points plus hauts.
* Crée un tableau hebdomadaire qui résume ces points. Utilise des emojis pour indiquer le niveau de risque.

Partie 4 : Problèmes spécifiques, Contexte qualitatif et Recommandations

* Synthèse Qualitative : Mettre en évidence les 2 ou 3 facteurs non liés à l'entraînement (tirés des feedbacks) qui ont le plus impacté positivement ou négativement les métriques de récupération/performance (ex: "Stress externe élevé mentionné par l'athlète A les 3 premières semaines").
* Tableau des 5 jours/périodes les plus critiques : Liste des 5 événements majeurs (surcharge, SBM effondré, Damping, etc.) avec la raison expliquée par les chiffres et le contexte qualitatif.
* A contrario, tableau des 5 jours/périodes les plus performants ou agréables.
* Recommandations Actionnables : 3 Recommandations spécifiques et chiffrées pour ajuster le programme, directement liées aux conclusions (ex: "Réduire la CIH Planifiée de 10% pour les deux prochaines semaines" ou "Changer l'entraînement du mercredi car la VFC est systématiquement basse").

Les données CSV 1, CSV 2 et CSV 3 vont être donnés dans les prompt suivants successivement par l'utilisateur. Attendre que l'utilisateur donne les données avant de faire le rapport.</textarea>
                    <flux:button variant="primary" class="mt-4"
                        x-on:click="copyToClipboardAndAdvance('promptPart1', 2)">
                        Copier les instructions initiales (Étape 1)
                    </flux:button>
                    <a href="https://gemini.google.com/" target="_blank" class="text-blue-500 hover:underline ml-4">Ouvrir Gemini</a>
                </div>

                {{-- Step 2: Metrics CSV 1 --}}
                <div x-show="currentStep === 2" x-cloak class="p-4 border rounded-lg shadow-sm bg-white">
                    <h3 class="text-xl font-semibold mb-2">Étape 2 : Copiez le jeu de données des métriques (CSV 1)
                    </h3>
                    <p class="text-gray-600 mb-4">
                        Collez les instructions initiales dans l'IA. Lorsque l'IA vous invite à fournir les données CSV 1,
                        copiez le texte ci-dessous.
                    </p>
                    <textarea id="promptPart2" rows="10"
                        class="w-full p-3 border rounded-md bg-gray-50 font-mono text-sm resize-y">Le jeu de données des métriques (CSV 1) :
{{ $csvMetrics }}

Attendre que l'utilisateur donne les données CSV 2 et CSV 3 avant de faire le rapport.</textarea>
                    <flux:button variant="primary" class="mt-4"
                        x-on:click="copyToClipboardAndAdvance('promptPart2', 3)">
                        Copier les métriques (CSV 1) (Étape 2)
                    </flux:button>
                </div>

                {{-- Step 3: Calculated Metrics CSV 2 --}}
                <div x-show="currentStep === 3" x-cloak class="p-4 border rounded-lg shadow-sm bg-white">
                    <h3 class="text-xl font-semibold mb-2">Étape 3 : Copiez le jeu de données des métriques calculées
                        (CSV 2)</h3>
                    <p class="text-gray-600 mb-4">
                        Lorsque l'IA vous invite à fournir les données CSV 2, copiez le texte ci-dessous.
                    </p>
                    <textarea id="promptPart3" rows="10"
                        class="w-full p-3 border rounded-md bg-gray-50 font-mono text-sm resize-y">Le feu de données des métriques (CSV 2) :
{{ $csvCalculatedMetrics }}

Attendre que l'utilisateur donne les données CSV 3 avant de faire le rapport.</textarea>
                    <flux:button variant="primary" class="mt-4"
                        x-on:click="copyToClipboardAndAdvance('promptPart3', 4)">
                        Copier les métriques calculées (CSV 2) (Étape 3)
                    </flux:button>
                </div>

                {{-- Step 4: Feedbacks CSV 3 --}}
                <div x-show="currentStep === 4" x-cloak class="p-4 border rounded-lg shadow-sm bg-white">
                    <h3 class="text-xl font-semibold mb-2">Étape 4 : Copiez le jeu de données des feedbacks (CSV 3)
                    </h3>
                    <p class="text-gray-600 mb-4">
                        Lorsque l'IA vous invite à fournir les données CSV 3, copiez le texte ci-dessous. Une fois copié,
                        vous pouvez le coller dans l'IA et le rapport devrait être généré.
                    </p>
                    <textarea id="promptPart4" rows="10"
                        class="w-full p-3 border rounded-md bg-gray-50 font-mono text-sm resize-y">Le jeu de données des feedbacks (CSV 3) :
{{ $csvFeedbacks }}

Le rapport peut être effectuée selon les instructions.</textarea>
                    <flux:button color="green" class="mt-4"
                        x-on:click="copyToClipboardAndAdvance('promptPart4', 5)">
                        Copier les feedbacks (CSV 3) (Étape 4 - Terminé)
                    </flux:button>
                </div>

                {{-- Final Step --}}
                <div x-show="currentStep === 5" x-cloak class="p-4 border rounded-lg shadow-sm bg-green-50">
                    <h3 class="text-xl font-semibold mb-2 text-green-800">Processus terminé !</h3>
                    <p class="text-green-700">Vous avez copié toutes les parties nécessaires. Collez la dernière partie dans votre IA et attendez le rapport.</p>
                    <a href="https://gemini.google.com/" target="_blank" class="text-blue-500 hover:underline mt-4 inline-block">Ouvrir Gemini</a>
                </div>
            </div>
        </div>
</x-layouts.athlete>
