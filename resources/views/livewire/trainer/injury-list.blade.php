<div class="container mx-auto">
    <div class="flex justify-between items-center">
        <div>
            <flux:heading size="xl">Blessures et douleurs</flux:heading>
            <flux:text class="mb-6 mt-2 text-base">Liste des blessures ou douleurs déclarées par vos athlètes.</flux:text>
        </div>
        <div>
            <flux:button href="{{ route('trainers.injuries.create', ['hash' => $trainer->hash]) }}"
                size="sm"
                variant="primary"
                icon="plus">Ajouter une blessure</flux:button>
        </div>
    </div>
    <flux:separator class="my-8" variant="subtle" />

    <section>
        <flux:table :paginate="$injuries">
            <flux:table.columns>
                <flux:table.column>Date</flux:table.column>
                <flux:table.column>Athlète</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Localisation</flux:table.column>
                <flux:table.column>Statut</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($injuries as $injury)
                    <flux:table.row :key="$injury->id">
                        <flux:table.cell>
                            <a class="text-zinc-600 hover:text-zinc-900" href="{{ route('trainers.injuries.show', ['hash' => $trainer->hash, 'injury' => $injury->id]) }}">{{ $injury->declaration_date->format('d.m.Y') }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $injury->athlete->name }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $injury->injury_type?->getLabel() ?? 'n/a' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $injury->pain_location?->getLabel() ?? 'n/a' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:select wire:change="updateStatus({{ $injury->id }}, $event.target.value)">
                                @foreach($statuses as $status)
                                    <option value="{{ $status->value }}" @if($injury->status === $status) selected @endif>{{ $status->getLabel() }}</option>
                                @endforeach
                            </flux:select>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="outline"
                                size="sm"
                                icon="eye"
                                inset="bottom">
                                <a class="text-zinc-600 hover:text-zinc-900" href="{{ route('trainers.injuries.show', ['hash' => $trainer->hash, 'injury' => $injury->id]) }}">Détails</a>
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <div class="p-8">
                        <flux:callout icon="clipboard-document-list">
                            <flux:callout.heading>Aucun élément trouvé</flux:callout.heading>

                            <flux:callout.text>
                                Il semble qu'aucun élément n'ait été ajouté pour le moment.
                            </flux:callout.text>
                        </flux:callout>
                    </div>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </section>

</div>