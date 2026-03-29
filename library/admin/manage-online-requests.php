<?php
session_start();
error_reporting(0);
include('includes/config.php');
include_once('../includes/store-helpers.php');
if(strlen($_SESSION['alogin'])==0)
{
header('location:index.php');
exit;
}

if(isset($_POST['approve_request']) || isset($_POST['reject_request']))
{
$orderid=isset($_POST['orderid']) ? intval($_POST['orderid']) : 0;
if($orderid<=0)
{
$_SESSION['error']="Invalid online request selected.";
header('location:manage-online-requests.php');
exit;
}

$paymentStatus=isset($_POST['approve_request']) ? 'paid' : 'payment_rejected';
$statusNote=isset($_POST['statusnote']) ? trim((string)$_POST['statusnote']) : '';
if($statusNote==='')
{
$statusNote=isset($_POST['approve_request']) ? 'Online 1-year rent approved by admin.' : 'Online 1-year rent rejected by admin.';
}

$updateSql="UPDATE tblorders
SET PaymentStatus=:paymentstatus, StatusNote=:statusnote
WHERE id=:orderid AND OrderType='read_online'";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':paymentstatus',$paymentStatus,PDO::PARAM_STR);
$updateQuery->bindParam(':statusnote',$statusNote,PDO::PARAM_STR);
$updateQuery->bindParam(':orderid',$orderid,PDO::PARAM_INT);
$updateQuery->execute();

if($paymentStatus==='paid')
{
$accessResult=ensureReadOnlineAccessForOrder($dbh, $orderid);
$_SESSION['msg']=$accessResult['success'] ? 'Online request approved and preview permission added.' : $accessResult['message'];
}
else {
$_SESSION['msg']='Online request rejected successfully.';
}

header('location:manage-online-requests.php');
exit;
}

$sortDate=isset($_GET['sort_date']) ? trim((string)$_GET['sort_date']) : 'new';
$orderBySql=($sortDate==='old') ? 'tblorders.CreatedDate ASC, tblorders.id ASC' : 'tblorders.CreatedDate DESC, tblorders.id DESC';
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Manage Online Rent Requests</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-8">
                <h4 class="header-line">Manage Online Rent Requests</h4>
            </div>
            <div class="col-md-4 text-right">
                <form method="get" class="form-inline" style="display:inline-block;">
                    <label for="sort_date" style="margin-right:8px;">Date Order</label>
                    <select name="sort_date" id="sort_date" class="form-control" onchange="this.form.submit()">
                        <option value="new" <?php if($sortDate==='new'){ echo 'selected'; } ?>>New to Old</option>
                        <option value="old" <?php if($sortDate==='old'){ echo 'selected'; } ?>>Old to New</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="row">
<?php if($_SESSION['error']!=""){ ?>
            <div class="col-md-12">
                <div class="alert alert-danger">
                    <strong>Error :</strong>
                    <?php echo htmlentities($_SESSION['error']);?>
                    <?php echo htmlentities($_SESSION['error']="");?>
                </div>
            </div>
<?php } ?>
<?php if($_SESSION['msg']!=""){ ?>
            <div class="col-md-12">
                <div class="alert alert-success">
                    <strong>Success :</strong>
                    <?php echo htmlentities($_SESSION['msg']);?>
                    <?php echo htmlentities($_SESSION['msg']="");?>
                </div>
            </div>
<?php } ?>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Online 1-Year Rent Permission Requests
                    </div>
                    <div class="panel-body">
                        <div class="alert alert-info">
                            Use this screen to accept or reject online 1-year reading permission requests. Accepting a request unlocks the preview link for the user.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Order Number</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Book Name</th>
                                        <th>Amount</th>
                                        <th>Payment Status</th>
                                        <th>Requested On</th>
                                        <th>Permission</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblorders.id,tblorders.OrderNumber,tblorders.TotalAmount,tblorders.PaymentStatus,tblorders.OrderStatus,
tblorders.CreatedDate,tblorders.StatusNote,tblstudents.StudentId,tblstudents.FullName,tblbooks.BookName
FROM tblorders
INNER JOIN tblstudents ON tblstudents.StudentId=tblorders.StudentId
INNER JOIN tblorderitems ON tblorderitems.OrderId=tblorders.id
INNER JOIN tblbooks ON tblbooks.id=tblorderitems.BookId
WHERE tblorders.OrderType='read_online'
ORDER BY " . $orderBySql;
$query=$dbh->prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
$permissionLabel='Pending';
if($result->PaymentStatus==='paid')
{
$permissionLabel='Accepted';
}
elseif($result->PaymentStatus==='payment_rejected')
{
$permissionLabel='Rejected';
}
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td><?php echo htmlentities($result->OrderNumber);?></td>
                                        <td><?php echo htmlentities($result->StudentId);?></td>
                                        <td><?php echo htmlentities($result->FullName);?></td>
                                        <td><?php echo htmlentities($result->BookName);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$result->TotalAmount,2));?></td>
                                        <td><?php echo htmlentities(formatPaymentStatusLabel($result->PaymentStatus));?></td>
                                        <td><?php echo htmlentities($result->CreatedDate);?></td>
                                        <td>
                                            <?php echo htmlentities($permissionLabel);?>
<?php if(trim((string)$result->StatusNote)!==''){ ?>
                                            <br /><small><?php echo htmlentities($result->StatusNote);?></small>
<?php } ?>
                                        </td>
                                        <td>
<?php if($result->OrderStatus!=='cancelled' && $result->PaymentStatus!=='paid'){ ?>
                                            <form method="post" style="display:inline-block; margin:0 4px 4px 0;">
                                                <input type="hidden" name="orderid" value="<?php echo htmlentities($result->id);?>">
                                                <input type="hidden" name="statusnote" value="Online 1-year rent approved by admin.">
                                                <button type="submit" name="approve_request" class="btn btn-success btn-xs">Accept</button>
                                            </form>
<?php } ?>
<?php if($result->OrderStatus!=='cancelled' && $result->PaymentStatus!=='payment_rejected'){ ?>
                                            <form method="post" style="display:inline-block; margin:0 4px 4px 0;">
                                                <input type="hidden" name="orderid" value="<?php echo htmlentities($result->id);?>">
                                                <input type="hidden" name="statusnote" value="Online 1-year rent rejected by admin.">
                                                <button type="submit" name="reject_request" class="btn btn-warning btn-xs">Reject</button>
                                            </form>
<?php } ?>
                                            <a href="update-order.php?orderid=<?php echo htmlentities($result->id);?>" class="btn btn-primary btn-xs">View</a>
                                        </td>
                                    </tr>
<?php
$cnt=$cnt+1;
}}
?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/dataTables/jquery.dataTables.js"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
