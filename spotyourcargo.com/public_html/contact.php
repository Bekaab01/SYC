<?php
// Start session for user authentication - MUST be before any HTML output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Spot Your Cargo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="assets/img/favicon/favicon.ico" type="image/png">
    <style>
        :root {
            --primary-blue: #003366;
            --primary-yellow: #FFD700;
            --secondary-blue: #1E4D8F;
            --light-gray: #F5F5F5;
            --dark-gray: #333333;
            --white: #FFFFFF;
            --text-dark: #1d1d1f;
            --text-light: #86868b;
            --card-bg: rgba(255, 255, 255, 0.8);
            --card-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: var(--dark-gray);
            line-height: 1.6;
            font-weight: 400;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        h1, h2, h3, h4 {
            color: var(--primary-blue);
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            background-color: var(--primary-yellow);
            color: var(--primary-blue);
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn:hover {
            background-color: #FFC000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title h2 {
            font-size: 36px;
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background-color: var(--primary-yellow);
        }

        /* ========== HEADER STYLING (FROM INDEX.PHP) ========== */
        header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            z-index: 1000;
            transition: var(--transition);
        }

        /* Logo */
        .modern-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            transition: transform 0.3s var(--transition-timing);
        }
        
        .modern-logo:hover {
            transform: scale(1.03);
        }
        
        .modern-logo-icon {
            height: 36px;
            width: auto;
            transition: height 0.3s var(--transition-timing);
        }
        
        .modern-logo-text {
            font-weight: 700;
            color: var(--primary-blue);
            font-size: 24px;
            letter-spacing: -0.5px;
        }
        
        .modern-logo-dot {
            color: var(--primary-yellow);
        }

        nav ul {
            display: flex;
            list-style: none;
        }

        nav ul li {
            margin: 0 15px;
        }

        nav ul li a {
            text-decoration: none;
            color: var(--dark-gray);
            font-weight: 500;
            font-size: 16px;
            transition: var(--transition);
            position: relative;
        }

        nav ul li a:after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-blue);
            transition: var(--transition);
        }

        nav ul li a:hover:after {
            width: 100%;
        }

        .auth-buttons {
            display: flex;
        }

        .auth-buttons a {
            margin-left: 15px;
            padding: 10px 22px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: var(--transition);
        }

        .login {
            color: var(--primary-blue);
            border: 1px solid var(--primary-blue);
        }

        .login:hover {
            background-color: var(--primary-blue);
            color: white;
        }

        .signup {
            background-color: var(--primary-blue);
            color: white;
        }

        .signup:hover {
            background-color: var(--secondary-blue);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 51, 102, 0.3);
        }

        .menu-toggle {
            display: none;
        }

        /* Mobile menu styles */
        .mobile-menu {
            position: fixed;
            top: 0;
            right: -100%;
            width: 80%;
            max-width: 400px;
            height: 100vh;
            background-color: var(--primary-blue);
            z-index: 1001;
            padding: 80px 40px;
            transition: right 0.3s ease;
            box-shadow: -5px 0 25px rgba(0, 0, 0, 0.1);
        }

        .mobile-menu.active {
            right: 0;
        }

        .mobile-menu ul {
            list-style: none;
        }

        .mobile-menu ul li {
            margin-bottom: 25px;
        }

        .mobile-menu ul li a {
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            font-weight: 500;
            display: block;
            padding: 10px 0;
            transition: var(--transition);
        }

        .mobile-menu ul li a:hover {
            color: var(--primary-yellow);
            padding-left: 10px;
        }

        .mobile-auth {
            margin-top: 40px;
        }

        .mobile-auth a {
            display: block;
            text-align: center;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .mobile-login {
            color: var(--primary-blue);
            background-color: white;
        }

        .mobile-signup {
            color: white;
            background-color: var(--primary-yellow);
        }

        .menu-close {
            position: absolute;
            top: 25px;
            right: 25px;
            font-size: 24px;
            color: white;
            background: none;
            border: none;
            cursor: pointer;
        }

        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .toggle-icon {
            position: relative;
            width: 24px;
            height: 24px;
            transition: all 0.3s ease;
        }

        .toggle-icon span {
            display: block;
            position: absolute;
            height: 2px;
            width: 100%;
            background: var(--primary-blue);
            border-radius: 2px;
            opacity: 1;
            left: 0;
            transform: rotate(0deg);
            transition: all 0.3s ease;
        }

        .toggle-icon span:nth-child(1) {
            top: 4px;
        }

        .toggle-icon span:nth-child(2) {
            top: 11px;
        }

        .toggle-icon span:nth-child(3) {
            top: 18px;
        }

        .toggle-icon.active span:nth-child(1) {
            top: 11px;
            transform: rotate(135deg);
        }

        .toggle-icon.active span:nth-child(2) {
            opacity: 0;
            left: -20px;
        }

        .toggle-icon.active span:nth-child(3) {
            top: 11px;
            transform: rotate(-135deg);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }

            nav ul {
                display: none;
            }

            .auth-buttons {
                display: none;
            }

            .menu-toggle {
                display: block;
                font-size: 24px;
                color: var(--primary-blue);
                background: none;
                border: none;
                cursor: pointer;
            }
        }

        /* ========== CONTACT PAGE SPECIFIC STYLING ========== */
        /* Contact Header Section */
        .contact-header {
            padding: 140px 0 60px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .contact-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23FFD700" fill-opacity="0.1" d="M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,224C672,235,768,181,864,181.3C960,181,1056,235,1152,234.7C1248,235,1344,181,1392,154.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
            opacity: 0.3;
        }

        .contact-header h1 {
            font-size: 48px;
            margin-bottom: 20px;
            color: white;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .contact-header p {
            font-size: 20px;
            max-width: 700px;
            margin: 0 auto;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
        }

        /* Contact Form Section */
        .contact-form-section {
            padding: 80px 0;
            background-color: var(--white);
        }

        .contact-form-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        @media (max-width: 987px) {
            .contact-form-container {
                grid-template-columns: 1fr;
            }
        }

        .form-container {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .form-container h2 {
            margin-bottom: 25px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--primary-blue);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
        }

        .form-group .error {
            color: #ff4444;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        .form-group input:invalid:not(:placeholder-shown),
        .form-group textarea:invalid:not(:placeholder-shown) {
            border-color: #ff4444;
        }

        .form-group input:invalid:not(:placeholder-shown) + .error,
        .form-group textarea:invalid:not(:placeholder-shown) + .error {
            display: block;
        }

        /* Contact Details Section */
        .contact-details {
            background-color: var(--light-gray);
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }

        .contact-details h3 {
            margin-bottom: 20px;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
            transition: var(--transition);
        }

        .contact-item:hover {
            background-color: rgba(0, 51, 102, 0.03);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background-color: var(--primary-yellow);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }

        .contact-icon i {
            color: var(--primary-blue);
            font-size: 20px;
        }

        .contact-text h4 {
            margin-bottom: 5px;
            font-size: 18px;
        }

        .contact-text p {
            color: var(--dark-gray);
        }

        .contact-text a {
            color: var(--primary-blue);
            text-decoration: none;
            transition: var(--transition);
        }

        .contact-text a:hover {
            color: var(--secondary-blue);
            text-decoration: underline;
        }

        /* Map Section */
        .map-section {
            padding: 0 0 80px;
        }

        .map-container {
            height: 400px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* FAQ Section */
        .faq-section {
            padding: 80px 0;
            background-color: var(--light-gray);
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            margin-bottom: 15px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--card-shadow);
            background-color: var(--white);
            transition: var(--transition);
        }

        .faq-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .faq-question {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--primary-blue);
            transition: var(--transition);
        }

        .faq-question:hover {
            background-color: rgba(0, 51, 102, 0.03);
        }

        .faq-question i {
            transition: transform 0.3s;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s, padding 0.3s;
            color: var(--dark-gray);
        }

        .faq-item.active .faq-answer {
            padding: 0 20px 20px;
            max-height: 300px;
        }



        /* Social Media Section */
        .social-section {
            padding: 60px 0;
            background-color: var(--light-gray);
            text-align: center;
        }

        .social-section h2 {
            margin-bottom: 30px;
        }

        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .social-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-blue);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: var(--transition);
        }

        .social-icon:hover {
            background-color: var(--primary-yellow);
            color: var(--primary-blue);
            transform: translateY(-5px);
        }

        .social-icon i {
            font-size: 20px;
        }

        /* SVG Animation */
        .svg-animation {
            text-align: center;
            margin: 40px 0;
        }

        .animated-truck {
            max-width: 200px;
            margin: 0 auto;
        }

        .wheel {
            animation: rotate 2s linear infinite;
            transform-origin: center;
        }

        @keyframes rotate {
            100% {
                transform: rotate(360deg);
            }
        }

        /* Chat Widget */
        .chat-widget {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .chat-button {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: var(--primary-blue);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .chat-button:hover {
            background-color: var(--secondary-blue);
            transform: scale(1.1);
        }

        .chat-button i {
            font-size: 24px;
        }

        .chat-container {
            position: absolute;
            bottom: 70px;
            right: 0;
            width: 350px;
            height: 450px;
            background-color: var(--white);
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.1);
            display: none;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            background-color: var(--primary-blue);
            color: var(--white);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-header h3 {
            color: var(--white);
            margin: 0;
            font-size: 18px;
        }

        .chat-close {
            background: none;
            border: none;
            color: var(--white);
            cursor: pointer;
            font-size: 20px;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message {
            max-width: 80%;
            padding: 10px 15px;
            border-radius: 18px;
            font-size: 14px;
            line-height: 1.4;
        }

        .message.bot {
            align-self: flex-start;
            background-color: #f0f0f0;
            color: var(--dark-gray);
        }

        .message.user {
            align-self: flex-end;
            background-color: var(--primary-blue);
            color: var(--white);
        }

        .chat-input {
            display: flex;
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .chat-input input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 20px;
            outline: none;
        }

        .chat-input button {
            background: var(--primary-blue);
            color: var(--white);
            border: none;
            border-radius: 20px;
            padding: 10px 15px;
            margin-left: 10px;
            cursor: pointer;
        }

        .faq-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .faq-option {
            background-color: var(--primary-yellow);
            color: var(--primary-blue);
            border: none;
            border-radius: 15px;
            padding: 8px 15px;
            font-size: 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .faq-option:hover {
            background-color: var(--primary-blue);
            color: var(--white);
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .contact-header h1 {
                font-size: 36px;
            }
            
            .contact-header p {
                font-size: 18px;
            }
            
            .section-title h2 {
                font-size: 30px;
            }
            
            .chat-container {
                width: 300px;
                right: -20px;
            }
        }
        
        @media (max-width: 480px) {
            .contact-header {
                padding: 100px 0 40px;
            }
            
            .contact-header h1 {
                font-size: 28px;
            }
            
            .contact-header p {
                font-size: 16px;
            }
            
            .section-title h2 {
                font-size: 24px;
            }
            
            .social-icons {
                flex-wrap: wrap;
            }
            
            .chat-container {
                width: 280px;
                right: -40px;
            }
        }
    </style>
</head>
<body>

    <!-- Header Section -->
    <header>
        <a href="index.php" class="modern-logo">
            <img src="assets/img/SYC-Transparent.png" alt="Spot Your Cargo" class="modern-logo-icon">
            <span class="modern-logo-text">Spot Your Cargo<span class="modern-logo-dot">.</span></span>
        </a>
    
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="index.php#value-props">Services</a></li>
                <li><a href="index.php#how-it-works">How It Works</a></li>
                <li><a href="index.php#features">Features</a></li>
                <li><a href="index.php#testimonials">Testimonials</a></li>
                <li><a href="contact.php" style="color: var(--primary-blue); font-weight: 600;">Contact</a></li>
            </ul>
        </nav>
        
        <div class="auth-buttons">
            <a href="access.php" class="login">Log In</a>
            <a href="access.php" class="signup">Sign Up</a>
        </div>
        
        <button class="menu-toggle" id="menuToggle">
            <div class="toggle-icon" id="toggleIcon">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <button class="menu-close" id="menuClose">
            <i class="fas fa-times"></i>
        </button>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#value-props">Services</a></li>
            <li><a href="index.php#how-it-works">How It Works</a></li>
            <li><a href="index.php#features">Features</a></li>
            <li><a href="index.php#testimonials">Testimonials</a></li>
            <li><a href="contact.php" style="color: var(--primary-yellow);">Contact</a></li>
        </ul>
        <div class="mobile-auth">
            <a href="access.php" class="mobile-login">Log In</a>
            <a href="access.php" class="mobile-signup">Sign Up</a>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>

    <header class="contact-header">
        <div class="container">
            <h1>Get in Touch with Us</h1>
            <p>Have questions about our logistics services? We're here to help you find the perfect freight solution.</p>
        </div>
    </header>

    <!-- Contact Form & Details Section -->
    <section class="contact-form-section">
        <div class="container">
            <div class="contact-form-container">
                <!-- Contact Form -->
                <div class="form-container">
                    <h2><i class="fas fa-envelope"></i> Send Us a Message</h2>
                    <form id="contactForm">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" placeholder="Your full name" class="form-control" required>
                            <div class="error">Please enter your name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="Your email address" class="form-control" required>
                            <div class="error">Please enter a valid email address</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" placeholder="What is this regarding?" class="form-control" required>
                            <div class="error">Please enter a subject</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message</label>
                            <textarea id="message" name="message" rows="5" placeholder="How can we help you?" class="form-control" required></textarea>
                            <div class="error">Please enter your message</div>
                        </div>
                        
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>
                
                <!-- Contact Details -->
                <div class="contact-details">
                    <h3><i class="fas fa-info-circle"></i> Contact Information</h3>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Phone</h4>
                            <p><a href="tel:+251997459695">+251 997 459 695</a></p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Email</h4>
                            <p><a href="mailto:help@spotyourcargo.com">help@spotyourcargo.com</a></p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Address</h4>
                            <p>Addis Ababa, Ethiopia</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Business Hours</h4>
                            <p>Not live yet, <br>Coming soon...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <div class="container">
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d126766.53148481987!2d38.70844855000001!3d9.010787!2m3!1f0!2f0!3f0!4f0!5f0!6f0!7i16384!8i8192!9m2!1b1!2sEthiopia!4v1698765432101!5m2!1b1!2sEthiopia" allowfullscreen="" loading="lazy"></iframe>
            </div>
        </div>
    </section>

    <!-- SVG Animation Section -->
    <div class="svg-animation">
        <div class="container">
            <svg class="animated-truck" viewBox="0 0 200 120" xmlns="http://www.w3.org/2000/svg">
                <!-- Truck Body -->
                <rect x="30" y="50" width="100" height="40" fill="#003366" rx="5" />
                <rect x="130" y="60" width="40" height="30" fill="#003366" rx="3" />
                
                <!-- Truck Window -->
                <rect x="105" y="55" width="20" height="15" fill="#1E4D8F" rx="2" />
                
                <!-- Wheels -->
                <circle class="wheel" cx="60" cy="95" r="12" fill="#333" />
                <circle class="wheel" cx="130" cy="95" r="12" fill="#333" />
                <circle cx="60" cy="95" r="5" fill="#FFF" />
                <circle cx="130" cy="95" r="5" fill="#FFF" />
                
                <!-- Headlights -->
                <circle cx="35" cy="65" r="4" fill="#FFD700" />
                
                <!-- Cargo Details -->
                <rect x="40" y="55" width="20" height="10" fill="#FFD700" rx="2" />
                <rect x="65" y="55" width="20" height="10" fill="#FFD700" rx="2" />
                <rect x="40" y="70" width="20" height="10" fill="#FFD700" rx="2" />
                <rect x="65" y="70" width="20" height="10" fill="#FFD700" rx="2" />
            </svg>
        </div>
    </div>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="section-title">
                <h2>Frequently Asked Questions</h2>
                <p>Find quick answers to common questions about our services</p>
            </div>
            
            <div class="faq-container" id="faq">
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How quickly can I find a truck for my cargo?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Our platform matches you with available trucks in real-time. Most customers find suitable transportation within minutes of submitting their cargo details.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>What types of cargo do you handle?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>We handle a wide range of cargo types including general goods, perishables, construction materials, and more. Contact us for specialized transportation needs.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>How do you ensure the safety of my cargo?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>All our partner carriers are vetted and verified. We also offer tracking services and insurance options to ensure your cargo's safety throughout the journey.</p>
                    </div>
                </div>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <span>Can I track my shipment in real-time?</span>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="faq-answer">
                        <p>Yes, our platform provides real-time tracking for all shipments. You'll receive updates on your cargo's location and estimated arrival time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- Social Media Section -->
    <section class="social-section">
        <div class="container">
            <h2>Follow Us on Social Media</h2>
            <p>Stay updated with the latest news and offers</p>
            
            <div class="social-icons">
                <a href="#" class="social-icon">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#" class="social-icon">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#" class="social-icon">
                    <i class="fab fa-linkedin-in"></i>
                </a>
                <a href="#" class="social-icon">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#" class="social-icon">
                    <i class="fab fa-telegram"></i>
                </a>
            </div>
            
            <p>Join our community of logistics professionals</p>
        </div>
    </section>

    <!-- Chat Widget -->
    <div class="chat-widget">
        <div class="chat-button" id="chatButton">
            <i class="fas fa-comments"></i>
        </div>
        
        <div class="chat-container" id="chatContainer">
            <div class="chat-header">
                <h3>Spot Your Cargo Support</h3>
                <button class="chat-close" id="chatClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="chat-messages" id="chatMessages">
                <div class="message bot">
                    <p>Hello! How can we help you today?</p>
                    <div class="faq-options">
                        <button class="faq-option" data-question="pricing">Pricing Information</button>
                        <button class="faq-option" data-question="services">Our Services</button>
                        <button class="faq-option" data-question="tracking">Shipment Tracking</button>
                    </div>
                </div>
            </div>
            
            <div class="chat-input">
                <input type="text" id="chatInput" placeholder="Type your message...">
                <button id="sendMessage"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menuToggle');
        const mobileMenu = document.getElementById('mobileMenu');
        const overlay = document.getElementById('overlay');
        const menuClose = document.getElementById('menuClose');
        const toggleIcon = document.getElementById('toggleIcon');

        menuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('active');
            overlay.classList.toggle('active');
            toggleIcon.classList.toggle('active');
        });

        menuClose.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            toggleIcon.classList.remove('active');
        });

        overlay.addEventListener('click', function() {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            toggleIcon.classList.remove('active');
        });

        // FAQ Accordion
        const faqItems = document.querySelectorAll('.faq-item');
        
        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            
            question.addEventListener('click', () => {
                // Close all other items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item) {
                        otherItem.classList.remove('active');
                    }
                });
                
                // Toggle current item
                item.classList.toggle('active');
            });
        });

        // Chat Widget
        const chatButton = document.getElementById('chatButton');
        const chatContainer = document.getElementById('chatContainer');
        const chatClose = document.getElementById('chatClose');
        const sendMessage = document.getElementById('sendMessage');
        const chatInput = document.getElementById('chatInput');
        const chatMessages = document.getElementById('chatMessages');
        const faqOptions = document.querySelectorAll('.faq-option');

        chatButton.addEventListener('click', function() {
            chatContainer.style.display = 'flex';
        });

        chatClose.addEventListener('click', function() {
            chatContainer.style.display = 'none';
        });

        sendMessage.addEventListener('click', function() {
            sendUserMessage();
        });

        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                sendUserMessage();
            }
        });

        function sendUserMessage() {
            const message = chatInput.value.trim();
            if (message) {
                addMessage(message, 'user');
                chatInput.value = '';
                
                // Simulate bot response
                setTimeout(() => {
                    addMessage("Thank you for your message. Our team will get back to you shortly. In the meantime, is there anything else we can help with?", 'bot');
                }, 1000);
            }
        }

        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message', sender);
            messageDiv.innerHTML = `<p>${text}</p>`;
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // FAQ Option buttons in chat
        faqOptions.forEach(option => {
            option.addEventListener('click', function() {
                const question = this.getAttribute('data-question');
                let response = "";
                
                switch(question) {
                    case 'pricing':
                        response = "Our pricing is competitive and depends on factors like distance, cargo type, and urgency. Contact us for a personalized quote.";
                        break;
                    case 'services':
                        response = "We offer freight matching, real-time tracking, insurance options, and logistics consulting. Let us know what specific service you're interested in.";
                        break;
                    case 'tracking':
                        response = "All shipments can be tracked in real-time through our platform. You'll receive a tracking link once your cargo is on the way.";
                        break;
                    default:
                        response = "I'm here to help! Please let me know if you have any specific questions about our services.";
                }
                
                addMessage(this.textContent, 'user');
                
                setTimeout(() => {
                    addMessage(response, 'bot');
                }, 1000);
            });
        });

        // Form Validation
        const contactForm = document.getElementById('contactForm');
        
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Simple validation
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            if (name && email && subject && message) {
                // In a real application, you would send this data to a server
                dashboardModalSystem.showSuccessModal('Thank you for your message! We will get back to you soon.');
                contactForm.reset();
            } else {
                dashboardModalSystem.showErrorModal('Please fill in all required fields.');
            }
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.padding = '15px 40px';
                header.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.padding = '20px 40px';
                header.style.boxShadow = 'none';
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    mobileMenu.classList.remove('active');
                    overlay.classList.remove('active');
                    toggleIcon.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>