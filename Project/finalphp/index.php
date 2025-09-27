<?php
session_start();
require_once 'config/database.php';

// Fetch approved recipes to display on homepage
$recipes = [];
try {
    $query = "SELECT r.*, u.username 
              FROM recipes r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.status = 'approved' 
              ORDER BY r.created_at DESC 
              LIMIT 6";
    $result = $conn->query($query);
    if ($result->rowCount() > 0) {
        while($row = $result->fetch(PDO::FETCH_ASSOC_ASSOC)) {
            $recipes[] = $row;
        }
    }
} catch (Exception $e) {
    // Handle error silently for now

}

// Handle messages from other pages
$message = '';
$message_type = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'success';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}
?>
<!DOCTYPE html> 
<html lang="en">  
<head>     
    <meta charset="UTF-8">     
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>Cookpad- online recipe sharing platform</title>     
    <!-- Linking font Awesome for icons-->     
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">     
    <!-- Linking Swiper CSS-->     
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">     
    <!-- linking google font for icons-->     
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=close"/>     
    <style>
        /*Importing Google fonts */
        @import url('https://fonts.googleapis.com/css2?family=Miniver&family=Poly&display=swap');
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Poly", serif;
        }

        :root {
            /*Color */
            --white-color: #fff;
            --dark-color: #252525;
            --primary-color: #3d0a37;
            --secondary-color: #f3961c;
            --light-pink-color: #faf4f5;
            --medium-gray-color: #ccc;

            /* Font size */
            --font-size-s: 0.9rem;
            --font-size-n: 1rem;
            --font-size-m: 1.12rem;
            --font-size-1: 1.5rem;
            --font-size-x1: 2rem;
            --font-size-xx1: 2.3rem;

            /*Font weight */
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;

            /* Border radius */
            --border-radius-s: 8px;
            --border-radius-m: 30px;
            --border-radius-circle: 50%;

            /* Site max width */
            --site-max-width: 1300px;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Styling the whole site*/
        ul {
            list-style: none;
        }

        a {
            text-decoration: none;
        }

        button {
            cursor: pointer;
            border: none;
            background: none;
        }

        img {
            width: 100%;
        }

        .section-content {
            margin:0 auto;
            padding: 0 20px;
            max-width: var(--site-max-width);
        }

        .section-title {
            text-align: center;
            padding: 60px 0 100px;
            text-transform: uppercase;
            font-size: var(--font-size-1);
        }

        .section-title::after {
            content:"";
            width: 80px;
            height: 5px;
            display: block;
            margin: 10px auto 0;
            border-radius: var(--border-radius-s);
            background: var(--secondary-color);
        }

        /* Message popup */
        .message-popup {
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 15px 20px;
            border-radius: var(--border-radius-s);
            z-index: 1000;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-popup.success {
            background-color: #4CAF50;
            color: white;
        }

        .message-popup.error {
            background-color: #f44336;
            color: white;
        }

        .close-message {
            cursor: pointer;
            margin-left: 15px;
            font-weight: bold;
        }

        /* Navbar styling*/
        header {
            position: fixed;
            width: 100%;
            z-index: 5;
            background: var(--primary-color);
        }

        header .navbar {
            display: flex;
            padding: 20px;
            align-items: center;
            justify-content: space-between;
        }

        .navbar .nav-logo .logo-text {
            color: var(--white-color);
            font-size: var(--font-size-1);
            font-weight: var(--font-weight-semibold);
        }

        .navbar .nav-menu {
            display: flex;
            gap: 10px;
        }

        .navbar .nav-menu .nav-link {
            padding: 10px;
            color: var(--white-color);
            font-size: var(--font-size-m);
            border-radius: var(--border-radius-m);
            transition: 0.3s ease;
        }

        .navbar .nav-menu .nav-link:hover {
            color: var(--primary-color);
            background: var(--secondary-color);
        }

        .navbar :where(#menu-close-button, #menu-open-button) {
            display: none;
        }

        .navbar .login-btn {
            border: none;
            outline: none;
            color: #3d0a37;
            font-size: 1rem;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 3px;
            cursor: pointer;
            background: #fff;
            text-decoration: none;
            display: inline-block;
        }

        .navbar .login-btn:hover {
            background: #f3961c;
        }

        /* Login page styling*/
        .blur-bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            pointer-events: none;
            backdrop-filter: blur(5px);
            transition: 0.1s ease;
        }

        .show-popup .blur-bg-overlay {
            opacity: 1;
            pointer-events: auto;
        }

        .form-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            max-width: 720px;
            width: 100%;
            opacity: 0;
            pointer-events: none;
            background: #fff;
            border: 2px solid #fff;
            transform: translate(-50%, -100%);
        }

        .show-popup .form-popup {
            opacity: 1;
            pointer-events: auto;
            transform: translate(-50%, -50%);
            transition: transform 0.3s ease, opacity 0.1s;
        }

        .form-popup .close-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            color: #3d0a37;
            cursor: pointer;
        }

        .form-popup .form-box {
            display: flex;
        }

        .form-box .form-details {
            max-width: 330px;
            width: 100%;
            color: #fff;
            display: flex;
            padding: 0 20px;
            text-align: center;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .login .form-details {
            background: #3d0a37;
        }

        .signup .form-details {
            background: #3d0a37;
        }

        .form-box h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-box .form-content {
            width: 100%;
            padding: 35px;
        }

        form .input-field {
            height: 50px;
            width: 100%;
            margin-top: 20px;
            position: relative;
        }

        form .input-field input {
            width: 100%;
            height: 100%;
            outline: none;
            padding: 0 15px;
            font-size: 0.95rem;
            border-radius: 3px;
            border: 1px solid #3d0a37;
        }

        .input-field input:focus {
            border-color: #f3961c;
        }

        .input-field input:is(:focus, :valid) {
            padding: 16px 15px 0;
        }

        form .input-field label {
            position: absolute;
            top: 50%;
            left: 15px;
            color: #717171;
            pointer-events: none;
            transform: translateY(-50%);
            transition: 0.2s ease;
        }

        .input-field input:is(:focus, :valid) ~ label {
            color: #3d0a37;
            font-size: 0.75rem;
            transform: translateY(-120%);
        }

        .form-box a {
            color: #3d0a37;
            text-decoration: none;
        }

        .form-box a:hover {
            text-decoration: underline;
        }

        .form-box :where(.forget-pass, .policy-text) {
            display: inline-flex;
            margin-top: 14px;
            font-size: 0.95rem;
        }

        form button {
            width: 100%;
            outline: none;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            padding: 14px 0;
            border-radius: 3px;
            margin: 25px 0;
            color: #fff;
            cursor: pointer;
            background: #f3961c;
            transition: 0.2s ease;
        }

        form button:hover {
            background: #3d0a37;
        }

        .form-box .bottom-link {
            text-align: center;
        }

        .form-popup .signup,
        .form-popup.show-signup .login {
            display: none;
        }

        .form-popup.show-signup .signup {
            display: flex;
        }

        .signup .policy-text {
            display: flex;
            align-items: center;
        }

        .signup .policy-text input {
            width: 14px;
            height: 14px;
            margin-right: 7px;
        }

        /* hero section styling*/
        .hero-section {
            min-height: 100vh;
            background: var(--primary-color);
        }

        .hero-section .section-content {
            display: flex;
            align-items: center;
            min-height: 100vh;
            color: var(--white-color);
            justify-content: space-between;
        }

        .hero-section .hero-details .title {
            font-size: var(--font-size-xx1);
            color: var(--secondary-color);
            font-family: "Miniver", "serif";
        }

        .hero-section .hero-details .subtitle {
            margin-top: 8px;
            max-width: 70%;
            font-size: var(--font-size-x1);
            font-weight: var(--font-weight-semibold);
        }

        .hero-section .hero-details .description {
            max-width: 70%;
            margin: 24px 0 40px;
            font-size: var(--font-size-m);
        }

        .hero-section .hero-details .buttons {
            display: flex;
            gap: 23px;
        }

        .hero-section .hero-details .button {
            padding: 10px 26px;
            border: 2px solid transparent;
            color: var(--primary-color);
            border-radius: var(--border-radius-m);
            background: var(--secondary-color);
            font-weight: var(--font-weight-medium);
            transition: 0.3s ease;
            text-decoration: none;
        }

        .hero-section .hero-details .button:hover,
        .hero-section .hero-details .contact-us {
            color: var(--white-color);
            border-color: var(--white-color);
            background: transparent;
        }

        .hero-section .hero-details .contact-us:hover {
            color: var(--primary-color);
            border-color: var(--secondary-color);
            background: var(--secondary-color);
        }

        .hero-section .hero-image-wrapper {
            max-width: 500px;
            margin-right: 30px;
        }

        /* About section styling */
        .about-section {
            padding: 120px 0;
            background: var(--light-pink-color);
        }

        .about-section .section-content {
            display: flex;
            gap: 50px;
            align-items: center;
            justify-content: space-between;
        }

        .about-section .about-image-wrapper .about-image {
            width: 400px;
            height: 400px;
            object-fit: cover;
            border-radius: var(--border-radius-circle);
        }

        .about-section .about-details .section-title {
            padding: 0;
        }

        .about-section .about-details {
            max-width: 50%;
        }

        .about-section .about-details .text {
            line-height: 30px;
            margin: 50px 0 30px;
            text-align: center;
            font-size: var(--font-size-m);
        }

        .about-section .social-link-list {
            display: flex;
            gap: 25px;
            justify-content: center;
        }

        .about-section .social-link-list .social-link {
            color: var(--primary-color);
            font-size: var(--font-size-1);
            transition: 0.2s ease;
        }

        .about-section .social-link-list .social-link:hover {
            color: var(--secondary-color);
        }

        /* Recipes section styling */
        .recipes-section {
            color: var(--white-color);
            background: var(--dark-color);
            padding: 50px 0 100px;
        }

        .recipes-section  .recipes-list {
            display: flex;
            flex-wrap: wrap;
            gap: 110px ;
            align-items: center;
            justify-content: space-between;
        }

        .recipes-section  .recipes-list .recipes-item {
            display: flex;
            align-items: center;
            text-align: center;
            flex-direction: column;
            justify-content: space-between;
            width: calc(100% / 3 - 100px);
        }

        .recipes-section  .recipes-list .recipes-item .recipes-image {
            max-width: 83%;
            aspect-ratio: 1;
            margin-bottom: 15px;
            object-fit: contain;
        }

        .recipes-section  .recipes-list .recipes-item .name{
            margin: 12px 0;
            font-size: var(--font-size-1);
            font-weight: var(--font-weight-semibold);
        }

        .recipes-section  .recipes-list .recipes-item .text {
            font-size: var(--font-size-m);
        }

        /* Testimonials sectoin styling*/
        .testimonials-section {
            padding: 50px 0 100px;
            background: var(--light-pink-color);
        }

        .testimonials-section .slider-wrapper {
            overflow: hidden;
            margin: 0 60px 50px;
        }

        .testimonials-section .testimonial {
            user-select: none;
            display: flex;
            padding: 35px;
            text-align: center;
            flex-direction: column;
            align-items: center;
        }

        .testimonials-section .testimonial .user-image {
            width: 280px;
            height: 280px;
            object-fit: cover;
            margin-bottom: 50px;
            border-radius: var(--border-radius-circle);
        }

        .testimonials-section .testimonial .name {
            margin-bottom: 16px;
            font-size: var(--font-size-m);
        }

        .testimonials-section .testimonial .feedback {
            line-height: 25px;
        }

        .testimonials-section .swiper-pagination-bullet {
            width: 15px;
            height: 15px;
            opacity: 1;
            background: var(--secondary-color);
        }

        /* Contact us section styling */
        .contact-section {
            padding: 50px 0 100px;
            background: var(--light-pink-color);
        }

        .contact-section .section-content {
            display: flex;
            gap: 48px;
            align-items: flex-start;
            justify-content: space-between;
        }

        .section-content .contact-info-list .contact-info {
            display: flex;
            gap: 20px;
            margin: 20px 0;
            align-items: center;
        }

        .contact-section .contact-info-list .contact-info i {
            font-size: var(--font-size-m);
        }

        .contact-section .contact-form .form-input {
            width: 100%;
            height: 50px;
            padding: 0 12px;
            outline: none;
            margin-bottom: 16px;
            background: var(--white-color);
            border-radius: var(--border-radius-s);
            border: 1px solid var(--medium-gray-color);
        }

        .contact-section .contact-form {
            max-width: 50%;
        }

        .contact-section .contact-form .form-input:focus {
            border-color: var(--secondary-color);
        }

        .contact-section .contact-form textarea.form-input {
            height: 100px;
            padding: 12px;
            resize: vertical;
        }

        .contact-section .contact-form .submit-button {
            padding: 10px 26px;
            margin-top: 10px;
            color: var(--white-color);
            font-size: var(--font-size-m);
            font-weight: var(--font-weight-medium);
            background: var(--primary-color);
            border-radius: var(--border-radius-m);
            border: 1px solid var(--primary-color);
            transition: 0.3s ease;
        }

        .contact-section .contact-form .submit-button:hover {
            color: var(--primary-color);
            background: transparent;
        }

        /* Fotter section Styling*/
        .footer-section {
            padding: 20px 0;
            background: var(--dark-color)
        }

        .footer-section .section-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-section :where(.copyright-text, .social-link, .policy-link) {
            color: var(--white-color);
            transition: 0.2s ease ;
        }

        .footer-section .social-link-list {
            display: flex;
            gap: 25px;
        }

        .footer-section .social-link-list .social-link {
            font-size: var(--font-size-1);
        }

        .footer-section .social-link-list .social-link:hover,.footer-section .policy-text .policy-link:hover {
            color: var(--secondary-color);
        }

        .footer-section .policy-text {
            margin: 0.5px;
            color: var(--white-color);
        }

        /* Respomsive media query code for max width 1024px */
        @media screen and (max-width: 1024px) {
        .recipes-section  .recipes-list {
            gap: 60px;
        }

            .recipes-section  .recipes-list .recipes-item {
                width: calc(100% / 3 - 60px);
            }
        }

        /* Respomsive media query code for max width 900px */
        @media  screen and (max-width: 900px) {
            :root {
                --font-size-m: 1rem;
                --font-size-1: 1.3rem;
                --font-size-x1: 1.5rem;
                --font-size-xx1: 1.8rem;
            }
        /* Blur idea from youtube*/
            body.show-mobile-menu header::before {
                content: "";
                position: fixed;
                left: 0;
                top: 0;
                height: 100%;
                width: 100%;
                backdrop-filter: blur(5px);
                background: rgba(0, 0, 0, 0.2);
            }

            .navbar :where(#menu-close-button, #menu-open-button) {
                display: block;
                font-size: var(--font-size-1);
            }

            .navbar #menu-close-button {
                position: absolute;
                right: 30px;
                top: 30px;
            }

            .navbar #menu-open-button {
                color: var(--white-color);
            }
            .navbar .nav-menu {
                display: block;
                position: fixed;
                left: -300px;
                top: 0;
                width: 300px;
                height: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                padding-top: 100px;
                background: var(--white-color);
                transition: left 0.2s ease;
            }

            body.show-mobile-menu .navbar .nav-menu {
                left: 0;
            }

            .navbar .nav-menu .nav-link {
                color: var(--dark-color);
                display: block;
                margin-top: 17px;
                font-size: var(--font-size-1);
            }

            .hero-section .section-content {
                gap: 50px;
                text-align: center;
                padding: 30px 20px 20px;
                flex-direction: column-reverse;
                justify-content: center;
            }

            .hero-section .hero-details :is(.subtitle, .description), .about-section .about-details, .contact-section .contact-form {
                max-width: 100%;
            }

            .hero-section .hero-details .buttons {
                justify-content: center;
            }

            .hero-section .hero-image-wrapper {
                max-width: 270px;
                margin-right: 0;
            }

            .about-section .section-content {
                gap: 70px;
                flex-direction: column-reverse;
            }

            .about-section .about-image-wrapper .about-image {
                width: 100%;
                height: 100%;
                max-width: 250px;
                aspect-ratio: 1;
            }

            .recipes-section .recipes-list {
            gap: 30px;
            }

            .recipes-section .recipes-list .recipes-item {
                width: calc(100% / 2 - 30px);
            }

            .recipes-section .recipes-list .recipes-item .recipes-image {
                max-width: 200px;
            }

            .contact-section .section-content {
                align-items: center;
                flex-direction: column-reverse;
            }
        }

        /* Respomsive media query code for max width 900px */
        @media (max-width: 760px) {
            .form-popup {
                width: 95%;
            }

            .form-box .form-details {
                display: none;
            }

            .form-box .form-content {
                padding: 30px 20px;
            }
        }

        /* Respomsive media query code for max width 640px */
        @media screen and (max-width: 640px) {
            .recipes-section .recipes-list {
            gap: 60px;
            }
            .recipes-section .recipes-list .recipes-item {
                width: 100%;
            }

            .testimonials-section .slider-wrapper {
                margin: 0 0 30px;;
            }

            .testimonials-section .swiper-slide-buttom {
                display: none;
            }

            .footer-section .section-content {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>  

<body>     
    <!-- Message Display -->
    <?php if (!empty($message)): ?>
    <div class="message-popup <?php echo $message_type; ?>">
        <?php echo $message; ?>
        <span class="close-message">&times;</span>
    </div>
    <?php endif; ?>

    <!--Creating Header and Navbar-->     
    <header>         
        <nav class="navbar section-content">             
            <a href="index.php" class="nav-logo">                 
                <h2 class="logo-text">Cookpad!</h2>             
            </a>             
            <ul class="nav-menu">                 
                <button id="menu-close-button" class="fas fa-times"></button>                  
                <li class="nav-item"><a href="index.php" class="nav-link">Home</a></li>                 
                <li class="nav-item"><a href="#about" class="nav-link">About</a></li>                 
                <li class="nav-item"><a href="#recipes" class="nav-link">Recipes</a></li>                 
                <li class="nav-item"><a href="#testimonials" class="nav-link">Testimonials</a></li>                 
                <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>             
            </ul>              
            <button id="menu-open-button" class="fas fa-bars"></button>             
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="user_dashboard.php" class="login-btn">MY ACCOUNT</a>
            <?php else: ?>
                <button class="login-btn">LOG IN</button>
            <?php endif; ?>
        </nav>     
    </header>     

    <!-- Popup Form -->      
    <div class="blur-bg-overlay"></div>      
    <div class="form-popup">         
        <span class="close-btn material-symbols-rounded">close</span>         
        <div class="form-box login">             
            <div class="form-details">                 
                <h2>Welcome Back</h2>                 
                <p>Please log in using your personal information to stay connected with us. </p>             
            </div>             
            <div class="form-content">                 
                <h2>LOGIN</h2>                 
                <form id="login-form" action="login.php" method="POST">                     
                    <div class="input-field">                         
                        <input type="email" id="email" name="email" required>                         
                        <label>Email</label>                     
                    </div>                     
                    <div class="input-field">                         
                        <input type="password" id="password" name="password" required>                         
                        <label>Password</label>                     
                    </div>                     
                    <a href="#" class="forget-pass">forget-password?</a>                     
                    <button type="submit">Log In</button>                 
                </form>                 
                <div class="bottom-link">                     
                    Don't have an account? <a href="#" id="signup-link">Signup</a>                 
                </div>             
            </div>         
        </div>         

        <div class="form-box signup">             
            <div class="form-details">                 
                <h2>Create Account</h2>                 
                <p>Sign up now with your personal information to be part of our growing community.</p>             
            </div>             
            <div class="form-content">                 
                <h2>SIGNUP</h2>                 
                <form action="register.php" method="POST">                     
                    <div class="input-field">                         
                        <input type="text" name="username" required>                         
                        <label>Username</label>                     
                    </div>
                    <div class="input-field">                         
                        <input type="email" name="email" required>                         
                        <label>Enter your email</label>                     
                    </div>                     
                    <div class="input-field">                         
                        <input type="password" name="password" required>                         
                        <label>Create password</label>                     
                    </div>                     
                    <div class="policy-text">                         
                        <input type="checkbox" id="policy" required>                         
                        <label for="policy"> I agree the <a href="#">Terms and Conditions</a></label>                     
                    </div>                     
                    <button type="submit">Sign Up</button>                 
                </form>                 
                <div class="bottom-link">                     
                    Already have an account? <a href="#" id="login-link">Login</a>                 
                </div>             
            </div>         
        </div>      
    </div>      

    <main>         
        <!-- Hero Section -->         
        <section class="hero-section">             
            <div class="section-content">                 
                <div class="hero-details">                     
                    <h2 class="title">Your Favourite Recipes</h2>                     
                    <h3 class="subtitle">Great cooking is about sharing - one recipe, one story, one flavor at a time!</h3>                     
                    <p class="description">Welcome to your recipe-sharing hub, share your favorite dishes, explore new flavors, and connect with passionate home cooks worldwide</p>                      
                    <div class="buttons">                         
                        <a href="recipepage.php" class="button view-recipes">Recipes</a>                         
                        <a href="#" class="button contact-us">Contact Us</a>                     
                    </div>                 
                </div>                 
                <div class="hero-image-wrapper">                     
                    <img src="img/chef.png" alt="Hero" class="hero image">                 
                </div>             
            </div>         
        </section>         

        <!-- About Section -->         
        <section class="about-section" id="about">             
            <div class="section-content">                 
                <div class="about-image-wrapper">                     
                    <img src="img/IMG_8995.JPG" alt="About" class="about-image">                 
                </div>                 
                <div class="about-details">                     
                    <h2 class="section-title">About Us</h2>                     
                    <p class="text">At Foodpad, we bring together food lovers from around the world to share recipes, discover exciting new dishes, and connect through a shared passion for cooking. Whether you’re a beginner or a seasoned chef, Foodpad makes it easy to find inspiration, save your favorites, and share your own culinary creations.</p>                     
                    <div class="social-link-list">                         
                        <a href="#" class="social-link"><i class="fa-brands fa-facebook"></i></a>                         
                        <a href="#" class="social-link"><i class="fa-brands fa-instagram"></i></a>                         
                        <a href="#" class="social-link"><i class="fa-brands fa-x-twitter"></i></a>                     
                    </div>                 
                </div>             
            </div>         
        </section>         

        <!-- Recipes Section -->         
        <section class="recipes-section" id="recipes">             
            <h2 class="section-title">Recipes</h2>             
            <div class="section-content">                 
                <ul class="recipes-list">                  
                    <?php if (!empty($recipes)): ?>
                        <?php foreach($recipes as $recipe): ?>
                        <li class="recipes-item">   
                            <a href="recipe_detail.php?id=<?php echo $recipe['id']; ?>">                     
                            <img src="<?php echo $recipe['image_path'] ?: 'img/placeholder.png'; ?>" alt="<?php echo htmlspecialchars($recipe['title']); ?>" class="recipes-image"> 
                            </a>                        
                            <h3 class="name"><?php echo htmlspecialchars($recipe['title']); ?></h3>                         
                            <p class="text"><?php echo htmlspecialchars(substr($recipe['description'], 0, 100) . '...'); ?></p>  
                            <p class="text"><small>By: <?php echo htmlspecialchars($recipe['username']); ?></small></p>                
                        </li> 
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Fallback content if no recipes in database -->
                        <li class="recipes-item">   
                            <a href="recipepage.php">                     
                            <img src="img/hot-beverages.png" alt="Hot Beverages" class="recipes-image"> 
                            </a>                        
                            <h3 class="name">Hot Beverages</h3>                         
                            <p class="text">Wide range of Steaming hot coffee to make you fresh and light.</p>  
                        </li>                     
                        <li class="recipes-item">  
                            <a href="recipepage.php">                      
                            <img src="img/cold-beverages.png" alt="Cold Beverages" class="recipes-image">
                            </a>                        
                            <h3 class="name">Cold Beverages</h3>                         
                            <p class="text">Refreshing cold drinks to keep you cool and hydrated.</p>                     
                        </li>                     
                        <li class="recipes-item"> 
                            <a href="recipepage.php">                        
                            <img src="img/refreshment.png" alt="Refreshment" class="recipes-image"> 
                            </a>                        
                            <h3 class="name">Refreshments</h3>                         
                            <p class="text">Fresh juices and smoothies full of flavor and energy.</p>                     
                        </li>                     
                        <li class="recipes-item"> 
                            <a href="recipepage.php">                       
                            <img src="img/special-combo.png" alt="Hot Special Combos" class="recipes-image"> 
                            </a>                        
                            <h3 class="name">Special Combos</h3>                         
                            <p class="text">Delicious combo meals perfect for any time of day.</p>                     
                        </li>                     
                        <li class="recipes-item">   
                            <a href="recipepage.php">                    
                            <img src="img/burger-frenchfries.png" alt="Burger and French fries" class="recipes-image"> 
                            </a>                        
                            <h3 class="name">Burger & Fries</h3>                         
                            <p class="text">Classic comfort food to satisfy your cravings.</p>                     
                        </li>                     
                        <li class="recipes-item"> 
                            <a href="recipepage.php">                       
                            <img src="img/desserts.png" alt="Dessert" class="recipes-image"> 
                            </a>                        
                            <h3 class="name">Desserts</h3>                         
                            <p class="text">Sweet treats to end your meal with a smile.</p>                     
                        </li>
                    <?php endif; ?>
                </ul>             
            </div>         
        </section>         

        <!-- Testimonials Section -->         
        <section class="testimonials-section" id="testimonials">             
            <h2 class="section-title">Testimonials</h2>             
            <div class="section-content">                 
                <div class="slider-container swiper">                     
                    <div class="slider-wrapper">                         
                        <ul class="testimonials-list swiper-wrapper">                             
                            <li class="testimonial swiper-slide">                                 
                                <img src="img/Bishal-M.jpg" alt="User" class="user-image">                                 
                                <h3 class="name">Bishwas Manandhar </h3>                                 
                                <i class="feedback">"FoodPad made cooking so easy! I can find recipes for any mood or occasion."</i>                             
                            </li>                             
                            <li class="testimonial swiper-slide">                                 
                                <img src="img/Anil.jpg" alt="User" class="user-image">                                 
                                <h3 class="name">Anil Bhusal</h3>                                 
                                <i class="feedback">"The variety of recipes is amazing. I’ve discovered so many new dishes!"</i>                             
                            </li>                             
                            <li class="testimonial swiper-slide">                                 
                                <img src="img/IMG_8995.JPG" alt="User" class="user-image">                                 
                                <h3 class="name">Syeda Fazeela Mohsin</h3>                                 
                                <i class="feedback">"I love how simple the instructions are. Even as a beginner, I can cook confidently."</i>                             
                            </li>                             
                            <li class="testimonial swiper-slide">                                 
                                <img src="img/Prajwol.jpg" alt="User" class="user-image">                                 
                                <h3 class="name">Prajwol Tiwari</h3>                                 
                                <i class="feedback">"FoodPad feels like having a chef in my kitchen, guiding me step-by-step."</i>                             
                            </li>                             
                            <li class="testimonial swiper-slide">                                 
                                <img src="img/PHOTO-2025-09-20-10-35-17.jpg" alt="User" class="user-image">                                 
                                <h3 class="name">Bishal Adhikari</h3>                                 
                                <i class="feedback">"Sharing my own recipes and seeing others try them is the best feeling!"</i>                             
                            </li>                         
                        </ul>                          
                        <div class="swiper-pagination"></div>                         
                        <div class="swiper-button-prev"></div>                         
                        <div class="swiper-button-next"></div>                     
                    </div>                 
                </div>             
            </div>         
        </section>         

        <!-- Contact Section -->         
        <section class="contact-section" id="contact">             
            <h2 class="section-title">Contact Us</h2>             
            <div class="section-content">                 
                <ul class="contact-info-list">                     
                    <li class="contact-info"><i class="fa-solid fa-location-crosshairs"></i><p>123 Recipe Street, Sydney, NSW 2000, Australia</p></li>                     
                    <li class="contact-info"><i class="fa-regular fa-envelope"></i><p>support@Cookpad.com</p></li>                     
                    <li class="contact-info"><i class="fa-solid fa-phone"></i><p>+61 4 1234 5678</p></li>                     
                    <li class="contact-info"><i class="fa-regular fa-clock"></i><p>Working hours: Monday - Friday: 9:00 AM - 5:00 PM</p></li>                 
                </ul>                  
                <form action="contact.php" method="POST" class="contact-form">                     
                    <input type="text" name="name" placeholder="Your name" class="form-input" required>                     
                    <input type="email" name="email" placeholder="Your email" class="form-input" required>                     
                    <textarea name="message" placeholder="Your message" class="form-input" required></textarea>                     
                    <button type="submit" class="button submit-button">Submit</button>                 
                </form>             
            </div>          
        </section>           

        <!-- Footer Section-->           
        <footer class="footer-section">             
            <div class="section-content">                 
                <p class="copyright-text">@ 2025 caption project, Cookpad</p>                  
                <div class="social-link-list">                     
                    <a href="#" class="social-link"><i class="fa-brands fa-facebook"></i></a>                     
                    <a href="#" class="social-link"><i class="fa-brands fa-instagram"></i></a>                     
                    <a href="#" class="social-link"><i class="fa-brands fa-x-twitter"></i></a>                 
                </div>                 
                <p class="policy-text"><a href="#" class="policy-link">Privacy policy</a></p>             
            </div>           
        </footer>     
    </main>      

    <!-- Linking Swiper script -->     
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>      
    <!-- Linking custom script -->     
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const navLinks = document.querySelectorAll(".nav-menu .nav-link");
            const menuOpenButton = document.querySelector("#menu-open-button");
            const menuCloseButton = document.querySelector("#menu-close-button");

            // Mobile menu toggle
            menuOpenButton.addEventListener("click", () => {
                document.body.classList.toggle("show-mobile-menu");
            });
            menuCloseButton.addEventListener("click", () => menuOpenButton.click());
            navLinks.forEach(link => {
                link.addEventListener("click", () => menuOpenButton.click());
            });

            // Popup form
            const showPopupBtn = document.querySelector(".login-btn");
            const formPopup = document.querySelector(".form-popup");
            const hidePopupBtn = document.querySelector(".form-popup .close-btn");
            const loginSignupLink = document.querySelectorAll(".form-box .bottom-link a");

            if (showPopupBtn && showPopupBtn.tagName === 'BUTTON') {
                showPopupBtn.addEventListener("click", () => {
                    document.body.classList.toggle("show-popup");
                });
            }
            
            if (hidePopupBtn) {
                hidePopupBtn.addEventListener("click", () => {
                    document.body.classList.remove("show-popup");
                });
            }

            if (loginSignupLink) {
                loginSignupLink.forEach(link => {
                    link.addEventListener("click", (e) => {
                        e.preventDefault();
                        formPopup.classList[link.id === "signup-link" ? "add" : "remove"]("show-signup");
                    });
                });
            }

            // Initialize Swiper
            const swiper = new Swiper(".slider-wrapper", {
                loop: true,
                spaceBetween: 25,
                pagination: {
                    el: ".swiper-pagination",
                    clickable: true,
                    dynamicBullets: true,
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
                breakpoints: {
                    0: { slidesPerView: 1 },
                    768: { slidesPerView: 2 },
                    1024: { slidesPerView: 3 },
                },
            });

            // Close message popup
            const closeMessage = document.querySelector('.close-message');
            if (closeMessage) {
                closeMessage.addEventListener('click', () => {
                    document.querySelector('.message-popup').style.display = 'none';
                });
            }

            // Auto-hide message after 5 seconds
            const messagePopup = document.querySelector('.message-popup');
            if (messagePopup) {
                setTimeout(() => {
                    messagePopup.style.display = 'none';
                }, 5000);
            }
        });
    </script> 
</body>  
</html>