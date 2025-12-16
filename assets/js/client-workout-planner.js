document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('generator-form');
    const fitnessLevelSelect = document.getElementById('fitnessLevel');
    const customDaysCheckbox = document.getElementById('customDays');
    const workoutDaysContainer = document.getElementById('workoutDaysContainer');
    const workoutDayCheckboxes = document.querySelectorAll('input[name="workoutDays[]"]');

    // Default configs
    const levelConfigs = {
        'beginner': 3,
        'intermediate': 4,
        'advanced': 5
    };

    function updateWorkoutDaysLogic() {
        const level = fitnessLevelSelect.value;
        const isCustom = customDaysCheckbox.checked;
        const targetCount = levelConfigs[level] || 0;

        if (!level || isCustom) {
            // Unlock all logic
            workoutDayCheckboxes.forEach(cb => {
                cb.disabled = false;
                // We don't force uncheck, just let user choose
            });
            return;
        }

        // Logic for constrained mode
        const checkedBoxes = Array.from(workoutDayCheckboxes).filter(cb => cb.checked);
        
        // If we have more checked than allowed, user must uncheck some. 
        // We disable unchecked ones if limit reached.
        
        // First, check if we need to auto-select days (only on fresh selection/change of level)
        // But preventing data loss is better. Let's just enforce the max limit.
        // Actually, the requirement says "Automatically set and lock number of days".
        
        // Auto-set logic: If current checked count != targetCount, we might need to reset or adjust.
        // Simple approach: If checked < target, enable unchecked. If checked == target, disable unchecked.
        
        if (checkedBoxes.length >= targetCount) {
             workoutDayCheckboxes.forEach(cb => {
                 if (!cb.checked) cb.disabled = true;
             });
        } else {
             workoutDayCheckboxes.forEach(cb => {
                 cb.disabled = false;
             });
        }
    }

    // Initialize state
    updateWorkoutDaysLogic();

    // Event Listeners
    if (fitnessLevelSelect) {
        fitnessLevelSelect.addEventListener('change', () => {
            const level = fitnessLevelSelect.value;
            const targetCount = levelConfigs[level] || 0;
            const isCustom = customDaysCheckbox.checked;

            if (!isCustom && targetCount > 0) {
                // Auto-select the first N days as a default helper
                // First uncheck all
                workoutDayCheckboxes.forEach(cb => cb.checked = false);
                // Then check first N
                for (let i = 0; i < targetCount; i++) {
                    if (workoutDayCheckboxes[i]) workoutDayCheckboxes[i].checked = true;
                }
            }
            updateWorkoutDaysLogic();
        });
    }

    if (customDaysCheckbox) {
        customDaysCheckbox.addEventListener('change', updateWorkoutDaysLogic);
    }

    workoutDayCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateWorkoutDaysLogic);
    });

    if (form) {
        form.addEventListener('submit', function(e) {
            const checkedDays = form.querySelectorAll('input[name="workoutDays[]"]:checked').length;
            
            // Basic validation
            if (checkedDays < 3) {
                e.preventDefault();
                alert('Please select at least 3 workout days.');
                return;
            }
            
            // Enforce exact count if not custom (optional, but good for "Locking" requirement)
            const level = fitnessLevelSelect.value;
            const isCustom = customDaysCheckbox.checked;
            const targetCount = levelConfigs[level] || 0;
            
            if (!isCustom && targetCount > 0 && checkedDays !== targetCount) {
                 e.preventDefault();
                 alert(`For ${level} level, please select exactly ${targetCount} days, or check "Customize" to override.`);
            }
        });
    }
});