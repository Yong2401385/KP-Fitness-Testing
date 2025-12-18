document.addEventListener('DOMContentLoaded', () => {
    // Check if we are on the dashboard page by looking for a dashboard-specific element
    const updateStatsBtn = document.getElementById('update-stats-btn');

    if (updateStatsBtn) { // Only execute dashboard-specific logic on the dashboard page
        // Update Stats Logic
        const updateStatsModal = new bootstrap.Modal(document.getElementById('updateStatsModal'));
        const updateStatsForm = document.getElementById('updateStatsForm');

        updateStatsBtn.addEventListener('click', () => {
            // Pre-fill values from data attributes (Robust)
            const listItems = document.querySelectorAll('#health-stats-list li.list-group-item');
            
            if (listItems.length >= 2) {
                document.getElementById('stats-height').value = listItems[0].dataset.height || '';
                document.getElementById('stats-weight').value = listItems[1].dataset.weight || '';
            }
            
            updateStatsModal.show();
        });

        if (updateStatsForm) {
            updateStatsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                // Use window.clientConfig for csrfToken as it's globally available
                formData.append('csrf_token', window.clientConfig.csrfToken);

                fetch('../api/update_stats.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        const listItems = document.querySelectorAll('#health-stats-list li.list-group-item');
                        const newHeight = formData.get('height');
                        const newWeight = formData.get('weight');
                        
                        if (listItems.length >= 3) {
                            // Update attributes
                            listItems[0].dataset.height = newHeight;
                            listItems[1].dataset.weight = newWeight;
                            listItems[2].dataset.bmi = data.bmi;
                            listItems[2].dataset.bmiCategory = data.bmiCategory;

                            // Update Display
                            listItems[0].innerHTML = `<strong>Height:</strong> <span>${newHeight} cm</span>`;
                            listItems[1].innerHTML = `<strong>Weight:</strong> <span>${newWeight} kg</span>`;
                            listItems[2].innerHTML = `<strong>BMI:</strong> <span>${data.bmi} (${data.bmiCategory})</span>`;
                        }
                        
                        // Update the BMI card at the top too if it exists
                        // The BMI card is the 3rd card in the row of 4
                        const topCards = document.querySelectorAll('.row .col-md-3 .card-body .display-4');
                        if (topCards.length >= 3) {
                            topCards[2].textContent = data.bmi;
                            topCards[2].nextElementSibling.textContent = data.bmiCategory;
                        }

                        updateStatsModal.hide();
                        alert('Stats updated successfully!');
                        
                        // Optional: Reload page to refresh chart, or just leave it for now
                        // location.reload(); 
                    } else {
                        alert(data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        }

        // BMI Chart
        const bmiChartElement = document.getElementById('bmiChart');
        if (bmiChartElement) {
            fetch('../api/get_weight_history.php')
                .then(response => response.json())
                .then(data => {
                    const labels = data.map(item => new Date(item.CreatedAt).toLocaleDateString('en-GB'));
                    const weights = data.map(item => item.Weight);
                    const height = window.dashboardConfig.userHeight; // Still use dashboardConfig for user-specific data
                    const bmiData = weights.map(weight => {
                        if (height > 0) {
                            const heightInMeters = height / 100;
                            return (weight / (heightInMeters * heightInMeters)).toFixed(1);
                        }
                        return 0;
                    });

                    const ctx = bmiChartElement.getContext('2d');
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'BMI',
                                data: bmiData,
                                borderColor: 'rgba(75, 192, 192, 1)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                fill: true,
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: {
                                    beginAtZero: false
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error fetching weight history:', error);
                });
        }

        // --- Complete Profile Logic ---
        if (window.dashboardConfig.showCompleteProfile) {
            const completeProfileModal = new bootstrap.Modal(document.getElementById('completeProfileModal'));
            completeProfileModal.show();

            const cpForm = document.getElementById('completeProfileForm');
            const cpPhone = document.getElementById('cp-phone');

            // Phone Formatting
            if (cpPhone) {
                cpPhone.addEventListener('input', () => {
                    let value = cpPhone.value.replace(/\D/g, '');
                    let formattedValue = '';
                    if (value.length > 11) value = value.substring(0, 11);

                    if (value.length > 0) {
                        formattedValue = value.substring(0, 3);
                        if (value.length > 3) {
                            formattedValue += '-';
                            const remainingDigits = value.substring(3);
                            if (value.length <= 10) {
                                formattedValue += remainingDigits.substring(0, 3);
                                if (remainingDigits.length > 3) {
                                    formattedValue += ' ' + remainingDigits.substring(3, 7);
                                }
                            } else {
                                formattedValue += remainingDigits.substring(0, 4);
                                if (remainingDigits.length > 4) {
                                    formattedValue += ' ' + remainingDigits.substring(4, 8);
                                }
                            }
                        }
                    }
                    cpPhone.value = formattedValue;
                });
            }

            // Form Submission
            if (cpForm) {
                cpForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Client-side Validation
                    const phoneRegex = /^01\d-\d{3,4} \d{4}$/;
                    if (!phoneRegex.test(cpPhone.value)) {
                        cpPhone.classList.add('is-invalid');
                        cpPhone.focus();
                        return;
                    } else {
                        cpPhone.classList.remove('is-invalid');
                    }

                    const formData = new FormData(this);
                    formData.append('csrf_token', window.clientConfig.csrfToken); // Use global clientConfig

                    fetch('../api/complete_profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            completeProfileModal.hide();
                            alert(data.message);
                            location.reload(); // Reload to update dashboard stats
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred.');
                    });
                });
            }
            
            // Dismiss Logic
            const dismissBtn = document.getElementById('dismiss-profile-btn');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', () => {
                    fetch('../api/dismiss_profile_prompt.php', { method: 'POST' });
                });
            }
        }
    }
});