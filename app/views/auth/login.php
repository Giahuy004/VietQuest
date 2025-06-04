<?php require 'app/views/layouts/header.php'; ?>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm p-4">
            <h3 class="text-center mb-4">🔐 Đăng nhập</h3>

            <!-- Hiển thị thông báo lỗi nếu có -->
            <?php if (isset($error)) : ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <!-- Form đăng nhập -->
            <form method="POST" action="/VietQuest/auth/login">
                <div class="mb-3">
                    <label class="form-label">👤 Tên đăng nhập</label>
                    <input type="text" name="email" class="form-control" placeholder="Nhập tên email" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">🔒 Mật khẩu</label>
                    <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" required>
                </div>

                <button type="submit" class="btn btn-success w-100">🔓 Đăng nhập</button>
            </form>

            <!-- Liên kết Quên mật khẩu và Đăng ký -->
            <div class="text-center mt-3">
                <small>❓ <a href="/VietQuest/auth/showforgot">Quên mật khẩu?</a> | Chưa có tài khoản? <a href="/VietQuest/auth/showregister">Đăng ký ngay</a></small>
            </div>
        </div>
    </div>
</div>

<?php require 'app/views/layouts/footer.php'; ?>

<!-- Thêm Bootstrap JS và Popper.js -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
