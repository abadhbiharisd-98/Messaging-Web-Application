<div class="h-100 d-flex flex-column">
    <div class="col-12">
            <div class="hs-5 fw-bold flex-grow-1 col-auto pe-3">Welcome to Messaging Web App</div>
    </div>
    <hr>
    <div class="col-12 flex-grow-1">
        <div class="row h-100">
            <div class="col-md-4 h-100 d-flex flex-column">
                <div class="col-auto position-relative" id="search-area">
                    <div class="form-group">
                        <div class="input-group">
                            <input type="search" id="search" class="form-control rounded-0" autocomplete="off" placeholder="Search...">
                            <button type="button" class="btn bg-transparent" style="margin-left: -40px; z-index: 100;" onclick="$('#search').val('').trigger('focus')">
                            <i class="fa fa-times"></i>
                            </button>
                        </div>
                        <div class="w-100 position-absolute bg-light d-none rounded-0  shadow border border-dark" id="search-suggest">
                            <div class="list-group rounded-0" id="list-search-items">
                                
                            </div>
                            <div class="text-center  d-none" id="search-loader">
                                <div class="spinner-border spinner-border-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                            <div class="text-center d-none" id="search-no-data">
                                Unknown User
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card shadow-lg rounded-0 flex-grow-1">
                    <div class="card-header  rounded-0 py-1 w-100 d-flex align-items-center bg-primary bg-gradient text-light">
                        <h5 class="card-title">
                            Conversation List
                        </h5>
                    </div>
                    <div class="card-body  rounded-0 overflow-auto">
                    <div class="list-group" id="convo_list">
                        <?php 
                        $convo_list_arr = array();
                        if($conn && !$mydb->error){
                            if(isset($_GET['eid'])){
                                $eid_clean = $conn->real_escape_string($_GET['eid']);
                                $conn->query("UPDATE `messages` set status = 1 where to_user = '{$_SESSION['id']}' and md5(`from_user`) = '{$eid_clean}' ");
                            }
                            $convo_qry = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users where id in (SELECT to_user FROM messages where from_user = '{$_SESSION['id']}') or id in (SELECT from_user FROM messages where to_user = '{$_SESSION['id']}')");
                            if($convo_qry && $convo_qry instanceof mysqli_result){
                                while($row=$convo_qry->fetch_assoc()):
                                    $last_message_qry = $conn->query("SELECT * FROM messages where (from_user = '{$_SESSION['id']}' and to_user = '{$row['id']}') or (to_user = '{$_SESSION['id']}' and from_user = '{$row['id']}') order by unix_timestamp(`date_created`) desc limit 1");
                                    $last_row = ($last_message_qry && $last_message_qry instanceof mysqli_result && $last_message_qry->num_rows > 0) ? $last_message_qry->fetch_array() : array();
                                    $row['last_message_id'] = isset($last_row['from_user']) ? $last_row['from_user'] : '';
                                    $row['last_message'] = isset($last_row['message']) ? $last_row['message'] : '';
                                    $row['last_message_created'] = isset($last_row['message']) ? $last_row['date_created'] : '';
                                    $un_read_qry = $conn->query("SELECT * FROM messages where to_user = '{$_SESSION['id']}' and from_user = '{$row['id']}' and status = '0' ");
                                    $un_read = ($un_read_qry && $un_read_qry instanceof mysqli_result) ? $un_read_qry->num_rows : 0;
                                    $row['un_read'] = $un_read > 0 ? $un_read : '';
                                    $sort_time = !empty($row['last_message_created']) ? strtotime($row['last_message_created']) : strtotime($row['date_created']);
                                    $convo_list_arr[$sort_time] = $row;
                                endwhile;
                            }
                            $conn->query("UPDATE messages set popped = 1 where to_user = '{$_SESSION['id']}' ");
                            krsort($convo_list_arr);
                        }
                        ?>
                        <?php foreach($convo_list_arr as $row): ?>
                            <a href="./index.php?eid=<?php echo md5($row['id']) ?>" class="list-group-item list-group-item-action list-item rounded-0 convo_with" data-id = '<?php echo $row['id'] ?>'>
                                <div class="w-100 d-flex position-relative">
                                    <div class="col-2 px-1">
                                        <img src="<?php echo is_file('./uploads/avatars/'.$row['id'].'.png') ? './uploads/avatars/'.$row['id'].'.png?v='.(!is_null($row['date_updated']) ? strtotime($row['date_updated']) : strtotime($row['date_created'])) : './images/no-image-available.png' ?>" class='user-search-avatar rounded-circle bg-light border border-dark' alt="">
                                        <span class="rounded-circle notif-count badge bg-danger"><?php echo $row['un_read'] ?></span>
                                    </div>
                                    <div class="col-10 lh-1">
                                        <div class='text-truncate' title="<?php echo htmlspecialchars($row['name'] . ' ' . $row['email']) ?>"><b class="user-name"><?php echo htmlspecialchars($row['name']) ?> <span class="text-muted search-user-email">(<?php echo htmlspecialchars($row['email']) ?>)</span> </b></div>
                                        <div class="text-muted search-user-email text-truncate last-message-field" title="<?php echo htmlspecialchars($row['last_message']) ?>"><?php echo ($row['last_message_id'] == $_SESSION['id'] ? 'You: ':'').htmlspecialchars($row['last_message']) ?></div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-8 h-100" id="right-panel">
                <?php 
                $tmp_messages_arr = array();
                $messages_arr = array();
                $messages_ids = array();
                $convo_with = null;
                $user_to = null;
                $message_offset = 0;
                $message_limit = 25;
                $eid = isset($_GET['eid']) && $conn ? $conn->real_escape_string($_GET['eid']) : '-1';
                $qry = ($conn && !$mydb->error) ? $conn->query("SELECT *,CONCAT(firstname,' ',middlename,' ',lastname) as fullname FROM `users` where md5(id) = '{$eid}' ") : null;
                if($qry && $qry instanceof mysqli_result && $qry->num_rows > 0):
                    $user_to_row = $qry->fetch_array();
                    foreach($user_to_row as $k => $v){
                        $user_to[$k] = $v;
                    }
                    $convo_with = $user_to['id'];
                    $messages = $conn->query("SELECT * FROM `messages` where (from_user = '{$_SESSION['id']}' and to_user = '{$user_to['id']}') OR (to_user = '{$_SESSION['id']}' and from_user = '{$user_to['id']}') order by unix_timestamp(date_created) desc limit {$message_limit} offset {$message_offset}");
                    if($messages && $messages instanceof mysqli_result){
                        while($row=$messages->fetch_assoc()){
                            foreach($row as $k=>$v){
                                $row[$k] = $conn->real_escape_string($v);
                            }
                            $tmp_messages_arr[] = $row;
                            $messages_ids[] = $row['id'];
                        }
                    }
                    $user_to_avatar = is_file('./uploads/avatars/'.$user_to['id'].'.png') ? './uploads/avatars/'.$user_to['id'].'.png?v='.(!is_null($user_to['date_updated']) ? strtotime($user_to['date_updated']) : strtotime($user_to['date_created'])) : './images/no-image-available.png';
                    $user_to_name = $user_to['firstname'].' '.$user_to['lastname'];
                    krsort($tmp_messages_arr);
                    foreach($tmp_messages_arr as $values){
                        $messages_arr[]=$values;
                    }
                ?>
                <div class="card h-100 shadow-lg rounded-0">
                    <div class="card-header d-flex w-100 align-items-center">
                        <div class="col-auto px-1">
                            <img src="<?php echo is_file('./uploads/avatars/'.$user_to['id'].'.png') ? './uploads/avatars/'.$user_to['id'].'.png?v='.(!is_null($user_to['date_updated']) ? strtotime($user_to['date_updated']) : strtotime($user_to['date_created'])) : './images/no-image-available.png' ?>" class="rounded-circle border border-dark view-avatar" alt="">
                        </div>
                        <div class="col-auto flex-grow-1 lh-1">
                            <div><b class="user-name"><?php echo htmlspecialchars($user_to['fullname']) ?></b></div>
                            <span class="text-muted search-user-email"><?php echo htmlspecialchars($user_to['email']) ?></span>
                        </div>
                        <div class="col-auto">
                            <a href="./index.php?page=profile&user=<?php echo htmlspecialchars($_GET['eid']) ?>" class="text-decoration-none text-muted me-3" title="View Profile"><span class="fa fa-user-circle"></span></a>
                            <a href="./index.php" class="btn-close" title="Close Conversation"></a>
                        </div>
                    </div>
                    <div class="position-relative col-auto" id="prev-loader-holder">
                        <div class="position-absolute d-none" id="prev-loader">
                            <div class="d-flex justify-content-center">
                                <div class="spinner-grow spinner-grow-sm" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex flex-column h-100 w-100">
                        <div class="col-auto flex-grow-1 px-1 py-1" id="">
                            <div id="convo-field">
                                <div class="" id="convo-box">
                                    
                                </div>
                                <div class="d-none" id="end-prev-data">
                                    <center><small class='text-muted'>You've reached the top messages.</small></center>
                                </div>
                            </div>
                        </div>
                    <div class="position-relative col-auto" id="scroll-bottom-holder">
                        <div class="position-absolute d-none" id="scroll-bottom">
                            <a href="javascript:void(0)" class="bg-info bg-gradient text-center text-decoration-none text-light">New Unread Message <i class="fa fa-angle-down"></i></a>
                        </div>
                    </div>
                        <div class="col-auto bg-light bg-gradient border-top px-1 py-1" id="message-form-holder">
                            <form action="" id="message-form">
                                <input type="hidden" name="user_to" value='<?php echo $user_to['id'] ?>'>
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                    </div>
                                    <div class="col-auto flex-grow-1 mx-0">
                                        <textarea name="message" autofocus id="message" rows="3" class="form-control"></textarea>
                                    </div>
                                    <div class="col-auto">
                                        <button type="submit" class="btn btn-primary">Send</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="card h-100 shadow-lg rounded-0">
                    <div class="d-flex flex-column h-100 w-100 align-items-center justify-content-center">
                        <div style="font-size:9rem" class="text-muted"><span class="fa fa-comments"></span></div>
                        <div class="text-muted">Start a Conversation Now</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</div>
<div id="bubble_clone" class="d-none">
    <div class="w-100 bubble-from d-flex align-items-start my-2">
        <div class="col-auto bubble bg-gradient"></div>
        <div class="col-2 px-2"><a href="javascript:void(0)" class="text-muted delete-message"><div class="fa fa-trash-alt"></div></a></div>
    </div>
    <div class="w-100 bubble-to d-flex align-items-start my-2">
        <div class="col-auto bubble bg-gradient"></div>
        <div class="col-2"></div>
    </div>
</div>
<div id="user-item-clone" class="d-none">
    <a href="#" class="list-group-item list-group-item-action list-item rounded-0">
        <div class="w-100 d-flex">
            <div class="col-auto px-1">
                <img src="./images/no-image-available.png" class='user-search-avatar rounded-circle bg-light border border-dark' alt="">
            </div>
            <div class="flex-grow-1 lh-1">
                <div><b class="user-name"></b></div>
                <span class="text-muted search-user-email"></span>
            </div>
        </div>
    </a>
</div>
<div id="convo-user-clone" class=" d-none">
<a href="#" class="list-group-item list-group-item-action list-item rounded-0 convo_with" data-id = ''>
    <div class="w-100 d-flex">
        <div class="col-auto px-1">
            <img src="" class='user-search-avatar rounded-circle bg-light border border-dark' alt="">
            <span class="rounded-circle notif-count badge bg-danger"></span>
        </div>
        <div class="flex-grow-1 lh-1">
            <div class='text-truncate'><b><span class="user-name"></span><span class="text-muted search-user-email email-field"></span> </b></div>
            <div class="text-muted search-user-email last-message-field text-truncate"></div>
        </div>
    </div>
</a>
</div>
<script>
    var message_limit = '<?php echo $message_limit ?>';
    var message_offset = '<?php echo $message_offset ?>';
    var messages = $.parseJSON('<?php echo json_encode($messages_arr) ?>');
    var mids = $.parseJSON('<?php echo json_encode($messages_ids) ?>');
    var last_id = mids.length > 0 ? mids[0] : 0;
    var messageInterval;
    var nmInterval;
    var deleteInterval;
    function search_user($keyword){
        $('#list-search-items').html('')
        $('#search-loader').removeClass('d-none')
        $.ajax({
            url:'Actions.php?a=find_user',
            method:'POST',
            data:{'keyword':$keyword},
            dataType:'json',
            error:err=>{
                console.log(err)
            },
            success:function(resp){
                if(resp.length > 0){
                    Object.keys(resp).map((k)=>{
                        var list = $('#user-item-clone .list-item').clone()
                        list.attr('href','./index.php?eid='+resp[k].id)
                        list.find('.user-name').text(resp[k].name)
                        list.find('.search-user-email').text(resp[k].email)
                        list.find('.user-search-avatar').attr('src',resp[k].avatar)
                        $('#list-search-items').append(list)
                    })
                    $('#search-no-data').addClass('d-none')
                }else{
                    $('#search-no-data').removeClass('d-none')
                }
                $('#search-loader').addClass('d-none')
            }
        })
    }
    function render_bubbles(data,direction='bottom'){
        data.map(row=>{
            if(row.from_user == '<?php echo $_SESSION['id'] ?>'){
                var bubble = $('#bubble_clone .bubble-from').clone()
            }else{
                var bubble = $('#bubble_clone .bubble-to').clone()
            }
            bubble.attr('data-id',row.id)
            bubble.find('.bubble').html(row.message)
            if(direction == 'bottom')
            $('#convo-box').append(bubble)
            else
            $('#convo-box').prepend(bubble)
        })
        delete_message()
    }
    function scroll_bottom(){
        $('#convo-field').animate({scrollTop:$('#convo-box').height()},'fast')
    }
    function delete_message(){
        $('.delete-message').click(function(){
            var id = $(this).closest('.bubble-from').attr('data-id')
            _conf("Are you sure to delete this message?",'delete_message_action',[id])
        })
    }
    function delete_message_action(id){
        $('.modal').modal('hide')
        $.ajax({
            url:'Actions.php?a=delete_message',
            method:'POST',
            data:{id:id},
            dataType:'json',
            error:err=>{
                console.log(err)
                alert("An error occurred.")
            },
            success:function(resp){
                if(resp.status == 'success'){
                    $('.bubble-from[data-id="'+id+'"]').remove()
                }else{
                    alert("An error occurred.")
                }
            }
        })
    }
    function check_deleted(){
        var ids = [];
        $('.bubble-from,.bubble-to').each(function(){
            ids.push($(this).attr('data-id'))
        })
        if(ids.length > 0){
            $.ajax({
                url:'Actions.php?a=check_deleted',
                method:'POST',
                data:{ids:ids.join(',')},
                dataType:'json',
                error:err=>{
                    console.log(err)
                },
                success:function(resp){
                    if(resp.length > 0){
                        Object.keys(resp).map(k=>{
                            $('.bubble-from[data-id="'+resp[k]+'"],.bubble-to[data-id="'+resp[k]+'"]').remove()
                        })
                    }
                }
            })
        }
    }
    function get_messages(){
        var convo_with = '<?php echo $convo_with ?>';
        $.ajax({
            url:'Actions.php?a=get_messages',
            method:'POST',
            data:{last_id:last_id,convo_with:convo_with},
            dataType:'json',
            error:err=>{
                console.log(err)
            },
            success:function(resp){
                if(resp.length > 0){
                    render_bubbles(resp,'bottom')
                    last_id = resp[resp.length - 1].id
                    if($('#convo-field').scrollTop() + $('#convo-field').height() >= $('#convo-box').height() - 100){
                        scroll_bottom()
                    }else{
                        $('#scroll-bottom').removeClass('d-none')
                    }
                }
            }
        })
    }
    function get_unread(){
        $.ajax({
            url:'Actions.php?a=get_unread',
            dataType:'json',
            error:err=>{
                console.log(err)
            },
            success:function(resp){
                if(resp.length > 0){
                    resp.map(row=>{
                        if($('.convo_with[data-id="'+row.convo_with+'"]').length > 0){
                            var item = $('.convo_with[data-id="'+row.convo_with+'"]')
                            item.find('.notif-count').text(row.un_read)
                            item.find('.last-message-field').text(row.message)
                            $('#convo_list').prepend(item)
                        }else{
                            var item = $('#convo-user-clone .convo_with').clone()
                            item.attr('data-id',row.convo_with)
                            item.attr('href','./index.php?eid='+row.eid)
                            item.find('.user-name').text(row.name)
                            item.find('.user-search-avatar').attr('src',row.avatar)
                            item.find('.notif-count').text(row.un_read)
                            item.find('.last-message-field').text(row.message)
                            $('#convo_list').prepend(item)
                        }
                    })
                }
            }
        })
    }
    $(function(){
        if(messages.length > 0){
            render_bubbles(messages,'top')
            scroll_bottom()
        }
        $('#search').on('input',function(){
            var val = $(this).val()
            if(val != ''){
                $('#search-suggest').removeClass('d-none')
                search_user(val)
            }else{
                $('#search-suggest').addClass('d-none')
            }
        })
        $('#scroll-bottom a').click(function(){
            scroll_bottom()
            $('#scroll-bottom').addClass('d-none')
        })
        $('#message-form').submit(function(e){
            e.preventDefault()
            var message = $('#message').val()
            if(message == '')
            return false;
            $.ajax({
                url:'Actions.php?a=send_message',
                method:'POST',
                data:$(this).serialize(),
                dataType:'json',
                error:err=>{
                    console.log(err)
                },
                success:function(resp){
                    if(resp.status == 'success'){
                        $('#message').val('').focus()
                        get_messages()
                    }
                }
            })
        })
        if('<?php echo $convo_with ?>' != ''){
            messageInterval = setInterval(get_messages,2500)
            deleteInterval = setInterval(check_deleted,3000)
        }
        nmInterval = setInterval(get_unread,3500)
    })
</script>
