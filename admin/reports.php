<?php
define('PAGE_TITLE', 'Reports & Analytics');
require_once '../includes/config.php';
require_admin();

// Get filter period (default to 12 months)
$period = isset($_GET['period']) ? intval($_GET['period']) : 12;
if (!in_array($period, [1, 3, 6, 12])) {
    $period = 12;
}

// --- Fetch Report Data ---
try {
    // Revenue data (filtered by period, split by type)
    // We assume 'Non-Member' or 'onetime' plans are Class bookings, others are Memberships
    $revenue_data = $pdo->query("
        SELECT 
            DATE_FORMAT(p.PaymentDate, '%Y-%m') as month,
            SUM(CASE WHEN m.PlanName = 'Non-Member' THEN p.Amount ELSE 0 END) as class_revenue,
            SUM(CASE WHEN m.PlanName != 'Non-Member' THEN p.Amount ELSE 0 END) as membership_revenue
        FROM payments p
        JOIN membership m ON p.MembershipID = m.MembershipID
        WHERE p.Status = 'completed' AND p.PaymentDate >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)
        GROUP BY DATE_FORMAT(p.PaymentDate, '%Y-%m')
        ORDER BY month ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Popular classes (all time)
    $popular_activities = $pdo->query("
        SELECT a.ClassName, COUNT(r.ReservationID) as booking_count 
        FROM activities a 
        JOIN sessions s ON a.ClassID = s.ClassID 
        JOIN reservations r ON s.SessionID = r.SessionID 
        GROUP BY a.ClassID 
        ORDER BY booking_count DESC LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Membership distribution
    $membership_dist = $pdo->query("
        SELECT m.PlanName, COUNT(u.UserID) as member_count 
        FROM users u
        JOIN membership m ON u.MembershipID = m.MembershipID
        WHERE u.Role = 'client' AND u.IsActive = TRUE
        GROUP BY u.MembershipID
    ")->fetchAll(PDO::FETCH_ASSOC);

    // --- KPI Metrics Calculation ---

    // 1. Total Revenue (Current vs Previous Period)
    $stmt = $pdo->prepare("SELECT SUM(Amount) FROM payments WHERE Status = 'completed' AND PaymentDate >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)");
    $stmt->execute([$period]);
    $currentRevenue = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT SUM(Amount) FROM payments WHERE Status = 'completed' AND PaymentDate >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND PaymentDate < DATE_SUB(CURDATE(), INTERVAL ? MONTH)");
    $stmt->execute([$period * 2, $period]);
    $prevRevenue = $stmt->fetchColumn() ?: 0;

    $revenueChange = $prevRevenue > 0 ? (($currentRevenue - $prevRevenue) / $prevRevenue) * 100 : 0;

    // 2. New Members (Current vs Previous Period)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Role = 'client' AND CreatedAt >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)");
    $stmt->execute([$period]);
    $currentNewMembers = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE Role = 'client' AND CreatedAt >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) AND CreatedAt < DATE_SUB(CURDATE(), INTERVAL ? MONTH)");
    $stmt->execute([$period * 2, $period]);
    $prevNewMembers = $stmt->fetchColumn() ?: 0;

    $membersChange = $prevNewMembers > 0 ? (($currentNewMembers - $prevNewMembers) / $prevNewMembers) * 100 : 0;

    // 3. Class Occupancy Rate (Avg of (CurrentBookings / MaxCapacity) * 100)
    $stmt = $pdo->prepare("
        SELECT AVG((s.CurrentBookings / a.MaxCapacity) * 100) 
        FROM sessions s 
        JOIN activities a ON s.ClassID = a.ClassID 
        WHERE s.SessionDate >= DATE_SUB(CURDATE(), INTERVAL ? MONTH) 
        AND s.Status IN ('completed', 'scheduled')
        AND a.MaxCapacity > 0
    ");
    $stmt->execute([$period]);
    $occupancyRate = round($stmt->fetchColumn() ?: 0);

    // 4. Churn Rate (Approximate: Expired Memberships in period)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users 
        WHERE MembershipEndDate < CURDATE() 
        AND MembershipEndDate >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
        AND (MembershipID IS NULL OR MembershipID NOT IN (SELECT MembershipID FROM membership))
    ");
    $stmt->execute([$period]);
    $churnCount = $stmt->fetchColumn() ?: 0;

    // 5. Heatmap Data (Day of Week vs Hour)
    // DAYOFWEEK() returns 1=Sunday, 7=Saturday
    // HOUR() returns 0-23
    $heatmap_data = $pdo->query("
        SELECT 
            DAYOFWEEK(s.SessionDate) as day_num, 
            HOUR(s.StartTime) as hour_num, 
            COUNT(r.ReservationID) as booking_count
        FROM sessions s
        JOIN reservations r ON s.SessionID = r.SessionID
        WHERE s.SessionDate >= DATE_SUB(CURDATE(), INTERVAL $period MONTH)
        AND r.Status IN ('booked', 'attended', 'Done', 'Rated')
        GROUP BY day_num, hour_num
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $feedback = ['type' => 'danger', 'message' => 'Could not fetch report data: ' . $e->getMessage()];
    $revenue_data = $popular_activities = $membership_dist = $heatmap_data = [];
    $currentRevenue = $prevRevenue = $currentNewMembers = $prevNewMembers = $occupancyRate = $churnCount = 0;
    $revenueChange = $membersChange = 0;
}

// Check if this is a PDF generation request
if (isset($_GET['generate_pdf'])) {
    require_once 'generate_report_pdf.php';
    exit;
}

// Prepare data for Chart.js
$revenue_labels = json_encode(array_column($revenue_data, 'month'));
$class_revenue_values = json_encode(array_column($revenue_data, 'class_revenue'));
$membership_revenue_values = json_encode(array_column($revenue_data, 'membership_revenue'));

$pop_activity_labels = json_encode(array_column($popular_activities, 'ClassName'));
$pop_activity_values = json_encode(array_column($popular_activities, 'booking_count'));

$membership_labels = json_encode(array_column($membership_dist, 'PlanName'));
$membership_values = json_encode(array_column($membership_dist, 'member_count'));

// Prepare Heatmap Data
// Initialize 7x24 grid with zeros
$heatmap_grid = array_fill(1, 7, array_fill(6, 17, 0)); // Only showing 6am to 10pm (17 hours) to save space
$max_heatmap_val = 0;

foreach ($heatmap_data as $row) {
    $d = intval($row['day_num']);
    $h = intval($row['hour_num']);
    $val = intval($row['booking_count']);
    
    // Filter to reasonable gym hours (6am - 10pm)
    if ($h >= 6 && $h <= 22) {
        $heatmap_grid[$d][$h] = $val;
        if ($val > $max_heatmap_val) $max_heatmap_val = $val;
    }
}
$heatmap_json = json_encode($heatmap_grid);



include 'includes/admin_header.php';
?>


<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Reports & Analytics</h1>
    <div>
        <a href="#" onclick="window.open('reports.php?generate_pdf=1&period=<?php echo $period; ?>', 'ReportWindow', 'width=1000,height=800'); return false;" class="btn btn-danger">
            <i class="fas fa-file-pdf me-1"></i>Generate PDF
        </a>
        <a href="generate_report_csv.php?period=<?php echo $period; ?>" class="btn btn-success ms-2">
            <i class="fas fa-file-csv me-1"></i>Export CSV
        </a>
    </div>
</div>

<?php if (isset($feedback)): ?>
    <div class="alert alert-<?php echo $feedback['type']; ?> alert-dismissible fade show" role="alert">
        <?php echo $feedback['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Growth Metrics Cards -->
<div class="row mb-4">
    <!-- Total Revenue -->
    <div class="col-md-3 mb-3">
        <div class="card h-100 border-left-primary shadow-sm py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo format_currency($currentRevenue); ?></div>
                        <div class="mt-2 small">
                            <span class="<?php echo $revenueChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-<?php echo $revenueChange >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i> 
                                <?php echo number_format(abs($revenueChange), 1); ?>%
                            </span>
                            <span class="text-muted">vs last period</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-dollar-sign fa-2x text-gray-300" style="color: #dddfeb;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Members -->
    <div class="col-md-3 mb-3">
        <div class="card h-100 border-left-success shadow-sm py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">New Members</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $currentNewMembers; ?></div>
                        <div class="mt-2 small">
                            <span class="<?php echo $membersChange >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="fas fa-<?php echo $membersChange >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i> 
                                <?php echo number_format(abs($membersChange), 1); ?>%
                            </span>
                            <span class="text-muted">vs last period</span>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300" style="color: #dddfeb;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Occupancy Rate -->
    <div class="col-md-3 mb-3">
        <div class="card h-100 border-left-info shadow-sm py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Class Occupancy</div>
                        <div class="row no-gutters align-items-center">
                            <div class="col-auto">
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?php echo $occupancyRate; ?>%</div>
                            </div>
                            <div class="col">
                                <div class="progress progress-sm mr-2">
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $occupancyRate; ?>%" aria-valuenow="<?php echo $occupancyRate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 small text-muted">Avg capacity filled</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300" style="color: #dddfeb;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Churn (Expired) -->
    <div class="col-md-3 mb-3">
        <div class="card h-100 border-left-warning shadow-sm py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Expired/Churned</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $churnCount; ?></div>
                        <div class="mt-2 small text-muted">Memberships ended</div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-times fa-2x text-gray-300" style="color: #dddfeb;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Monthly Revenue (Last <?php echo $period; ?> Month<?php echo $period > 1 ? 's' : ''; ?>)</span>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" id="periodDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-filter me-1"></i>Filter Period
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="periodDropdown">
                        <li><a class="dropdown-item <?php echo $period == 1 ? 'active' : ''; ?>" href="reports.php?period=1">1 Month</a></li>
                        <li><a class="dropdown-item <?php echo $period == 3 ? 'active' : ''; ?>" href="reports.php?period=3">3 Months</a></li>
                        <li><a class="dropdown-item <?php echo $period == 6 ? 'active' : ''; ?>" href="reports.php?period=6">6 Months</a></li>
                        <li><a class="dropdown-item <?php echo $period == 12 ? 'active' : ''; ?>" href="reports.php?period=12">12 Months</a></li>
                    </ul>
                </div>
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                Most Popular Activities
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php foreach($popular_activities as $activity): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($activity['ClassName']); ?>
                            <span class="badge bg-primary rounded-pill"><?php echo $activity['booking_count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                Busiest Times (Heatmap)
            </div>
            <div class="card-body">
                <style>
                    .heatmap-container {
                        overflow-x: auto;
                        padding-bottom: 10px;
                    }
                    .heatmap-grid {
                        display: grid;
                        grid-template-columns: 60px repeat(17, 1fr); /* 17 hours (6am-10pm) */
                        gap: 6px;
                        min-width: 800px;
                    }
                    .heatmap-cell {
                        position: relative;
                        aspect-ratio: 1; /* Make cells perfect squares */
                        border-radius: 6px;
                        background-color: #f8f9fa; /* Default empty color */
                        transition: transform 0.2s, box-shadow 0.2s;
                    }
                    .heatmap-cell.has-data:hover {
                        transform: scale(1.15);
                        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                        z-index: 2;
                        border: 1px solid rgba(255,255,255,0.5);
                    }
                    .heatmap-header-col {
                        text-align: center;
                        font-size: 0.7rem;
                        color: #FFF;
                        font-weight: 600;
                        display: flex;
                        align-items: flex-end; /* Align time labels to bottom */
                        justify-content: center;
                        background: none !important;
                        padding-bottom: 5px;
                    }
                    .heatmap-header-row {
                        display: flex;
                        align-items: center;
                        font-size: 0.75rem;
                        font-weight: 600;
                        color: #FFF;
                        background: none !important;
                    }
                    .tooltip-val {
                        display: none;
                        position: absolute;
                        bottom: 110%;
                        left: 50%;
                        transform: translateX(-50%);
                        background: rgba(33, 37, 41, 0.9);
                        color: white;
                        padding: 5px 10px;
                        border-radius: 4px;
                        font-size: 0.75rem;
                        white-space: nowrap;
                        z-index: 10;
                        pointer-events: none;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                    }
                    .heatmap-cell:hover .tooltip-val {
                        display: block;
                    }
                    /* Legend Gradient */
                    .heatmap-legend-bar {
                        width: 150px; 
                        height: 8px; 
                        background: linear-gradient(to right, #e0f2f1, #00bcd4, #673ab7, #f44336); 
                        border-radius: 4px;
                    }
                </style>
                <div class="heatmap-container">
                    <div class="heatmap-grid" id="heatmapGrid">
                        <!-- Generated by JS -->
                    </div>
                </div>
                <div class="mt-4 d-flex justify-content-center align-items-center">
                    <small class="text-muted me-3" style="font-size: 0.8rem;">Low Activity</small>
                    <div class="heatmap-legend-bar"></div>
                    <small class="text-muted ms-3" style="font-size: 0.8rem;">High Activity</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                Active Membership Distribution
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="membershipChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                Most Popular Activities
            </div>
            <div class="card-body">
                <div style="height: 300px;">
                    <canvas id="popActivityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- Heatmap Logic (Temperature Gradient) ---
    const heatmapData = <?php echo $heatmap_json; ?>;
    const maxVal = <?php echo $max_heatmap_val; ?>;
    const grid = document.getElementById('heatmapGrid');
    const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    
    // Helper: Interpolate Colors
    function getColor(intensity) {
        // Gradient Stops: 
        // 0.0: #e0f2f1 (Very Light Teal)
        // 0.3: #00bcd4 (Cyan)
        // 0.6: #673ab7 (Deep Purple)
        // 1.0: #f44336 (Red)
        
        if (intensity === 0) return '#f8f9fa'; // Empty
        
        if (intensity <= 0.3) {
            // Mix Light Teal to Cyan
            return '#00bcd4'; 
        } else if (intensity <= 0.6) {
            // Cyan to Purple
            return '#673ab7';
        } else {
            // Purple to Red
            return '#f44336';
        }
        
        // Simple distinct steps are often cleaner than full interpolation for this data volume
        if(intensity < 0.1) return '#e0f2f1';
        if(intensity < 0.3) return '#b2ebf2';
        if(intensity < 0.5) return '#00bcd4';
        if(intensity < 0.7) return '#673ab7';
        if(intensity < 0.9) return '#d32f2f';
        return '#f44336';
    }

    function getInterpolatedColor(value) {
        // 0 = #e0f2f1 (224, 242, 241)
        // 0.5 = #673ab7 (103, 58, 183)
        // 1 = #f44336 (244, 67, 54)
        
        if(value <= 0.05) return '#f1f3f5'; // Almost empty

        let r, g, b;
        if (value < 0.5) {
            // Interpolate Blue (#00bcd4) to Purple (#673ab7)
            // Normalized 0-0.5 to 0-1
            let t = value * 2; 
            r = Math.round(0 + (103 - 0) * t);
            g = Math.round(188 + (58 - 188) * t);
            b = Math.round(212 + (183 - 212) * t);
        } else {
            // Interpolate Purple (#673ab7) to Red (#f44336)
            // Normalized 0.5-1 to 0-1
            let t = (value - 0.5) * 2;
            r = Math.round(103 + (244 - 103) * t);
            g = Math.round(58 + (67 - 58) * t);
            b = Math.round(183 + (54 - 183) * t);
        }
        return `rgb(${r}, ${g}, ${b})`;
    }

    // 1. Header Row (Hours)
    const corner = document.createElement('div');
    grid.appendChild(corner); 
    
    for (let h = 6; h <= 22; h++) {
        const header = document.createElement('div');
        header.className = 'heatmap-cell heatmap-header-col';
        const ampm = h >= 12 ? 'pm' : 'am';
        const h12 = h % 12 || 12;
        header.textContent = h12 + ampm;
        grid.appendChild(header);
    }

    // 2. Data Rows
    for (let d = 1; d <= 7; d++) {
        const rowLabel = document.createElement('div');
        rowLabel.className = 'heatmap-cell heatmap-header-row';
        rowLabel.textContent = days[d-1]; 
        grid.appendChild(rowLabel);

        for (let h = 6; h <= 22; h++) {
            const cell = document.createElement('div');
            cell.className = 'heatmap-cell';
            
            const count = heatmapData[d] && heatmapData[d][h] ? heatmapData[d][h] : 0;
            const intensity = maxVal > 0 ? count / maxVal : 0;
            
            if (count > 0) {
                cell.classList.add('has-data');
                cell.style.backgroundColor = getInterpolatedColor(intensity);
                cell.innerHTML = `<div class="tooltip-val">${count} booking${count > 1 ? 's' : ''}<br><span style="color:#ccc;font-size:0.65rem">${h}:00 - ${h+1}:00</span></div>`;
            }

            grid.appendChild(cell);
        }
    }

    // --- Chart.js Global Settings ---
    Chart.defaults.font.family = 'Inter', 'Helvetica Neue', 'Helvetica, Arial, sans-serif';
    Chart.defaults.color = '#6C757D'; 
    Chart.defaults.borderColor = '#DEE2E6'; 

    const baseChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: '#6C757D', usePointStyle: true, boxWidth: 8 }
            },
            tooltip: {
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleColor: '#fff',
                bodyColor: '#fff',
                borderColor: '#6C757D',
                borderWidth: 1,
                cornerRadius: 4,
                padding: 10,
                displayColors: true,
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#6C757D' }
            },
            y: {
                beginAtZero: true,
                grid: { borderDash: [5, 5], color: '#E9ECEF' },
                ticks: { color: '#6C757D' }
            }
        }
    };

    // Revenue Chart (Stacked Bar)
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $revenue_labels; ?>,
            datasets: [
                {
                    label: 'Memberships',
                    data: <?php echo $membership_revenue_values; ?>,
                    backgroundColor: '#0d6efd',
                    hoverBackgroundColor: '#0b5ed7',
                    barPercentage: 0.5,
                    categoryPercentage: 0.6,
                    borderRadius: 4,
                },
                {
                    label: 'Classes',
                    data: <?php echo $class_revenue_values; ?>,
                    backgroundColor: '#198754',
                    hoverBackgroundColor: '#157347',
                    barPercentage: 0.5,
                    categoryPercentage: 0.6,
                    borderRadius: 4,
                }
            ]
        },
        options: {
            ...baseChartOptions,
            scales: {
                x: { ...baseChartOptions.scales.x, stacked: false },
                y: { ...baseChartOptions.scales.y, stacked: false }
            }
        }
    });

    // Membership Chart (Doughnut)
    const membershipCtx = document.getElementById('membershipChart').getContext('2d');
    new Chart(membershipCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $membership_labels; ?>,
            datasets: [{
                data: <?php echo $membership_values; ?>,
                backgroundColor: ['#0d6efd', '#6610f2', '#fd7e14', '#20c997'],
                borderColor: '#fff',
                borderWidth: 2,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%', // Thinner ring
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true } }
            }
        }
    });

    // Popular Activities Chart (Horizontal Bar for readability)
    const popActivityCtx = document.getElementById('popActivityChart').getContext('2d');
    new Chart(popActivityCtx, {
        type: 'bar',
        indexAxis: 'y', // Horizontal bars are better for names
        data: {
            labels: <?php echo $pop_activity_labels; ?>,
            datasets: [{
                label: 'Bookings',
                data: <?php echo $pop_activity_values; ?>,
                backgroundColor: '#0dcaf0',
                hoverBackgroundColor: '#0aa2c0',
                borderRadius: 4,
                barPercentage: 0.5,
                categoryPercentage: 0.6,
            }]
        },
        options: baseChartOptions
    });
});
</script>

<?php include 'includes/admin_footer.php'; ?>
