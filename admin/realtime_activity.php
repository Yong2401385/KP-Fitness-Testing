<?php
define('PAGE_TITLE', 'Real-Time Activity');
require_once '../includes/config.php';
require_admin();

$viewMode = $_GET['view'] ?? 'graph'; // 'graph' or 'details'
$section = $_GET['section'] ?? 'all'; // 'all', 'registrations', 'memberships', 'bookings', 'sessions', etc.

// Fetch last 24 hours data
$last24Hours = date('Y-m-d H:i:s', strtotime('-24 hours'));

try {
    // New user registrations (last 24h)
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(CreatedAt, '%H:00') as hour, COUNT(*) as count
        FROM users 
        WHERE CreatedAt >= ? AND Role != 'admin'
        GROUP BY DATE_FORMAT(CreatedAt, '%H:00')
        ORDER BY hour ASC
    ");
    $stmt->execute([$last24Hours]);
    $registrations_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT UserID, FullName, Email, Role, CreatedAt
        FROM users 
        WHERE CreatedAt >= ? AND Role != 'admin'
        ORDER BY CreatedAt DESC
    ");
    $stmt->execute([$last24Hours]);
    $registrations_details = $stmt->fetchAll();
    
    // Membership purchases (last 24h)
    $stmt = $pdo->prepare("
        SELECT m.PlanName, COUNT(p.PaymentID) as count
        FROM payments p
        JOIN membership m ON p.MembershipID = m.MembershipID
        WHERE p.PaymentDate >= ? AND p.Status = 'completed'
        GROUP BY m.MembershipID
    ");
    $stmt->execute([$last24Hours]);
    $memberships_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT p.PaymentID, u.FullName, u.Email, m.PlanName, p.Amount, p.PaymentDate, p.PaymentMethod
        FROM payments p
        JOIN users u ON p.UserID = u.UserID
        JOIN membership m ON p.MembershipID = m.MembershipID
        WHERE p.PaymentDate >= ? AND p.Status = 'completed'
        ORDER BY p.PaymentDate DESC
    ");
    $stmt->execute([$last24Hours]);
    $memberships_details = $stmt->fetchAll();
    
    // Bookings (last 24h)
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(BookingDate, '%H:00') as hour, COUNT(*) as count
        FROM reservations 
        WHERE BookingDate >= ?
        GROUP BY DATE_FORMAT(BookingDate, '%H:00')
        ORDER BY hour ASC
    ");
    $stmt->execute([$last24Hours]);
    $bookings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT r.ReservationID, u.FullName, u.Email, a.ClassName, s.SessionDate, 
               s.StartTime, r.BookingDate, r.Status
        FROM reservations r
        JOIN users u ON r.UserID = u.UserID
        JOIN sessions s ON r.SessionID = s.SessionID
        JOIN activities a ON s.ClassID = a.ClassID
        WHERE r.BookingDate >= ?
        ORDER BY r.BookingDate DESC
    ");
    $stmt->execute([$last24Hours]);
    $bookings_details = $stmt->fetchAll();
    
    // Sessions completed (last 24h)
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(s.SessionDate, '%H:00') as hour, COUNT(*) as count
        FROM sessions s
        WHERE s.Status = 'completed' AND s.SessionDate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY DATE_FORMAT(s.SessionDate, '%H:00')
        ORDER BY hour ASC
    ");
    $stmt->execute();
    $sessions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT s.SessionID, a.ClassName, u.FullName as TrainerName, s.SessionDate, 
               s.StartTime, s.Room, s.CurrentBookings
        FROM sessions s
        JOIN activities a ON s.ClassID = a.ClassID
        JOIN users u ON s.TrainerID = u.UserID
        WHERE s.Status = 'completed' AND s.SessionDate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY s.SessionDate DESC
    ");
    $stmt->execute();
    $sessions_details = $stmt->fetchAll();
    
    // Activity summary counts
    $total_registrations = count($registrations_details);
    $total_memberships = count($memberships_details);
    $total_bookings = count($bookings_details);
    $total_sessions = count($sessions_details);
    
} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch activity data: ' . $e->getMessage()];
    $registrations_data = $memberships_data = $bookings_data = $sessions_data = [];
    $registrations_details = $memberships_details = $bookings_details = $sessions_details = [];
    $total_registrations = $total_memberships = $total_bookings = $total_sessions = 0;
}

// Prepare data for charts
$reg_labels = json_encode(array_column($registrations_data, 'hour'));
$reg_values = json_encode(array_column($registrations_data, 'count'));

$mem_labels = json_encode(array_column($memberships_data, 'PlanName'));
$mem_values = json_encode(array_column($memberships_data, 'count'));

$book_labels = json_encode(array_column($bookings_data, 'hour'));
$book_values = json_encode(array_column($bookings_data, 'count'));

$sess_labels = json_encode(array_column($sessions_data, 'hour'));
$sess_values = json_encode(array_column($sessions_data, 'count'));

include 'includes/admin_header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Real-Time Activity (Last 24 Hours)</h1>
    <div>
        <small class="text-muted me-3" id="lastUpdated">Updated: <?php echo date('H:i:s'); ?></small>
    </div>
</div>

<?php if (isset($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
            <div>
                <div class="fs-4 fw-bold" id="stat-registrations"><?php echo $total_registrations; ?></div>
                <h6>New Registrations</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-credit-card"></i></div>
            <div>
                <div class="fs-4 fw-bold" id="stat-memberships"><?php echo $total_memberships; ?></div>
                <h6>Memberships Purchased</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div>
                <div class="fs-4 fw-bold" id="stat-bookings"><?php echo $total_bookings; ?></div>
                <h6>New Bookings</h6>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="fs-4 fw-bold" id="stat-sessions"><?php echo $total_sessions; ?></div>
                <h6>Sessions Completed</h6>
            </div>
        </div>
    </div>
</div>

<?php if ($viewMode === 'graph'): ?>
    <!-- Graph View -->
    <div class="row">
        <!-- New Registrations -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>New User Registrations</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="openDetailsModal('registrations', 'New User Registrations')">Details</button>
                </div>
                <div class="card-body">
                    <canvas id="registrationsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Membership Purchases -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Membership Types Purchased</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="openDetailsModal('memberships', 'Membership Purchases')">Details</button>
                </div>
                <div class="card-body">
                    <canvas id="membershipsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Bookings -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>New Bookings</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="openDetailsModal('bookings', 'New Bookings')">Details</button>
                </div>
                <div class="card-body">
                    <canvas id="bookingsChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Sessions Completed -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Sessions Completed</span>
                    <button class="btn btn-sm btn-outline-secondary" onclick="openDetailsModal('sessions', 'Completed Sessions')">Details</button>
                </div>
                <div class="card-body">
                    <canvas id="sessionsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
<?php else: ?>
    <!-- Details View -->
    <div class="row">
        <!-- New Registrations Details -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>New User Registrations (<?php echo $total_registrations; ?>)</span>
                    <a href="realtime_activity.php?view=graph&section=registrations" class="btn btn-sm btn-outline-light">Graph</a>
                </div>
                <div class="card-body">
                    <?php if (empty($registrations_details)): ?>
                        <p class="text-muted text-center">No new registrations in the last 24 hours.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations_details as $reg): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reg['FullName']); ?></td>
                                            <td><?php echo htmlspecialchars($reg['Email']); ?></td>
                                            <td><span class="badge bg-<?php echo $reg['Role'] === 'trainer' ? 'success' : 'info'; ?>"><?php echo ucfirst($reg['Role']); ?></span></td>
                                            <td><?php echo date('H:i', strtotime($reg['CreatedAt'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Membership Purchases Details -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Membership Purchases (<?php echo $total_memberships; ?>)</span>
                    <a href="realtime_activity.php?view=graph&section=memberships" class="btn btn-sm btn-outline-light">Graph</a>
                </div>
                <div class="card-body">
                    <?php if (empty($memberships_details)): ?>
                        <p class="text-muted text-center">No membership purchases in the last 24 hours.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Plan</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($memberships_details as $mem): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($mem['FullName']); ?></td>
                                            <td><?php echo htmlspecialchars($mem['PlanName']); ?></td>
                                            <td><?php echo format_currency($mem['Amount']); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $mem['PaymentMethod'])); ?></td>
                                            <td><?php echo date('H:i', strtotime($mem['PaymentDate'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Bookings Details -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>New Bookings (<?php echo $total_bookings; ?>)</span>
                    <a href="realtime_activity.php?view=graph&section=bookings" class="btn btn-sm btn-outline-light">Graph</a>
                </div>
                <div class="card-body">
                    <?php if (empty($bookings_details)): ?>
                        <p class="text-muted text-center">No new bookings in the last 24 hours.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Activity</th>
                                        <th>Session Date</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings_details as $book): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($book['FullName']); ?></td>
                                            <td><?php echo htmlspecialchars($book['ClassName']); ?></td>
                                            <td><?php echo format_date($book['SessionDate']); ?></td>
                                            <td><span class="badge bg-<?php echo $book['Status'] === 'booked' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($book['Status']); ?></span></td>
                                            <td><?php echo date('H:i', strtotime($book['BookingDate'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Sessions Completed Details -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Sessions Completed (<?php echo $total_sessions; ?>)</span>
                    <a href="realtime_activity.php?view=graph&section=sessions" class="btn btn-sm btn-outline-light">Graph</a>
                </div>
                <div class="card-body">
                    <?php if (empty($sessions_details)): ?>
                        <p class="text-muted text-center">No sessions completed in the last 24 hours.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Trainer</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Attendees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions_details as $sess): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($sess['ClassName']); ?></td>
                                            <td><?php echo htmlspecialchars($sess['TrainerName']); ?></td>
                                            <td><?php echo format_date($sess['SessionDate']); ?></td>
                                            <td><?php echo format_time($sess['StartTime']); ?></td>
                                            <td><?php echo $sess['CurrentBookings']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center mt-4">
        <a href="realtime_activity.php?view=graph" class="btn btn-primary btn-lg">
            <i class="fas fa-chart-bar me-2"></i>View Graphs
        </a>
    </div>
<?php endif; ?>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="detailsModalTitle">Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead class="table-dark" id="detailsTableHead">
                            <!-- Populated by JS -->
                        </thead>
                        <tbody id="detailsTableBody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>
                <div id="detailsLoading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="detailsEmpty" class="text-center py-4 d-none">
                    <p class="text-muted">No records found for the last 24 hours.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = 'Inter', 'Helvetica Neue', 'Helvetica, Arial, sans-serif';
    Chart.defaults.color = '#ADB5BD'; // Light gray for text
    Chart.defaults.borderColor = '#495057'; // Darker gray for borders/grid lines

    // Store chart instances globally to update them later
    const charts = {};

    // Base options for line/bar charts for consistency
    const baseChartOptions = {
        responsive: true,
        maintainAspectRatio: false, // Allow charts to fill container
        plugins: {
            legend: {
                display: false, // Default to no legend unless specified
                labels: {
                    color: '#ADB5BD',
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.7)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#6C757D',
                borderWidth: 1,
                cornerRadius: 5,
                displayColors: false,
            }
        },
        scales: {
            x: {
                grid: {
                    color: '#495057', // X-axis grid lines
                    drawBorder: false,
                },
                ticks: {
                    color: '#ADB5BD', // X-axis labels
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    color: '#495057', // Y-axis grid lines
                    drawBorder: false,
                },
                ticks: {
                    color: '#ADB5BD', // Y-axis labels
                }
            }
        }
    };

    <?php if ($viewMode === 'graph'): ?>
    // Registrations Chart
    const regCtx = document.getElementById('registrationsChart');
    if (regCtx) {
        charts.registrations = new Chart(regCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo $reg_labels ?: '[]'; ?>,
                datasets: [{
                    label: 'Registrations',
                    data: <?php echo $reg_values ?: '[]'; ?>,
                    borderColor: '#0d6efd', // Primary blue
                    backgroundColor: 'rgba(13, 110, 253, 0.15)', // Light blue fill
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4, // Smoother lines
                    pointRadius: 3,
                    pointBackgroundColor: '#0d6efd',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 5,
                }]
            },
            options: { ...baseChartOptions }
        });
    }
    
    // Memberships Chart
    const memCtx = document.getElementById('membershipsChart');
    if (memCtx) {
        charts.memberships = new Chart(memCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo $mem_labels ?: '[]'; ?>,
                datasets: [{
                    data: <?php echo $mem_values ?: '[]'; ?>,
                    backgroundColor: ['#0d6efd', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545', '#6f42c1', '#20c997'], // More distinct colors
                    borderColor: '#343A40', // Dark background for slices
                    borderWidth: 2,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right', // Legend on the right for doughnuts
                        labels: {
                            color: '#ADB5BD',
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.7)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#6C757D',
                        borderWidth: 1,
                        cornerRadius: 5,
                    }
                }
            }
        });
    }
    
    // Bookings Chart
    const bookCtx = document.getElementById('bookingsChart');
    if (bookCtx) {
        charts.bookings = new Chart(bookCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo $book_labels ?: '[]'; ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo $book_values ?: '[]'; ?>,
                    backgroundColor: '#17a2b8', // Info blue
                    borderColor: '#17a2b8',
                    borderWidth: 1,
                    borderRadius: 4, // Rounded bars
                    barPercentage: 0.7, // Adjust bar width
                    categoryPercentage: 0.8,
                }]
            },
            options: { ...baseChartOptions }
        });
    }
    
    // Sessions Chart
    const sessCtx = document.getElementById('sessionsChart');
    if (sessCtx) {
        charts.sessions = new Chart(sessCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo $sess_labels ?: '[]'; ?>,
                datasets: [{
                    label: 'Sessions',
                    data: <?php echo $sess_values ?: '[]'; ?>,
                    borderColor: '#28a745', // Success green
                    backgroundColor: 'rgba(40, 167, 69, 0.15)', // Light green fill
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#28a745',
                    pointBorderColor: '#fff',
                    pointHoverRadius: 5,
                }]
            },
            options: { ...baseChartOptions }
        });
    }
    <?php endif; ?>

    // --- Auto-Refresh Logic (Every 60 Seconds) ---
    setInterval(fetchRealTimeData, 60000);

    function fetchRealTimeData() {
        fetch('api_get_realtime_data.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 1. Update Stats
                    document.getElementById('stat-registrations').textContent = data.stats.registrations;
                    document.getElementById('stat-memberships').textContent = data.stats.memberships;
                    document.getElementById('stat-bookings').textContent = data.stats.bookings;
                    document.getElementById('stat-sessions').textContent = data.stats.sessions;
                    
                    // 2. Update Timestamp
                    const now = new Date();
                    document.getElementById('lastUpdated').textContent = 'Updated: ' + now.toLocaleTimeString();

                    // 3. Update Charts (if in graph view)
                    if (charts.registrations) {
                        updateChart(charts.registrations, data.charts.registrations);
                    }
                    if (charts.memberships) {
                        updateChart(charts.memberships, data.charts.memberships);
                    }
                    if (charts.bookings) {
                        updateChart(charts.bookings, data.charts.bookings);
                    }
                    if (charts.sessions) {
                        updateChart(charts.sessions, data.charts.sessions);
                    }
                }
            })
            .catch(error => console.error('Error fetching real-time data:', error));
    }

    function updateChart(chart, newData) {
        // Doughnut chart has datasets[0].backgroundColor, not borderColor
        if (chart.config.type === 'doughnut') {
            chart.data.labels = newData.labels;
            chart.data.datasets[0].data = newData.values;
        } else {
            chart.data.labels = newData.labels;
            chart.data.datasets[0].data = newData.values;
        }
        chart.update();
    }
});

// --- Modal Logic (Global Scope) ---
function openDetailsModal(type, title) {
    const modalEl = document.getElementById('detailsModal');
    if (!modalEl) {
        console.error('Modal element not found!');
        return;
    }
    
    // Use getOrCreateInstance to prevent multiple initializations/conflicts
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    document.getElementById('detailsModalTitle').textContent = title;
    
    const thead = document.getElementById('detailsTableHead');
    const tbody = document.getElementById('detailsTableBody');
    const loading = document.getElementById('detailsLoading');
    const empty = document.getElementById('detailsEmpty');
    const table = tbody.closest('table');

    // Reset state
    thead.innerHTML = '';
    tbody.innerHTML = '';
    loading.classList.remove('d-none');
    empty.classList.add('d-none');
    table.classList.add('d-none');
    
    modal.show();

    // Define headers per type
    let headers = [];
    switch(type) {
        case 'registrations': headers = ['Name', 'Email', 'Role', 'Time']; break;
        case 'memberships': headers = ['Member', 'Plan', 'Amount', 'Method', 'Time']; break;
        case 'bookings': headers = ['Member', 'Activity', 'Session Date', 'Time', 'Booked At']; break;
        case 'sessions': headers = ['Activity', 'Trainer', 'Date', 'Time', 'Attendees']; break;
    }

    // Render Headers
    let headerRow = '<tr>';
    headers.forEach(h => headerRow += `<th>${h}</th>`);
    headerRow += '</tr>';
    thead.innerHTML = headerRow;

    // Fetch Data
    fetch(`api_get_realtime_data.php?detail_type=${type}`)
        .then(res => res.json())
        .then(data => {
            loading.classList.add('d-none');
            if (data.success && data.data.length > 0) {
                table.classList.remove('d-none');
                let rows = '';
                data.data.forEach(item => {
                    rows += '<tr>';
                    Object.values(item).forEach(val => {
                        // Simple check to format numbers as currency if they look like it
                        if (!isNaN(val) && val > 0 && type === 'memberships') {
                            // Just a quick hack for display, better to format in PHP
                            if(val == item.Amount) val = 'RM ' + val; 
                        }
                        rows += `<td>${val}</td>`;
                    });
                    rows += '</tr>';
                });
                tbody.innerHTML = rows;
            } else {
                empty.classList.remove('d-none');
            }
        })
        .catch(err => {
            loading.classList.add('d-none');
            empty.textContent = 'Error loading data.';
            empty.classList.remove('d-none');
        });
}
</script>

<?php include 'includes/admin_footer.php'; ?>

