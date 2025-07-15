<div class="container mx-auto">
    <flux:heading size="xl">Blessures et douleurs</flux:heading>
    <flux:text class="mb-6 mt-2 text-base">Liste des blessures ou douleurs déclarées.</flux:text>
    <flux:separator class="my-8" variant="subtle" />

    <section>
        <flux:table :paginate="$injuries">
            <flux:table.columns>
                <flux:table.column>Date de déclaration</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Statut</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($injuries as $injury)
                    <flux:table.row :key="$injury->id">
                        <flux:table.cell>
                            <a class="text-zinc-600 hover:text-zinc-900" href="{{ route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}">{{ $injury->declaration_date->format('d.m.Y') }}</a>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $injury->injury_type?->getLabel() ?? 'N/A' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm"
                                :color="$injury->status?->getColor()"
                                inset="top bottom">{{ $injury->status?->getLabel() ?? 'N/A' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="outline"
                                size="sm"
                                icon="eye"
                                inset="bottom">
                                <a class="text-zinc-600 hover:text-zinc-900" href="{{ route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}">Détails</a>
                            </flux:button>
                            <flux:button href="{{ route('athletes.injuries.feedback.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}"
                                size="sm"
                                variant="primary"
                                icon="plus">Consultation</flux:button>
                            <flux:button href="{{ route('athletes.injuries.recovery-protocols.create', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}"
                                size="sm"
                                variant="outline"
                                icon="plus">Séance</flux:button>
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
</div>
