<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spot Your Cargo - Premium Freight Matching</title>
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

        html {
            scroll-behavior: smooth;
        }

        body {
            overflow-x: hidden;
            color: var(--dark-gray);
            background-color: #ffffff;
            line-height: 1.6;
            font-weight: 400;
        }

        /* Header & Navigation */
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

        /* Hero Section */
        .hero {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: var(--white);
            padding: 0 20px;
        }

        .hero::before {
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

        .hero-content {
            max-width: 900px;
            z-index: 1;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 1s ease forwards 0.5s;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: -0.5px;
        }

        .hero p {
            font-size: 1.5rem;
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            font-weight: 400;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
        }

        .cta-button {
            padding: 16px 32px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            min-width: 200px;
        }

        .cta-button.shipper {
            background-color: var(--primary-yellow);
            color: var(--dark-gray);
        }

        .cta-button.carrier {
            background-color: var(--secondary-blue);
            color: var(--white);
        }

        .cta-button:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .cta-button.shipper:hover {
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.7);
        }

        .cta-button.carrier:hover {
            box-shadow: 0 0 20px rgba(30, 77, 143, 0.7);
        }

        .cta-button:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: var(--transition);
        }

        .cta-button:hover:after {
            opacity: 1;
        }

        /* New CTA styles */
        .cta-row {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 16px 32px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            min-width: 200px;
        }

        .btn-primary {
            background-color: var(--primary-yellow);
            color: var(--dark-gray);
        }

        .btn-secondary {
            background-color: var(--secondary-blue);
            color: var(--white);
        }

        .btn-accent {
            background-color: var(--primary-blue);
            color: var(--white);
        }

        .btn:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .btn-primary:hover {
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.7);
        }

        .btn-secondary:hover {
            box-shadow: 0 0 20px rgba(30, 77, 143, 0.7);
        }

        .btn-accent:hover {
            box-shadow: 0 0 20px rgba(0, 51, 102, 0.7);
        }

        .btn:after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
            opacity: 0;
            transition: var(--transition);
        }

        .btn:hover:after {
            opacity: 1;
        }

        .cta-row.small {
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .cta-row.small a {
            color: var(--white);
            text-decoration: none;
            margin: 0 10px;
        }

        .cta-row.small a:hover {
            color: var(--primary-yellow);
        }

        /* Animated elements */
        .truck-animation {
            position: absolute;
            bottom: 10%;
            left: -100px;
            font-size: 40px;
            animation: moveTruck 20s linear infinite;
            color: var(--primary-yellow);
        }

        @keyframes moveTruck {
            0% { left: -100px; }
            100% { left: 100%; }
        }

        .floating-icon {
            position: absolute;
            font-size: 24px;
            opacity: 0.7;
            color: var(--primary-yellow);
        }

        .floating-icon:nth-child(1) {
            top: 20%;
            left: 10%;
            animation: float 6s ease-in-out infinite;
        }

        .floating-icon:nth-child(2) {
            top: 30%;
            right: 15%;
            animation: float 8s ease-in-out infinite 1s;
        }

        .floating-icon:nth-child(3) {
            bottom: 40%;
            left: 20%;
            animation: float 7s ease-in-out infinite 0.5s;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0) rotate(0deg); }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Sections common styles */
        section {
            padding: 100px 40px;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.8rem;
            color: var(--primary-blue);
            margin-bottom: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
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

        .section-title p {
            font-size: 1.2rem;
            color: var(--dark-gray);
            max-width: 700px;
            margin: 0 auto;
            font-weight: 400;
        }

        /* Value Proposition Section */
        .value-props {
            background-color: var(--light-gray);
        }

        .cards-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 30px;
        }

        .card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 30px;
            width: 320px;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            text-align: center;
            opacity: 0;
            transform: translateY(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            font-size: 50px;
            margin-bottom: 25px;
            color: var(--primary-blue);
            transition: var(--transition);
        }

        .card:hover .card-icon {
            transform: scale(1.1);
        }

        .card h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .card p {
            color: var(--dark-gray);
            line-height: 1.6;
            font-size: 1rem;
        }

        /* How It Works Section */
        .how-it-works {
            position: relative;
            overflow: hidden;
        }

        .steps-container {
            display: flex;
            justify-content: space-between;
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
        }

        .step {
            text-align: center;
            width: 30%;
            opacity: 0;
            transform: translateY(20px);
            position: relative;
            z-index: 2;
        }

        .step.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .step-number {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-yellow) 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 auto 25px;
            box-shadow: 0 10px 20px rgba(0, 51, 102, 0.2);
        }

        .step h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .step p {
            color: var(--dark-gray);
            font-size: 1rem;
        }

        .connecting-line {
            position: absolute;
            top: 35px;
            left: 20%;
            width: 60%;
            height: 3px;
            background: linear-gradient(to right, var(--primary-blue), var(--primary-yellow));
            z-index: 1;
        }

        /* Features Section */
        .features {
            background-color: var(--light-gray);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background-color: white;
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            opacity: 0;
            transform: translateY(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .feature-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            font-size: 45px;
            color: var(--primary-blue);
            margin-bottom: 25px;
            transition: var(--transition);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1);
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .feature-card p {
            color: var(--dark-gray);
            font-size: 1rem;
        }



        .logos-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 40px;
            margin: 60px 0;
            opacity: 0;
            transform: translateY(20px);
        }

        .logos-container.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .logo-item {
            height: 80px;
            width: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 20px;
            transition: var(--transition);
        }

        .logo-item img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            filter: grayscale(100%);
            opacity: 0.7;
            transition: var(--transition);
        }

        .logo-item:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .logo-item:hover img {
            filter: grayscale(0%);
            opacity: 1;
        }

        /* Final CTA Section */
        .final-cta {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--secondary-blue) 100%);
            color: white;
            text-align: center;
            padding: 100px 40px;
            position: relative;
            overflow: hidden;
            border-radius: 30px;
            margin: 40px;
        }

        .final-cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23FFFFFF" fill-opacity="0.1" d="M0,128L48,117.3C96,107,192,85,288,112C384,139,480,213,576,224C672,235,768,181,864,181.3C960,181,1056,235,1152,234.7C1248,235,1344,181,1392,154.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom;
            background-size: cover;
        }

        .final-cta-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            margin: 0 auto;
        }

        .final-cta h2 {
            font-size: 2.8rem;
            margin-bottom: 30px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        /* Footer */
        footer {
            background-color: var(--primary-blue);
            color: white;
            padding: 80px 40px 40px;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-column {
            flex: 1;
            min-width: 200px;
        }

        .footer-column h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
            font-weight: 600;
        }

        .footer-column h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-yellow);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 12px;
        }

        .footer-column ul li a {
            color: var(--light-gray);
            text-decoration: none;
            transition: var(--transition);
            font-size: 15px;
        }

        .footer-column ul li a:hover {
            color: var(--primary-yellow);
        }

        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .social-icons a {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transition: var(--transition);
            text-decoration: none;
        }

        .social-icons a:hover {
            background-color: var(--primary-yellow);
            color: var(--primary-blue);
            transform: translateY(-5px);
        }

        .copyright {
            text-align: center;
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: var(--light-gray);
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .steps-container {
                flex-direction: column;
                align-items: center;
                gap: 40px;
            }
            
            .step {
                width: 100%;
                max-width: 400px;
            }
            
            .connecting-line {
                display: none;
            }
        }

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

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .cta-buttons {
                flex-direction: column;
                gap: 15px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .section-title h2 {
                font-size: 2.2rem;
            }

            .footer-content {
                flex-direction: column;
                gap: 30px;
            }

            .final-cta {
                margin: 20px;
                padding: 60px 20px;
            }

            .final-cta h2 {
                font-size: 2.2rem;
            }
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


    </style>
</head>
<body>
    <!-- Header & Navigation -->
    <header>
            <a href="index.php" class="modern-logo">
                <img src="assets/img/SYC-Transparent.png" alt="Spot Your Cargo" class="modern-logo-icon">
                <span class="modern-logo-text">Spot Your Cargo<span class="modern-logo-dot">.</span></span>
            </a>
        
        <nav>
            <ul>
                <li><a href="#value-props">Services</a></li>
                <li><a href="#how-it-works">How It Works</a></li>
                <li><a href="#features">Features</a></li>
                <li><a href="#testimonials">Testimonials</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </nav>
        
        <div class="auth-buttons">
            <a href="access.php" class="login">Log In</a>
            <a href="access.php?action=signup" class="signup">Sign Up</a>
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
            <li><a href="#value-props">Services</a></li>
            <li><a href="#how-it-works">How It Works</a></li>
            <li><a href="#features">Features</a></li>
            <li><a href="#testimonials">Testimonials</a></li>
            <li><a href="contact.php">Contact</a></li>
        </ul>
        <div class="mobile-auth">
            <a href="access.php" class="mobile-login">Log In</a>
            <a href="access.php?action=signup" class="mobile-signup">Sign Up</a>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>

    <!-- Hero Section -->
    <section class="hero">

    <?php include 'assets/php/floating-animation.php' ?>

        <div class="truck-animation">
            <i class="fas fa-truck"></i>
        </div>

        <div class="hero-content">
            <h1>Digital Logistics for Dire Dawa Free Trade Zone</h1>
            <p>Connect cargo owners, transitors, and carriers — move goods efficiently between Dire Dawa FTZ and the rest of Ethiopia.</p>
            <div class="cta-row">
                <a class="btn btn-primary" href="access.php?action=register&role=shipper">Get Started as a Shipper</a>
                <a class="btn btn-secondary" href="access.php?action=register&role=transitor">Get Started as a Transitor</a>
                <a class="btn btn-accent" href="access.php?action=register&role=association">Get Started as a Truck Association</a>
            </div>
            <div class="cta-row small">
                <a href="access.php?action=login&role=shipper">Login as Shipper</a> |
                <a href="access.php?action=login&role=transitor">Login as Transitor</a> |
                <a href="access.php?action=login&role=association">Login as Truck Association</a>
            </div>
        </div>
    </section>

    <!-- Value Proposition Section -->
    <section class="value-props" id="value-props">
        <div class="section-title">
            <h2>Why Choose Spot Your Cargo</h2>
            <p>Our platform offers unmatched benefits for shippers, transitors, and carriers in Dire Dawa FTZ</p>
        </div>
        <div class="cards-container">
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-dolly"></i>
                </div>
                <h3>For Shippers</h3>
                <p>Post cargo and connect with trusted transitors and carriers for seamless movement between Dire Dawa FTZ and Ethiopia.</p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <h3>For Transitors</h3>
                <p>Bridge the gap between FTZ and domestic markets - pick up cargo from shippers and deliver to carriers for nationwide distribution.</p>
            </div>
            <div class="card">
                <div class="card-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <h3>For Carriers</h3>
                <p>Receive cargo from transitors and deliver to final destinations across Ethiopia with full tracking and documentation.</p>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works" id="how-it-works">
        <div class="section-title">
            <h2>How It Works</h2>
            <p>Seamless logistics flow from Dire Dawa FTZ to Ethiopia</p>
        </div>
        <div class="steps-container">
            <div class="step">
                <div class="step-number">1</div>
                <h3>Post Cargo</h3>
                <p>Shippers post cargo loads from Dire Dawa FTZ or to Dire Dawa FTZ. Transitors pick up and prepare for domestic transport.</p>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <h3>Connect & Transfer</h3>
                <p>Transitors hand over cargo to carriers for final delivery across Ethiopia with full documentation.</p>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <h3>Track & Deliver</h3>
                <p>Real-time tracking from starting point pickup to final destination with complete visibility for all parties.</p>
            </div>
            <div class="connecting-line"></div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-title">
            <h2>Key Features</h2>
            <p>Everything you need for efficient freight management</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h3>Intelligent Load Board</h3>
                <p>Advanced matching algorithm connects the right shippers with the right carriers.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h3>Cargo Posting Templates</h3>
                <p>Pre-designed templates for quick and accurate load postings.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3>Real-Time Tracking</h3>
                <p>GPS and ELD integration for complete shipment visibility.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-file-contract"></i>
                </div>
                <h3>Automated Documents</h3>
                <p>Digital bills of lading, proof of delivery, and other essential documents.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Analytics Dashboard</h3>
                <p>Comprehensive insights into your shipping operations and performance.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Secure Payments</h3>
                <p>Fast, secure payment processing with automated invoicing.</p>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <?php include('assets/php/testimonials.php'); ?>
    </section>

    <!-- Final CTA Section -->
    <section class="final-cta">
        <div class="final-cta-content">
            <h2>Start moving smarter with Spot Your Cargo today</h2>
            <div class="cta-row">
                <a class="btn btn-primary" href="access.php?action=register&role=shipper">Get Started as a Shipper</a>
                <a class="btn btn-secondary" href="access.php?action=register&role=transitor">Get Started as a Transitor</a>
                <a class="btn btn-accent" href="access.php?action=register&role=association">Get Started as a Truck Association</a>
            </div>
            <div class="cta-row small">
                <a href="access.php?action=login&role=shipper">Login as Shipper</a> |
                <a href="access.php?action=login&role=transitor">Login as Transitor</a> |
                <a href="access.php?action=login&role=association">Login as Truck Association</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
     <?php include('assets/php/footer.php'); ?>

    <script>
        // Scroll animations
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                root: null,
                rootMargin: '0px',
                threshold: 0.1
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        if (entry.target.classList.contains('card')) {
                            entry.target.classList.add('visible');
                        } else if (entry.target.classList.contains('step')) {
                            entry.target.classList.add('visible');
                        } else if (entry.target.classList.contains('feature-card')) {
                            entry.target.classList.add('visible');
                        } else if (entry.target.classList.contains('logos-container')) {
                            entry.target.classList.add('visible');
                        }
                    }
                });
            }, observerOptions);

            // Observe cards
            document.querySelectorAll('.card').forEach(card => {
                observer.observe(card);
            });

            // Observe steps
            document.querySelectorAll('.step').forEach(step => {
                observer.observe(step);
            });

            // Observe feature cards
            document.querySelectorAll('.feature-card').forEach(card => {
                observer.observe(card);
            });

            // Observe logos container
            const logosContainer = document.querySelector('.logos-container');
            if (logosContainer) {
                observer.observe(logosContainer);
            }

            // Header scroll effect
            const header = document.querySelector('header');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });

            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const mobileMenu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('overlay');
            const menuClose = document.getElementById('menuClose');

            function openMenu() {
                mobileMenu.classList.add('active');
                overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeMenu() {
                mobileMenu.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            menuToggle.addEventListener('click', openMenu);
            menuClose.addEventListener('click', closeMenu);
            overlay.addEventListener('click', closeMenu);
        });
    </script>
</body>
</html>