<?php
include("components/header.php");

if (isset($_GET['tId'])) {
    $teacherId = $_GET['tId'];

    $teacherResult = $AuthController->GetUser($teacherId);

    if ($teacherResult->num_rows > 0) {
        $teacher = $teacherResult->fetch_assoc();
    } else {
        header("Location: ../index.php");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}
?>

<!-- hidden inputs -->

<input type="hidden" id="hidden_teacher_id" value="<?= $teacherId ?>">

<div class="container-fluid">

    <h4 class="my-3 text-main">Students of <?= $teacher['name'] ?></h4>

    <div class="d-flex justify-content-end align-items-center">
        <div class="me-2">
            <button class="btn btn-sm btn-main text-light" data-bs-toggle="modal" data-bs-target="#insertStudentModal">Insert Students</button>
        </div>
        <div>
            <div class="input-group input-group-sm">
                <label for="CSV" class="input-group-text">Import LRN</label>
                <input type="file" id="CSV" class="form-control" accept=".csv">
            </div>
        </div>
    </div>


    <div id="alert" style="position: absolute; top:10px; right:10px; font-size: 12px;"></div>

    <table class="table table-sm table-striped" style="font-size: 13px;">
        <thead>
            <tr>
                <th>LRN</th>
                <th>Name</th>
                <!-- <th>Action</th> -->
            </tr>
        </thead>
        <tbody id="student-table-tbody"></tbody>
    </table>
</div>


<!-- Assign Student Modal -->
<div class="modal fade" id="insertStudentModal" tabindex="-1" aria-labelledby="assignStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="assign-student-form">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-main" id="insertStudentModalLabel">Assign Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="lrn" class="form-label">LRN</label>
                        <input type="number" class="form-control" id="assign_lrn" name="lrn" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-main text-light">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>


<?php
include("components/footer-scripts.php");
?>

<script src="scripts/assignStudents.js"></script>

<?php
include("components/footer.php");
