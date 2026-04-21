<?php include("components/header.php"); ?>

<div class="container py-4">

    <h4 class="text-main mb-3">
        <i class="bi bi-journal-text me-2"></i>Curriculum
    </h4>

    <div class="card shadow-sm rounded-3 p-4" style="max-width: 500px;">
        <form id="curriculum-form">
            <div class="mb-3">
                <label for="curriculum" class="form-label">Current Curriculum</label>
                <input type="text" class="form-control" id="curriculum" placeholder="e.g. K-12 Curriculum" required>
            </div>
            <button type="submit" class="btn btn-main text-light w-100">
                <i class="bi bi-save me-1"></i>Update Curriculum
            </button>
        </form>
    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        const showAlert = (type, message) => {
            $("#alert").removeClass().addClass(`alert ${type}`).text(message).show();
            setTimeout(() => {
                $("#alert").fadeOut("slow", function() {
                    $(this).removeClass().text("").hide();
                });
            }, 2000);
        };

        const loadCurriculum = () => {
            $.ajax({
                type: "POST",
                url: "../backend/api/web/curriculum.php",
                data: {
                    requestType: "GetCurriculum"
                },
                success: function(response) {
                    let res = JSON.parse(response);
                    if (res.status === "success") {
                        $("#curriculum").val(res.data.curriculum);
                    }
                },
                error: function() {
                    showAlert("alert-danger", "Failed to load curriculum.");
                }
            });
        };

        $("#curriculum-form").submit(function(e) {
            e.preventDefault();
            let curriculum = $("#curriculum").val();

            $.ajax({
                type: "POST",
                url: "../backend/api/web/curriculum.php",
                data: {
                    requestType: "EditCurriculum",
                    curriculum
                },
                success: function(response) {
                    let res = JSON.parse(response);
                    if (res.status === "success") {
                        showAlert("alert-success", res.message);
                    } else {
                        showAlert("alert-danger", res.message);
                    }
                    loadCurriculum();
                },
                error: function() {
                    showAlert("alert-danger", "Something went wrong.");
                }
            });
        });

        loadCurriculum();
    });
</script>

<?php include("components/footer.php"); ?>