<?php

?>
        </div> <!-- End #content -->
    </div> <!-- End .wrapper -->

    <!-- jQuery (Optional but recommended) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ตรวจสอบว่า Bootstrap โหลดหรือยัง
        if (typeof bootstrap !== 'undefined') {
            console.log('✅ Bootstrap JS โหลดสำเร็จ');
        } else {
            console.error('❌ Bootstrap JS ไม่โหลด');
        }
        
        // Auto close alert after 3 seconds
        setTimeout(function() {
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (typeof bootstrap !== 'undefined') {
                    let bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 3000);
    </script>
</body>
</html>