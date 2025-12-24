<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');
require_once('../includes/header.php');

$msg = '';
$error = '';
$visit_id = $_GET['visit_id'] ?? null;
$preselected_patient_id = $_GET['patient_id'] ?? '';
$visit_data = [];

// Fetch Visit Data if Editing
if ($visit_id) {
    $stmt = $pdo->prepare("SELECT * FROM op_visits WHERE id = ?");
    $stmt->execute([$visit_id]);
    $visit_data = $stmt->fetch();
    
    if ($visit_data) {
        $preselected_patient_id = $visit_data['patient_id'];
        $dynamic_data = json_decode($visit_data['additional_data'], true) ?? [];
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_visit'])) {
    $patient_id = $_POST['patient_id'];
    $doctor_id = $_POST['doctor_id'];
    $weight = trim($_POST['weight']);
    $bp = trim($_POST['bp']);
    $symptoms = trim($_POST['symptoms']);
    $notes = trim($_POST['notes']);

    // Handle Dynamic Fields
    $dynamic_data_post = [];
    if (isset($_POST['custom_fields'])) {
        foreach ($_POST['custom_fields'] as $key => $value) {
            $dynamic_data_post[$key] = $value;
        }
    }
    $json_data = json_encode($dynamic_data_post);

    try {
        if ($visit_id) {
            // Update
            $stmt = $pdo->prepare("UPDATE op_visits SET patient_id=?, doctor_id=?, weight=?, bp=?, symptoms=?, notes=?, additional_data=? WHERE id=?");
            if (!$stmt->execute([$patient_id, $doctor_id, $weight, $bp, $symptoms, $notes, $json_data, $visit_id])) {
                 throw new Exception(implode(" ", $stmt->errorInfo()));
            }
            $msg = "OP Visit updated successfully!";
             // Refresh data
            $visit_data = array_merge($visit_data, $_POST);
             // Decode json for view
            $dynamic_data = $dynamic_data_post;

        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO op_visits (patient_id, doctor_id, weight, bp, symptoms, notes, additional_data) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt->execute([$patient_id, $doctor_id, $weight, $bp, $symptoms, $notes, $json_data])) {
                throw new Exception(implode(" ", $stmt->errorInfo()));
            }
            $msg = "OP Visit recorded successfully!";
        }
    } catch (Exception $e) {
        $error = "Error saving visit: " . $e->getMessage();
    }
}

// Fetch Doctors
$doctors = $pdo->query("SELECT d.id, u.full_name, d.specialization FROM doctors d JOIN users u ON d.user_id = u.id")->fetchAll();

// Fetch Patients (for dropdown)
$patients = $pdo->query("SELECT id, name, contact_no FROM patients ORDER BY name ASC")->fetchAll();

// Fetch Dynamic Fields Settings
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'op_custom_fields'");
$stmt->execute();
$custom_fields_json = $stmt->fetchColumn();
$custom_fields = $custom_fields_json ? json_decode($custom_fields_json, true) : [];

?>

<!-- Sidebar -->
<?php include('../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <?php include('../includes/navbar.php'); ?>

    <div class="container-fluid py-4">
        <h4 class="mb-4"><?php echo $visit_id ? 'Edit' : 'New'; ?> OP Entry</h4>

        <?php if($msg): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card card-custom p-4">
            <form method="POST">
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Select Patient</label>
                        <select name="patient_id" id="patientSelect" class="form-select" required>
                            <option value="">-- Choose Patient --</option>
                            <?php foreach($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($preselected_patient_id == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['contact_no']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Can't find patient? <a href="patients.php">Add New Patient</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Select Doctor</label>
                        <select name="doctor_id" class="form-select" required>
                            <option value="">-- Choose Doctor --</option>
                            <?php foreach($doctors as $d): ?>
                                <option value="<?php echo $d['id']; ?>" <?php echo (isset($visit_data['doctor_id']) && $visit_data['doctor_id'] == $d['id']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($d['full_name']); ?> (<?php echo htmlspecialchars($d['specialization']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <h5 class="mb-3 text-muted border-bottom pb-2">Vitals & Symptoms</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="text" name="weight" class="form-control" placeholder="e.g. 70.5" value="<?php echo htmlspecialchars($visit_data['weight'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Blood Pressure</label>
                        <input type="text" name="bp" class="form-control" placeholder="e.g. 120/80" value="<?php echo htmlspecialchars($visit_data['bp'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Symptoms</label>
                    <textarea name="symptoms" class="form-control" rows="3" placeholder="Describe symptoms..."><?php echo htmlspecialchars($visit_data['symptoms'] ?? ''); ?></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Diagnosis / Notes</label>
                    <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($visit_data['notes'] ?? ''); ?></textarea>
                </div>

                <!-- Dynamic Fields Section -->
                <?php if (!empty($custom_fields)): ?>
                    <h5 class="mb-3 text-muted border-bottom pb-2 mt-4">Additional Details</h5>
                    <div class="row">
                    <?php foreach ($custom_fields as $field): ?>
                        <?php $field_val = $dynamic_data[$field['name']] ?? ''; ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo htmlspecialchars($field['label']); ?></label>
                            <?php if ($field['type'] == 'text'): ?>
                                <input type="text" name="custom_fields[<?php echo htmlspecialchars($field['name']); ?>]" class="form-control" value="<?php echo htmlspecialchars($field_val); ?>">
                            <?php elseif ($field['type'] == 'number'): ?>
                                <input type="number" name="custom_fields[<?php echo htmlspecialchars($field['name']); ?>]" class="form-control" value="<?php echo htmlspecialchars($field_val); ?>">
                            <?php elseif ($field['type'] == 'textarea'): ?>
                                <textarea name="custom_fields[<?php echo htmlspecialchars($field['name']); ?>]" class="form-control"><?php echo htmlspecialchars($field_val); ?></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4">
                    <input type="hidden" name="save_visit" value="1">
                    <button type="submit" class="btn btn-primary btn-lg px-5"><?php echo $visit_id ? 'Update' : 'Save'; ?> Record</button>
                    <a href="dashboard.php" class="btn btn-light btn-lg px-4">Cancel</a>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>

<script>
    $(document).ready(function() {
        $('#patientSelect').select2({
            theme: 'bootstrap-5', // Use bootstrap-5 theme if available, otherwise fallback
            placeholder: '-- Choose Patient --',
            allowClear: true,
            width: '100%'
        });
    });
</script>
