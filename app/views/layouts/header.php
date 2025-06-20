<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>VietQuest</title>
    <style>
        * { box-sizing: border-box; }

body, html {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: Arial, sans-serif;
}

.wrapper {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

header {
    background: #333;
    color: white;
    padding: 15px;
}

nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nav-left a,
.nav-right a {
    color: white;
    margin-right: 15px;
    text-decoration: none;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.nav-left a:hover,
.nav-right a:hover {
    text-decoration: underline;
}

.container {
    flex: 1;
    padding: 20px;
}

footer {
    background: #eee;
    text-align: center;
    padding: 10px;
    font-size: 14px;
}


    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>
<div class="wrapper">

    <header>
        <nav>
            <div class="nav-left">
                <a href="/VietQuest/home">Trang chủ</a>
            </div>
            <div class="nav-right">
                <?php if (isset($_SESSION['user_name'])): ?>
                    <!-- Nếu đã đăng nhập -->
                    <span class="text-white">Chào, <?php echo $_SESSION['user_name']; ?>!</span>
                    <a href="/VietQuest/auth/logout" class="btn btn-danger">Đăng xuất</a>
                <?php else: ?>
                    <!-- Nếu chưa đăng nhập -->
                    <a href="/VietQuest/auth/showlogin" class="btn btn-success">Đăng nhập</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
