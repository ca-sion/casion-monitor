<div class="bg-white shadow-lg rounded-lg p-6">
    <h3 class="text-xl font-bold text-gray-800 mb-4">Glossaire des termes techniques</h3>
    <dl class="divide-y divide-gray-200">
        @foreach($glossary as $term => $definition)
            <div class="py-3">
                <dt class="text-lg font-semibold text-gray-900">{{ $term }}</dt>
                <dd class="mt-1 text-gray-700">{{ $definition }}</dd>
            </div>
        @endforeach
    </dl>
</div>
