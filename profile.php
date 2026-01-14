<?php


require_once 'config.php';
$pageTitle = 'โปรไฟล์ของฉัน';
include 'includes/header.php';

$user_id = $_SESSION['user_id'];

// อัพเดทข้อมูลส่วนตัว
if (isset($_POST['update_profile'])) {
    $full_name = clean($_POST['full_name']);
    
    $sql = "UPDATE users SET full_name = '$full_name' WHERE user_id = $user_id";
    $conn->query($sql);
    
    $_SESSION['full_name'] = $full_name;
    setAlert('success', 'อัพเดทข้อมูลเรียบร้อยแล้ว');
    redirect('profile.php');
}

// เปลี่ยนรหัสผ่าน
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // ตรวจสอบรหัสผ่านปัจจุบัน
    $result = $conn->query("SELECT password FROM users WHERE user_id = $user_id");
    $user = $result->fetch_assoc();
    
    if (md5($current_password) != $user['password']) {
        setAlert('danger', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
    } elseif ($new_password != $confirm_password) {
        setAlert('danger', 'รหัสผ่านใหม่ไม่ตรงกัน');
    } elseif (strlen($new_password) < 6) {
        setAlert('danger', 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร');
    } else {
        $password_hash = md5($new_password);
        $conn->query("UPDATE users SET password = '$password_hash' WHERE user_id = $user_id");
        setAlert('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
    }
    redirect('profile.php');
}

// ดึงข้อมูลผู้ใช้
$result = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
$user = $result->fetch_assoc();

// นับสถิติการใช้งาน
$stats = [
    'receipts' => 0,
    'issues' => 0,
    'total_received' => 0,
    'total_issued' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM goods_receipt WHERE user_id = $user_id");
$stats['receipts'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM goods_issue WHERE user_id = $user_id");
$stats['issues'] = $result->fetch_assoc()['count'];

$result = $conn->query("
    SELECT SUM(gri.quantity) as total 
    FROM goods_receipt_items gri
    JOIN goods_receipt gr ON gri.receipt_id = gr.receipt_id
    WHERE gr.user_id = $user_id
");
$row = $result->fetch_assoc();
$stats['total_received'] = $row['total'] ?? 0;

$result = $conn->query("
    SELECT SUM(gii.quantity) as total 
    FROM goods_issue_items gii
    JOIN goods_issue gi ON gii.issue_id = gi.issue_id
    WHERE gi.user_id = $user_id
");
$row = $result->fetch_assoc();
$stats['total_issued'] = $row['total'] ?? 0;
?>

<div class="page-header">
    <h3 class="mb-0">
        <i class="fas fa-user-circle text-primary me-2"></i>
        โปรไฟล์ของฉัน
    </h3>
    <p class="text-muted mb-0">จัดการข้อมูลส่วนตัวและเปลี่ยนรหัสผ่าน</p>
</div>

<div class="row">
    <!-- ข้อมูลส่วนตัว -->
    <div class="col-md-8">
        <!-- สถิติการใช้งาน -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">รับสินค้าเข้า</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($stats['receipts']) ?></h3>
                                <small class="text-muted"><?= number_format($stats['total_received']) ?> ชิ้น</small>
                            </div>
                            <div class="icon bg-success-light">
                                <i class="fas fa-truck-loading"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">เบิกสินค้าออก</h6>
                                <h3 class="fw-bold mb-0"><?= number_format($stats['issues']) ?></h3>
                                <small class="text-muted"><?= number_format($stats['total_issued']) ?> ชิ้น</small>
                            </div>
                            <div class="icon bg-primary-light">
                                <i class="fas fa-dolly"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- แก้ไขข้อมูล -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>ข้อมูลส่วนตัว</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">ชื่อผู้ใช้</label>
                        <input type="text" class="form-control" value="<?= $user['username'] ?>" disabled>
                        <small class="text-muted">ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="full_name" class="form-control" value="<?= $user['full_name'] ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">สิทธิ์การใช้งาน</label>
                        <input type="text" class="form-control" value="<?= $user['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 'พนักงาน' ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">สมาชิกตั้งแต่</label>
                        <input type="text" class="form-control" value="<?= date('d/m/Y H:i', strtotime($user['created_at'])) ?>" disabled>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>บันทึกข้อมูล
                    </button>
                </form>
            </div>
        </div>

        <!-- เปลี่ยนรหัสผ่าน -->
        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>เปลี่ยนรหัสผ่าน</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านปัจจุบัน</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านใหม่</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <small class="text-muted">อย่างน้อย 6 ตัวอักษร</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-warning">
                        <i class="fas fa-lock me-2"></i>เปลี่ยนรหัสผ่าน
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- การ์ดด้านขวา -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-5x text-primary"></i>
                </div>
                <h4 class="fw-bold"><?= $user['full_name'] ?></h4>
                <p class="text-muted mb-0">@<?= $user['username'] ?></p>
                <?php if ($user['role'] == 'admin'): ?>
                    <span class="badge bg-danger mt-2"><i class="fas fa-crown me-1"></i>ผู้ดูแลระบบ</span>
                <?php else: ?>
                    <span class="badge bg-info mt-2"><i class="fas fa-user me-1"></i>พนักงาน</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>ความปลอดภัย</h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        บัญชีได้รับการยืนยันแล้ว
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        รหัสผ่านถูกเข้ารหัสแล้ว
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-info-circle text-info me-2"></i>
                        เปลี่ยนรหัสผ่านเป็นประจำ
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>