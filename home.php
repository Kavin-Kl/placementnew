<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Placement Cell - Mount Carmel College</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Libertinus+Serif:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&display=swap" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: "Libertinus Serif", serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .container {
      max-width: 1000px;
      width: 100%;
    }

    .header {
      text-align: center;
      margin-bottom: 50px;
      color: white;
    }

    .logo {
      max-width: 350px;
      margin: 0 auto 30px;
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }

    .logo img {
      width: 100%;
      height: auto;
    }

    .header h1 {
      font-size: 42px;
      margin-bottom: 10px;
      text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .header p {
      font-size: 18px;
      opacity: 0.95;
    }

    .portals {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 30px;
      margin-top: 40px;
    }

    .portal-card {
      background: white;
      border-radius: 15px;
      padding: 40px;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
      cursor: pointer;
    }

    .portal-card:hover {
      transform: translateY(-10px);
      box-shadow: 0 12px 48px rgba(0, 0, 0, 0.3);
    }

    .portal-icon {
      font-size: 72px;
      margin-bottom: 20px;
    }

    .admin-card .portal-icon {
      color: #581729;
    }

    .student-card .portal-icon {
      color: #667eea;
    }

    .portal-card h2 {
      font-size: 28px;
      margin-bottom: 15px;
      color: #333;
    }

    .portal-card p {
      color: #666;
      margin-bottom: 25px;
      line-height: 1.6;
    }

    .portal-btn {
      display: inline-block;
      padding: 15px 40px;
      border-radius: 8px;
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s ease;
    }

    .admin-btn {
      background: #581729;
      color: white;
    }

    .admin-btn:hover {
      background: #3d0f1c;
      box-shadow: 0 4px 12px rgba(88, 23, 41, 0.4);
    }

    .student-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .student-btn:hover {
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
      transform: scale(1.05);
    }

    .features {
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #eee;
    }

    .feature-item {
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 8px 0;
      color: #666;
      font-size: 14px;
    }

    .feature-item i {
      margin-right: 8px;
      color: #28a745;
    }

    .footer {
      text-align: center;
      margin-top: 50px;
      color: white;
      opacity: 0.9;
    }

    .footer p {
      margin: 5px 0;
    }

    @media (max-width: 768px) {
      .header h1 {
        font-size: 32px;
      }

      .portal-card {
        padding: 30px 20px;
      }

      .portals {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">
        <img src="images/MCC_login_logo.png" alt="Mount Carmel College">
      </div>
      <h1>Placement Cell</h1>
      <p>Connecting talent with opportunities</p>
    </div>

    <div class="portals">
      <!-- Admin Portal -->
      <div class="portal-card admin-card" onclick="window.location.href='index.php'">
        <div class="portal-icon">
          <i class='bx bxs-user-circle'></i>
        </div>
        <h2>Admin Portal</h2>
        <p>Manage placement drives, track applications, and monitor student placements</p>
        <a href="index.php" class="portal-btn admin-btn">Admin Login</a>

        <div class="features">
          <div class="feature-item">
            <i class='bx bx-check-circle'></i>
            <span>Manage Drives</span>
          </div>
          <div class="feature-item">
            <i class='bx bx-check-circle'></i>
            <span>Track Applications</span>
          </div>
          <div class="feature-item">
            <i class='bx bx-check-circle'></i>
            <span>Generate Reports</span>
          </div>
        </div>
      </div>

      <!-- Student Portal -->
      <div class="portal-card student-card" onclick="window.location.href='student_login.php'">
        <div class="portal-icon">
          <i class='bx bxs-graduation'></i>
        </div>
        <h2>Student Portal</h2>
        <p>Explore opportunities, apply for positions, and track your placement journey</p>
        <a href="student_login.php" class="portal-btn student-btn">Student Login</a>

        <div class="features">
          <div class="feature-item">
            <i class='bx bx-check-circle'></i>
            <span>Browse Opportunities</span>
          </div>
          <div class="feature-item">
            <i class='bx bx-check-circle'></i>
            <span>Apply Online</span>
          </div>
          <div class="feature-item">
            <i class='bx bx-check-circle'></i>
            <span>Track Applications</span>
          </div>
        </div>
      </div>
    </div>

    <div class="footer">
      <p>&copy; 2026 Mount Carmel College Placement Cell</p>
      <p>Empowering careers, Building futures</p>
    </div>
  </div>
</body>
</html>
