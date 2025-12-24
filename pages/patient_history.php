<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');

if (!isset($_GET['patient_id'])) {
    header("Location: patients.php");
    exit();
}

$patient_id = $_GET['patient_id'];

// Handle Export (Must be before header)
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="patient_history_' . $patient_id . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Doctor', 'Specialization', 'Weight', 'BP', 'Diagnosis']);
    
    $sql = "SELECT v.*, u.full_name as doctor_name, d.specialization 
            FROM op_visits v
            LEFT JOIN doctors d ON v.doctor_id = d.id
            LEFT JOIN users u ON d.user_id = u.id
            WHERE v.patient_id = ? 
            ORDER BY v.visit_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$patient_id]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['visit_date'],
            $row['doctor_name'],
            $row['specialization'],
            $row['weight'],
            $row['bp'],
            $row['notes'] ?: $row['symptoms']
        ]);
    }
    fclose($output);
    exit();
}

require_once('../includes/header.php');

// Fetch Patient Details
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$patient_id]);
$patient = $stmt->fetch();

if (!$patient) {
    die("Patient not found");
}

// Fetch Visit History
$stmt = $pdo->prepare("SELECT v.*, u.full_name as doctor_name 
                       FROM op_visits v 
                       LEFT JOIN doctors d ON v.doctor_id = d.id 
                       LEFT JOIN users u ON d.user_id = u.id 
                       WHERE v.patient_id = ? 
                       ORDER BY v.visit_date DESC");
$stmt->execute([$patient_id]);
$visits = $stmt->fetchAll();

?>

<!-- Sidebar -->
<?php include('../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <?php include('../includes/navbar.php'); ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
             <a href="patients.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Patients</a>
             <a href="?patient_id=<?php echo $patient_id; ?>&export=1" class="btn btn-success">
                <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
             </a>
        </div>

        <div class="card card-custom p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($patient['name']); ?></h4>
                    <p class="text-muted mb-0">
                        <?php echo $patient['age'] . ' years / ' . $patient['gender']; ?> | 
                        Blood Group: <span class="badge bg-danger"><?php echo htmlspecialchars($patient['blood_group']); ?></span>
                    </p>
                    <small class="text-muted"><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($patient['contact_no']); ?></small>
                </div>
                <div>
                    <a href="op_entry.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> New Visit
                    </a>
                </div>
            </div>
        </div>

        <h5 class="mb-3">Visit History</h5>
        
        <div class="card card-custom p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>Doctor</th>
                            <th>Vitals</th>
                            <th>Diagnosis / Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($visits as $v): ?>
                        <tr>
                            <td>
                                <span class="fw-bold"><?php echo date('d M Y', strtotime($v['visit_date'])); ?></span><br>
                                <small class="text-muted"><?php echo date('h:i A', strtotime($v['visit_date'])); ?></small>
                            </td>
                            <td>Dr. <?php echo htmlspecialchars($v['doctor_name']); ?></td>
                            <td>
                                <?php if($v['weight']): ?> <span class="badge bg-light text-dark border">Wt: <?php echo htmlspecialchars($v['weight']); ?></span> <?php endif; ?>
                                <?php if($v['bp']): ?> <span class="badge bg-light text-dark border">BP: <?php echo htmlspecialchars($v['bp']); ?></span> <?php endif; ?>
                            </td>
                            <td>
                                <div style="max-width: 300px;">
                                    <?php if($v['symptoms']): ?>
                                        <strong>Symptoms:</strong> <?php echo htmlspecialchars($v['symptoms']); ?><br>
                                    <?php endif; ?>
                                    <?php if($v['notes']): ?>
                                        <strong>Notes:</strong> <?php echo htmlspecialchars($v['notes']); ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <a href="op_entry.php?visit_id=<?php echo $v['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="print_visit.php?id=<?php echo $v['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Print">
                                    <i class="bi bi-printer"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($visits)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No validation history found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php require_once('../includes/footer.php'); ?>
