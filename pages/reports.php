<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');
require_once('../includes/header.php');

// Default limits (Today)
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch Records
$sql = "SELECT v.*, p.name, p.contact_no, d.specialization, u.full_name as doctor_name 
        FROM op_visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN doctors d ON v.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE DATE(v.visit_date) BETWEEN :start AND :end
        ORDER BY v.visit_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute(['start' => $start_date, 'end' => $end_date]);
$visits = $stmt->fetchAll();

// Stats
$total_visits = count($visits);
$total_patients = count(array_unique(array_column($visits, 'patient_id')));

// Fetch Hospital Info
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'hospital_info'");
$stmt->execute();
$hospital_json = $stmt->fetchColumn();
$hosp = $hospital_json ? json_decode($hospital_json, true) : [
    'name' => 'Hospital Management System',
    'address' => '123 Health Ave, Medical City',
    'phone' => '+1 234 567 890'
];

?>

<!-- Sidebar -->
<?php include('../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <?php include('../includes/navbar.php'); ?>

    <div class="container-fluid py-4">
        
        <!-- No Print Section -->
        <div class="d-print-none">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4>Reports</h4>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>

            <div class="card card-custom p-4 mb-4">
                <form class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Filter Records</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Content -->
        <div class="print-area">
            <div class="text-center mb-4 d-none d-print-block">
                <h2><?php echo htmlspecialchars($hosp['name']); ?></h2>
                <p class="mb-0"><?php echo htmlspecialchars($hosp['address']); ?></p>
                <p>Phone: <?php echo htmlspecialchars($hosp['phone']); ?></p>
                <hr>
                <h5>OP Report (<?php echo $start_date; ?> to <?php echo $end_date; ?>)</h5>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card bg-light p-3 text-center border">
                        <h6 class="text-muted">Total Visits</h6>
                        <h3 class="fw-bold text-primary"><?php echo $total_visits; ?></h3>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card bg-light p-3 text-center border">
                        <h6 class="text-muted">Unique Patients</h6>
                        <h3 class="fw-bold text-success"><?php echo $total_patients; ?></h3>
                    </div>
                </div>
            </div>

            <div class="card border-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Patient Name</th>
                                <th>Contact</th>
                                <th>Doctor</th>
                                <th>Diagnosis</th>
                                <th class="d-print-none">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($visits as $v): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($v['visit_date'])); ?></td>
                                <td><?php echo htmlspecialchars($v['name']); ?></td>
                                <td><?php echo htmlspecialchars($v['contact_no']); ?></td>
                                <td>Dr. <?php echo htmlspecialchars($v['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($v['notes'] ?: $v['symptoms']); ?></td>
                                <td class="d-print-none">
                                    <a href="op_entry.php?visit_id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary me-1">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="print_visit.php?id=<?php echo $v['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($visits)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No records found for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once('../includes/footer.php'); ?>
