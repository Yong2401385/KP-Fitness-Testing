<?php
define('PAGE_TITLE', 'Session Scheduling');
require_once '../includes/config.php';
require_admin();

$feedback = [];
$edit_session = null;

// --- Auto-update session status based on time ---
try {
    $now = date('Y-m-d H:i:s');
    // Update scheduled to ongoing when StartTime is reached
    $pdo->exec("
        UPDATE sessions 
        SET Status = 'ongoing' 
        WHERE Status = 'scheduled' 
        AND CONCAT(SessionDate, ' ', StartTime) <= '$now'
    ");
    // Update ongoing to completed when EndTime is reached
    $pdo->exec("
        UPDATE sessions 
        SET Status = 'completed' 
        WHERE Status = 'ongoing' 
        AND CONCAT(SessionDate, ' ', EndTime) <= '$now'
    ");
} catch (PDOException $e) {
    // Silently fail - don't break the page if status update fails
    error_log("Failed to auto-update session status: " . $e->getMessage());
}

// --- Handle Form Submissions ---

// Handle Create or Update Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    validate_csrf_token($_POST['csrf_token']);
    $classId = intval($_POST['classId']);
    $trainerId = intval($_POST['trainerId']);
    $sessionDate = sanitize_input($_POST['sessionDate']);
    $startTime = sanitize_input($_POST['startTime']);
    $endTime = sanitize_input($_POST['endTime']);
    $room = sanitize_input($_POST['room']);
    $sessionId = isset($_POST['sessionId']) ? intval($_POST['sessionId']) : null;

    // Validation
    if (empty($classId) || empty($trainerId) || empty($sessionDate) || empty($startTime) || empty($endTime) || empty($room)) {
        $feedback = ['type' => 'danger', 'message' => 'Please fill in all required fields.'];
    } elseif ($startTime >= $endTime) {
        $feedback = ['type' => 'danger', 'message' => 'End Time must be after Start Time.'];
    } else {
        try {
            if ($sessionId) { // Update
                $stmt = $pdo->prepare("UPDATE sessions SET ClassID = ?, TrainerID = ?, SessionDate = ?, StartTime = ?, EndTime = ?, Room = ? WHERE SessionID = ?");
                if ($stmt->execute([$classId, $trainerId, $sessionDate, $startTime, $endTime, $room, $sessionId])) {
                    $feedback = ['type' => 'success', 'message' => 'Session updated successfully.'];
                }
            } else { // Create
                $stmt = $pdo->prepare("INSERT INTO sessions (ClassID, TrainerID, SessionDate, StartTime, EndTime, Room) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$classId, $trainerId, $sessionDate, $startTime, $endTime, $room])) {
                    $feedback = ['type' => 'success', 'message' => 'Session scheduled successfully.'];
                }
            }
        } catch (PDOException $e) {
            $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}

// Handle Cancel/Reactivate Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['cancel_session']) || isset($_POST['reactivate_session']))) {
    validate_csrf_token($_POST['csrf_token']);
    $sessionId = intval($_POST['sessionId']);
    $newStatus = isset($_POST['cancel_session']) ? 'cancelled' : 'scheduled';
    $action = $newStatus === 'cancelled' ? 'cancelled' : 'reactivated';
    try {
        $stmt = $pdo->prepare("UPDATE sessions SET Status = ? WHERE SessionID = ?");
        if ($stmt->execute([$newStatus, $sessionId])) {
            $feedback = ['type' => 'success', 'message' => "Session has been $action."];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Handle Delete Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_session'])) {
    validate_csrf_token($_POST['csrf_token']);
    $sessionId = intval($_POST['sessionId']);
    try {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE SessionID = ?");
        if ($stmt->execute([$sessionId])) {
            $feedback = ['type' => 'success', 'message' => 'Session deleted successfully.'];
        }
    } catch (PDOException $e) {
        $feedback = ['type' => 'danger', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// --- Fetch Data for Display ---
try {
    $orderBy = "ORDER BY s.SessionDate DESC, s.StartTime DESC";
    $selectFields = "s.*, s.StartTime, s.EndTime, a.ClassName, a.MaxCapacity, u.FullName as TrainerName";
    
    $stmt = $pdo->prepare("
        SELECT $selectFields
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        JOIN users u ON s.TrainerID = u.UserID
        WHERE u.Role = 'trainer'
        $orderBy
    ");
    $stmt->execute();
    $sessions = $stmt->fetchAll();

    // Fetch active activities for the dropdown
    $stmt_activities = $pdo->prepare("SELECT ClassID, ClassName, Specialist, Duration FROM activities WHERE IsActive = TRUE ORDER BY ClassName");
    $stmt_activities->execute();
    $active_activities = $stmt_activities->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch active trainers for the dropdown
    $stmt_trainers = $pdo->prepare("SELECT UserID, FullName, Specialist FROM users WHERE IsActive = TRUE AND Role = 'trainer' ORDER BY FullName");
    $stmt_trainers->execute();
    $active_trainers = $stmt_trainers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch data: ' . $e->getMessage()];
    $sessions = $active_activities = $active_trainers = [];
}

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Session Scheduling</h1>
</div>

<?php if (!empty($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Schedule Session Form -->
<div class="mb-4">
    <h3 class="mb-3">Schedule New Session</h3>
    <form action="sessions.php" method="POST" id="scheduleForm">
        <input type="hidden" name="csrf_token" value="<?php echo get_csrf_token(); ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="classId" class="form-label">Activity</label>
                <select class="form-select" id="classId" name="classId" required>
                    <option value="">Select an activity...</option>
                    <?php foreach ($active_activities as $activity): ?>
                        <option value="<?php echo $activity['ClassID']; ?>" data-specialist="<?php echo htmlspecialchars($activity['Specialist']); ?>" data-duration="<?php echo $activity['Duration']; ?>"><?php echo htmlspecialchars($activity['ClassName']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted" id="specialistHint"></small>
            </div>
            <div class="col-md-6">
                <label for="trainerId" class="form-label">Trainer</label>
                <select class="form-select" id="trainerId" name="trainerId" required>
                    <option value="">Select a trainer...</option>
                     <?php foreach ($active_trainers as $trainer): ?>
                        <option value="<?php echo $trainer['UserID']; ?>" data-specialist="<?php echo htmlspecialchars($trainer['Specialist']); ?>"><?php echo htmlspecialchars($trainer['FullName']); ?> (<?php echo htmlspecialchars($trainer['Specialist']); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="sessionDate" class="form-label">Date</label>
                <input type="date" class="form-control" id="sessionDate" name="sessionDate" required>
            </div>
            <div class="col-md-3">
                <label for="startTime" class="form-label">Start Time</label>
                <input type="time" class="form-control" id="startTime" name="startTime" required>
            </div>
            <div class="col-md-3">
                <label for="endTime" class="form-label">End Time</label>
                <input type="time" class="form-control" id="endTime" name="endTime" required>
            </div>
            <div class="col-md-3">
                <label for="room" class="form-label">Room</label>
                <input type="text" class="form-control" id="room" name="room" placeholder="e.g., Studio A" required>
            </div>
            <div class="col-12">
                <button type="submit" name="save_session" class="btn btn-primary">Schedule Session</button>
            </div>
        </div>
    </form>
</div>

<!-- Scheduled Sessions List -->
<div class="mb-4">
    <h3 class="mb-3">Scheduled Sessions</h3>
    
    <!-- Filters -->
    <div class="card bg-dark mb-3">
        <div class="card-body">
            <!-- Month Navigation -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <button class="btn btn-outline-light" id="prevMonthBtn"><i class="fas fa-chevron-left"></i> Previous Month</button>
                <h4 class="text-white mb-0" id="currentMonthDisplay"></h4>
                <button class="btn btn-outline-light" id="nextMonthBtn">Next Month <i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label text-white">From Date</label>
                    <input type="date" id="filterStartDate" class="form-control">
                </div>
                <div class="col-md-5">
                    <label class="form-label text-white">To Date</label>
                    <input type="date" id="filterEndDate" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button id="resetFilters" class="btn btn-secondary w-100">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Date Folders Container -->
    <div id="sessionFolders" class="row">
        <!-- Populated by JS -->
        <div class="col-12 text-center text-muted">Loading sessions...</div>
    </div>
</div>

<!-- Session Date Modal -->
<div class="modal fade" id="sessionDateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background-color: var(--primary-color); color: white;">
                <h5 class="modal-title" id="sessionDateModalTitle">Sessions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1);"></button>
            </div>
            <div class="modal-body">
                <!-- Modal Filters -->
                <div class="row g-2 mb-3">
                    <div class="col-md-3">
                        <select id="modalFilterActivity" class="form-select form-select-sm">
                            <option value="all">All Activities</option>
                            <!-- Populated by JS -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="modalFilterTrainer" class="form-select form-select-sm">
                            <option value="all">All Trainers</option>
                            <!-- Populated by JS -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" id="modalFilterRoom" class="form-control form-select-sm" placeholder="Filter by Room">
                    </div>
                    <div class="col-md-3">
                        <select id="modalFilterStatus" class="form-select form-select-sm">
                            <option value="all">All Status</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover table-dark mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Activity</th>
                                <th>Trainer</th>
                                <th>Room</th>
                                <th>Bookings</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="modalSessionBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    .date-folder-card {
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        border: 1px solid var(--border-color);
        background-color: var(--light-bg);
        color: var(--text-light);
    }
    .date-folder-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        border-color: var(--primary-color);
    }
    .folder-date-header {
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--primary-color);
    }
    .folder-session-count {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
    .modal-body {
        max-height: 60vh; /* Set a maximum height relative to viewport height */
        overflow-y: auto; /* Enable vertical scrolling */
    }
</style>

<script>
    // --- Data ---
    const allSessions = <?php echo json_encode($sessions); ?>;
    const allActivities = <?php echo json_encode($active_activities); ?>;
    const allTrainers = <?php echo json_encode($active_trainers); ?>;
    const csrfToken = '<?php echo get_csrf_token(); ?>';
    
    // State
    let currentMonth = new Date(); // Stores the currently displayed month (for month navigation)
    let filteredSessions = []; // Stores sessions filtered by current month and other criteria
    let currentModalDate = null; // Track currently open date for modal filtering

    document.addEventListener('DOMContentLoaded', () => {
        
        // --- 1. Automated Trainer Selection ---
        const classSelect = document.getElementById('classId');
        const trainerSelect = document.getElementById('trainerId');
        const specialistHint = document.getElementById('specialistHint');

        classSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const requiredSpecialist = selectedOption.dataset.specialist;
            const duration = parseInt(selectedOption.dataset.duration) || 60;
            
            const startTimeInput = document.getElementById('startTime');
            const endTimeInput = document.getElementById('endTime');
            if(startTimeInput.value) {
                const [hours, minutes] = startTimeInput.value.split(':').map(Number);
                const date = new Date();
                date.setHours(hours, minutes + duration);
                endTimeInput.value = date.toTimeString().slice(0,5);
            }

            if (!requiredSpecialist) {
                specialistHint.textContent = '';
                return;
            }
            specialistHint.textContent = `Recommended Specialist: ${requiredSpecialist}`;

            const options = Array.from(trainerSelect.options);
            options.sort((a, b) => {
                if (a.value === "") return -1;
                if (b.value === "") return 1;
                const aSpec = a.dataset.specialist === requiredSpecialist;
                const bSpec = b.dataset.specialist === requiredSpecialist;
                if (aSpec && !bSpec) return -1;
                if (!aSpec && bSpec) return 1;
                return 0;
            });
            trainerSelect.innerHTML = '';
            options.forEach(opt => trainerSelect.add(opt));
            if (options.length > 1 && options[1].dataset.specialist === requiredSpecialist) {
                trainerSelect.selectedIndex = 1;
            } else {
                trainerSelect.selectedIndex = 0;
            }
        });
        
        document.getElementById('startTime').addEventListener('change', function() {
            const selectedClass = classSelect.options[classSelect.selectedIndex];
            if(selectedClass && selectedClass.value) {
                const duration = parseInt(selectedClass.dataset.duration) || 60;
                const [hours, minutes] = this.value.split(':').map(Number);
                const date = new Date();
                date.setHours(hours, minutes + duration);
                document.getElementById('endTime').value = date.toTimeString().slice(0,5);
            }
        });

        const dateInput = document.getElementById('sessionDate');
        const startTimeInput = document.getElementById('startTime');
        const roomInput = document.getElementById('room');

        dateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            if (!selectedDate) return;
            const sessionsOnDate = allSessions.filter(s => s.SessionDate === selectedDate && s.Status !== 'cancelled');
            let suggestedTime = '09:00';
            let suggestedRoom = 'Studio A';
            if (sessionsOnDate.length > 0) {
                sessionsOnDate.sort((a, b) => (a.EndTime < b.EndTime ? 1 : -1));
                const lastSession = sessionsOnDate[0];
                const [hours, minutes] = lastSession.EndTime.split(':').map(Number);
                const nextStart = new Date();
                nextStart.setHours(hours, minutes + 30);
                suggestedTime = nextStart.toTimeString().slice(0,5);
                const collision = sessionsOnDate.find(s => 
                    (s.StartTime <= suggestedTime && s.EndTime > suggestedTime) && s.Room === suggestedRoom
                );
                if (collision) {
                    suggestedRoom = 'Studio B';
                }
            }
            startTimeInput.value = suggestedTime;
            roomInput.value = suggestedRoom;
            startTimeInput.dispatchEvent(new Event('change'));
        });


        // --- Folder & Filtering Logic ---
        const filterStartDate = document.getElementById('filterStartDate');
        const filterEndDate = document.getElementById('filterEndDate');
        const resetFiltersBtn = document.getElementById('resetFilters');
        const foldersContainer = document.getElementById('sessionFolders');
        const prevMonthBtn = document.getElementById('prevMonthBtn');
        const nextMonthBtn = document.getElementById('nextMonthBtn');
        const currentMonthDisplay = document.getElementById('currentMonthDisplay');

        // Modal Filter Elements
        const modalFilterActivity = document.getElementById('modalFilterActivity');
        const modalFilterTrainer = document.getElementById('modalFilterTrainer');
        const modalFilterRoom = document.getElementById('modalFilterRoom');
        const modalFilterStatus = document.getElementById('modalFilterStatus');

        function updateMonthDisplay() {
            currentMonthDisplay.textContent = currentMonth.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
            applyFilters(); // Re-apply filters for the new month
        }

        prevMonthBtn.addEventListener('click', () => {
            currentMonth.setMonth(currentMonth.getMonth() - 1);
            updateMonthDisplay();
        });

        nextMonthBtn.addEventListener('click', () => {
            currentMonth.setMonth(currentMonth.getMonth() + 1);
            updateMonthDisplay();
        });

        function applyFilters() {
            const start = filterStartDate.value;
            const end = filterEndDate.value;
            
            // Calculate start/end of currentMonth
            const monthStart = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1);
            const monthEnd = new Date(currentMonth.getFullYear(), currentMonth.getMonth() + 1, 0); // Last day of month
            const formattedMonthStart = monthStart.toISOString().split('T')[0];
            const formattedMonthEnd = monthEnd.toISOString().split('T')[0];

            filteredSessions = allSessions.filter(session => {
                let match = true;
                // Month Filter
                if (session.SessionDate < formattedMonthStart || session.SessionDate > formattedMonthEnd) match = false;
                
                // Date Range Filter (within the current month)
                if (start && session.SessionDate < start) match = false;
                if (end && session.SessionDate > end) match = false;
                
                return match;
            });

            renderFolders();
        }

        function renderFolders() {
            foldersContainer.innerHTML = '';
            
            if (filteredSessions.length === 0) {
                foldersContainer.innerHTML = '<div class="col-12 text-center text-muted py-5">No sessions found for this month matching your filters.</div>';
                return;
            }

            // Group by Date
            const grouped = {};
            filteredSessions.forEach(session => {
                if (!grouped[session.SessionDate]) grouped[session.SessionDate] = [];
                grouped[session.SessionDate].push(session);
            });

            const sortedDates = Object.keys(grouped).sort();

            sortedDates.forEach(date => {
                const dateObj = new Date(date);
                const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'short' });
                const dayNum = dateObj.getDate();
                const month = dateObj.toLocaleDateString('en-US', { month: 'short' });
                const year = dateObj.getFullYear();
                const sessionCount = grouped[date].length;

                const col = document.createElement('div');
                col.className = 'col-md-3 mb-3';
                
                col.innerHTML = `
                    <div class="card date-folder-card h-100 text-center p-3" onclick="openSessionModal('${date}')">
                        <div class="folder-date-header mb-2">
                            <i class="far fa-calendar-alt me-2"></i>${dayNum} ${month}
                        </div>
                        <div class="text-muted small mb-2">${dayName}, ${year}</div>
                        <div class="folder-session-count">
                            <span class="badge bg-secondary">${sessionCount} Sessions</span>
                        </div>
                    </div>
                `;
                foldersContainer.appendChild(col);
            });
        }

        // --- Modal Filter Logic ---
        
        function populateModalFilters() {
            // Populate Activity Dropdown
            modalFilterActivity.innerHTML = '<option value="all">All Activities</option>';
            const uniqueActivities = [...new Set(allActivities.map(a => a.ClassName))]; // Ensure unique
            uniqueActivities.sort().forEach(act => {
                modalFilterActivity.add(new Option(act, act));
            });

            // Populate Trainer Dropdown
            modalFilterTrainer.innerHTML = '<option value="all">All Trainers</option>';
            allTrainers.forEach(trainer => {
                modalFilterTrainer.add(new Option(trainer.FullName, trainer.FullName));
            });
        }

        // Expose to global scope for onclick handler
        window.openSessionModal = function(date) {
            currentModalDate = date; // Set current date context
            
            // Populate filters if empty (first run)
            if (modalFilterActivity.options.length <= 1) {
                populateModalFilters();
            }
            
            // Reset filters on open
            modalFilterActivity.value = 'all';
            modalFilterTrainer.value = 'all';
            modalFilterRoom.value = '';
            modalFilterStatus.value = 'all';

            renderModalSessions();

            // Update Title
            const dateObj = new Date(date);
            document.getElementById('sessionDateModalTitle').textContent = `Sessions for ${dateObj.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;

            // Show Modal
            const modal = new bootstrap.Modal(document.getElementById('sessionDateModal'));
            modal.show();
        };

        function renderModalSessions() {
            if (!currentModalDate) return;

            // Get sessions for this date from the FILTERED list (so main date filters still apply)
            let sessionsForDate = filteredSessions.filter(s => s.SessionDate === currentModalDate);
            
            // Apply Modal Filters
            const actFilter = modalFilterActivity.value;
            const trainerFilter = modalFilterTrainer.value;
            const roomFilter = modalFilterRoom.value.toLowerCase();
            const statusFilter = modalFilterStatus.value;

            sessionsForDate = sessionsForDate.filter(s => {
                if (actFilter !== 'all' && s.ClassName !== actFilter) return false;
                if (trainerFilter !== 'all' && s.TrainerName !== trainerFilter) return false;
                if (roomFilter && !s.Room.toLowerCase().includes(roomFilter)) return false;
                if (statusFilter !== 'all' && s.Status !== statusFilter) return false;
                return true;
            });

            // Sort by time
            sessionsForDate.sort((a, b) => (a.StartTime > b.StartTime ? 1 : -1));

            // Populate Table
            const tbody = document.getElementById('modalSessionBody');
            tbody.innerHTML = '';

            if (sessionsForDate.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No sessions found matching filters.</td></tr>';
                return;
            }

            sessionsForDate.forEach(session => {
                const tr = document.createElement('tr');
                
                // Status Badge
                let statusClass = 'bg-secondary';
                if (session.Status === 'scheduled') statusClass = 'bg-success';
                if (session.Status === 'ongoing') statusClass = 'bg-info';
                if (session.Status === 'completed') statusClass = 'bg-secondary';
                if (session.Status === 'cancelled') statusClass = 'bg-danger';
                
                // Action Buttons
                let actions = `
                    <div class="btn-group" role="group">
                        <a href="view_attendance.php?session_id=${session.SessionID}" class="btn btn-info btn-sm" title="Attendance"><i class="fas fa-eye"></i></a>
                        <form action="sessions.php" method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="sessionId" value="${session.SessionID}">
                `;
                
                if (session.Status === 'scheduled') {
                    actions += `<button type="submit" name="cancel_session" class="btn btn-danger btn-sm" title="Cancel"><i class="fas fa-ban"></i></button>`;
                } else if (session.Status === 'cancelled') {
                    actions += `<button type="submit" name="reactivate_session" class="btn btn-success btn-sm" title="Reactivate"><i class="fas fa-redo"></i></button>`;
                }
                
                actions += `<button type="submit" name="delete_session" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>`;

                // Time Format (HH:MM:SS -> HH:MM)
                const startStr = session.StartTime.substring(0, 5);
                const endStr = session.EndTime ? session.EndTime.substring(0, 5) : '?';

                tr.innerHTML = `
                    <td>${startStr} - ${endStr}</td>
                    <td>${escapeHtml(session.ClassName)}</td>
                    <td>${escapeHtml(session.TrainerName)}</td>
                    <td>${escapeHtml(session.Room)}</td>
                    <td>${session.CurrentBookings} / ${session.MaxCapacity}</td>
                    <td><span class="badge ${statusClass} text-capitalize">${session.Status}</span></td>
                    <td>${actions}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        // Filter Listeners
        filterStartDate.addEventListener('change', applyFilters);
        filterEndDate.addEventListener('change', applyFilters);

        // Modal Filter Listeners
        modalFilterActivity.addEventListener('change', renderModalSessions);
        modalFilterTrainer.addEventListener('change', renderModalSessions);
        modalFilterRoom.addEventListener('input', renderModalSessions);
        modalFilterStatus.addEventListener('change', renderModalSessions);

        resetFiltersBtn.addEventListener('click', () => {
            filterStartDate.value = '';
            filterEndDate.value = '';
            applyFilters();
        });

        // Initial Render
        populateModalFilters(); // Populate modal filters initially
        updateMonthDisplay(); // Call this to set initial month and render folders
    });
</script>

<?php include 'includes/admin_footer.php'; ?>