<?php
require_once 'config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($password === $user['password']) {

        // if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            header("Location: admin/dashboard.php");
            exit();
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - School Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style>
        body { 
            font-family: 'Kanit', sans-serif;
            background-color: #0d1117;
            color: #c9d1d9;
        }
        #ai-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
        }
        .glowing-text {
            text-shadow: 0 0 8px rgba(59, 130, 246, 0.5);
        }
        .glass-card {
            background: rgba(18, 24, 33, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        .glowing-btn {
            background: linear-gradient(90deg, #3b82f6, #60a5fa);
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.6);
            transition: all 0.3s ease;
        }
        .glowing-btn:hover {
            box-shadow: 0 0 25px rgba(96, 165, 250, 0.8);
            transform: scale(1.05);
        }
        .dark-input {
            background-color: rgba(13, 17, 23, 0.8);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #c9d1d9;
        }
        .dark-input:focus {
            outline: none;
            border-color: rgba(96, 165, 250, 1);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>
<body class="flex items-center justify-center h-screen px-4">
    <canvas id="ai-background"></canvas>

    <div class="w-full max-w-md">
        <div class="glass-card shadow-2xl rounded-xl p-8">
            <div class="text-center mb-8">
                <img src="https://www.i-pic.info/i/krVq1004512.jpg" alt="Logo" class="w-20 h-20 mx-auto mb-4 rounded-full border-2 border-blue-500/50">
                <h1 class="text-2xl font-bold text-white glowing-text">ลงชื่อเข้าสู่ระบบ</h1>
                <p class="text-gray-400">ระบบบันทึกคะแนนผลการเรียน</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-lg relative mb-4 text-sm" role="alert">
                    <span class="block sm:inline"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST">
                <div class="mb-4">
                    <label for="username" class="block text-gray-400 text-sm font-bold mb-2">ชื่อผู้ใช้</label>
                    <input type="text" id="username" name="username" required class="dark-input appearance-none rounded-lg w-full py-3 px-4 leading-tight">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-gray-400 text-sm font-bold mb-2">รหัสผ่าน</label>
                    <input type="password" id="password" name="password" required class="dark-input appearance-none rounded-lg w-full py-3 px-4 leading-tight">
                </div>
                <div class="flex items-center justify-between">
                    <button type="submit" class="w-full glowing-btn text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:shadow-outline">
                        <i class="fas fa-sign-in-alt mr-2"></i> เข้าสู่ระบบ
                    </button>
                </div>
            </form>

            <div class="text-center mt-6">
                <a href="index.php" class="inline-block align-baseline font-bold text-sm text-blue-400 hover:text-blue-300">
                    <i class="fas fa-arrow-left mr-1"></i> กลับสู่หน้าหลัก
                </a>
            </div>
        </div>
    </div>

<script>
// Lightweight Particle Animation Script (same as index.php)
const canvas = document.getElementById('ai-background');
const ctx = canvas.getContext('2d');
canvas.width = window.innerWidth;
canvas.height = window.innerHeight;
let particlesArray;
function init() {
    particlesArray = [];
    let numberOfParticles = (canvas.height * canvas.width) / 9000;
    for (let i = 0; i < numberOfParticles; i++) {
        let size = (Math.random() * 2) + 1;
        let x = (Math.random() * ((innerWidth - size * 2) - (size * 2)) + size * 2);
        let y = (Math.random() * ((innerHeight - size * 2) - (size * 2)) + size * 2);
        let directionX = (Math.random() * .4) - .2;
        let directionY = (Math.random() * .4) - .2;
        particlesArray.push(new Particle(x, y, directionX, directionY, size));
    }
}
class Particle {
    constructor(x, y, directionX, directionY, size) { this.x = x; this.y = y; this.directionX = directionX; this.directionY = directionY; this.size = size; }
    draw() { ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2, false); ctx.fillStyle = 'rgba(59, 130, 246, 0.1)'; ctx.fill(); }
    update() { if (this.x > canvas.width || this.x < 0) { this.directionX = -this.directionX; } if (this.y > canvas.height || this.y < 0) { this.directionY = -this.directionY; } this.x += this.directionX; this.y += this.directionY; this.draw(); }
}
function connect() {
    let opacityValue = 1;
    for (let a = 0; a < particlesArray.length; a++) {
        for (let b = a; b < particlesArray.length; b++) {
            let distance = ((particlesArray[a].x - particlesArray[b].x) * (particlesArray[a].x - particlesArray[b].x)) + ((particlesArray[a].y - particlesArray[b].y) * (particlesArray[a].y - particlesArray[b].y));
            if (distance < (canvas.width/7) * (canvas.height/7)) {
                opacityValue = 1 - (distance/20000);
                ctx.strokeStyle = `rgba(59, 130, 246, ${opacityValue * 0.1})`;
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.moveTo(particlesArray[a].x, particlesArray[a].y);
                ctx.lineTo(particlesArray[b].x, particlesArray[b].y);
                ctx.stroke();
            }
        }
    }
}
function animate() { requestAnimationFrame(animate); ctx.clearRect(0, 0, innerWidth, innerHeight); for (let i = 0; i < particlesArray.length; i++) { particlesArray[i].update(); } connect(); }
window.addEventListener('resize', function() { canvas.width = innerWidth; canvas.height = innerHeight; init(); });
init();
animate();
</script>

</body>
</html>