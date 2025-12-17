document.addEventListener('DOMContentLoaded', () => {
    const bookingModalEl = document.getElementById('bookingModal');
    const bookingModal = new bootstrap.Modal(bookingModalEl);
    const ratingModalEl = document.getElementById('ratingModal');
    const ratingModal = new bootstrap.Modal(ratingModalEl);
    const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
    const scheduleDetailsModal = new bootstrap.Modal(document.getElementById('scheduleDetailsModal'));
    
    // --- My Schedule Logic ---
    let currentWeekStart = getStartOfWeek(new Date());

    function getStartOfWeek(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust when day is Sunday
        return new Date(d.setDate(diff));
    }

    function formatDate(date) {
        return date.toISOString().split('T')[0];
    }
    
    function updateScheduleDisplay() {
        const start = new Date(currentWeekStart);
        const end = new Date(currentWeekStart);
        end.setDate(end.getDate() + 6);

        const options = { month: 'short', day: 'numeric' };
        document.getElementById('current-week-display').textContent = `${start.toLocaleDateString('en-GB', options)} - ${end.toLocaleDateString('en-GB', options)}`;
        
        fetchSchedule(formatDate(start), formatDate(end));
    }

    function fetchSchedule(startDate, endDate) {
        const container = document.getElementById('my-schedule-container');
        container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        fetch(`../api/get_user_schedule.php?start_date=${startDate}&end_date=${endDate}`)
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                if (data.length === 0) {
                    container.innerHTML = '<div class="text-center text-muted py-4">No bookings found, Book a Class now!</div>';
                    return;
                }

                // Group by date
                const grouped = {};
                data.forEach(item => {
                    if (!grouped[item.SessionDate]) grouped[item.SessionDate] = [];
                    grouped[item.SessionDate].push(item);
                });

                // Render
                Object.keys(grouped).forEach(date => {
                    const dateObj = new Date(date);
                    const dayName = dateObj.toLocaleDateString('en-GB', { weekday: 'long' });
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'list-group-item list-group-item-secondary fw-bold';
                    dayHeader.textContent = `${dayName}, ${dateObj.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' })}`;
                    container.appendChild(dayHeader);

                    grouped[date].forEach(session => {
                        const item = document.createElement('div');
                        item.className = 'list-group-item';
                        // Escaping logic for attributes
                        const safeActivity = session.ActivityName.replace(/"/g, '&quot;');
                        const safeDesc = (session.Description || 'No description').replace(/"/g, '&quot;');
                        
                        // Format time to HH:MM
                        const timeStr = session.StartTime.substring(0, 5);
                        
                        item.innerHTML = `
                            <div class="row align-items-center text-center text-md-start">
                                <div class="col-md-2">
                                    <h4 class="mb-0 fw-bold text-primary">${timeStr}</h4>
                                </div>
                                <div class="col-md-3">
                                    <h4 class="mb-0 text-primary text-wrap">${session.ActivityName}</h4>
                                </div>
                                <div class="col-md-2">
                                    <h5 class="mb-0 text-secondary"><i class="fas fa-map-marker-alt me-2"></i>${session.Room || 'N/A'}</h5>
                                </div>
                                <div class="col-md-3">
                                    <h5 class="mb-0 text-secondary"><i class="fas fa-user-tie me-2"></i>${session.TrainerName}</h5>
                                </div>
                                <div class="col-md-2 text-md-end mt-2 mt-md-0">
                                    <button class="btn btn-outline-info w-100 view-details-btn" 
                                        data-activity="${safeActivity}"
                                        data-time="${session.SessionDate} at ${timeStr}"
                                        data-trainer="${session.TrainerName}"
                                        data-room="${session.Room || 'N/A'}"
                                        data-category="${session.CategoryName} (${session.DifficultyLevel})"
                                        data-description="${safeDesc}">
                                        Details
                                    </button>
                                </div>
                            </div>
                        `;
                        container.appendChild(item);
                    });
                });
            })
            .catch(err => {
                container.innerHTML = '<div class="text-center text-danger py-4">Failed to load schedule.</div>';
            });
    }

    document.getElementById('prev-week-btn').addEventListener('click', () => {
        currentWeekStart.setDate(currentWeekStart.getDate() - 7);
        updateScheduleDisplay();
    });

    document.getElementById('next-week-btn').addEventListener('click', () => {
        currentWeekStart.setDate(currentWeekStart.getDate() + 7);
        updateScheduleDisplay();
    });

    // Details Button Click
    document.addEventListener('click', function(e) {
        if (e.target.matches('.view-details-btn')) {
            const btn = e.target;
            document.getElementById('detail-activity').textContent = btn.dataset.activity;
            document.getElementById('detail-time').textContent = btn.dataset.time;
            document.getElementById('detail-trainer').textContent = btn.dataset.trainer;
            document.getElementById('detail-room').textContent = btn.dataset.room;
            document.getElementById('detail-category').textContent = btn.dataset.category;
            document.getElementById('detail-description').textContent = btn.dataset.description;
            scheduleDetailsModal.show();
        }
    });

    // Initial load
    updateScheduleDisplay();

    // Variables to store pending booking details
    let pendingSessionId = null;
    let pendingRepeatWeekly = false;
    let pendingCancellationId = null;

    let calendar = new VanillaCalendar('#calendar-container', {
        settings: { 
            lang: 'en-GB',
            selection: { day: 'single' }, 
            visibility: { theme: 'light' } 
        },
        actions: {
            clickDay(e, self) {
                selectedDate = self.selectedDates[0];
                if (selectedDate) {
                    document.getElementById('selected-date-display').textContent = selectedDate;
                    fetchSessions();
                }
            },
        },
    });
    calendar.init();
    let selectedDate = null;
    
    // Payment Modal Logic
    document.querySelectorAll('.payment-method-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
            e.currentTarget.classList.add('active');
            document.getElementById('pay-now-btn').disabled = false;
        });
    });

    document.getElementById('pay-now-btn').addEventListener('click', () => {
        document.getElementById('payment-methods').classList.add('d-none');
        document.getElementById('payment-loading').classList.remove('d-none');

        // Simulate payment processing
        setTimeout(() => {
            document.getElementById('payment-loading').classList.add('d-none');
            // document.getElementById('payment-success').classList.remove('d-none'); // Don't show success yet, wait for booking confirmation
            
            // Proceed with booking after simulated payment
            handleBooking(pendingSessionId, pendingRepeatWeekly, true);
            paymentModal.hide();
             // Reset modal state for next time
            setTimeout(() => {
                document.getElementById('payment-methods').classList.remove('d-none');
                document.querySelectorAll('.payment-method-btn').forEach(btn => btn.classList.remove('active'));
                document.getElementById('pay-now-btn').disabled = true;
            }, 500);

        }, 2000);
    });

    // Cancel Modal Logic
    document.getElementById('cancel-one-btn').addEventListener('click', () => {
        if (pendingCancellationId) {
            handleCancellation(pendingCancellationId, 'one');
            cancelModal.hide();
        }
    });

    document.getElementById('cancel-series-btn').addEventListener('click', () => {
        if (pendingCancellationId) {
            handleCancellation(pendingCancellationId, 'all');
            cancelModal.hide();
        }
    });

    // Fetch sessions function
    function fetchSessions() {
        if (!selectedDate) return;
        const categoryFilter = document.getElementById('category-filter').value;
        const difficultyFilter = document.getElementById('difficulty-filter').value;
        const sessionsContainer = document.getElementById('sessions-container');
        sessionsContainer.innerHTML = '<div class="text-center p-3"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';

        fetch(`../api/get_sessions.php?date=${selectedDate}&category_id=${categoryFilter}&difficulty=${difficultyFilter}`)
            .then(response => response.json())
            .then(data => {
                sessionsContainer.innerHTML = '';
                if (data.length > 0) {
                    data.forEach(session => {
                        const is_full = session.CurrentBookings >= session.MaxCapacity;
                        const percentage = session.MaxCapacity > 0 ? (session.CurrentBookings / session.MaxCapacity) * 100 : 0;
                        const sessionEl = document.createElement('div');
                        sessionEl.className = 'list-group-item';

                        const div1 = document.createElement('div');
                        div1.className = 'd-flex w-100 justify-content-between';

                        const h6 = document.createElement('h6');
                        h6.className = 'mb-1';
                        h6.textContent = session.ActivityName;
                        div1.appendChild(h6);

                        const small1 = document.createElement('small');
                        small1.textContent = session.Time;
                        div1.appendChild(small1);

                        sessionEl.appendChild(div1);

                        const p = document.createElement('p');
                        p.className = 'mb-1';
                        p.textContent = 'Trainer: ' + session.TrainerName;
                        sessionEl.appendChild(p);

                        const progressDiv = document.createElement('div');
                        progressDiv.className = 'progress';
                        progressDiv.style.height = '5px';
                        const progressBar = document.createElement('div');
                        progressBar.className = 'progress-bar';
                        progressBar.style.width = percentage + '%';
                        progressDiv.appendChild(progressBar);
                        sessionEl.appendChild(progressDiv);

                        const small2 = document.createElement('small');
                        small2.textContent = `${session.CurrentBookings} / ${session.MaxCapacity} Booked`;
                        sessionEl.appendChild(small2);

                        const button = document.createElement('button');
                        button.className = 'btn btn-primary btn-sm float-end book-session-btn';
                        button.dataset.sessionId = session.SessionID;
                        button.textContent = is_full ? 'Full' : 'Book';
                        if (is_full) {
                            button.disabled = true;
                        }
                        sessionEl.appendChild(button);
                        
                        sessionsContainer.appendChild(sessionEl);
                    });
                } else {
                    sessionsContainer.innerHTML = '<div class="text-center text-muted p-3">No classes available.</div>';
                }
            });
    }

    document.getElementById('category-filter').addEventListener('change', fetchSessions);
    document.getElementById('difficulty-filter').addEventListener('change', fetchSessions);

    // Main event delegator for clicks
    document.addEventListener('click', function(e) {
        // Book button
        if (e.target.matches('.book-session-btn')) {
            e.preventDefault();
            const sessionId = e.target.dataset.sessionId;
            
            let confirmMessage = "Confirm this booking?";
            
            if (window.bookingConfig.isPremiumMember) {
                confirmMessage += "\n\nWould you like to book this class for the next 2 weeks as well?";
                const repeatWeekly = confirm(confirmMessage);
                handleBooking(sessionId, repeatWeekly);
            } else {
                if(confirm(confirmMessage)) {
                    handleBooking(sessionId, false);
                }
            }
        }

        // Cancel button
        if (e.target.matches('.cancel-booking-btn')) {
            e.preventDefault();
            pendingCancellationId = e.target.dataset.reservationId;
            const isRecurring = e.target.dataset.isRecurring === '1';
            
            const cancelMessage = document.getElementById('cancel-message');
            const cancelSeriesBtn = document.getElementById('cancel-series-btn');
            const cancelOneBtn = document.getElementById('cancel-one-btn');

            if (isRecurring) {
                cancelMessage.textContent = "This is a recurring booking. Do you want to cancel just this booking or stop the entire pre-booking series?";
                cancelSeriesBtn.classList.remove('d-none');
                cancelOneBtn.textContent = "Only Current Booking";
            } else {
                cancelMessage.textContent = "Are you sure you want to cancel this booking?";
                cancelSeriesBtn.classList.add('d-none');
                cancelOneBtn.textContent = "Cancel Booking";
            }
            
            cancelModal.show();
        }
        
        // Rate button
        if (e.target.matches('.rate-btn')) {
            e.preventDefault();
            const reservationId = e.target.dataset.reservationId;
            document.getElementById('rating-reservation-id').value = reservationId;
            ratingModal.show();
        }

        // Star rating
        if (e.target.matches('.star-rating')) {
            const score = e.target.dataset.value;
            document.getElementById('rating-score').value = score;
            document.querySelectorAll('.star-rating').forEach(star => {
                star.classList.toggle('text-warning', star.dataset.value <= score);
            });
        }
    });
    
    // Rating form submission
    document.getElementById('rating-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'rate');
        formData.append('csrf_token', window.bookingConfig.csrfToken);

        fetch('../api/booking_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            showFeedback(data.message, data.success);
            if(data.success) {
                ratingModal.hide();
                setTimeout(() => location.reload(), 1500);
            }
        });
    });

    // Booking handler function
    function handleBooking(sessionId, repeatWeekly, paymentConfirmed = false) {
        const body = new FormData();
        body.append('action', 'book');
        body.append('sessionId', sessionId);
        body.append('csrf_token', window.bookingConfig.csrfToken);
        if (repeatWeekly) {
            body.append('repeat_weekly', 'true');
        }
        if (paymentConfirmed) {
            body.append('payment_confirmed', 'true');
        }

        fetch('../api/booking_handler.php', { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => {
                if (data.payment_required) {
                    // Store details for payment modal
                    pendingSessionId = sessionId;
                    pendingRepeatWeekly = repeatWeekly;
                    document.getElementById('payment-amount').textContent = 'RM ' + parseFloat(data.price).toFixed(2);
                    paymentModal.show();
                } else {
                    showFeedback(data.message, data.success);
                    if (data.success) {
                        bookingModal.hide();
                        setTimeout(() => location.reload(), 1500);
                    }
                }
            });
    }
    
    // Cancellation handler function
    function handleCancellation(reservationId, cancelScope) {
        const body = new FormData();
        body.append('action', 'cancel');
        body.append('reservationId', reservationId);
        body.append('cancel_scope', cancelScope);
        body.append('csrf_token', window.bookingConfig.csrfToken);

        fetch('../api/booking_handler.php', { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => {
                showFeedback(data.message, data.success);
                if (data.success) {
                    setTimeout(() => location.reload(), 1500);
                }
            });
    }

    // Toast feedback function
    function showFeedback(message, success) {
        const toastEl = document.getElementById('feedback-toast');
        const toastBody = toastEl.querySelector('.toast-body');
        toastEl.classList.remove('bg-success', 'bg-danger');
        toastEl.classList.add(success ? 'bg-success' : 'bg-danger');
        toastBody.textContent = message;
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
    }
});
