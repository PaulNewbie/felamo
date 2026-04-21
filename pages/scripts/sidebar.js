$(document).ready(function() {
    // Toggle Sidebar
    $("#sidebarToggleBtn").click(function() {
        $(".dashboard-wrapper").toggleClass("toggled");
        
        // Optional: Change the icon direction based on state
        const icon = $(this).find('i');
        if ($(".dashboard-wrapper").hasClass("toggled")) {
            icon.removeClass("bi-chevron-left").addClass("bi-chevron-right");
        } else {
            icon.removeClass("bi-chevron-right").addClass("bi-chevron-left");
        }
    });

    // Logout Modal Trigger
    $("#logoutBtn").click(function(e) {
        e.preventDefault();
        const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
        logoutModal.show();
    });
});