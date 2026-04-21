<?php
include("components/header.php");

// CRITICAL FIX: Include safely
include_once("../backend/controller/SectionController.php");

$isSuperAdmin = isset($user['role']) && $user['role'] === 'super_admin';

// Initialize Controller
$sections = null;
try {
    if (class_exists('SectionController')) {
        $sectionController = new SectionController();
        if ($isSuperAdmin) {
            $sections = $sectionController->GetSectionsResult(null);
        } else {
            $sections = $sectionController->GetSectionsResult($auth_user_id);
        }
    }
} catch (Exception $e) {
    error_log("Error fetching sections: " . $e->getMessage());
}
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_is_super_admin" value="<?= $isSuperAdmin ? 'true' : 'false' ?>">

<style>
    /* --- UNIFIED CSS (Restored from your file) --- */
    /* CRITICAL: This hides the header.php navbar to prevent "Ruined" layout */
    .navbar { display: none !important; }
    
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; min-height: 100vh; width: 100%; overflow-x: hidden; }

    /* SIDEBAR */
    .sidebar { width: 280px; background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; display: flex; flex-direction: column; padding: 20px; position: fixed; height: 100vh; z-index: 1000; left: 0; transition: all 0.3s ease; overflow: visible !important; }
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; max-width: 100%; display: block; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107; color: #333; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; text-decoration: none; cursor: pointer; }
    .logout-btn:hover { background-color: #e0a800; color: black; }
    .sidebar-toggle { position: absolute; right: -15px; top: 50%; width: 30px; height: 60px; background-color: #FFC107; border-radius: 0 4px 4px 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #333; transition: right 0.3s ease; z-index: 1001; }
    .sidebar-toggle i { transition: transform 0.3s ease; }
    .dashboard-wrapper.toggled .sidebar { left: -280px; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0; }
    .dashboard-wrapper.toggled .sidebar-toggle { right: -30px; }
    .dashboard-wrapper.toggled .sidebar-toggle i { transform: rotate(180deg); }

    /* CONTENT & HEADER */
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; transition: all 0.3s ease; }
    .page-header { background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%); color: white; padding: 15px 30px; border-radius: 8px; font-weight: bold; font-size: 1.5rem; margin-bottom: 20px; text-transform: uppercase; display: flex; justify-content: space-between; align-items: center; }
    .header-select { background-color: white; color: #a71b1b; font-weight: bold; border: none; padding: 8px 15px; border-radius: 5px; min-width: 200px; cursor: pointer; text-transform: uppercase; }
</style>

<div class="dashboard-wrapper">
    <?php include("components/sidebar.php"); ?>
    
    <main class="main-content">
        <div class="page-header">
            <div><i class="bi bi-people-fill me-2"></i>Students of Section <span id="header-section-name">ALL SECTIONS</span></div>
            <div>
                <select name="section_id" id="sectionDropdown" class="form-select header-select">
                    <option value="">ALL SECTIONS</option>
                    <?php 
                    if ($sections && $sections->num_rows > 0): 
                        while ($section = $sections->fetch_assoc()): 
                    ?>
                        <option value="<?= htmlspecialchars($section['id']) ?>" data-name="<?= htmlspecialchars($section['section_name']) ?>">
                            <?= htmlspecialchars($section['section_name']) ?>
                        </option>
                    <?php 
                        endwhile; 
                    endif; 
                    ?>
                </select>
                </div>
        </div>

        <div class="card shadow-sm rounded-3">
            <div class="card-body p-3">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Last Name</th>
                                <th>Section</th>
                                <th>LRN</th>
                                <th>Birth Date</th>
                                <th>Gender</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Points</th>
                                <?php if (!$isSuperAdmin) { ?><th>Action</th><?php } ?>
                            </tr>
                        </thead>
                        <tbody id="students-table-tbody">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal fade" id="progressModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-main text-white" style="background: linear-gradient(180deg, #a71b1b 0%, #880f0b 100%);">
                <h5 class="modal-title">Student Aralin Progress</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-sm">
                    <thead class="table-light"><tr><th>Markahan</th><th>Aralin No.</th><th>Title</th><th>Completed At</th></tr></thead>
                    <tbody id="progressModalBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        // 1. Sidebar Toggle
        $(document).off('click', '.sidebar-toggle').on('click', '.sidebar-toggle', function() {
            $(".dashboard-wrapper").toggleClass("toggled");
        });

        const auth_user_id = $("#hidden_user_id").val();
        const is_super_admin = $("#hidden_is_super_admin").val(); 

        // 2. Load Student Logic
        const loadStudent = (section_id) => {
             $.ajax({
                type: "POST", 
                url: "../backend/api/web/students.php",
                data: { 
                    requestType: "GetStudents", 
                    auth_user_id: auth_user_id, 
                    is_super_admin: is_super_admin, 
                    section_id: section_id 
                },
                success: function(response) {
                    try {
                        let res = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (res.status === "success") {
                            const students = res.data;
                            let rowsHtml = "";
                            const colSpan = (is_super_admin === "true") ? 10 : 11;

                            if(students.length > 0) {
                                students.forEach((student) => {
                                    const fname = student.first_name || "";
                                    const mname = student.middle_name || "";
                                    const lname = student.last_name || "";
                                    const section = student.section_name || "N/A";
                                    const lrn = student.student_lrn || student.lrn || "";
                                    const bdate = student.birth_date || "";
                                    const gender = student.gender || "";
                                    const email = student.email || "";
                                    const contact = student.contact_no || "";
                                    const points = student.points || "0";

                                    rowsHtml += `
                                        <tr>
                                            <td class="fw-bold">${fname}</td>
                                            <td>${mname}</td>
                                            <td>${lname}</td>
                                            <td><span class="badge bg-light text-dark border">${section}</span></td>
                                            <td>${lrn}</td>
                                            <td>${bdate}</td>
                                            <td>${gender}</td>
                                            <td>${email}</td>
                                            <td>${contact}</td>
                                            <td><span class="badge bg-success">${points}</span></td>
                                            
                                            ${is_super_admin !== "true" ? `
                                            <td>
                                                ${student.id ? 
                                                `<button class="btn-view-progress btn btn-sm btn-outline-danger" data-id="${student.id}" title="View Progress">
                                                    <i class="bi bi-graph-up"></i>
                                                </button>` : ""}
                                            </td>` : ""}
                                        </tr>
                                    `;
                                });
                            } else {
                                rowsHtml = `<tr><td colspan="${colSpan}" class="text-center py-4 text-muted">No students found.</td></tr>`;
                            }
                            $("#students-table-tbody").html(rowsHtml);
                        } else {
                            $("#students-table-tbody").html(`<tr><td colspan="11" class="text-center text-danger">Error: ${res.message}</td></tr>`);
                        }
                    } catch (e) {
                        console.error("Parse Error:", e);
                    }
                },
                error: function() {
                    alert("Connection failed!");
                }
            });
        }

        // 3. Dropdown Change
        $("#sectionDropdown").change(function(e) {
            e.preventDefault();
            $("#header-section-name").text($(this).find(':selected').data('name') || "ALL SECTIONS");
            loadStudent($(this).val());
        });

        // 4. Progress Modal Logic
        $(document).on("click", ".btn-view-progress", function(e) {
            e.preventDefault();
            let userId = $(this).data("id");
            $.ajax({
                type: "POST",
                url: "../backend/api/web/aralin.php",
                data: { requestType: "GetDoneAralin", userId },
                success: function(response) {
                    try {
                        let res = typeof response === 'string' ? JSON.parse(response) : response;
                        if (res.status === "success") {
                            let rows = "";
                            if(res.data && res.data.length > 0) {
                                res.data.forEach(item => {
                                    let markahanLabel = "Unknown";
                                    if(item.level == 1) markahanLabel = "Unang Markahan";
                                    else if(item.level == 2) markahanLabel = "Pangalawang Markahan";
                                    else if(item.level == 3) markahanLabel = "Pangatlong Markahan";
                                    else if(item.level == 4) markahanLabel = "Ika-apat na Markahan";
                                    rows += `<tr><td><span class="badge bg-secondary">${markahanLabel}</span></td><td>Aralin ${item.aralin_no}</td><td class="fw-bold">${item.title}</td><td>${new Date(item.completed_at).toLocaleDateString()}</td></tr>`;
                                });
                            } else {
                                rows = `<tr><td colspan="4" class="text-center text-muted">No progress recorded yet.</td></tr>`;
                            }
                            $("#progressModalBody").html(rows);
                            new bootstrap.Modal(document.getElementById('progressModal')).show();
                        } else { alert(res.message || "No progress data."); }
                    } catch(e) { console.error(e); }
                }
            });
        });

        loadStudent("");
    });
</script>

<?php include("components/footer.php"); ?>