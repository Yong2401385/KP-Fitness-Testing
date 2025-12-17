document.addEventListener('DOMContentLoaded', function() {
    const notifBtn = document.getElementById('sidebarNotifBtn');
    const notifBadge = document.getElementById('sidebarNotifBadge');
    const notifListContainer = document.getElementById('modalNotificationList');
    const markReadBtn = document.getElementById('markAllReadBtn');
    
    // Initialize Modal
    let notifModal = null;
    const modalEl = document.getElementById('notificationModal');
    if (modalEl) {
        notifModal = new bootstrap.Modal(modalEl);
    }

    if (!notifBtn) return;

    // Fetch notifications periodically
    fetchNotifications();
    setInterval(fetchNotifications, 30000);

    // Open Modal on Click
    notifBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (notifModal) {
            notifModal.show();
            // Refresh list when opening
            fetchNotifications(true); 
        }
    });

    function fetchNotifications(render = false) {
        fetch('../admin/api_get_notifications.php') // Adjust path if needed
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.unreadCount);
                    if (render || (modalEl && modalEl.classList.contains('show'))) {
                        renderList(data.notifications);
                    }
                }
            })
            .catch(err => console.error('Notification error:', err));
    }

    function updateBadge(count) {
        if (count > 0) {
            notifBadge.textContent = count > 99 ? '99+' : count;
            notifBadge.classList.remove('d-none');
        } else {
            notifBadge.textContent = '0';
            notifBadge.classList.add('d-none');
        }
    }

    function renderList(notifications) {
        if (!notifListContainer) return;
        notifListContainer.innerHTML = '';

        if (notifications.length === 0) {
            notifListContainer.innerHTML = '<div class="text-center p-4 text-muted">No notifications</div>';
            return;
        }

        notifications.forEach(n => {
            const item = document.createElement('div');
            item.className = `list-group-item ${n.IsRead == 0 ? 'bg-light fw-bold' : ''}`;
            
            // Icon based on type
            let icon = 'info-circle';
            let color = 'primary';
            if (n.Type === 'success') { icon = 'check-circle'; color = 'success'; }
            if (n.Type === 'warning') { icon = 'exclamation-triangle'; color = 'warning'; }
            if (n.Type === 'error') { icon = 'times-circle'; color = 'danger'; }

            item.innerHTML = `
                <div class="d-flex align-items-start">
                    <div class="flex-shrink-0 text-${color} mt-1">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">${n.TimeAgo}</small>
                        </div>
                        <p class="mb-0 text-break" style="font-size: 0.9rem;">${n.Message}</p>
                    </div>
                </div>
            `;
            notifListContainer.appendChild(item);
        });
    }

    if (markReadBtn) {
        markReadBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'mark_all_read');

            fetch('../admin/api_get_notifications.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        fetchNotifications(true); // Refresh list
                    }
                });
        });
    }
});