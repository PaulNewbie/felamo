<?php
include("header.php");
if (isset($_GET['email'])) {
    $email = $_GET['email'];

    if (!$AuthController->CheckAvailableOTP($email)) {
        header("Location: forgot-password.php");
        exit;
    }
} else {
    header("Location: forgot-password.php");
    exit;
}
?>

<div class="login-container">
    <form class="card p-4 col-11 col-md-7 col-lg-4" id="LoginUsingOtpForm">

        <div class="text-center my-2">
            <img src="backend/storage/assets/logo.png" alt="Felamo" style="height: 100px;">
        </div>
        <h3 class="text-center mb-4 text-main fw-bold">Login using OTP</h3>

        <div class="mb-3 text-main text-center small d-none" id="login-using-otp-error">
            Invalid OTP!
        </div>

        <input type="hidden" id="email" value="<?php echo htmlspecialchars($email); ?>">

        <div class="mb-3">
            <label for="otp" class="form-label">OTP</label>
            <input type="number" id="otp" class="form-control" required>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <!-- <span class="small">Don't have an account? <a href="register.php" class="link-main">Register</a></span> -->
            <a href="login.php" class="small link-main">Back to login</a>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-main text-white">Login</button>
        </div>
    </form>
</div>


<?php
include("footer.php");
