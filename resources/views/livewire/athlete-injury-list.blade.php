<div class="container mx-auto">
        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <div class="px-4 py-6 sm:px-6 border-b border-gray-200 bg-gradient-to-r from-gray-100 to-white">
                <flux:heading size="xl" level="1" class="font-extrabold text-gray-900 mb-1">
                    Blessures et douleurs
                </flux:heading>
                <flux:text class="text-gray-600 text-md">
                    Liste des blessures ou douleurs déclarées
                </flux:text>
            </div>

            <div class="p-4">
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
                                {{ $injury->declaration_date->format('d.m.Y') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $injury->injury_type?->getLabel() ?? 'N/A' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$injury->status?->getColor()" inset="top bottom">{{ $injury->status?->getLabel() ?? 'N/A' }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button variant="ghost" size="sm" icon="eye" inset="bottom">
                                    <a href="{{ route('athletes.injuries.show', ['hash' => $athlete->hash, 'injury' => $injury->id]) }}" class="text-indigo-600 hover:text-indigo-900">Voir</a>
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
            </div>
        </div>
    </div>
</div>
