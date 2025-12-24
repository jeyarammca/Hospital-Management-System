<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');
require_once('../includes/header.php');

// Access Control
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$msg = '';
$error = '';

// Fetch Current Settings
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'op_custom_fields'");
$stmt->execute();
$current_json = $stmt->fetchColumn();
$custom_fields = $current_json ? json_decode($current_json, true) : [];

// Fetch Hospital Info
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'hospital_info'");
$stmt->execute();
$hospital_json = $stmt->fetchColumn();
$hospital_info = $hospital_json ? json_decode($hospital_json, true) : [
    'name' => 'Hospital Management System',
    'address' => '123 Health Ave, Medical City',
    'phone' => '+1 234 567 890'
];

// Handle Save Hospital Settings
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $info = [
        'name' => trim($_POST['name']),
        'address' => trim($_POST['address']),
        'phone' => trim($_POST['phone'])
    ];
    $json = json_encode($info);
    
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('hospital_info', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$json, $json]);
    $hospital_info = $info; // Update local view
    $msg = "Hospital details updated successfully!";
}

// Handle Add Field
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_field'])) {
    $label = trim($_POST['label']);
    $type = $_POST['type'];
    // Generate a safe variable name from label
    $name = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $label));

    // Check if duplicate
    $exists = false;
    foreach ($custom_fields as $f) {
        if ($f['name'] === $name) {
            $exists = true;
            break;
        }
    }

    if ($exists) {
        $error = "Field with this name already exists.";
    } else {
        $custom_fields[] = [
            'label' => $label,
            'name' => $name,
            'type' => $type
        ];
        
        // Save back to DB
        $new_json = json_encode($custom_fields);
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('op_custom_fields', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$new_json, $new_json]);
        $msg = "Field added successfully!";
    }
}

// Handle Delete Field
if (isset($_GET['delete_name'])) {
    $delete_name = $_GET['delete_name'];
    $new_fields = [];
    foreach ($custom_fields as $f) {
        if ($f['name'] !== $delete_name) {
            $new_fields[] = $f;
        }
    }
    $custom_fields = $new_fields;
    $new_json = json_encode($custom_fields);
    $stmt = $pdo->prepare("INFO: ON DUPLICATE KEY UPDATE setting_value = ?"); // Wrong SQL, fixing below
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'op_custom_fields'");
    $stmt->execute([$new_json]);
    header("Location: admin_settings.php?msg=deleted");
    exit();
}
if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') {
    $msg = "Field deleted successfully.";
}

?>

<!-- Sidebar -->
<?php include('../includes/sidebar.php'); ?>

<!-- Main Content -->
<div class="main-content">
    <?php include('../includes/navbar.php'); ?>

    <div class="container-fluid py-4">
        <h4>Admin Settings</h4>
        <p class="text-muted">Configure dynamic fields for Patient OP Entry form.</p>

        <?php if($msg): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-custom p-4">
                    <h5 class="mb-3">Hospital Details (for Reports)</h5>
                    <form method="POST" class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Hospital Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($hospital_info['name']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($hospital_info['address']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($hospital_info['phone']); ?>" required>
                        </div>
                        <div class="col-12 text-end">
                            <input type="hidden" name="save_settings" value="1">
                            <button type="submit" class="btn btn-success">Update Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Add New Field -->
            <div class="col-md-4">
                <div class="card card-custom p-4">
                    <h5 class="mb-3">Add New Field</h5>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Field Label</label>
                            <input type="text" name="label" class="form-control" placeholder="e.g. Temperature" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Field Type</label>
                            <select name="type" class="form-select">
                                <option value="text">Text Input</option>
                                <option value="number">Number Input</option>
                                <option value="textarea">Text Area</option>
                            </select>
                        </div>
                        <input type="hidden" name="add_field" value="1">
                        <button type="submit" class="btn btn-primary w-100">Add Field</button>
                    </form>
                </div>
            </div>

            <!-- Existing Fields List -->
            <div class="col-md-8">
                <div class="card card-custom p-4">
                    <h5 class="mb-3">Existing Dynamic Fields</h5>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Label</th>
                                    <th>Field Name</th>
                                    <th>Type</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($custom_fields as $field): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($field['label']); ?></td>
                                    <td><code><?php echo htmlspecialchars($field['name']); ?></code></td>
                                    <td><span class="badge bg-secondary"><?php echo htmlspecialchars($field['type']); ?></span></td>
                                    <td>
                                        <a href="?delete_name=<?php echo urlencode($field['name']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($custom_fields)): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No custom fields configured.</td></tr>
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
