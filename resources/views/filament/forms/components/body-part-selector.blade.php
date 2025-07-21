@php
    // Get the unique ID for the component instance
    $fieldId = $getId();
    // Get the current value of the field (array of selected parts)
    $state = $getState();
    // Ensure $state is an array, even if it's null or a single string
    $selectedParts = is_array($state) ? $state : ($state ? [$state] : []);
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div x-data="{
        state: $wire.$entangle(@js($getStatePath())),
        selectedParts: @js($selectedParts), // Initialize with current values
        updateSelectedParts(partId) {
            const index = this.selectedParts.indexOf(partId);
            if (index > -1) {
                // Part is already selected, remove it
                this.selectedParts.splice(index, 1);
            } else {
                // Part is not selected, add it
                this.selectedParts.push(partId);
            }
            // Update the Filament field state
            this.state = this.selectedParts;
        },
        getPartDisplayName(partId) {
            // Converts 'left_upper_arm_anterior' to 'Left Upper Arm (Anterior)'
            if (!partId) return '';
            const parts = partId.split('_');
            const side = parts[0];
            const name = parts.slice(1, -1).join(' '); // Join all but first and last
            const face = parts[parts.length - 1];

            let displayName = name.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            if (side === 'left') {
                displayName = 'Left ' + displayName;
            } else if (side === 'right') {
                displayName = 'Right ' + displayName;
            }

            displayName += ` (${face.charAt(0).toUpperCase() + face.slice(1)})`;
            return displayName;
        }
    }"
    x-init="
        // Apply initial selection styles
        selectedParts.forEach(partId => {
            const path = document.getElementById(partId);
            if (path) {
                path.classList.add('selected-body-part');
            }
        });

        // Add event listeners to SVG paths
        document.querySelectorAll('#body-anterior-view path, #body-posterior-view path').forEach(path => {
            path.addEventListener('click', (event) => {
                const partId = event.currentTarget.id;
                updateSelectedParts(partId);
                event.currentTarget.classList.toggle('selected-body-part'); // Toggle visual state
            });
            // Optional: Add hover effect for better UX
            path.addEventListener('mouseenter', (event) => {
                event.currentTarget.style.fill = '#C0C0C0'; // Slightly darker on hover
            });
            path.addEventListener('mouseleave', (event) => {
                // Only revert if not selected
                if (!event.currentTarget.classList.contains('selected-body-part')) {
                    event.currentTarget.style.fill = '#E0E0E0';
                }
            });
        });
    "
    {{ $getExtraAttributeBag() }}
    class="filament-forms-body-part-selector-component"
    >
        {{-- Display selected parts --}}
        <div class="selected-parts-display" style="margin-bottom: 15px; min-height: 24px;">
            <template x-if="selectedParts.length > 0">
                <span class="text-sm text-gray-700 dark:text-gray-300">Selected:</span>
            </template>
            <template x-for="partId in selectedParts" :key="partId">
                <span x-text="getPartDisplayName(partId)" class="selected-tag"></span>
            </template>
        </div>

        {{-- SVG Human Body (Anterior and Posterior Views) --}}
        <div style="display: flex; justify-content: center; gap: 40px;">
            <svg id="body-anterior-view" width="250" height="500" viewBox="0 0 250 500" style="background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;">
                <text x="125" y="20" font-family="Arial, sans-serif" font-size="16" text-anchor="middle" fill="#555">Vue Antérieure</text>

                <path id="head_anterior" d="M125,30 A30,30 0 1,0 125,90 A30,30 0 1,0 125,30" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="neck_anterior" d="M115,90 L115,100 C115,105 135,105 135,100 L135,90 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                
                <path id="torso_anterior" d="M115,100 C100,105 90,120 90,150 V200 C90,210 95,215 100,220 H150 C155,215 160,210 160,200 V150 C160,120 150,105 135,100 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                
                <path id="left_upper_arm_anterior" d="M90,120 C85,110 70,115 65,130 L60,160 C60,170 70,170 75,160 L80,130 C85,120 90,110 90,120 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_forearm_anterior" d="M60,160 C55,170 50,190 50,210 L55,230 C60,240 70,230 65,210 L60,190 C60,170 65,165 60,160 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_hand_anterior" d="M55,230 C50,240 45,250 45,260 L50,270 C55,280 65,270 60,260 L55,250 C55,240 60,235 55,230 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="right_upper_arm_anterior" d="M160,120 C165,110 180,115 185,130 L190,160 C190,170 180,170 175,160 L170,130 C165,120 160,110 160,120 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_forearm_anterior" d="M190,160 C195,170 200,190 200,210 L195,230 C190,240 180,230 185,210 L190,190 C190,170 185,165 190,160 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_hand_anterior" d="M195,230 C200,240 205,250 205,260 L200,270 C195,280 185,270 190,260 L195,250 C195,240 190,235 195,230 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                
                <path id="pelvis_anterior" d="M100,220 H150 C160,230 160,240 150,250 H100 C90,240 90,230 100,220 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="left_thigh_anterior" d="M100,250 L100,350 C100,360 95,370 90,370 L85,360 C80,350 80,260 90,250 L100,250 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_calf_anterior" d="M90,370 L90,440 C90,450 95,460 100,460 L105,450 C110,440 110,380 100,370 L90,370 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_foot_anterior" d="M100,460 L90,480 C90,490 100,495 110,490 L115,480 C110,470 105,465 100,460 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="right_thigh_anterior" d="M150,250 L150,350 C150,360 155,370 160,370 L165,360 C170,350 170,260 160,250 L150,250 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_calf_anterior" d="M160,370 L160,440 C160,450 155,460 150,460 L145,450 C140,440 140,380 150,370 L160,370 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_foot_anterior" d="M150,460 L160,480 C160,490 150,495 140,490 L135,480 C140,470 145,465 150,460 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
            </svg>

            <svg id="body-posterior-view" width="250" height="500" viewBox="0 0 250 500" style="background-color: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;">
                <text x="125" y="20" font-family="Arial, sans-serif" font-size="16" text-anchor="middle" fill="#555">Vue Postérieure</text>

                <path id="head_posterior" d="M125,30 A30,30 0 1,0 125,90 A30,30 0 1,0 125,30" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="neck_posterior" d="M115,90 L115,100 C115,105 135,105 135,100 L135,90 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="back_posterior" d="M115,100 C100,105 90,120 90,150 V200 C90,210 95,215 100,220 H150 C155,215 160,210 160,200 V150 C160,120 150,105 135,100 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="left_upper_arm_posterior" d="M90,120 C85,110 70,115 65,130 L60,160 C60,170 70,170 75,160 L80,130 C85,120 90,110 90,120 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_forearm_posterior" d="M60,160 C55,170 50,190 50,210 L55,230 C60,240 70,230 65,210 L60,190 C60,170 65,165 60,160 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_hand_posterior" d="M55,230 C50,240 45,250 45,260 L50,270 C55,280 65,270 60,260 L55,250 C55,240 60,235 55,230 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="right_upper_arm_posterior" d="M160,120 C165,110 180,115 185,130 L190,160 C190,170 180,170 175,160 L170,130 C165,120 160,110 160,120 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_forearm_posterior" d="M190,160 C195,170 200,190 200,210 L195,230 C190,240 180,230 185,210 L190,190 C190,170 185,165 190,160 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_hand_posterior" d="M195,230 C200,240 205,250 205,260 L200,270 C195,280 185,270 190,260 L195,250 C195,240 190,235 195,230 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="pelvis_posterior" d="M100,220 H150 C160,230 160,240 150,250 H100 C90,240 90,230 100,220 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="left_hamstring_posterior" d="M100,250 L100,350 C100,360 95,370 90,370 L85,360 C80,350 80,260 90,250 L100,250 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_calf_posterior" d="M90,370 L90,440 C90,450 95,460 100,460 L105,450 C110,440 110,380 100,370 L90,370 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="left_foot_posterior" d="M100,460 L90,480 C90,490 100,495 110,490 L115,480 C110,470 105,465 100,460 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />

                <path id="right_hamstring_posterior" d="M150,250 L150,350 C150,360 155,370 160,370 L165,360 C170,350 170,260 160,250 L150,250 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_calf_posterior" d="M160,370 L160,440 C160,450 155,460 150,460 L145,450 C140,440 140,380 150,370 L160,370 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
                <path id="right_foot_posterior" d="M150,460 L160,480 C160,490 150,495 140,490 L135,480 C140,470 145,465 150,460 Z" fill="#E0E0E0" stroke="#B0B0B0" stroke-width="1" />
            </svg>
        </div>
    </div>
</x-dynamic-component>

<style>
    /* Base styles for SVG paths */
    #body-anterior-view path,
    #body-posterior-view path {
        cursor: pointer;
        transition: fill 0.2s ease-in-out;
        fill: #E0E0E0; /* Default neutral fill */
    }

    /* Style for selected parts */
    #body-anterior-view path.selected-body-part,
    #body-posterior-view path.selected-body-part {
        fill: #4CAF50 !important; /* A vibrant green to indicate selection */
        stroke: #388E3C;
    }

    /* Hover effect for all parts (including selected ones) */
    #body-anterior-view path:hover:not(.selected-body-part),
    #body-posterior-view path:hover:not(.selected-body-part) {
        fill: #C0C0C0; /* Slightly darker grey on hover for non-selected */
    }

    /* Style for selected tags */
    .selected-tag {
        display: inline-block;
        background-color: #e0f2f7; /* Light blue background */
        color: #0288d1; /* Darker blue text */
        padding: 4px 8px;
        border-radius: 4px;
        margin-right: 8px;
        margin-top: 4px;
        font-size: 0.8em;
        font-weight: 500;
        white-space: nowrap; /* Prevent wrapping */
    }
</style>