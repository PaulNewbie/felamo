</body>
<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">© 2024 Felamo. All rights reserved.</span>
    </div>
</footer>

<script>
    $(document).ready(function() {
        // Logout Confirmation Logic
        $(document).on('click', '#logoutBtn', function(e) {
            e.preventDefault();
            var myModal = new bootstrap.Modal(document.getElementById('logoutModal'));
            myModal.show();
        });
    });
</script>
</body>
</html>
