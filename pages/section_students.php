<?php
include("components/header.php");

// FIX: Initialize variables safely
$sectionId = isset($_GET['sectionId']) ? $_GET['sectionId'] : null;
$section = [];

if ($sectionId) {
    try {
        // SAFETY CHECK: Ensure AuthController exists and method exists
        if (isset($AuthController) && method_exists($AuthController, 'GetUsingId')) {
            $sectionResult = $AuthController->GetUsingId('sections', $sectionId);
            
            // CHECK: Ensure we got a valid object back, not false/null
            if ($sectionResult && is_object($sectionResult) && $sectionResult->num_rows > 0) {
                $section = $sectionResult->fetch_assoc();
            } else {
                // Section not found - Redirect safely using JS
                echo "<script>window.location.href='../index.php';</script>";
                exit;
            }
        }
    } catch (Exception $e) {
        // If error occurs, prevent crash and redirect
        echo "<script>window.location.href='../index.php';</script>";
        exit;
    }
} else {
    // No ID provided
    echo "<script>window.location.href='../index.php';</script>";
    exit;
}
?>

<input type="hidden" id="hidden_section_id" value="<?= htmlspecialchars($sectionId) ?>">
<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">

<style>
    /* --- RESET & LAYOUT --- */
    /* CRITICAL: Hides the header.php navbar so the layout isn't 'ruined' */
    nav.navbar { display: none !important; } 
    
    body { background-color: #f4f6f9; overflow-x: hidden; }
    .dashboard-wrapper { display: flex; width: 100%; min-height: 100vh; overflow-x: hidden; }
    .main-content { flex: 1; margin-left: 280px; padding: 30px 40px; background-color: #f8f9fa; transition: margin-left 0.3s ease-in-out; }
    .dashboard-wrapper.toggled .main-content { margin-left: 0 !important; }

    /* --- SIDEBAR --- */
    .sidebar-profile { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid rgba(255, 255, 255, 0.5); }
    .sidebar-profile img { width: 80px !important; height: 80px !important; border-radius: 50%; object-fit: cover; border: 2px solid white; }
    .sidebar-profile h5 { font-weight: bold; margin: 0; font-size: 1.2rem; text-transform: uppercase; color: white; }
    .nav-link-custom { display: flex; align-items: center; padding: 12px 15px; color: white; text-decoration: none; font-weight: 600; margin-bottom: 10px; transition: 0.3s; border-radius: 5px; }
    .nav-link-custom:hover { background-color: rgba(255, 255, 255, 0.2); color: white; }
    .nav-link-custom.active { background-color: #FFC107 !important; color: #440101 !important; }
    .nav-link-custom i { margin-right: 15px; font-size: 1.2rem; }
    .logout-btn { margin-top: auto; background-color: #FFC107; color: black; font-weight: bold; border: none; width: 100%; padding: 12px; border-radius: 25px; text-align: center; cursor: pointer; }

    /* --- FIXED HEADER BANNER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; 
        padding: 0 30px; 
        border-radius: 8px; 
        margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
        
        display: flex; 
        align-items: center; 
        justify-content: space-between;
        height: 80px;      
        min-height: 80px;  
    }
    
    .page-header-banner h4 {
        margin: 0;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 1.5rem;
        line-height: 1;
    }

    .btn-back-text {
        background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.5);
        font-size: 0.85rem; font-weight: 600; padding: 8px 18px; border-radius: 50px; text-decoration: none;
        transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center;
    }
    .btn-back-text:hover { background-color: white; color: #a71b1b; transform: scale(1.02); }

    .btn-header-action {
        background-color: white; color: #a71b1b; border: none;
        font-size: 0.85rem; font-weight: 700; padding: 8px 20px;
        border-radius: 50px; text-transform: uppercase;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2); text-decoration: none;
        transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-header-action:hover { background-color: #f0f0f0; transform: translateY(-2px); color: #880f0b; }

    /* --- TABLE CONTAINER --- */
    .table-container {
        background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden; border: 1px solid #dee2e6;
    }
    .custom-table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
    .custom-table thead {
        background-color: #e9ecef; color: #333; font-weight: 800; text-transform: uppercase; font-size: 0.8rem; white-space: nowrap;
    }
    .custom-table th, .custom-table td {
        padding: 12px 20px; vertical-align: middle; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }

    @media (max-width: 991.98px) { 
        .main-content { margin-left: 0; padding: 1rem; } 
        .page-header-banner { 
            height: auto; 
            padding: 20px; 
            flex-direction: column; 
            gap: 15px; 
            text-align: center; 
        } 
    }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <a href="my_sections.php" class="btn-back-text">
                    BACK
                </a>
                <h4 class="m-0 fw-bold text-uppercase">
                    Students of Section <?= isset($section['section_name']) ? htmlspecialchars($section['section_name']) : 'Unknown' ?>
                </h4>
            </div>

            <div class="header-right">
                <div class="dropdown">
                    <button class="btn-header-action dropdown-toggle" type="button" id="sectionDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-arrow-left-right"></i> Switch Section
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="sectionDropdown" id="sections-dropdown-list" style="max-height: 300px; overflow-y: auto;">
                        <li><span class="dropdown-item-text text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Loading...</span></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th><i class="bi bi-person-vcard me-1"></i>LRN</th>
                            <th><i class="bi bi-person me-1"></i>First Name</th>
                            <th><i class="bi bi-person me-1"></i>Middle Name</th>
                            <th><i class="bi bi-person me-1"></i>Last Name</th>
                            <th><i class="bi bi-calendar-event me-1"></i>Birth Date</th>
                            <th><i class="bi bi-gender-ambiguous me-1"></i>Gender</th>
                            <th><i class="bi bi-envelope me-1"></i>Email</th>
                            <th><i class="bi bi-phone me-1"></i>Contact No</th>
                        </tr>
                    </thead>
                    <tbody id="student-table-tbody">
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <div class="spinner-border text-secondary" role="status"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include("components/footer-scripts.php"); ?>

<script>
    $(document).ready(function() {
        // Fix Sidebar Toggle
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault(); e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });
        
        $('a.nav-link-custom[href="my_sections.php"]').addClass('active');

        const section_id = $("#hidden_section_id").val();
        const teacher_id = $("#hidden_user_id").val();
        
        const loadAssignedStudents = () => {
            $.ajax({
                type: "POST",
                url: "../backend/api/web/student_teacher_assignment.php",
                data: {
                    requestType: "GetAssignedStudents",
                    section_id: section_id // Fixed missing colon if strict mode
                },
                success: function(response) {
                    try {
                        let res = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (res.status === "success") {
                            let rows = "";
                            if(!res.data || res.data.length === 0) {
                                 rows = `<tr><td colspan="8" class="text-center py-4 text-muted">No students found in this section.</td></tr>`;
                            } else {
                                res.data.forEach((student) => {
                                    rows += `
                                    <tr>
                                        <td class="fw-bold text-main">${student.student_lrn || ""}</td>
                                        <td>${student.first_name || ""}</td>
                                        <td>${student.middle_name || ""}</td>
                                        <td>${student.last_name || ""}</td>
                                        <td>${student.birth_date || "N/A"}</td>
                                        <td>${student.gender || "N/A"}</td>
                                        <td>${student.student_email || "N/A"}</td>
                                        <td>${student.contact_no || "N/A"}</td>
                                    </tr>`;
                                });
                            }
                            $("#student-table-tbody").html(rows);
                        } else {
                            $("#student-table-tbody").html(`
                                <tr><td colspan="8" class="text-center text-danger py-4">Failed to load students: ${res.message || "Unknown error"}</td></tr>
                            `);
                        }
                    } catch (e) {
                        console.error(e);
                        $("#student-table-tbody").html(`
                            <tr><td colspan="8" class="text-center text-danger py-4">Error parsing data</td></tr>
                        `);
                    }
                },
                error: function() {
                    $("#student-table-tbody").html(`
                        <tr><td colspan="8" class="text-center text-danger py-4">Error connecting to server</td></tr>
                    `);
                },
            });
        };

        const loadSectionDropdown = () => {
            $.ajax({
                type: "POST",
                url: "../backend/api/web/section_assignment.php",
                data: {
                    requestType: "GetAssignedSections",
                    teacher_id: teacher_id
                },
                success: function(response) {
                    try {
                        let res = typeof response === 'string' ? JSON.parse(response) : response;
                        let listHtml = "";

                        if (res.status === "success" && res.data && res.data.length > 0) {
                            res.data.forEach((sec) => {
                                let isActive = (sec.id == section_id) ? "active bg-light text-dark fw-bold border-start border-4 border-danger" : "";
                                let pointer = (sec.id == section_id) ? "none" : "auto";
                                
                                listHtml += `
                                    <li>
                                        <a class="dropdown-item ${isActive}" href="section_students.php?sectionId=${sec.id}" style="pointer-events:${pointer}">
                                            ${sec.section_name}
                                        </a>
                                    </li>`;
                            });
                        } else {
                            listHtml = `<li><span class="dropdown-item-text">No other sections found.</span></li>`;
                        }
                        $("#sections-dropdown-list").html(listHtml);
                    } catch(e) {
                         $("#sections-dropdown-list").html(`<li><span class="dropdown-item-text text-danger">Error loading sections</span></li>`);
                    }
                }
            });
        };

        if(section_id) {
            loadAssignedStudents();
            loadSectionDropdown();
        }
    });
</script>

<?php include("components/footer.php"); ?>