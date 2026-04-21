<?php
include("header.php");
?>

<style>
    /* Styles for the background image and positioning */
    .login-container {
        background-image: linear-gradient(rgba(136, 15, 11, 0.5), rgba(136, 15, 11, 0.5)), url('assets/img/bg-login.jpg'); 
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        min-height: 100vh;
        
        display: flex;
        align-items: center;       
        justify-content: flex-start; 
        padding-left: 10%; 
        
        /* Gap between the login card and the side logo */
        gap: 80px; /* Increased gap slightly for the bigger logo */
    }

    /* Color for the icons */
    .input-group-text i {
        color: #880f0b;
    }

    /* Gradient Border with Rounded Corners */
    #LoginForm {
        border: 20px solid transparent;
        border-radius: 50px; 
        background-image: linear-gradient(white, white), 
                          linear-gradient(45deg, #880f0b, #ff4d4d);
        background-origin: padding-box, border-box;
        background-clip: padding-box, border-box;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        min-height: 600px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    /* Gradient Style for the Login Button */
    .btn-main {
        background-image: linear-gradient(45deg, #880f0b, #ff4d4d);
        border: none;
        transition: transform 0.2s, opacity 0.2s;
    }

    .btn-main:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    
    /* Style for the side logo */
    .side-logo {
        height: 700px; /* INCREASED from 300px to 500px */
        width: auto;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); 
        transition: height 0.3s; /* Smooth transition when resizing */
    }
    
    /* Responsive adjustment: Medium screens (Laptops) */
    @media (max-width: 1200px) {
        .side-logo {
            height: 400px; /* Slightly smaller on laptops */
        }
        .login-container {
             padding-left: 5%;
             gap: 40px;
        }
    }
    
    /* Responsive adjustment: Small screens (Mobile/Tablet) */
    @media (max-width: 900px) {
        .login-container {
            /* Column-reverse puts the logo (last element) on top */
            flex-direction: column-reverse; 
            justify-content: center;
            padding-left: 0;
            padding-bottom: 50px;
            gap: 30px;
        }
        .side-logo {
            height: 200px; /* Smaller on mobile to fit screen */
        }
        #LoginForm {
            min-height: auto; /* Allow height to adjust on mobile */
            width: 90%;
        }
    }
</style>

<div class="login-container">
    
    <form class="card p-4 col-11 col-md-7 col-lg-4" id="LoginForm">

        <div class="text-center mb-5 mt-0" style="margin-top: -20px;">
            <img src="assets/img/afgbmts-logo.jpg" alt="Felamo" style="height: 100px;">
        </div>

        <div class="mb-3 text-main text-center small d-none" id="login-error">
            Invalid email or password!
        </div>

        <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" class="form-control" placeholder="Email address" required>
        </div>

        <div class="input-group mb-3">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" class="form-control" placeholder="Password" required>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="forgot-password.php" class="small link-main">Forgot password?</a>
        </div>

        <div class="d-flex justify-content-center">
            <button type="submit" class="btn btn-main text-white rounded-pill w-50">Login</button>
        </div>
    </form>

    <img src="assets/img/logo (1).png" style="margin-top: 30px; alt="Logo" class="side-logo">

</div>

<?php
include("footer.php");
?>