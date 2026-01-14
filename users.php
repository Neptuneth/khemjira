<?php

require_once 'config.php';
$pageTitle = 'จัดการผู้ใช้งาน';
include 'includes/header.php';

// ตรวจสอบว่าเป็น Admin หรือไม่
if ($_SESSION['role'] != 'admin') {
    setAlert('danger', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
    redirect('dashboard.php');
}

// ลบผู้ใช้
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    if ($user_id != $_SESSION['user_id']) { // ป้องกันลบตัวเอง
        $conn->query("DELETE FROM users WHERE user_id = $user_id");
        setAlert('success', 'ลบผู้ใช้เรียบร้อยแล้ว');
    } else {
        setAlert('danger', 'ไม่สามารถลบบัญชีของตัวเองได้');
    }
    redirect('users.php');
}

// เพิ่ม/แก้ไขผู้ใช้
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = clean($_POST['username']);
    $full_name = clean($_POST['full_name']);
    $role = clean($_POST['role']);
    $password = $_POST['password'];
    
    if (isset($_POST['user_id']) && !empty($_POST['user_id'])) {
        // แก้ไข
        $user_id = (int)$_POST['user_id'];
        if (!empty($password)) {
            // เปลี่ยนรหัสผ่านด้วย
            $password_hash = md5($password);
            $sql = "UPDATE users SET 
                    username = '$username',
                    password = '$password_hash',
                    full_name = '$full_name',
                    role = '$role'
                    WHERE user_id = $user_id";
        } else {
            // ไม่เปลี่ยนรหัสผ่าน
            $sql = "UPDATE users SET 
                    username = '$username',
                    full_name = '$full_name',
                    role = '$role'
                    WHERE user_id = $user_id";
        }
        $conn->query($sql);
        setAlert('success', 'แก้ไขข้อมูลผู้ใช้เรียบร้อยแล้ว');
    } else {
        // เพิ่มใหม่
        if (!empty($password)) {
            $password_hash = md5($password);
            $sql = "INSERT INTO users (username, password, full_name, role)
                    VALUES ('$username', '$password_hash', '$full_name', '$role')";
            $conn->query($sql);
            setAlert('success', 'เพิ่มผู้ใช้เรียบร้อยแล้ว');
        } else {
            setAlert('danger', 'กรุณากำหนดรหัสผ่าน');
        }
    }
    redirect('users.php');
}

// ดึงข้อมูลผู้ใช้สำหรับแก้ไข
$editUser = null;
if (isset($_GET['edit'])) {
    $user_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM users WHERE user_id = $user_id");
    $editUser = $result->fetch_assoc();
}

// ดึงข้อมูลผู้ใช้ทั้งหมด
$users = $conn->query("SELECT * FROM users ORDER BY user_id DESC");
?>

<div class="page-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h3 class="mb-0">
                <i class="fas fa-users text-primary me-2"></i>
                จัดการผู้ใช้งานระบบ
            </h3>
            <p class="text-muted mb-0">เพิ่ม แก้ไข ลบผู้ใช้งาน และกำหนดสิทธิ์</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้ใหม่
        </button>
    </div>
</div>

<!-- ตารางผู้ใช้ -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>รหัส</th>
                        <th>ชื่อผู้ใช้</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>สิทธิ์</th>
                        <th>วันที่สร้าง</th>
                        <th class="text-center">การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users->num_rows > 0): ?>
                        <?php while ($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?= $row['user_id'] ?></strong></td>
                                <td><i class="fas fa-user-circle me-2"></i><?= $row['username'] ?></td>
                                <td><?= $row['full_name'] ?></td>
                                <td>
                                    <?php if ($row['role'] == 'admin'): ?>
                                        <span class="badge bg-danger"><i class="fas fa-crown me-1"></i>ผู้ดูแลระบบ</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><i class="fas fa-user me-1"></i>พนักงาน</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                                <td class="text-center">
                                    <a href="?edit=<?= $row['user_id'] ?>" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#userModal">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="?delete=<?= $row['user_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('ต้องการลบผู้ใช้นี้?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled title="ไม่สามารถลบตัวเองได้">
                                            <i class="fas fa-lock"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">ไม่พบข้อมูลผู้ใช้</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- คำอธิบายสิทธิ์ -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0"><i class="fas fa-info-circle text-info me-2"></i>ข้อมูลสิทธิ์การใช้งาน</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6><span class="badge bg-danger me-2"><i class="fas fa-crown"></i></span>ผู้ดูแลระบบ (Admin)</h6>
                <ul>
                    <li>เข้าถึงได้ทุกฟังก์ชัน</li>
                    <li>จัดการผู้ใช้งานได้</li>
                    <li>ดูรายงานทั้งหมด</li>
                    <li>ลบข้อมูลได้</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6><span class="badge bg-info me-2"><i class="fas fa-user"></i></span>พนักงาน (Staff)</h6>
                <ul>
                    <li>รับ-เบิกสินค้าได้</li>
                    <li>ดูสต็อกคงเหลือ</li>
                    <li>ดูรายงานพื้นฐาน</li>
                    <li>ไม่สามารถจัดการผู้ใช้</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขผู้ใช้ -->
<div class="modal fade" id="userModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <?= $editUser ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="users.php">
                <div class="modal-body">
                    <?php if ($editUser): ?>
                        <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?>">
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อผู้ใช้ (Username) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" name="username" class="form-control" required 
                                   value="<?= $editUser['username'] ?? '' ?>"
                                   placeholder="admin, staff01">
                        </div>
                        <small class="text-muted">ใช้สำหรับเข้าสู่ระบบ (ภาษาอังกฤษเท่านั้น)</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่าน (Password) <?= $editUser ? '' : '<span class="text-danger">*</span>' ?></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" name="password" class="form-control" 
                                   <?= $editUser ? '' : 'required' ?>
                                   placeholder="<?= $editUser ? 'ไม่ระบุ = ไม่เปลี่ยนรหัสผ่าน' : 'กำหนดรหัสผ่าน' ?>">
                        </div>
                        <?php if ($editUser): ?>
                            <small class="text-muted">ปล่อยว่างถ้าไม่ต้องการเปลี่ยนรหัสผ่าน</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="full_name" class="form-control" required
                                   value="<?= $editUser['full_name'] ?? '' ?>"
                                   placeholder="นายสมชาย ใจดี">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">สิทธิ์การใช้งาน <span class="text-danger">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="staff" <?= ($editUser && $editUser['role'] == 'staff') ? 'selected' : '' ?>>
                                พนักงาน (Staff)
                            </option>
                            <option value="admin" <?= ($editUser && $editUser['role'] == 'admin') ? 'selected' : '' ?>>
                                ผู้ดูแลระบบ (Admin)
                            </option>
                        </select>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>คำเตือน:</strong> ผู้ดูแลระบบสามารถเข้าถึงและแก้ไขข้อมูลทั้งหมดได้
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// เปิด Modal อัตโนมัติถ้ามีการแก้ไข
if ($editUser): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var myModal = new bootstrap.Modal(document.getElementById('userModal'));
        myModal.show();
    });
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>