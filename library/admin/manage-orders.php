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
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Manage Orders</title>
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
            <div class="col-md-12">
                <h4 class="header-line">Manage Orders</h4>
            </div>
        </div>

        <div class="row">
<?php if($_SESSION['error']!=""){ ?>
            <div class="col-md-6">
                <div class="alert alert-danger">
                    <strong>Error :</strong>
                    <?php echo htmlentities($_SESSION['error']);?>
                    <?php echo htmlentities($_SESSION['error']="");?>
                </div>
            </div>
<?php } ?>
<?php if($_SESSION['msg']!=""){ ?>
            <div class="col-md-6">
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
                        Order List
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Order Number</th>
                                        <th>Student ID</th>
                                        <th>Student Name</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Payment</th>
                                        <th>Order Status</th>
                                        <th>Created Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblorders.id,tblorders.OrderNumber,tblorders.TotalAmount,tblorders.PaymentProvider,tblorders.PaymentStatus,
tblorders.OrderStatus,tblorders.StatusNote,tblorders.CreatedDate,tblstudents.StudentId,tblstudents.FullName,
COALESCE(order_items.totalItems,0) AS totalItems
FROM tblorders
JOIN tblstudents ON tblstudents.StudentId=tblorders.StudentId
LEFT JOIN (
SELECT OrderId,SUM(Quantity) AS totalItems
FROM tblorderitems
GROUP BY OrderId
) order_items ON order_items.OrderId=tblorders.id
ORDER BY FIELD(tblorders.PaymentStatus,'refund_pending','paid','refunded'), FIELD(tblorders.OrderStatus,'placed','packed','in_transit','out_for_delivery','delivered','cancelled'), tblorders.id DESC";
$query=$dbh->prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td><?php echo htmlentities($result->OrderNumber);?></td>
                                        <td><?php echo htmlentities($result->StudentId);?></td>
                                        <td><?php echo htmlentities($result->FullName);?></td>
                                        <td><?php echo htmlentities($result->totalItems);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$result->TotalAmount,2));?></td>
                                        <td><?php echo htmlentities($result->PaymentProvider . ' / ' . formatPaymentStatusLabel($result->PaymentStatus));?></td>
                                        <td>
                                            <?php echo htmlentities(formatOrderStatusLabel($result->OrderStatus));?>
<?php if(trim((string)$result->StatusNote)!==''){ ?>
                                            <br /><small><?php echo htmlentities($result->StatusNote);?></small>
<?php } ?>
                                        </td>
                                        <td><?php echo htmlentities($result->CreatedDate);?></td>
                                        <td><a href="update-order.php?orderid=<?php echo htmlentities($result->id);?>" class="btn btn-primary btn-xs">View / Update</a></td>
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
