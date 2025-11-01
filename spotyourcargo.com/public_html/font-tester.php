<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SYC Font Comparison</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 40px 20px;
            color: #333;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            margin-top: 20px;
        }
        
        h1 {
            text-align: center;
            color: #003366;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }
        
        .font-sample {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px;
            margin-bottom: 20px;
            border-radius: 15px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            border-left: 5px solid #003366;
        }
        
        .font-sample:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        }
        
        .font-name {
            width: 180px;
            font-weight: 600;
            color: #003366;
            font-size: 1.1rem;
        }
        
        .logo-text {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #003366;
        }
        
        .logo-dot {
            color: #FFD700;
        }
        
        .font-info {
            width: 200px;
            font-size: 0.9rem;
            color: #666;
            text-align: right;
        }
        
        .inter {
            font-family: 'Inter', sans-serif;
        }
        
        .segoe {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .tahoma {
            font-family: Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .geneva {
            font-family: Geneva, Verdana, sans-serif;
        }
        
        .verdana {
            font-family: Verdana, Geneva, sans-serif;
        }
        
        .arial {
            font-family: Arial, Helvetica, sans-serif;
        }
        
        .serif {
            font-family: serif;
        }
        
        .sans-serif {
            font-family: sans-serif;
        }
        
        .recommendation {
            background: #e8f4ff;
            padding: 25px;
            border-radius: 15px;
            margin-top: 30px;
            border-left: 5px solid #FFD700;
        }
        
        .recommendation h3 {
            color: #003366;
            margin-bottom: 10px;
        }
        
        .recommendation p {
            line-height: 1.6;
            color: #555;
        }
        
        @media (max-width: 768px) {
            .font-sample {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .font-name, .font-info {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <h1>SYC Logo Font Comparison</h1>
    <p class="subtitle">Comparing the SYC logo text across 8 different font families</p>
    
    <div class="container">
        <div class="font-sample">
            <div class="font-name">1. Inter</div>
            <div class="logo-text inter">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Modern, geometric, excellent readability</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">2. Segoe UI</div>
            <div class="logo-text segoe">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Windows system font, clean and professional</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">3. Tahoma</div>
            <div class="logo-text tahoma">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Narrow, space-efficient, good for compact use</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">4. Geneva</div>
            <div class="logo-text geneva">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Mac system font, clean and legible</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">5. Verdana</div>
            <div class="logo-text verdana">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Wide characters, excellent screen readability</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">6. Arial</div>
            <div class="logo-text arial">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Universal fallback, neutral appearance</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">7. Serif</div>
            <div class="logo-text serif">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">Traditional, formal appearance</div>
        </div>
        
        <div class="font-sample">
            <div class="font-name">8. Sans-Serif</div>
            <div class="logo-text sans-serif">SYC<span class="logo-dot">.</span></div>
            <div class="font-info">System default sans-serif font</div>
        </div>
        
        <div class="recommendation">
            <h3>Recommendation</h3>
            <p>For your SYC logo, <strong>Inter</strong> is recommended as the primary font due to its modern, clean appearance that aligns well with a tech/logistics brand. Use the font stack <code>'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, Arial, sans-serif</code> to ensure consistent display across all devices and operating systems.</p>
        </div>
    </div>
</body>
</html>