<?php 
$user_id = isset($_GET['user']) && $conn ? $conn->real_escape_string($_GET['user']) : (isset($_SESSION['id']) ? md5($_SESSION['id']) : '');
$user_qry = ($conn && !$mydb->error) ? $conn->query("SELECT *,CONCAT(firstname,' ',middlename,' ',lastname) as fullname FROM users where md5(id) = '{$user_id}'") : null;
if(!$user_qry || !($user_qry instanceof mysqli_result) || $user_qry->num_rows <= 0){
    echo '<script>location.replace("404.html")</script>';
    exit;
}
$user_data = $user_qry->fetch_array();
foreach($user_data as $k => $v){
    if(!is_numeric($k)){
        $$k = $v;
    }
}
$profile_avatar = is_file('./uploads/avatars/'.$id.'.png') ? './uploads/avatars/'.$id.'.png?v='.(!is_null($date_updated) ? strtotime($date_updated) : strtotime($date_created)) : './images/no-image-available.png';
?>
<div class="h-100">
    <div class="row h-100">
        <div class="col-md-4 h-100 text-center">
            <img src="<?php echo $profile_avatar ?>" class="shadow-lg rounded-0 img-fluid mb-3" style="max-height: 250px; object-fit: cover;" alt="Avatar">
            <div class="fs-4 fw-bold"><?php echo htmlspecialchars($fullname) ?></div>
        </div>
        <div class="col-md-8 h-100">
            <div class="card shadow-lg rounded-0">
                <div class="card-header bg-primary bg-gradient text-light">
                    <div class="card-title mb-0 fw-bold">About User</div>
                </div>
                <div class="card-body">
                    <div class="container-fluid pb-4">
                        <dl class="row">
                            <dt class="col-sm-4 text-muted">Email</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($email) ?></dd>
                            
                            <dt class="col-sm-4 text-muted">Gender</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($gender) ?></dd>
                            
                            <dt class="col-sm-4 text-muted">Date of Birth</dt>
                            <dd class="col-sm-8"><?php echo date("F d, Y", strtotime($dob)) ?></dd>
                            
                            <dt class="col-sm-4 text-muted">Contact #</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($contact) ?></dd>
                        </dl>
                        <?php if(isset($_SESSION['id']) && $_SESSION['id'] == $id): ?>
                            <a href="./index.php?page=manage_account" class="btn btn-sm btn-primary rounded-0"><i class="fa fa-edit me-1"></i> Edit Account</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
