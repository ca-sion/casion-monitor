<?php

namespace App\Livewire;

use App\Models\Athlete;
use App\Models\Injury;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class AthleteInjuryList extends Component
{
    use WithPagination;

    public Athlete $athlete;

    public function mount(): void
    {
        $this->athlete = auth('athlete')->user();
    }

    #[Layout('components.layouts.athlete')]
    public function render()
    {
        $injuries = $this->athlete->injuries()->paginate(10);

        return view('livewire.athlete-injury-list', [
            'injuries' => $injuries,
        ]);
    }
}
