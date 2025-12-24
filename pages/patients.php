<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');

// Handle Export (Must be before header)
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="patients_list.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Age', 'Gender', 'Contact', 'Address', 'Blood Group']);
    
    $search = $_GET['search'] ?? '';
    $sql = "SELECT id, name, age, gender, contact_no, address, blood_group FROM patients WHERE name LIKE :search OR contact_no LIKE :search";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['search' => "%$search%"]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

require_once('../includes/header.php');

$msg = '';
$error = '';

// Handle Delete Patient
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_patient'])) {
    $patient_id = $_POST['delete_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete Visits first (if not cascading)
        $stmt = $pdo->prepare("DELETE FROM op_visits WHERE patient_id = ?");
        $stmt->execute([$patient_id]);

        // Delete Patient
        $stmt = $pdo->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        
        $pdo->commit();
        $msg = "Patient and their history deleted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting patient: " . $e->getMessage();
    }
}

// Handle Add Patient
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_patient'])) {
    $name = trim($_POST['name']);
    $age = (int)$_POST['age'];
    $gender = $_POST['gender'];
    $contact_no = trim($_POST['contact_no']);
    $address = trim($_POST['address']);
    $blood_group = $_POST['blood_group'];

    try {
        $stmt = $pdo->prepare("INSERT INTO patients (name, age, gender, contact_no, address, blood_group) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $age, $gender, $contact_no, $address, $blood_group]);
        $msg = "Patient added successfully!";
    } catch (Exception $e) {
        $error = "Error adding patient: " . $e->getMessage();
    }
}

// Handle AJAX Duplicate Check
if (isset($_GET['check_phone'])) {
    $phone = trim($_GET['check_phone']);
    $stmt = $pdo->prepare("SELECT id, name FROM patients WHERE contact_no = ?");
    $stmt->execute([$phone]);
    $existing = $stmt->fetch();
    echo json_encode(['exists' => !!$existing, 'patient' => $existing]);
    exit;
}

// Search & Pagination Logic
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$search = $_GET['search'] ?? '';

// Fetch Count
$count_sql = "SELECT COUNT(*) FROM patients WHERE name LIKE :search OR contact_no LIKE :search";
$stmt = $pdo->prepare($count_sql);
$stmt->execute(['search' => "%$search%"]);
$total_rows = $stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

// Fetch Patients
$sql = "SELECT * FROM patients WHERE name LIKE :search OR contact_no LIKE :search ORDER BY id DESC LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', "%$search%");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$patients = $stmt->fetchAll();

?>

<!-- Sidebar -->
<?php include('../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <?php include('../includes/navbar.php'); ?>

    <div class="container-fluid py-4">
        <div class="row align-items-center mb-4">
            <div class="col-md-4">
                <h4>Patient Records</h4>
            </div>
            <div class="col-md-8 d-flex gap-2 justify-content-end">
                <form class="d-flex" method="GET">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search Name or Phone..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    <?php if($search): ?>
                        <a href="patients.php" class="btn btn-outline-danger" title="Clear Search"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </form>
                <a href="?export=1&search=<?php echo urlencode($search); ?>" class="btn btn-success text-nowrap">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </a>
                <button class="btn btn-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                    <i class="bi bi-person-plus"></i> Add New
                </button>
            </div>
        </div>

        <?php if($msg): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card card-custom p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Age/Gender</th>
                            <th>Contact</th>
                            <th>Blood Group</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($patients as $p): ?>
                        <tr>
                            <td>#<?php echo $p['id']; ?></td>
                            <td><?php echo htmlspecialchars($p['name']); ?></td>
                            <td><?php echo $p['age'] . ' / ' . $p['gender']; ?></td>
                            <td><?php echo htmlspecialchars($p['contact_no']); ?></td>
                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($p['blood_group']); ?></span></td>
                            <td>
                                <a href="op_entry.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-primary" title="New Visit">
                                    <i class="bi bi-plus-lg"></i>
                                </a>
                                <a href="patient_history.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-info text-white" title="View History">
                                    <i class="bi bi-clock-history"></i> History
                                </a>
                                <button class="btn btn-sm btn-light text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $p['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal<?php echo $p['id']; ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Delete Patient</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($p['name']); ?></strong>?</p>
                                                <p class="text-danger small">WARNING: This will permanently delete ALL visit history for this patient.</p>
                                            </div>
                                            <div class="modal-footer">
                                                <form method="POST">
                                                    <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="delete_patient" class="btn btn-danger">Delete Patient</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-3">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Patient Modal (Same as before) -->
<div class="modal fade" id="addPatientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Patient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Age</label>
                            <input type="number" name="age" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Contact No</label>
                        <input type="text" name="contact_no" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Blood Group</label>
                        <select name="blood_group" class="form-select">
                            <option value="Unknown">Unknown</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="add_patient" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>

<script>
document.querySelector('input[name="contact_no"]').addEventListener('input', function() {
    const phone = this.value;
    const feedback = document.getElementById('phone-feedback');
    
    // Create feedback element if missing
    if (!feedback) {
        const div = document.createElement('div');
        div.id = 'phone-feedback';
        div.className = 'form-text mt-1';
        this.parentNode.appendChild(div);
    }
    
    if (phone.length > 5) {
        fetch('patients.php?check_phone=' + encodeURIComponent(phone))
            .then(response => response.json())
            .then(data => {
                const fb = document.getElementById('phone-feedback');
                if (data.exists) {
                    fb.innerHTML = `<span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Patient already exists: <a href="patient_history.php?patient_id=${data.patient.id}" target="_blank">${data.patient.name}</a></span>`;
                } else {
                    fb.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Number available</span>';
                }
            });
    }
});
</script>
