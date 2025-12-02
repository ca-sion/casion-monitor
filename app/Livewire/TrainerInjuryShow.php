<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Trainer;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TrainerInjuryShow extends Component
{
    public Trainer $trainer;

    public Injury $injury;

    public function mount(Injury $injury): void
    {
        $this->trainer = auth('trainer')->user();
        $athleteIds = $this->trainer->athletes()->pluck('athletes.id')->toArray();

        if (! in_array($injury->athlete_id, $athleteIds)) {
            throw new NotFoundHttpException;
        }

        $this->injury = $injury->load('healthEvents');
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        return view('livewire.trainer.injury-show', [
            'injury' => $this->injury,
        ]);
    }
}
