<?php
require_once('../includes/auth_session.php');
require_once('../config/db.php');

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$id = $_GET['id'];

// Fetch Visit Data
$sql = "SELECT v.*, p.name, p.age, p.gender, p.contact_no, p.address, u.full_name as doctor_name, d.specialization
        FROM op_visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN doctors d ON v.doctor_id = d.id
        LEFT JOIN users u ON d.user_id = u.id
        WHERE v.id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id]);
$visit = $stmt->fetch();

if (!$visit) {
    die("Record not found.");
}

// Decode Dynamic Data
$custom_fields = json_decode($visit['additional_data'], true) ?? [];

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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print OP Record - #<?php echo $id; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; font-family: 'Times New Roman', Times, serif; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #333; padding-bottom: 20px; }
        .meta-table td { padding: 5px 15px 5px 0; }
        .section-title { border-bottom: 1px solid #ddd; margin-top: 20px; margin-bottom: 10px; font-weight: bold; text-transform: uppercase; font-size: 0.9rem; }
        @media print {
            .no-print { display: none; }
            .invoice-box { border: none; }
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <div class="text-end mb-3 no-print">
        <button onclick="window.print()" class="btn btn-primary">Print Record</button>
        <button onclick="window.close()" class="btn btn-secondary">Close</button>
    </div>

    <div class="invoice-box">
        <div class="header">
            <h2><?php echo htmlspecialchars($hosp['name']); ?></h2>
            <p class="mb-0"><?php echo htmlspecialchars($hosp['address']); ?></p>
            <p>Phone: <?php echo htmlspecialchars($hosp['phone']); ?></p>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <table class="meta-table">
                    <tr><td class="text-muted">Patient Name:</td> <td><strong><?php echo htmlspecialchars($visit['name']); ?></strong></td></tr>
                    <tr><td class="text-muted">Age / Gender:</td> <td><?php echo $visit['age'] . ' / ' . $visit['gender']; ?></td></tr>
                    <tr><td class="text-muted">Contact:</td> <td><?php echo htmlspecialchars($visit['contact_no']); ?></td></tr>
                </table>
            </div>
            <div class="col-6 text-end">
                <table class="meta-table float-end">
                    <tr><td class="text-muted">Date:</td> <td><?php echo date('d M Y, h:i A', strtotime($visit['visit_date'])); ?></td></tr>
                    <tr><td class="text-muted">OP ID:</td> <td><strong>#<?php echo $visit['id']; ?></strong></td></tr>
                    <tr><td class="text-muted">Doctor:</td> <td>Dr. <?php echo htmlspecialchars($visit['doctor_name']); ?></td></tr>
                </table>
            </div>
        </div>

        <div class="section-title">Vitals</div>
        <div class="row">
            <div class="col-4"><strong>Weight:</strong> <?php echo htmlspecialchars($visit['weight']); ?> kg</div>
            <div class="col-4"><strong>BP:</strong> <?php echo htmlspecialchars($visit['bp']); ?></div>
        </div>

        <div class="section-title">Clinical Notes</div>
        <p><strong>Symptoms:</strong> <?php echo nl2br(htmlspecialchars($visit['symptoms'])); ?></p>
        <p><strong>Diagnosis/Notes:</strong> <?php echo nl2br(htmlspecialchars($visit['notes'])); ?></p>

        <?php if(!empty($custom_fields)): ?>
            <div class="section-title">Additional Info</div>
            <ul>
            <?php foreach($custom_fields as $key => $val): ?>
                <li><strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong> <?php echo htmlspecialchars($val); ?></li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="mt-5 pt-5 text-end">
            <p>_________________________</p>
            <p>Doctor's Signature</p>
        </div>
    </div>
</div>

<script>
    // Auto print on load
    window.onload = function() { window.print(); }
</script>
</body>
</html>
