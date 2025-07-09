<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeedbackType: string implements HasLabel
{
    case PRE_SESSION_GOALS = 'pre_session_goals';
    case POST_SESSION_FEEDBACK = 'post_session_feedback';
    case POST_SESSION_SENSATION = 'post_session_sensation';
    case PRE_COMPETITION_GOALS = 'pre_competition_goals';
    case POST_COMPETITION_FEEDBACK = 'post_competition_feedback';
    case POST_COMPETITION_SENSATION = 'post_competition_sensation';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PRE_SESSION_GOALS          => 'Objectifs de la séance',
            self::POST_SESSION_FEEDBACK      => 'Feedback de la séance',
            self::POST_SESSION_SENSATION     => 'Sensation après la séance',
            self::PRE_COMPETITION_GOALS      => 'Objectifs de la compétition',
            self::POST_COMPETITION_FEEDBACK  => 'Feedback de la compétition',
            self::POST_COMPETITION_SENSATION => 'Sensation après la compétition',
        };
    }

    public function getLabelShort(): ?string
    {
        return match ($this) {
            self::PRE_SESSION_GOALS          => 'Obj. séance',
            self::POST_SESSION_FEEDBACK      => 'Feedback',
            self::POST_SESSION_SENSATION     => 'Sensation',
            self::PRE_COMPETITION_GOALS      => 'Obj. compét.',
            self::POST_COMPETITION_FEEDBACK  => 'Feedback comp.',
            self::POST_COMPETITION_SENSATION => 'Sensation comp.',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::PRE_SESSION_GOALS          => 'Objectifs de la séance.',
            self::POST_SESSION_FEEDBACK      => 'Feedback de la séance.',
            self::POST_SESSION_SENSATION     => 'Sensations après la séance.',
            self::PRE_COMPETITION_GOALS      => 'Objectifs de la compétition.',
            self::POST_COMPETITION_FEEDBACK  => 'Feedback de la compétition.',
            self::POST_COMPETITION_SENSATION => 'Sensations après la compétitions.',
        };
    }

    /**
     * Retourne une indication de la métrique pour aider l'utilisateur à remplir le formulaire.
     */
    public function getHint(): ?string
    {
        return match ($this) {
            self::PRE_SESSION_GOALS          => "Avant ta séance, fixe-toi des objectifs clairs. Par exemple : 'Je veux réussir 5 sauts à 1m80', 'Je veux améliorer ma technique de départ', ou 'Je veux me concentrer sur ma respiration pendant l'effort'. Ça t'aide à rester focus et à mesurer tes progrès.",
            self::POST_SESSION_FEEDBACK      => "Après ta séance, fais le point : Qu'est-ce qui a bien marché ? Qu'est-ce qui était plus difficile ? Y a-t-il des choses que tu aurais pu faire différemment ? Par exemple : 'J'ai bien géré mon sprint, mais j'ai eu du mal sur les haies', ou 'J'ai bien appliqué les conseils du coach, mais je dois encore travailler ma réception'. Sois honnête avec toi-même, c'est comme ça qu'on progresse !",
            self::POST_SESSION_SENSATION     => "Comment te sens-tu physiquement et mentalement après ta séance ? Écris ce que tu ressens : 'J'ai les jambes lourdes mais je suis content(e) de ma performance', 'Je me sens fatigué(e) mais motivé(e) pour la prochaine fois', ou 'Je suis frustré(e) car je n'ai pas atteint mes objectifs'. C'est important de noter tes sensations pour comprendre comment ton corps réagit à l'entraînement.",
            self::PRE_COMPETITION_GOALS      => "Avant la compétition, définis tes objectifs. Ça peut être un objectif de performance ('Je veux faire un temps de X secondes', 'Je veux sauter Y mètres'), ou un objectif de processus ('Je veux rester concentré(e) sur ma technique', 'Je veux gérer mon stress'). Avoir des objectifs clairs t'aide à te préparer mentalement.",
            self::POST_COMPETITION_FEEDBACK  => "Après la compétition, analyse ta performance : Qu'est-ce qui a été positif ? Qu'est-ce qui a été négatif ? Qu'est-ce que tu as appris ? Par exemple : 'J'ai bien géré ma course, mais j'ai manqué de puissance sur le dernier saut', ou 'J'ai été surpris(e) par le niveau des adversaires, mais j'ai donné le meilleur de moi-même'. Ce feedback est crucial pour les prochaines compétitions.",
            self::POST_COMPETITION_SENSATION => "Comment te sens-tu après la compétition ? Écris tes sensations physiques et émotionnelles : 'Je suis épuisé(e) mais fier(ère) de mon résultat', 'Je suis déçu(e) mais je sais ce que je dois travailler', ou 'Je me sens léger(ère) et prêt(e) pour la prochaine étape'. Tes sensations sont une mine d'informations pour ton entraîneur et pour toi-même.",
        };
    }
}
