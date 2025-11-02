<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Athlete;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AthleteInjuryShow extends Component
{
    public Athlete $athlete;

    public Injury $injury;

    public function mount(Injury $injury): void
    {
        $this->athlete = auth('athlete')->user();

        if ($injury->athlete_id !== $this->athlete->id) {
            throw new NotFoundHttpException;
        }

        $this->injury = $injury->load('medicalFeedbacks', 'recoveryProtocols');
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        return view('livewire.athlete-injury-show', [
            'injury' => $this->injury,
        ]);
    }
}
