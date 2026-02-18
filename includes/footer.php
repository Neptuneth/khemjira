<?php
// footer.php
?>

        </div> <!-- End #content -->
    </div> <!-- End .wrapper -->

    <!-- jQuery (ใช้ได้ แต่ไม่บังคับสำหรับ Bootstrap 5) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <!-- Bootstrap Bundle JS (รวม Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // ตรวจสอบ Bootstrap
            if (typeof bootstrap !== 'undefined') {
                console.log('✅ Bootstrap JS โหลดสำเร็จ');
            } else {
                console.error('❌ Bootstrap JS ไม่โหลด');
            }

            // ปิด alert อัตโนมัติ
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0 && typeof bootstrap !== 'undefined') {
                setTimeout(() => {
                    alerts.forEach(alert => {
                        try {
                            const bsAlert = new bootstrap.Alert(alert);
                            bsAlert.close();
                        } catch (e) {
                            console.warn('Alert close error', e);
                        }
                    });
                }, 3000);
            }

        });
    </script>

</body>
</html>
