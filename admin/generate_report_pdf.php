<?php
// This file is included from reports.php
// It has access to the $revenue_data, $popular_activities, etc. variables

// Simple PDF generation - HTML version that can be printed as PDF
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>KP Fitness - Business Report</title>
    <style>
        :root {
            --primary-color: #ff6b00;
            --secondary-color: #2c3e50;
            --light-bg: #f8f9fa;
            --border-color: #dee2e6;
        }
        body { 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            margin: 0; 
            padding: 40px; 
            color: #333;
            background: #555; /* Dark background for the window, paper white for the report */
        }
        .report-page {
            background: white;
            max-width: 210mm; /* A4 width */
            margin: 0 auto;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            min-height: 297mm; /* A4 height */
        }
        
        /* Header */
        .report-header {
            border-bottom: 3px solid var(--primary-color);
            padding-bottom: 20px;
            margin-bottom: 40px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .report-logo {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .report-meta {
            text-align: right;
            font-size: 14px;
            color: #666;
        }
        
        h1 { margin: 0; font-size: 28px; color: var(--secondary-color); }
        h2 { 
            color: var(--secondary-color); 
            border-left: 5px solid var(--primary-color); 
            padding-left: 10px; 
            margin-top: 40px; 
            margin-bottom: 20px;
            font-size: 18px;
        }

        /* Tables */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px; 
            font-size: 14px;
        }
        th { 
            background-color: var(--secondary-color); 
            color: white; 
            text-align: left; 
            padding: 10px; 
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        td { 
            border-bottom: 1px solid var(--border-color); 
            padding: 10px; 
        }
        tr:nth-child(even) { background-color: var(--light-bg); }
        
        /* Summary Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }
        .kpi-card {
            background: var(--light-bg);
            padding: 15px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        .kpi-value {
            display: block;
            font-size: 20px;
            font-weight: bold;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        .kpi-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }

        /* Controls */
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            font-size: 14px;
        }
        .btn-print { background: var(--primary-color); color: white; }
        .btn-close { background: white; color: #333; }
        .btn:hover { opacity: 0.9; }

        @media print {
            body { background: white; padding: 0; }
            .report-page { box-shadow: none; margin: 0; width: 100%; max-width: none; }
            .controls { display: none; }
            .no-break { page-break-inside: avoid; }
        }
    </style>
</head>
<body>

    <div class="controls">
        <button class="btn btn-print" onclick="window.print()">Save as PDF</button>
        <button class="btn btn-close" onclick="window.close()">Close</button>
    </div>

    <div class="report-page">
        <div class="report-header">
            <div>
                <div class="report-logo">KP Fitness</div>
                <div style="margin-top: 5px; color: #666;">Performance Report</div>
            </div>
            <div class="report-meta">
                <strong>Period:</strong> Last <?php echo $period; ?> Months<br>
                <strong>Generated:</strong> <?php echo date('d M Y, h:i A'); ?><br>
                <strong>By:</strong> <?php echo $_SESSION['FullName'] ?? 'Admin'; ?>
            </div>
        </div>

        <!-- KPI Summary -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <span class="kpi-value"><?php echo format_currency($currentRevenue ?? 0); ?></span>
                <span class="kpi-label">Total Revenue</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-value"><?php echo $currentNewMembers ?? 0; ?></span>
                <span class="kpi-label">New Members</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-value"><?php echo $occupancyRate ?? 0; ?>%</span>
                <span class="kpi-label">Occupancy Rate</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-value"><?php echo $churnCount ?? 0; ?></span>
                <span class="kpi-label">Churned Users</span>
            </div>
        </div>

        <!-- Revenue Table -->
        <div class="no-break">
            <h2>Monthly Revenue Breakdown</h2>
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%">Month</th>
                        <th style="text-align: right;">Membership Revenue</th>
                        <th style="text-align: right;">Class Revenue</th>
                        <th style="text-align: right;">Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalMem = 0; $totalClass = 0;
                    foreach ($revenue_data as $row): 
                        $total = $row['membership_revenue'] + $row['class_revenue'];
                        $totalMem += $row['membership_revenue'];
                        $totalClass += $row['class_revenue'];
                    ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                            <td style="text-align: right;"><?php echo format_currency($row['membership_revenue']); ?></td>
                            <td style="text-align: right;"><?php echo format_currency($row['class_revenue']); ?></td>
                            <td style="text-align: right;"><strong><?php echo format_currency($total); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background-color: #f0f0f0; font-weight: bold;">
                        <td>TOTAL</td>
                        <td style="text-align: right;"><?php echo format_currency($totalMem); ?></td>
                        <td style="text-align: right;"><?php echo format_currency($totalClass); ?></td>
                        <td style="text-align: right;"><?php echo format_currency($totalMem + $totalClass); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Popular Activities -->
        <div class="no-break">
            <h2>Top Performing Classes</h2>
            <table>
                <thead>
                    <tr>
                        <th>Class Name</th>
                        <th style="text-align: right;">Total Bookings</th>
                        <th style="width: 40%;">Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $maxBookings = !empty($popular_activities) ? max(array_column($popular_activities, 'booking_count')) : 1;
                    foreach ($popular_activities as $activity): 
                        $percent = ($activity['booking_count'] / $maxBookings) * 100;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['ClassName']); ?></td>
                            <td style="text-align: right; font-weight: bold;"><?php echo $activity['booking_count']; ?></td>
                            <td>
                                <div style="background: #e9ecef; height: 8px; border-radius: 4px; width: 100%;">
                                    <div style="background: var(--primary-color); width: <?php echo $percent; ?>%; height: 100%; border-radius: 4px;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Membership Stats -->
        <div class="no-break">
            <h2>Membership Distribution</h2>
            <table>
                <thead>
                    <tr>
                        <th>Plan Name</th>
                        <th style="text-align: right;">Active Members</th>
                        <th style="text-align: right;">Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $totalMembers = array_sum(array_column($membership_dist, 'member_count'));
                    foreach ($membership_dist as $mem): 
                        $share = $totalMembers > 0 ? ($mem['member_count'] / $totalMembers) * 100 : 0;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mem['PlanName']); ?></td>
                            <td style="text-align: right;"><?php echo $mem['member_count']; ?></td>
                            <td style="text-align: right;"><?php echo number_format($share, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 50px; text-align: center; color: #999; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px;">
            End of Report &bull; KP Fitness Administration
        </div>
    </div>

</body>
</html>
<?php exit; ?>