<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CPM Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow" style="width:380px">
        <div class="card-body">
            <h4 class="text-center mb-3">CPM Shop Floor</h4>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger">
                    <?= session()->getFlashdata('error') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/login">
                <div class="mb-3">
                    <input type="text"
                           name="username"
                           class="form-control form-control-lg"
                           placeholder="Username"
                           required autofocus>
                </div>

                <div class="mb-3">
                    <input type="password"
                           name="password"
                           class="form-control form-control-lg"
                           placeholder="Password"
                           required>
                </div>

                <button class="btn btn-primary btn-lg w-100">
                    LOGIN
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
