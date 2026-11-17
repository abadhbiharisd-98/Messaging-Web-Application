<?php 
session_start();
require_once('DBConnection.php');

Class Actions extends DBConnection{
    function __construct(){
        parent::__construct();
    }
    function __destruct(){
        parent::__destruct();
    }
    function login(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $resp = array();
        try {
            if(!$this->conn || $this->error){
                $resp['status'] = "failed";
                $resp['msg'] = "Database Connection Failed: " . ($this->error ?: "Cannot connect to database. Please click 'DB Setup' below to check Aiven settings.");
                return json_encode($resp);
            }
            $email = isset($email) ? $this->conn->real_escape_string(trim($email)) : '';
            $password = isset($password) ? md5($password) : '';
            $sql = "SELECT * FROM `users` where `email` = '{$email}' and `password` = '{$password}' ";
            $qry = @$this->conn->query($sql);

            // AUTO-FIX SCHEMA MISMATCH:
            // If table 'users' belongs to an old project and lacks 'email' column (error 1054)
            // or table doesn't exist (error 1146):
            if (!$qry && ($this->conn->errno == 1054 || $this->conn->errno == 1146 || strpos($this->conn->error, "Unknown column 'email'") !== false)) {
                $fixed = $this->autoFixSchema();
                if ($fixed) {
                    $qry = @$this->conn->query($sql);
                }
            }

            if (!$qry) {
                $resp['status'] = "failed";
                $resp['msg'] = "Database Error: " . ($this->conn->error ?: "Table 'users' issue. Please click 'DB Setup' below to re-import the database.");
                return json_encode($resp);
            }
            $row = ($qry instanceof mysqli_result) ? $qry->fetch_array() : null;
            if(!$row){
                $resp['status'] = "failed";
                $resp['msg'] = "Invalid email or password.";
            }else{
                $resp['status'] = "success";
                $resp['msg'] = "Login successfully.";
                foreach($row as $k => $v){
                    if(!is_numeric($k))
                    $_SESSION[$k] = $v;
                }
            }
        } catch (Throwable $e) {
            $resp['status'] = "failed";
            $resp['msg'] = "Server Error: " . $e->getMessage();
        }
        return json_encode($resp);
    }
    function logout(){
        session_destroy();
        header("location:./login.php");
        exit;
    }
    function save_user(){
        header('Content-Type: application/json; charset=utf-8');
        $resp = array();
        try {
            if(!$this->conn || $this->error){
                $resp['status'] = "failed";
                $resp['msg'] = "Database connection error: " . ($this->error ?: "Unable to connect.");
                return json_encode($resp);
            }

            if(isset($_POST['cpassword']))
                unset($_POST['cpassword']);
            
            if(isset($_POST['password']) && !empty($_POST['password']))
                $_POST['password'] = md5($_POST['password']);
            if(isset($_POST['password']) && empty($_POST['password']))
                unset($_POST['password']);

            extract($_POST);
            $data = "";

            foreach($_POST as $k => $v){
                if(!in_array($k,array('id'))){
                    if(!empty($data)) $data .= ",";
                    $v = $this->conn->real_escape_string($v);
                    $data .= " `{$k}` = '{$v}' ";
                }
            }
        
            $id = isset($id) ? intval($id) : 0;
            $email_escaped = isset($email) ? $this->conn->real_escape_string($email) : '';
            $check_qry = @$this->conn->query("SELECT * FROM users where `email` = '{$email_escaped}' ".($id > 0 ? " and id != '{$id}' " : ""));
            
            if (!$check_qry && ($this->conn->errno == 1054 || $this->conn->errno == 1146)) {
                $this->autoFixSchema();
                $check_qry = @$this->conn->query("SELECT * FROM users where `email` = '{$email_escaped}' ".($id > 0 ? " and id != '{$id}' " : ""));
            }

            $check = ($check_qry && $check_qry instanceof mysqli_result) ? $check_qry->num_rows : 0;

            if($check > 0){
                $resp['status'] = 'failed';
                $resp['msg'] = "Email already exists.";
            }else{
                if(empty($id)){
                    $sql = "INSERT INTO `users` set {$data}";
                }else{
                    $sql = "UPDATE `users` set {$data} where id = '{$id}'";
                }
                $save = @$this->conn->query($sql);
                if($save){
                    $resp['status'] = 'success';
                    if(empty($id)){
                        $resp['msg'] = 'New User successfully saved.';
                        $user_id = $this->conn->insert_id;
                    }else{
                        $resp['msg'] = 'User Details successfully updated.';
                        $user_id = $id;
                    }
                    if(isset($_FILES['avatar']['tmp_name']) && !empty($_FILES['avatar']['tmp_name'])){
                        $dir_path = __DIR__.'/uploads/avatars/';
                        if(!is_dir($dir_path))
                            @mkdir($dir_path, 0777, true);
                        $fname = $dir_path.$user_id.'.png';
                        $upload = $_FILES['avatar']['tmp_name'];
                        $type = mime_content_type($upload);
                        $allowed = array('image/png','image/jpeg');
                        if(!in_array($type,$allowed)){
                            $resp['msg'].=" But Image failed to upload due to invalid file type.";
                        }else{
                            $new_height = 200; 
                            $new_width = 200; 
                    
                            list($width, $height) = getimagesize($upload);
                            $t_image = imagecreatetruecolor($new_width, $new_height);
                            $gdImg = ($type == 'image/png')? imagecreatefrompng($upload) : imagecreatefromjpeg($upload);
                            imagecopyresampled($t_image, $gdImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                            if($gdImg){
                                if(is_file($fname))
                                    @unlink($fname);
                                imagepng($t_image,$fname);
                                imagedestroy($gdImg);
                                imagedestroy($t_image);
                            }else{
                                $resp['msg'].=" But Image failed to upload due to unknown reason.";
                            }
                        }
                    }
                }else{
                    $resp['status'] = 'failed';
                    $resp['msg'] = 'Saving User Details Failed. Error: '.$this->conn->error;
                    $resp['sql'] = $sql;
                }
            }
        } catch (Throwable $e) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Server Error: ' . $e->getMessage();
        }
        return json_encode($resp);
    }
    function delete_user(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $resp = array();
        try {
            if(!$this->conn || $this->error){
                $resp['status']='failed';
                $resp['error']="Database not connected.";
                return json_encode($resp);
            }
            $id = isset($id) ? intval($id) : 0;
            $delete = @$this->conn->query("DELETE FROM `users` where id = '{$id}'");
            if($delete){
                $resp['status']='success';
                $_SESSION['flashdata']['type'] = 'success';
                $_SESSION['flashdata']['msg'] = 'User successfully deleted.';
            }else{
                $resp['status']='failed';
                $resp['error']=$this->conn ? $this->conn->error : 'Database query failed';
            }
        } catch (Throwable $e) {
            $resp['status'] = 'failed';
            $resp['error'] = $e->getMessage();
        }
        return json_encode($resp);
    }
    function update_credentials(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $resp = array();
        try {
            if(!$this->conn || $this->error){
                $resp['status'] = 'failed';
                $resp['msg'] = 'Database not connected.';
                return json_encode($resp);
            }
            $data = "";
            foreach($_POST as $k => $v){
                if(!in_array($k,array('id','old_password')) && !empty($v)){
                    if(!empty($data)) $data .= ",";
                    if($k == 'password') $v = md5($v);
                    $v = $this->conn->real_escape_string($v);
                    $data .= " `{$k}` = '{$v}' ";
                }
            }
            if(!empty($password) && md5($old_password) != $_SESSION['password']){
                $resp['status'] = 'failed';
                $resp['msg'] = "Old password is incorrect.";
            }else{
                $sql = "UPDATE `users` set {$data} where id = '{$_SESSION['id']}'";
                $save = @$this->conn->query($sql);
                if($save){
                    $resp['status'] = 'success';
                    $_SESSION['flashdata']['type'] = 'success';
                    $_SESSION['flashdata']['msg'] = 'Credential successfully updated.';
                    foreach($_POST as $k => $v){
                        if(!in_array($k,array('id','old_password')) && !empty($v)){
                            if(!empty($data)) $data .= ",";
                            if($k == 'password') $v = md5($v);
                            $_SESSION[$k] = $v;
                        }
                    }
                    if(isset($_FILES['avatar']['tmp_name']) && !empty($_FILES['avatar']['tmp_name'])){
                        $dir_path = __DIR__.'/uploads/avatars/';
                        if(!is_dir($dir_path))
                            @mkdir($dir_path, 0777, true);
                        $fname = $dir_path.$_SESSION['id'].'.png';
                        $upload = $_FILES['avatar']['tmp_name'];
                        $type = mime_content_type($upload);
                        $allowed = array('image/png','image/jpeg');
                        if(!in_array($type,$allowed)){
                            $resp['msg'].=" But Image failed to upload due to invalid file type.";
                        }else{
                            $new_height = 200; 
                            $new_width = 200; 
                    
                            list($width, $height) = getimagesize($upload);
                            $t_image = imagecreatetruecolor($new_width, $new_height);
                            $gdImg = ($type == 'image/png')? imagecreatefrompng($upload) : imagecreatefromjpeg($upload);
                            imagecopyresampled($t_image, $gdImg, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                            if($gdImg){
                                if(is_file($fname))
                                    @unlink($fname);
                                imagepng($t_image,$fname);
                                imagedestroy($gdImg);
                                imagedestroy($t_image);
                            }else{
                                $resp['msg'].=" But Image failed to upload due to unknown reason.";
                            }
                        }
                    }
                }else{
                    $resp['status'] = 'failed';
                    $resp['msg'] = 'Updating Credentials Failed. Error: '.$this->conn->error;
                    $resp['sql'] =$sql;
                }
            }
        } catch (Throwable $e) {
            $resp['status'] = 'failed';
            $resp['msg'] = 'Server Error: ' . $e->getMessage();
        }
        return json_encode($resp);
    }
    function find_user(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $data = array();
        try {
            if(!$this->conn || $this->error){
                return json_encode($data);
            }
            $keyword = isset($keyword) ? $this->conn->real_escape_string($keyword) : '';
            $sess_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
            $sql = "SELECT *,CONCAT(firstname,' ',middlename,' ',lastname) as `name` FROM `users` where (CONCAT(firstname,' ',middlename,' ',lastname) LIKE '%{$keyword}%' OR CONCAT(lastname,' ',firstname,' ',middlename) LIKE '%{$keyword}%' OR email LIKE '%{$keyword}%') and id != '{$sess_id}'";
            $search = @$this->conn->query($sql);
            if($search && $search instanceof mysqli_result){
                while($row = $search->fetch_assoc()){
                    $row['avatar'] = is_file('./uploads/avatars/'.$row['id'].'.png') ? './uploads/avatars/'.$row['id'].'.png?v='.(!is_null($row['date_updated']) ? strtotime($row['date_updated']) : strtotime($row['date_created'])) : './images/no-image-available.png';
                    $row['id'] = md5($row['id']);
                    $data[] = $row;
                }
            }
        } catch (Throwable $e) {}
        return json_encode($data);
    }
    function send_message(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $resp = array();
        try {
            if(!$this->conn || $this->error){
                $resp['status'] = "failed";
                $resp['error'] = "Database not connected.";
                return json_encode($resp);
            }
            $from_user = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
            $to_user = isset($user_to) ? intval($user_to) : 0;
            $type = 1;
            $message = isset($message) ? $this->conn->real_escape_string($message) : '';
            $message = str_replace('/r','<br>',$message);
            $ins_message = @$this->conn->query("INSERT INTO `messages` set from_user='{$from_user}', to_user='{$to_user}', `type` = '{$type}',`message` ='{$message}' ");
            if($ins_message){
                $resp['status'] = "success";
            }else{
                $resp['status'] = "failed";
                $resp['error'] = $this->conn->error;
            }
        } catch (Throwable $e) {
            $resp['status'] = "failed";
            $resp['error'] = $e->getMessage();
        }
        return json_encode($resp);
    }
    function get_messages(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $data = array();
        try {
            if(!$this->conn || $this->error){
                return json_encode($data);
            }
            $last_id = empty($last_id) ? 0 : intval($last_id);
            $sess_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
            $convo_with = isset($convo_with) ? intval($convo_with) : 0;
            $messages = @$this->conn->query("SELECT * FROM `messages` where ((from_user = '{$sess_id}' and to_user = '{$convo_with}') OR (to_user = '{$sess_id}' and from_user = '{$convo_with}')) and id > {$last_id} order by unix_timestamp(date_created) asc ");
            $ids = array();
            if($messages && $messages instanceof mysqli_result){
                while($row = $messages->fetch_assoc()){
                    $row['message'] = str_replace('\r','<br>',$row['message']);
                    $data[] = $row;
                    if($row['from_user'] != $sess_id)
                        $ids[] = $row['id'];
                }
            }
            if(count($ids) > 0){
                $clean_ids = implode(',', array_map('intval', $ids));
                @$this->conn->query("UPDATE `messages` set status = 1 where id in ({$clean_ids}) ");
            }
        } catch (Throwable $e) {}
        return json_encode($data);
    }
    function get_prev_messages(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $data = array();
        try {
            if(!$this->conn || $this->error){
                return json_encode($data);
            }
            $sess_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
            $convo_with = isset($convo_with) ? intval($convo_with) : 0;
            $message_limit = isset($message_limit) ? intval($message_limit) : 25;
            $message_offset = isset($message_offset) ? intval($message_offset) : 0;

            $messages = @$this->conn->query("SELECT * FROM `messages` where (from_user = '{$sess_id}' and to_user = '{$convo_with}') OR (to_user = '{$sess_id}' and from_user = '{$convo_with}') order by unix_timestamp(date_created) desc limit {$message_limit} offset {$message_offset}");
            if($messages && $messages instanceof mysqli_result){
                while($row = $messages->fetch_assoc()){
                    $data[] = $row;
                }
            }
        } catch (Throwable $e) {}
        return json_encode($data);
    }
    function get_unread(){
        header('Content-Type: application/json; charset=utf-8');
        $data = array();
        try {
            if(!$this->conn || $this->error){
                return json_encode($data);
            }
            $sess_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
            $qry = @$this->conn->query("SELECT distinct(m.from_user),m.*,concat(u.firstname,' ',u.lastname) as name FROM `messages` m inner join `users` u on m.from_user = u.id where m.to_user = '{$sess_id}' and m.popped = '0' and m.status =0 order by unix_timestamp(m.date_created) desc ");
            if($qry && $qry instanceof mysqli_result){
                while($row = $qry->fetch_assoc()){
                    $row['avatar'] = is_file('./uploads/avatars/'.$row['from_user'].'.png') ? './uploads/avatars/'.$row['from_user'].'.png' : './images/no-image-available.png';
                    $row['convo_with'] = $row['from_user'];
                    $row['eid'] = md5($row['from_user']);
                    $from_uid = intval($row['from_user']);
                    $row_id = intval($row['id']);
                    @$this->conn->query("UPDATE messages set popped = 1 where id <= '{$row_id}' and from_user = '{$from_uid}' and to_user = '{$sess_id}' ");
                    $un_read_qry = @$this->conn->query("SELECT * FROM messages where to_user = '{$sess_id}' and from_user = '{$from_uid}' and status = '0' ");
                    $un_read = ($un_read_qry && $un_read_qry instanceof mysqli_result) ? $un_read_qry->num_rows : 0;
                    $row['un_read'] = $un_read > 0 ? $un_read : '';
                    $data[] = $row;
                }
            }
        } catch (Throwable $e) {}
        return json_encode($data);
    }
    function delete_message(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $resp = array();
        try {
            if(!$this->conn || $this->error){
                $resp['status'] ='failed';
                $resp['err'] = "Database not connected.";
                return json_encode($resp);
            }
            $id = isset($id) ? intval($id) : 0;
            $delete = @$this->conn->query("UPDATE messages set delete_flag = 1 where id ='{$id}'");
            if($delete){
                $resp['status'] ='success';
            }else{
                $resp['status'] ='failed';
                $resp['err'] =$this->conn->error;
            }
        } catch (Throwable $e) {
            $resp['status'] = 'failed';
            $resp['err'] = $e->getMessage();
        }
        return json_encode($resp);
    }
    function check_deleted(){
        header('Content-Type: application/json; charset=utf-8');
        extract($_POST);
        $data = array();
        try {
            if(!$this->conn || $this->error){
                return json_encode($data);
            }
            if(!empty($ids)){
                $ids = is_array($ids) ? implode(',', array_map('intval', $ids)) : $this->conn->real_escape_string($ids);
                $qry = @$this->conn->query("SELECT * FROM messages where id in ({$ids}) and delete_flag=1");
                if($qry && $qry instanceof mysqli_result){
                    while($row = $qry->fetch_assoc()){
                        $data[] = $row['id'];
                    }
                }
            }
        } catch (Throwable $e) {}
        return json_encode($data);
    }
}
$a = isset($_GET['a']) ? $_GET['a'] : '';
$action = new Actions();
switch($a){
    case 'login':
        echo $action->login();
    break;
    case 'logout':
        echo $action->logout();
    break;
    case 'save_user':
        echo $action->save_user();
    break;
    case 'delete_user':
        echo $action->delete_user();
    break;
    case 'update_credentials':
        echo $action->update_credentials();
    break;
    case 'find_user':
        echo $action->find_user();
    break;
    case 'send_message':
        echo $action->send_message();
    break;
    case 'get_messages':
        echo $action->get_messages();
    break;
    case 'get_prev_messages':
        echo $action->get_prev_messages();
    break;
    case 'get_unread':
        echo $action->get_unread();
    break;
    case 'delete_message':
        echo $action->delete_message();
    break;
    case 'check_deleted':
        echo $action->check_deleted();
    break;
    default:
    break;
}
