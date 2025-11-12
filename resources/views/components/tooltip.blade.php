@props(['term', 'definition'])

<span class="relative group">
    <span class="underline decoration-dotted cursor-help">
        {{ $term }}
    </span>
    <span class="absolute z-20 invisible group-hover:visible opacity-0 group-hover:opacity-100 transition-opacity duration-300
                 bg-gray-800 text-white text-xs rounded py-1 px-2 bottom-full left-1/2 -translate-x-1/2 mb-2 w-max">
        {{ $definition }}
        <svg class="absolute text-gray-800 h-2 w-full left-0 top-full" x="0px" y="0px" viewBox="0 0 255 255" xml:space="preserve"><polygon class="fill-current" points="0,0 127.5,127.5 255,0"/></svg>
    </span>
</span>
