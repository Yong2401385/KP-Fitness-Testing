
</main> <!-- Closes the .main-content div from admin_header.php -->

<!-- Global Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #ff6b00;">
                <h5 class="modal-title"><i class="fas fa-bell me-2"></i>Notifications</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="modalNotificationList" class="list-group list-group-flush">
                    <!-- Items loaded via JS -->
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-sm btn-outline-primary w-100" id="markAllReadBtn">Mark all as read</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="../assets/js/main.js"></script>
<script src="../assets/js/admin-notifications.js"></script>
</body>
</html>
