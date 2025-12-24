<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');
require_once('../includes/header.php');

// Fetch Stats
// 1. Total Patients
$stmt = $pdo->query("SELECT COUNT(*) FROM patients");
$total_patients = $stmt->fetchColumn();

// 2. Today's OP
$stmt = $pdo->query("SELECT COUNT(*) FROM op_visits WHERE DATE(visit_date) = CURDATE()");
$todays_op = $stmt->fetchColumn();

// 3. Active Doctors
$stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$active_doctors = $stmt->fetchColumn();

?>
    
    <!-- Sidebar -->
    <?php include('../includes/sidebar.php'); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <?php include('../includes/navbar.php'); ?>

        <div class="container-fluid py-4">
            <h4 class="mb-4">Dashboard</h4>
            
            <div class="row g-4">
                <!-- Card 1: Today's OP -->
                <div class="col-md-4">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-card-title">Today's OP</div>
                                <div class="stat-card-value text-primary"><?php echo $todays_op; ?></div>
                            </div>
                            <div class="icon-box bg-primary bg-opacity-10 p-3 rounded-circle text-primary">
                                <i class="bi bi-calendar-check fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Total Patients -->
                <div class="col-md-4">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-card-title">Total Patients</div>
                                <div class="stat-card-value text-success"><?php echo $total_patients; ?></div>
                            </div>
                            <div class="icon-box bg-success bg-opacity-10 p-3 rounded-circle text-success">
                                <i class="bi bi-people fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Doctors -->
                <div class="col-md-4">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-card-title">Active Doctors</div>
                                <div class="stat-card-value text-info"><?php echo $active_doctors; ?></div>
                            </div>
                            <div class="icon-box bg-info bg-opacity-10 p-3 rounded-circle text-info">
                                <i class="bi bi-person-badge fs-4"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity: Today's OP Visits -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card card-custom p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Today's OP Visits</h5>
                            <a href="op_entry.php" class="btn btn-sm btn-primary">+ New Entry</a>
                        </div>
                        
                        <?php
                        // Fetch Today's Visits
                        $sql = "SELECT v.*, p.name as patient_name, d.specialization, u_doc.full_name as doctor_name 
                                FROM op_visits v 
                                JOIN patients p ON v.patient_id = p.id 
                                LEFT JOIN doctors d ON v.doctor_id = d.id 
                                LEFT JOIN users u_doc ON d.user_id = u_doc.id 
                                WHERE DATE(v.visit_date) = CURDATE() 
                                ORDER BY v.visit_date DESC";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute();
                        $visits = $stmt->fetchAll();
                        ?>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Time</th>
                                        <th>Patient Name</th>
                                        <th>Doctor</th>
                                        <th>Diagnosis/Notes</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($visits as $v): ?>
                                    <tr>
                                        <td><?php echo date('h:i A', strtotime($v['visit_date'])); ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($v['patient_name']); ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($v['doctor_name']); ?></td>
                                        <td><div class="text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($v['notes'] ?: $v['symptoms']); ?></div></td>
                                        <td><span class="badge bg-success">Completed</span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if(empty($visits)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-4">No visits recorded today.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

<?php require_once('../includes/footer.php'); ?>
