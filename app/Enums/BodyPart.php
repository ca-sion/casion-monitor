<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BodyPart: string implements HasLabel
{
    // Tête et Cou
    case Head = 'head';
    case Neck = 'neck';

    // Torse et Tronc
    case Chest = 'chest';
    case UpperBack = 'upper_back';
    case LowerBack = 'lower_back';
    case Abdomen = 'abdomen';
    case Pelvis = 'pelvis';

    // Membres Supérieurs (Droits et Gauches)
    case RightShoulderJoint = 'right_shoulder_joint';
    case LeftShoulderJoint = 'left_shoulder_joint';
    case RightBicep = 'right_bicep';
    case LeftBicep = 'left_bicep';
    case RightTricep = 'right_tricep';
    case LeftTricep = 'left_tricep';
    case RightElbowJoint = 'right_elbow_joint';
    case LeftElbowJoint = 'left_elbow_joint';
    case RightForearm = 'right_forearm';
    case LeftForearm = 'left_forearm';
    case RightWristJoint = 'right_wrist_joint';
    case LeftWristJoint = 'left_wrist_joint';
    case RightHand = 'right_hand';
    case LeftHand = 'left_hand';

    // Membres Inférieurs (Droits et Gauches)
    case RightHipJoint = 'right_hip_joint';
    case LeftHipJoint = 'left_hip_joint';
    case RightGluteus = 'right_gluteus';
    case LeftGluteus = 'left_gluteus';
    case RightQuadriceps = 'right_quadriceps';
    case LeftQuadriceps = 'left_quadriceps';
    case RightHamstring = 'right_hamstring';
    case LeftHamstring = 'left_hamstring';
    case RightKneeJoint = 'right_knee_joint';
    case LeftKneeJoint = 'left_knee_joint';
    case RightCalf = 'right_calf';
    case LeftCalf = 'left_calf';
    case RightShin = 'right_shin';
    case LeftShin = 'left_shin';
    case RightAnkleJoint = 'right_ankle_joint';
    case LeftAnkleJoint = 'left_ankle_joint';
    case RightFoot = 'right_foot';
    case LeftFoot = 'left_foot';
    case RightAchillesTendon = 'right_achilles_tendon';
    case LeftAchillesTendon = 'left_achilles_tendon';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Head                => 'Tête',
            self::Neck                => 'Cou',
            self::Chest               => 'Poitrine',
            self::UpperBack           => 'Haut du dos',
            self::LowerBack           => 'Bas du dos',
            self::Abdomen             => 'Abdomen',
            self::Pelvis              => 'Bassin',
            self::RightShoulderJoint  => 'Épaule droite',
            self::LeftShoulderJoint   => 'Épaule gauche',
            self::RightBicep          => 'Biceps droit',
            self::LeftBicep           => 'Biceps gauche',
            self::RightTricep         => 'Triceps droit',
            self::LeftTricep          => 'Triceps gauche',
            self::RightElbowJoint     => 'Coude droit',
            self::LeftElbowJoint      => 'Coude gauche',
            self::RightForearm        => 'Avant-bras droit',
            self::LeftForearm         => 'Avant-bras gauche',
            self::RightWristJoint     => 'Poignet droit',
            self::LeftWristJoint      => 'Poignet gauche',
            self::RightHand           => 'Main droite',
            self::LeftHand            => 'Main gauche',
            self::RightHipJoint       => 'Hanche droite (Articulation)',
            self::LeftHipJoint        => 'Hanche gauche (Articulation)',
            self::RightGluteus        => 'Fessier droit',
            self::LeftGluteus         => 'Fessier gauche',
            self::RightQuadriceps     => 'Quadriceps droit',
            self::LeftQuadriceps      => 'Quadriceps gauche',
            self::RightHamstring      => 'Ischio-jambiers droit',
            self::LeftHamstring       => 'Ischio-jambiers gauche',
            self::RightKneeJoint      => 'Genou droit',
            self::LeftKneeJoint       => 'Genou gauche',
            self::RightCalf           => 'Mollet droit',
            self::LeftCalf            => 'Mollet gauche',
            self::RightShin           => 'Tibia droit',
            self::LeftShin            => 'Tibia gauche',
            self::RightAnkleJoint     => 'Cheville droite',
            self::LeftAnkleJoint      => 'Cheville gauche',
            self::RightFoot           => 'Pied droit',
            self::LeftFoot            => 'Pied gauche',
            self::RightAchillesTendon => 'Tendon d\'Achille droit',
            self::LeftAchillesTendon  => 'Tendon d\'Achille gauche',
        };
    }
}
