<?php 
include("components/header.php"); 

$level_id = isset($_GET['level']) ? $_GET['level'] : null;
$levelText = "Unknown"; // Default

if ($level_id) {
    try {
        if (isset($AuthController) && method_exists($AuthController, 'GetUsingId')) {
            $levelResult = $AuthController->GetUsingId("levels", $level_id);
            
            // Check if result is a valid object
            if ($levelResult && is_object($levelResult) && $levelResult->num_rows > 0) {
                $levelData = $levelResult->fetch_assoc();
                
                // Number to Word Logic
                $levelNum = $levelData['level'];
                $ordinalMap = [ 1 => "Unang", 2 => "Ikalawang", 3 => "Ikatlong", 4 => "Ika-apat na" ];
                $levelText = isset($ordinalMap[$levelNum]) ? $ordinalMap[$levelNum] : $levelNum;

            } else {
                echo "<script>window.location.href='levels.php';</script>";
                exit();
            }
        }
    } catch (Exception $e) {
        // Prevent crash on database error
        echo "<script>window.location.href='levels.php';</script>";
        exit();
    }
} else {
    echo "<script>window.location.href='levels.php';</script>";
    exit();
}
?>

<input type="hidden" id="hidden_user_id" value="<?= isset($auth_user_id) ? $auth_user_id : '' ?>">
<input type="hidden" id="hidden_level_id" value="<?= htmlspecialchars($level_id) ?>">

<style>
    /* --- RESET & LAYOUT --- */
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

    /* --- HEADER --- */
    .page-header-banner {
        background: linear-gradient(90deg, #a71b1b 0%, #880f0b 100%);
        color: white; padding: 15px 25px; border-radius: 8px; margin-bottom: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: space-between;
        font-size: 1.5rem; font-weight: 700; text-transform: uppercase;
    }
    .btn-back-text {
        background-color: rgba(255,255,255,0.2); color: white; border: 1px solid rgba(255,255,255,0.5);
        font-size: 0.9rem; font-weight: 600; padding: 8px 20px; border-radius: 50px; text-decoration: none;
        transition: all 0.2s; display: flex; align-items: center; gap: 8px;
    }
    .btn-back-text:hover { background-color: white; color: #a71b1b; }

    /* --- TABLE --- */
    .table-container {
        background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow: hidden; border: 1px solid #dee2e6;
    }
    .custom-table { width: 100%; margin-bottom: 0; border-collapse: collapse; }
    .custom-table thead {
        background-color: #e9ecef; color: #333; font-weight: 800; text-transform: uppercase; font-size: 0.85rem;
    }
    .custom-table th, .custom-table td {
        padding: 15px 25px; vertical-align: middle; border-bottom: 1px solid #f0f0f0;
    }
    .custom-table tbody tr:hover { background-color: #f8f9fa; }
    .text-date { font-size: 0.9rem; color: #6c757d; font-family: monospace; }

    /* Buttons */
    .btn-action-red {
        background-color: #c92a2a; color: white; border: none; padding: 6px 14px;
        border-radius: 50px; font-size: 0.85rem; font-weight: 600; text-decoration: none;
        transition: background 0.2s;
    }
    .btn-action-red:hover { background-color: #a71b1b; color: white; }

    @media (max-width: 991.98px) { .main-content { margin-left: 0; padding: 1rem; } .page-header-banner { flex-direction: column; gap: 15px; text-align: center; } }
</style>

<div class="dashboard-wrapper">
    
    <?php include("components/sidebar.php"); ?>

    <div class="main-content">
        
        <div class="page-header-banner">
            <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
                <a href="levels.php" class="btn-back-text">BACK</a>
                <h4 class="m-0 fw-bold text-uppercase">
                    Taken Assessment sa <?= htmlspecialchars($levelText) ?> Markahan
                </h4>
            </div>
            <div class="header-right"></div>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table custom-table">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Assessment Title</th>
                            <th style="width: 25%;">Student Name</th>
                            <th style="width: 15%;">Date Taken</th>
                            <th style="width: 15%;">Score</th>
                            <th style="width: 15%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="taken-assessments-list">
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-secondary" role="status" style="width: 2rem; height: 2rem;"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include("components/footer-scripts.php"); ?>
<script src="scripts/takenAssessment.js?v=<?= time() ?>"></script>

<script>
    $(document).ready(function () {
        $(document).off('click', '.sidebar-toggle');
        $(document).on('click', '.sidebar-toggle', function(e) {
            e.preventDefault(); e.stopPropagation(); 
            $(".dashboard-wrapper").toggleClass("toggled");
        });
        $('a.nav-link-custom[href="levels.php"]').addClass('active');
    });
</script>

<?php include("components/footer.php"); ?>