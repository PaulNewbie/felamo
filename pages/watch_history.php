<?php
include("components/header.php");

// Authorization and Validation 
// note: create controller if the current user has access in this aralin_level
if (isset($_GET['aralinId'])) {
    $aralin_id = $_GET['aralinId'];
    $aralinResult = $AuthController->GetUsingId("aralin", $aralin_id);

    if ($aralinResult->num_rows > 0) {
        $aralin = $aralinResult->fetch_assoc();

        // add auth here
        // if ($level['teacher_id'] != $auth_user_id) {
        //     header("Location: ../index.php");
        // }
    } else {
        header("Location: ../index.php");
    }
} else {
    header("Location: ../index.php");
    exit;
}
?>

<!-- Hidden Inputs -->
<input type="hidden" id="hidden_user_id" value="<?= $auth_user_id ?>">
<input type="hidden" id="hidden_aralin_id" value="<?= $aralin_id ?>">

<!-- Page Content -->
<div class="container py-4">
    <h4 class="my-3 text-main">
        <i class="bi bi-journals me-2"></i>Mga nakapanood sa <?= $aralin['title'] ?>
    </h4>

    <table class="table table-striped">
        <thead>
            <tr>
                <th><i class="bi bi-list-ol me-1"></i>Name</th>
                <th><i class="bi bi-card-text me-1"></i>LRN</th>
                <th><i class="bi bi-card-text me-1"></i>Date</th>
            </tr>
        </thead>
        <tbody id="watch-history-table-tbody"></tbody>
    </table>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="scripts/watchHistory.js?v=1"></script>
<?php include("components/footer.php"); ?>