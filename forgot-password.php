<?php
include("header.php");
?>

<div class="login-container">
    <form class="card p-4 col-11 col-md-7 col-lg-4" id="ForgotPasswordForm">

        <div class="text-center my-2">
            <img src="backend/storage/assets/logo.png" alt="Felamo" style="height: 100px;">
        </div>
        <h3 class="text-center mb-4 text-main fw-bold">Login using OTP</h3>

        <div class="mb-3 text-main text-center small d-none" id="forgot-password-error">
            Invalid email!
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email address</label>
            <input type="email" id="email" class="form-control" placeholder="you@example.com" required>
        </div>

        <!-- <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" id="password" class="form-control" placeholder="Enter your password" required>
        </div> -->

        <div class="d-flex justify-content-between align-items-center mb-3">
            <!-- <span class="small">Don't have an account? <a href="register.php" class="link-main">Register</a></span> -->
            <a href="login.php" class="small link-main">Back to login</a>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-main text-white">Send OTP</button>
        </div>
    </form>
</div>


<?php
include("footer.php");
