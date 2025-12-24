<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');

// Handle Export (Must be before header)
if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="doctors_list.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Specialization', 'Contact', 'Schedule']);
    
    $stmt = $pdo->query("SELECT d.id, u.full_name, d.specialization, d.contact_no, d.schedule_details FROM doctors d JOIN users u ON d.user_id = u.id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

require_once('../includes/header.php');

$msg = '';
$error = '';

// Handle Edit Doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_doctor'])) {
    $doctor_id = $_POST['doctor_id'];
    $user_id = $_POST['user_id'];
    $name = trim($_POST['name']);
    $specialization = trim($_POST['specialization']);
    $contact = trim($_POST['contact']);
    $schedule = trim($_POST['schedule']);

    try {
        $pdo->beginTransaction();
        
        // Update User Name
        $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->execute([$name, $user_id]);

        // Update Doctor Details
        $stmt = $pdo->prepare("UPDATE doctors SET specialization = ?, contact_no = ?, schedule_details = ? WHERE id = ?");
        $stmt->execute([$specialization, $contact, $schedule, $doctor_id]);

        $pdo->commit();
        $msg = "Doctor updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating doctor: " . $e->getMessage();
    }
}

// Handle Delete Doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_doctor'])) {
    $doctor_id = $_POST['delete_id'];
    $user_id = $_POST['user_id'];
    
    try {
        $pdo->beginTransaction();
        // Delete Doctor Profile
        $stmt = $pdo->prepare("DELETE FROM doctors WHERE id = ?");
        $stmt->execute([$doctor_id]);
        
        // Delete User Account
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        $msg = "Doctor deleted successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting doctor: " . $e->getMessage();
    }
}

// Handle Add Doctor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_doctor'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $specialization = trim($_POST['specialization']);
    $contact = trim($_POST['contact']);
    $schedule = trim($_POST['schedule']);

    try {
        $pdo->beginTransaction();

        // 1. Create User
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password, role) VALUES (?, ?, ?, 'doctor')");
        $stmt->execute([$name, $username, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        // 2. Create Doctor Profile
        $stmt = $pdo->prepare("INSERT INTO doctors (user_id, specialization, contact_no, schedule_details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user_id, $specialization, $contact, $schedule]);

        $pdo->commit();
        $msg = "Doctor added successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error adding doctor: " . $e->getMessage();
    }
}

// Pagination Setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch Doctors with Pagination
$total_stmt = $pdo->query("SELECT COUNT(*) FROM doctors");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $pdo->prepare("SELECT d.id, d.user_id, u.full_name, d.specialization, d.contact_no, d.schedule_details FROM doctors d JOIN users u ON d.user_id = u.id ORDER BY d.id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$doctors = $stmt->fetchAll();


?>

<!-- Sidebar -->
<?php include('../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <?php include('../includes/navbar.php'); ?>

    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4>Doctor's Management</h4>
            <div>
                <a href="?export=1" class="btn btn-success me-2"><i class="bi bi-file-earmark-spreadsheet"></i> Export CSV</a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
                    <i class="bi bi-person-plus"></i> Add New Doctor
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
                            <th>Specialization</th>
                            <th>Contact</th>
                            <th>Schedule</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($doctors as $d): ?>
                        <tr>
                            <td>#<?php echo $d['id']; ?></td>
                            <td>Dr. <?php echo htmlspecialchars($d['full_name']); ?></td>
                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($d['specialization']); ?></span></td>
                            <td><?php echo htmlspecialchars($d['contact_no']); ?></td>
                            <td><?php echo htmlspecialchars($d['schedule_details']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-light text-primary edit-btn" 
                                    data-id="<?php echo $d['id']; ?>"
                                    data-user-id="<?php echo $d['user_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($d['full_name']); ?>"
                                    data-specialization="<?php echo htmlspecialchars($d['specialization']); ?>"
                                    data-contact="<?php echo htmlspecialchars($d['contact_no']); ?>"
                                    data-schedule="<?php echo htmlspecialchars($d['schedule_details']); ?>"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-sm btn-light text-danger delete-btn" 
                                    data-id="<?php echo $d['id']; ?>"
                                    data-user-id="<?php echo $d['user_id']; ?>"
                                    data-name="<?php echo htmlspecialchars($d['full_name']); ?>"
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?>">Previous</a>
                    </li>
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Specialization</label>
                        <input type="text" name="specialization" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Contact No</label>
                        <input type="text" name="contact" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Schedule</label>
                        <input type="text" name="schedule" class="form-control" placeholder="e.g. Mon-Fri 10am-2pm">
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="add_doctor" value="1">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Doctor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Doctor Modal -->
<div class="modal fade" id="editDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body text-start">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Specialization</label>
                        <input type="text" name="specialization" id="edit_specialization" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Contact No</label>
                        <input type="text" name="contact" id="edit_contact" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Schedule</label>
                        <input type="text" name="schedule" id="edit_schedule" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="doctor_id" id="edit_doctor_id">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="edit_doctor" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Doctor Modal -->
<div class="modal fade" id="deleteDoctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete_doctor_name"></strong>?</p>
                <p class="text-danger small">This will also delete the login account.</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="delete_id" id="delete_doctor_id">
                    <input type="hidden" name="user_id" id="delete_user_id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_doctor" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Button Click
    const editButtons = document.querySelectorAll('.edit-btn');
    const editModal = new bootstrap.Modal(document.getElementById('editDoctorModal'));
    
    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_doctor_id').value = this.dataset.id;
            document.getElementById('edit_user_id').value = this.dataset.userId;
            document.getElementById('edit_name').value = this.dataset.name;
            document.getElementById('edit_specialization').value = this.dataset.specialization;
            document.getElementById('edit_contact').value = this.dataset.contact;
            document.getElementById('edit_schedule').value = this.dataset.schedule;
            
            editModal.show();
        });
    });

    // Delete Button Click
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteDoctorModal'));

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('delete_doctor_id').value = this.dataset.id;
            document.getElementById('delete_user_id').value = this.dataset.userId;
            document.getElementById('delete_doctor_name').textContent = this.dataset.name;
            
            deleteModal.show();
        });
    });
});
</script>
