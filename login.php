<?php
session_start();
if(isset($_SESSION['id']) && $_SESSION['id'] > 0){
    header("Location: ./index.php");
    exit;
}
require_once('./DBConnection.php');
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOGIN | Messaging Web Application</title>
    <link rel="stylesheet" href="./fontawesome/css/all.min.css">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <script src="./js/jquery-3.6.0.min.js"></script>
    <script src="./js/popper.min.js"></script>
    <script src="./js/bootstrap.min.js"></script>
    <script src="./fontawesome/js/all.min.js"></script>
    <script src="./js/script.js"></script>
    <style>
        html, body{
            height: 100%;
        }
        body {
            background: linear-gradient(135deg, #1f4037 0%, #99f2c8 100%);
        }
        .login-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background: #0d6efd;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
   <div class="h-100 d-flex justify-content-center align-items-center">
       <div class='container' style="max-width: 480px;">
        <div class="card login-card my-3">
            <div class="login-header">
                <h3 class="mb-0"><i class="fa fa-comments me-2"></i> Messaging Web App</h3>
                <small class="opacity-75">Sign in to your account</small>
            </div>
            <div class="card-body p-4 bg-white">
                <?php if($mydb && $mydb->error): ?>
                    <div class="alert alert-warning py-2 mb-3">
                        <small><strong>Notice:</strong> Database is not connected yet.<br>
                        <a href="./db_setup.php" class="alert-link fw-bold">Click here to configure/import Aiven MySQL</a></small>
                    </div>
                <?php endif; ?>

                <form action="" id="login-form">
                    <input type="hidden" name="type" value="2">
                    <div class="form-group mb-3">
                        <label for="email" class="form-label fw-bold small text-muted">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                            <input type="email" id="email" autofocus name="email" class="form-control" placeholder="jsmith@sample.com" value="jsmith@sample.com" required>
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label for="password" class="form-label fw-bold small text-muted">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="form-control" placeholder="Password" value="jsmith" required>
                        </div>
                    </div>
                    <div class="d-grid gap-2 mb-3">
                        <button type="submit" class="btn btn-primary py-2 fw-bold">Login</button>
                    </div>
                    <div class="d-flex w-100 justify-content-between align-items-center pt-2 border-top">
                        <a href="./registration.php" class="text-decoration-none"><i class="fa fa-user-plus me-1"></i> Create Account</a>
                        <a href="./db_setup.php" class="text-decoration-none text-muted small"><i class="fa fa-database me-1"></i> DB Setup</a>
                    </div>
                </form>
            </div>
            <div class="card-footer bg-light text-center py-2">
                <small class="text-muted">Default Admin: <code>jsmith@sample.com</code> | Pass: <code>jsmith</code></small>
            </div>
        </div>
       </div>
   </div>
</body>
<script>
    $(function(){
        $('#login-form').submit(function(e){
            e.preventDefault();
            $('.pop_msg').remove();
            var _this = $(this);
            var _el = $('<div>');
            _el.addClass('pop_msg');
            _this.find('button[type="submit"]').attr('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Logging in...');
            
            $.ajax({
                url:'./Actions.php?a=login',
                method:'POST',
                data:$(this).serialize(),
                dataType:'JSON',
                error:err=>{
                    console.log(err);
                    var errMsg = "Database connect nahi ho paya ya table missing hai.";
                    try {
                        var json = JSON.parse(err.responseText);
                        if (json && json.msg) errMsg = json.msg;
                    } catch(e) {
                        if (err.responseText && err.responseText.length < 300) {
                            errMsg = err.responseText;
                        }
                    }
                    _el.addClass('alert alert-danger mb-3');
                    _el.html("<div><strong>Error:</strong> " + errMsg + "</div><div class='mt-2 small border-top pt-1'>👉 Agar database import nahi kiya hai to <a href='./db_setup.php' class='alert-link fw-bold text-decoration-underline'>DB Setup par click karein</a> aur 1-Click Import run karein.</div>");
                    _this.prepend(_el);
                    _el.show('slow');
                    _this.find('button[type="submit"]').attr('disabled', false).text('Login');
                },
                success:function(resp){
                    if(resp.status == 'success'){
                        _el.addClass('alert alert-success mb-3');
                        _el.text(resp.msg);
                        _this.prepend(_el);
                        _el.show('slow');
                        setTimeout(() => {
                            location.replace('./index.php');
                        }, 1000);
                    }else{
                        _el.addClass('alert alert-danger mb-3');
                        var helpLink = "";
                        if (resp.msg.indexOf("Table") !== -1 || resp.msg.indexOf("database") !== -1 || resp.msg.indexOf("Database") !== -1) {
                            helpLink = "<div class='mt-2 small border-top pt-1'>👉 Database ready nahi hai. <a href='./db_setup.php' class='alert-link fw-bold text-decoration-underline'>DB Setup par click karein</a> aur 1-Click Import run karein.</div>";
                        }
                        _el.html("<div>" + resp.msg + "</div>" + helpLink);
                        _this.prepend(_el);
                        _el.show('slow');
                        _this.find('button[type="submit"]').attr('disabled', false).text('Login');
                    }
                }
            });
        });
    });
</script>
</html>
