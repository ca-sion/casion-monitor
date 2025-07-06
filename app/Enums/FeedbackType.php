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
}
