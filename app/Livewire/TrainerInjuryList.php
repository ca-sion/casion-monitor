<?php

namespace App\Livewire;

use App\Models\Injury;
use App\Models\Trainer;
use Livewire\Component;
use App\Enums\InjuryStatus;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class TrainerInjuryList extends Component
{
    use WithPagination;

    public Trainer $trainer;

    public function mount(): void
    {
        $this->trainer = auth('trainer')->user();
    }

    public function updateStatus(int $injuryId, string $status): void
    {
        $injury = Injury::where('id', $injuryId)
            ->whereIn('athlete_id', $this->trainer->athletes()->pluck('athletes.id'))
            ->firstOrFail();

        $injury->update(['status' => InjuryStatus::from($status)]);

        $this->dispatch('notify', ['message' => 'Statut de la blessure mis à jour.', 'type' => 'success']);
    }

    #[Layout('components.layouts.trainer')]
    public function render()
    {
        $athleteIds = $this->trainer->athletes()->pluck('athletes.id');
        $injuries = Injury::whereIn('athlete_id', $athleteIds)->with('athlete')->latest('declaration_date')->paginate(10);

        return view('livewire.trainer.injury-list', [
            'injuries' => $injuries,
            'statuses' => InjuryStatus::cases(),
        ]);
    }
}
