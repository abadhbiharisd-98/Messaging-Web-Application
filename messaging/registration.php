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
    <title>Register | Messaging Web Application</title>
    <link rel="stylesheet" href="./fontawesome/css/all.min.css">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="stylesheet" href="./css/custom.css">
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
        .register-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        #logo-img{
            width: 75px;
            height: 75px;
            object-fit: scale-down;
            background: var(--bs-light);
            object-position: center center;
            border: 1px solid var(--bs-dark);
            border-radius: 50%;
        }
    </style>
</head>
<body>
   <div class="container py-5">
       <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="card register-card">
                <div class="register-header">
                    <h3 class="mb-0"><i class="fa fa-user-plus me-2"></i> Create an Account</h3>
                    <small class="opacity-75">Join Messaging Web Application</small>
                </div>
                <div class="card-body p-4 bg-white">
                    <form action="" id="register-form">
                        <input type="hidden" name="id" value="0">
                        <div class="form-group row mb-3">
                            <div class="col-md-4">
                                <label for="lastname" class="form-label small fw-bold">Last Name</label>
                                <input type="text" id="lastname" autofocus name="lastname" class="form-control rounded-0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="firstname" class="form-label small fw-bold">First Name</label>
                                <input type="text" id="firstname" name="firstname" class="form-control rounded-0" required>
                            </div>
                            <div class="col-md-4">
                                <label for="middlename" class="form-label small fw-bold">Middle Name</label>
                                <input type="text" id="middlename" name="middlename" class="form-control rounded-0" placeholder="(optional)">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label for="gender" class="form-label small fw-bold">Gender</label>
                                    <select id="gender" name="gender" class="form-select rounded-0" required>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="contact" class="form-label small fw-bold">Contact #</label>
                                    <input type="text" id="contact" pattern="[0-9+/s//]+" name="contact" class="form-control rounded-0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="dob" class="form-label small fw-bold">Date of Birth</label>
                                    <input type="date" id="dob" name="dob" class="form-control rounded-0" required>
                                </div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label for="email" class="form-label small fw-bold">Email</label>
                            <input type="email" id="email" name="email" class="form-control rounded-0" required>
                        </div>
                        <div class="form-group row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label small fw-bold">Password</label>
                                <input type="password" id="password" name="password" class="form-control rounded-0" required>
                            </div>
                            <div class="col-md-6">
                                <label for="cpassword" class="form-label small fw-bold">Confirm Password</label>
                                <input type="password" id="cpassword" name="cpassword" class="form-control rounded-0" required>
                            </div>
                        </div>
                        <div class="form-group row mb-4">
                            <div class="col-md-6">
                                <label for="avatar" class="form-label small fw-bold">Avatar</label>
                                <input type="file" id="avatar" name="avatar" class="form-control rounded-0" accept="image/png, image/jpeg" onchange="display_img(this)">
                            </div>
                            <div class="col-md-6 text-center">
                                <img src="./images/no-image-available.png" alt="Avatar Preview" id="logo-img">
                            </div>
                        </div>
                        <div class="d-flex w-100 justify-content-between align-items-center pt-3 border-top">
                            <a href="./login.php" class="text-decoration-none"><i class="fa fa-arrow-left me-1"></i> Already have an account?</a>
                            <button type="submit" class="btn btn-primary px-4">Sign Up</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
       </div>
   </div>
</body>
<script>
    function display_img(input) {
	    if (input.files && input.files[0]) {
	        var reader = new FileReader();
	        reader.onload = function (e) {
	        	$('#logo-img').attr('src', e.target.result);
	        }
	        reader.readAsDataURL(input.files[0]);
	    }
	}
    $(function(){
        $('#register-form').submit(function(e){
            e.preventDefault();
            $('.pop_msg').remove();
            $('#password, #cpassword').removeClass('border-danger border-success');
            $('.err_msg').remove();
            if($('#password').val() != $('#cpassword').val()){
                $('#password, #cpassword').addClass('border-danger');
                $('#cpassword').after('<small class="text-danger err_msg">Password does not match</small>');
                return false;
            }
            var _this = $(this);
            var _el = $('<div>');
            _el.addClass('pop_msg');
            _this.find('button[type="submit"]').attr('disabled', true).text('Saving data...');
            
            $.ajax({
                url:'./Actions.php?a=save_user',
                data: new FormData($(this)[0]),
                cache: false,
                contentType: false,
                processData: false,
                method: 'POST',
                type: 'POST',
                dataType: 'json',
                error:err=>{
                    console.log(err);
                    _el.addClass('alert alert-danger mb-3');
                    _el.text("An error occurred. Check database connection.");
                    _this.prepend(_el);
                    _el.show('slow');
                    _this.find('button[type="submit"]').attr('disabled', false).text('Sign Up');
                },
                success:function(resp){
                    if(resp.status == 'success'){
                        _el.addClass('alert alert-success mb-3');
                        _el.text(resp.msg + " Redirecting to login...");
                        _this.prepend(_el);
                        _el.show('slow');
                        setTimeout(() => {
                            location.replace('./login.php');
                        }, 1500);
                    }else{
                        _el.addClass('alert alert-danger mb-3');
                        _el.text(resp.msg);
                        _this.prepend(_el);
                        _el.show('slow');
                        _this.find('button[type="submit"]').attr('disabled', false).text('Sign Up');
                    }
                }
            });
        });
    });
</script>
</html>
