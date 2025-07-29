<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MetricType: string implements HasLabel
{
    // Métriques "Au réveil"
    case MORNING_BODY_WEIGHT_KG = 'morning_body_weight_kg';
    case MORNING_HRV = 'morning_hrv';
    case MORNING_SLEEP_QUALITY = 'morning_sleep_quality';
    case MORNING_GENERAL_FATIGUE = 'morning_general_fatigue';
    case MORNING_PAIN = 'morning_pain';
    case MORNING_PAIN_LOCATION = 'morning_pain_location';
    case MORNING_MOOD_WELLBEING = 'morning_mood_wellbeing';
    case MORNING_FIRST_DAY_PERIOD = 'morning_first_day_period';

    // Métriques "Avant la session"
    case PRE_SESSION_ENERGY_LEVEL = 'pre_session_energy_level';
    case PRE_SESSION_LEG_FEEL = 'pre_session_leg_feel';

    // Métriques "Après la session"
    case POST_SESSION_SESSION_LOAD = 'post_session_session_load';
    case POST_SESSION_PERFORMANCE_FEEL = 'post_session_performance_feel';
    case POST_SESSION_SUBJECTIVE_FATIGUE = 'post_session_subjective_fatigue';
    case POST_SESSION_PAIN = 'post_session_pain';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'Poids corporel le matin',
            self::MORNING_HRV              => 'Variabilité de la fréquence cardiaque',
            self::MORNING_SLEEP_QUALITY    => 'Qualité du sommeil',
            self::MORNING_GENERAL_FATIGUE  => 'Fatigue générale',
            self::MORNING_PAIN             => 'Douleurs musculaires/articulaires',
            self::MORNING_PAIN_LOCATION    => 'Localisation des douleurs',
            self::MORNING_MOOD_WELLBEING   => 'Humeur/bien-être',
            self::MORNING_FIRST_DAY_PERIOD => 'Premier jour des règles',

            self::PRE_SESSION_ENERGY_LEVEL => "Niveau d'énergie",
            self::PRE_SESSION_LEG_FEEL     => 'Ressenti des jambes',

            self::POST_SESSION_SESSION_LOAD       => 'Ressenti de la charge',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'Évaluation de la performance',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Evaluation de la fatigue',
            self::POST_SESSION_PAIN               => 'Douleurs après séance',
        };
    }

    public function getLabelShort(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'Poids',
            self::MORNING_HRV              => 'VFC',
            self::MORNING_SLEEP_QUALITY    => 'Sommeil',
            self::MORNING_GENERAL_FATIGUE  => 'Fatigue mat.',
            self::MORNING_PAIN             => 'Douleurs',
            self::MORNING_PAIN_LOCATION    => 'Loc. douleurs',
            self::MORNING_MOOD_WELLBEING   => 'Humeur',
            self::MORNING_FIRST_DAY_PERIOD => 'Règles J1',

            self::PRE_SESSION_ENERGY_LEVEL => 'Énergie',
            self::PRE_SESSION_LEG_FEEL     => 'Jambes',

            self::POST_SESSION_SESSION_LOAD       => 'Ress. charge',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'Éval. perf.',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Eval. fatigue post',
            self::POST_SESSION_PAIN               => 'Douleurs post',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'Poids corporel au matin.',
            self::MORNING_HRV              => 'Valeur de la Variabilité de la fréquence cardiaque (VFC/HRV) au matin.',
            self::MORNING_SLEEP_QUALITY    => 'Évaluation subjective de la qualité du sommeil au matin.',
            self::MORNING_GENERAL_FATIGUE  => 'Évaluation subjective de la fatigue générale au matin.',
            self::MORNING_PAIN             => 'Évaluation subjective des douleurs musculaires/articulaires au matin.',
            self::MORNING_PAIN_LOCATION    => "Localisation des douleurs si l'évaluation subjective des douleurs au matin est supérieure à 3.",
            self::MORNING_MOOD_WELLBEING   => "Évaluation subjective de l'humeur/bien-être au matin.",
            self::MORNING_FIRST_DAY_PERIOD => "Indique si c'est le premier jour des règles.",

            self::PRE_SESSION_ENERGY_LEVEL => "Évaluation subjective du niveau d'énergie perçu avant la session.",
            self::PRE_SESSION_LEG_FEEL     => 'Évaluation subjective du ressenti des jambes avant la session.',

            self::POST_SESSION_SESSION_LOAD       => 'Évaluation subjective de la charge après la session.',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'Évaluation subjective du ressenti de la performance après la session.',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Évaluation subjective de la fatigue après la session.',
            self::POST_SESSION_PAIN               => 'Évaluation de l\'intensité des douleurs ressenties après la session.',
        };
    }

    public function getScale(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => null,
            self::MORNING_HRV              => null,
            self::MORNING_SLEEP_QUALITY    => 10,
            self::MORNING_GENERAL_FATIGUE  => 10,
            self::MORNING_PAIN             => 10,
            self::MORNING_PAIN_LOCATION    => 10,
            self::MORNING_MOOD_WELLBEING   => 10,
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL => 10,
            self::PRE_SESSION_LEG_FEEL     => 10,

            self::POST_SESSION_SESSION_LOAD       => 10,
            self::POST_SESSION_PERFORMANCE_FEEL   => 10,
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 10,
            self::POST_SESSION_PAIN               => 10,
        };
    }

    public function getValueColumn(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'value',
            self::MORNING_HRV              => 'value',
            self::MORNING_SLEEP_QUALITY    => 'value',
            self::MORNING_GENERAL_FATIGUE  => 'value',
            self::MORNING_PAIN             => 'value',
            self::MORNING_PAIN_LOCATION    => 'note',
            self::MORNING_MOOD_WELLBEING   => 'value',
            self::MORNING_FIRST_DAY_PERIOD => 'value',

            self::PRE_SESSION_ENERGY_LEVEL => 'value',
            self::PRE_SESSION_LEG_FEEL     => 'value',

            self::POST_SESSION_SESSION_LOAD       => 'value',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'value',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'value',
            self::POST_SESSION_PAIN               => 'value',
        };
    }

    public function getUnit(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'kg',
            self::MORNING_HRV              => 'ms',
            self::MORNING_SLEEP_QUALITY    => null,
            self::MORNING_GENERAL_FATIGUE  => null,
            self::MORNING_PAIN             => null,
            self::MORNING_PAIN_LOCATION    => null,
            self::MORNING_MOOD_WELLBEING   => null,
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL => null,
            self::PRE_SESSION_LEG_FEEL     => null,

            self::POST_SESSION_SESSION_LOAD       => null,
            self::POST_SESSION_PERFORMANCE_FEEL   => null,
            self::POST_SESSION_SUBJECTIVE_FATIGUE => null,
            self::POST_SESSION_PAIN               => null,
        };
    }

    /**
     * Retourne le nombre de décimales pour l'affichage de la métrique.
     */
    public function getPrecision(): int
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG => 2, // Poids en kg, souvent avec décimales
            default                      => 0, // La plupart des autres métriques sont des entiers ou des scores
        };
    }

    /**
     * Retourne une indication de l'échelle pour aider l'utilisateur à remplir le formulaire.
     */
    public function getScaleHint(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => null,
            self::MORNING_HRV              => null,
            self::MORNING_SLEEP_QUALITY    => 'très mauvaise ➝ excellente',
            self::MORNING_GENERAL_FATIGUE  => 'pas fatigué ➝ épuisé',
            self::MORNING_PAIN             => 'aucune ➝ très fortes',
            self::MORNING_PAIN_LOCATION    => null,
            self::MORNING_MOOD_WELLBEING   => 'très mauvaise ➝ excellente',
            self::MORNING_FIRST_DAY_PERIOD => null,

            self::PRE_SESSION_ENERGY_LEVEL => 'très bas ➝ très haut',
            self::PRE_SESSION_LEG_FEEL     => 'très lourdes ➝ très légères',

            self::POST_SESSION_SESSION_LOAD       => 'basse ➝ très haute',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'mauvais ➝ excellent',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'aucune ➝ extrême',
            self::POST_SESSION_PAIN               => 'aucune ➝ très fortes',
        };
    }

    /**
     * Retourne une indication de la métrique pour aider l'utilisateur à remplir le formulaire.
     */
    public function getHint(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG => "C'est ton poids juste après t'être levé(e), avant de manger ou de boire. Pèse-toi toujours à jeun, après être allé(e) aux toilettes, et si possible, à la même heure chaque fois. Ça nous aide à voir comment ton corps réagit à l'entraînement et au repos.",
            self::MORNING_HRV            => "La VFC (Variabilité de la Fréquence Cardiaque) mesure les petites variations entre tes battements de cœur. C'est un super indicateur de la façon dont ton corps récupère et gère le stress (entraînement, vie quotidienne). Une VFC élevée est souvent un signe de bonne récupération, tandis qu'une VFC basse peut indiquer de la fatigue ou un stress important. Utilise l'application ou l'appareil que tu utilises habituellement pour la mesurer.",
            self::MORNING_SLEEP_QUALITY  => 'Comment as-tu dormi cette nuit ? Évalue la qualité de ton sommeil sur une échelle de 1 à 10.
                1 = Nuit horrible, tu as très mal dormi, tu te sens épuisé(e).
                5 = Nuit moyenne, tu as dormi mais tu ne te sens pas super frais(fraîche).
                10 = Nuit parfaite, tu as dormi comme un bébé et tu te sens en pleine forme !',
            self::MORNING_GENERAL_FATIGUE => "Comment te sens-tu globalement ce matin ? Évalue ton niveau de fatigue sur une échelle de 1 à 10.
                1 = Pas fatigué(e) du tout, tu as plein d'énergie.
                5 = Fatigue normale, tu sens que tu as besoin de te réveiller.
                10 = Épuisé(e), tu as l'impression de ne pas avoir dormi et tu as du mal à démarrer la journée.",
            self::MORNING_PAIN => "As-tu des douleurs musculaires ou articulaires ce matin ? Évalue l'intensité de tes douleurs sur une échelle de 1 à 10.
                1 = Aucune douleur.
                5 = Douleur légère mais présente, tu la sens un peu.
                10 = Douleur très forte, ça t'empêche de bouger normalement ou de te sentir bien.",
            self::MORNING_PAIN_LOCATION  => "Si tu as indiqué une douleur supérieure à 3 (légère à forte), précise où tu as mal. Par exemple : 'genou droit', 'épaule gauche', 'bas du dos', 'ischio-jambiers'. Sois le plus précis possible pour qu'on puisse comprendre et t'aider.",
            self::MORNING_MOOD_WELLBEING => 'Comment te sens-tu émotionnellement ce matin ? Évalue ton humeur et ton bien-être général sur une échelle de 1 à 10.
                1 = Très mauvaise humeur, tu te sens mal.
                5 = Humeur neutre, ça va.
                10 = Excellente humeur, tu te sens super bien et motivé(e) !',
            self::MORNING_FIRST_DAY_PERIOD => "Indique si aujourd'hui est le premier jour de tes règles. C'est une information importante pour adapter ton entraînement et comprendre tes sensations.",

            self::PRE_SESSION_ENERGY_LEVEL => "Avant de commencer ta session, comment évalues-tu ton niveau d'énergie ? Sur une échelle de 1 à 10.
                1 = Très bas, tu te sens mou(molle) et sans force.
                5 = Moyen, tu te sens normal mais pas au top.
                10 = Très haut, tu te sens prêt(e) à tout déchirer, plein(e) d'énergie !",
            self::PRE_SESSION_LEG_FEEL => "Comment sens-tu tes jambes juste avant l'entraînement ? Sur une échelle de 1 à 10.
                1 = Très lourdes, tu as l'impression d'avoir des parpaings à la place des jambes.
                5 = Normales, tu ne sens rien de particulier.
                10 = Très légères, tes jambes sont fraîches et prêtes à performer !",

            self::POST_SESSION_SESSION_LOAD => "Après ta session, évalue la charge totale de ton entraînement. C'est une combinaison de l'intensité (à quel point c'était dur) et du volume (combien tu as fait). Sur une échelle de 1 à 10 :
                1 = Très facile, échauffement léger, pas d'effort.
                5 = Modéré, c'était un entraînement chargé, mais OK.
                10 = Maximal, c'était la mort.",
            self::POST_SESSION_PERFORMANCE_FEEL => "Comment as-tu ressenti ta performance pendant la session ? Sur une échelle de 1 à 10.
                1 = Mauvaise, tu n'étais pas dans le coup, rien n'allait.
                5 = Moyenne, tu as fait ce que tu savais faire.
                10 = Excellente, tu as dépassé tes attentes, tu étais au top !",
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'Après ta session, quel est ton niveau de fatigue général ? Sur une échelle de 1 à 10.
                1 = Aucune fatigue, tu pourrais refaire une session.
                5 = Fatigue modérée, tu sens que tu as travaillé.
                10 = Fatigue extrême, tu es vidé(e), tu as besoin de repos immédiat.',
            self::POST_SESSION_PAIN => "As-tu des douleurs musculaires ou articulaires après cette séance ? Évalue l'intensité de tes douleurs sur une échelle de 1 à 10. 1 = Aucune douleur. 5 = Douleur légère mais présente, tu la sens un peu. 10 = Douleur très forte, ça t'empêche de bouger normalement ou de te sentir bien.",
        };
    }

    /**
     * Retourne la direction optimale de la tendance pour cette métrique.
     * 'good': une augmentation est généralement positive (ex: VFC, qualité du sommeil).
     * 'bad': une augmentation est généralement négative (ex: fatigue, douleur).
     * 'neutral': la direction n'a pas de signification intrinsèque positive/négative (ex: poids).
     */
    public function getTrendOptimalDirection(): string
    {
        return match ($this) {
            self::MORNING_HRV,
            self::MORNING_SLEEP_QUALITY,
            self::MORNING_MOOD_WELLBEING,
            self::PRE_SESSION_ENERGY_LEVEL,
            self::PRE_SESSION_LEG_FEEL,
            self::POST_SESSION_PERFORMANCE_FEEL => 'good',

            self::MORNING_GENERAL_FATIGUE,
            self::MORNING_PAIN,
            self::POST_SESSION_SUBJECTIVE_FATIGUE,
            self::POST_SESSION_SESSION_LOAD,
            self::POST_SESSION_PAIN => 'bad',

            self::MORNING_BODY_WEIGHT_KG,
            self::MORNING_PAIN_LOCATION,
            self::MORNING_FIRST_DAY_PERIOD => 'neutral',
        };
    }

    /**
     * Retourne l'icône iconify tailwind.
     */
    public function getIconifyTailwind(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'icon-[material-symbols-light--weight-outline]',
            self::MORNING_HRV              => 'icon-[material-symbols-light--monitor-heart-outline]',
            self::MORNING_SLEEP_QUALITY    => 'icon-[material-symbols-light--bedtime-outline]',
            self::MORNING_GENERAL_FATIGUE  => 'icon-[material-symbols-light--wb-twilight-outline]',
            self::MORNING_PAIN             => 'icon-[material-symbols-light--sick-outline]',
            self::MORNING_PAIN_LOCATION    => 'icon-[material-symbols-light--my-location-outline]',
            self::MORNING_MOOD_WELLBEING   => 'icon-[material-symbols-light--stress-management-outline]',
            self::MORNING_FIRST_DAY_PERIOD => 'icon-[material-symbols-light--menstrual-health-outline]',

            self::PRE_SESSION_ENERGY_LEVEL => 'icon-[material-symbols-light--battery-android-bolt-outline]',
            self::PRE_SESSION_LEG_FEEL     => 'icon-[material-symbols-light--tibia-alt-outline]',

            self::POST_SESSION_SESSION_LOAD       => 'icon-[material-symbols-light--clock-loader-80]',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'icon-[material-symbols-light--sprint]',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'icon-[material-symbols-light--contrast-square]',
            self::POST_SESSION_PAIN               => 'icon-[material-symbols-light--sick]',
        };
    }

    /**
     * Retourne la couleur.
     */
    public function getColor(): ?string
    {
        return match ($this) {
            self::MORNING_BODY_WEIGHT_KG   => 'zinc',
            self::MORNING_HRV              => 'zinc',
            self::MORNING_SLEEP_QUALITY    => 'cyan',
            self::MORNING_GENERAL_FATIGUE  => 'cyan',
            self::MORNING_PAIN             => 'cyan',
            self::MORNING_PAIN_LOCATION    => 'cyan',
            self::MORNING_MOOD_WELLBEING   => 'cyan',
            self::MORNING_FIRST_DAY_PERIOD => 'purple',

            self::PRE_SESSION_ENERGY_LEVEL => 'yellow',
            self::PRE_SESSION_LEG_FEEL     => 'yellow',

            self::POST_SESSION_SESSION_LOAD       => 'blue',
            self::POST_SESSION_PERFORMANCE_FEEL   => 'blue',
            self::POST_SESSION_SUBJECTIVE_FATIGUE => 'blue',
            self::POST_SESSION_PAIN               => 'blue',
        };
    }
}
