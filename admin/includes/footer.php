<?php
// admin/includes/footer.php
?>
        </div> <!-- End admin-content -->

        <footer class="admin-footer">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> <strong>EduDash</strong>. All rights reserved.</p>
            </div>
            <div class="footer-right">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Support</a>
            </div>
        </footer>
    </main> <!-- End admin-main -->
</div> <!-- End admin-wrapper -->

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/admin.js"></script>

<?php if (isset($_SESSION['success_msg'])): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?php echo addslashes($_SESSION['success_msg']); ?>',
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
</script>
<?php unset($_SESSION['success_msg']); endif; ?>

<?php if (isset($_SESSION['error_msg'])): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: '<?php echo addslashes($_SESSION['error_msg']); ?>',
        confirmButtonColor: '#219688'
    });
</script>
<?php unset($_SESSION['error_msg']); endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteBtns = document.querySelectorAll('.btn-delete-confirm');

    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#94a3b8',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = href;
                }
            });
        });
    });
});
</script>

</body>
</html>
