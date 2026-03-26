<?php
session_start();
error_reporting(0);
include('includes/config.php');
include_once('includes/store-helpers.php');
if (empty($_SESSION['login']) || empty($_SESSION['stdid']))
{
unset($_SESSION['login']);
unset($_SESSION['stdid']);
header('location:index.php');
exit;
}

$sid=$_SESSION['stdid'];
if(isset($_POST['cancel_order']))
{
$orderid=intval($_POST['orderid']);
$cancelResult=cancelOrderForStudent($dbh, $orderid, $sid);
if($cancelResult['success'])
{
$_SESSION['msg']=$cancelResult['message'];
}
else {
$_SESSION['error']=$cancelResult['message'];
}
header('location:my-orders.php');
exit;
}

if(isset($_POST['confirm_received']))
{
$orderid=intval($_POST['orderid']);
$confirmResult=confirmDeliveredOrderForStudent($dbh, $orderid, $sid);
if($confirmResult['success'])
{
$_SESSION['msg']=$confirmResult['message'];
}
else {
$_SESSION['error']=$confirmResult['message'];
}
header('location:my-orders.php');
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
    <title>Online Library Management System | My Orders</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .order-history-panel {
            border: 1px solid #dfe7f0;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .order-history-panel .panel-heading {
            background: linear-gradient(135deg, #eff8ff 0%, #f8fbff 100%);
            border-bottom: 1px solid #d8e5f2;
            font-size: 18px;
            font-weight: 700;
            color: #1f3b57;
        }

        .table-order-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef4ff;
            color: #27548a;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .uiverse-btn {
            position: relative;
            overflow: hidden;
            border: 0;
            border-radius: 999px;
            padding: 8px 14px;
            font-weight: 700;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
            box-shadow: 0 12px 24px rgba(33, 37, 41, 0.12);
        }

        .uiverse-btn:hover {
            transform: translateY(-1px);
        }

        .uiverse-btn--primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
        }

        .uiverse-btn--danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
        }

        .uiverse-btn--success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            animation: pulseGlow 1.8s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.28); }
            70% { box-shadow: 0 0 0 14px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-8">
                <h4 class="header-line">My Orders</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="listed-books.php" class="btn btn-default">Browse Books</a>
            </div>
        </div>

        <div class="row">
        <?php if($_SESSION['error']!="")
        {?>
        <div class="col-md-12">
            <div class="alert alert-danger">
                <strong>Error :</strong>
                <?php echo htmlentities($_SESSION['error']);?>
                <?php echo htmlentities($_SESSION['error']="");?>
            </div>
        </div>
        <?php } ?>
        <?php if($_SESSION['msg']!="")
        {?>
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
                <div class="panel panel-default order-history-panel">
                    <div class="panel-heading">
                        Order History
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Order Number</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblorders.id,tblorders.OrderNumber,tblorders.TotalAmount,tblorders.PaymentProvider,tblorders.PaymentStatus,
tblorders.OrderStatus,tblorders.StatusNote,tblorders.CreatedDate,COALESCE(order_items.totalItems,0) AS totalItems
FROM tblorders
LEFT JOIN (
SELECT OrderId,SUM(Quantity) AS totalItems
FROM tblorderitems
GROUP BY OrderId
) order_items ON order_items.OrderId=tblorders.id
WHERE tblorders.StudentId=:sid
ORDER BY tblorders.id DESC";
$query = $dbh->prepare($sql);
$query->bindParam(':sid',$sid,PDO::PARAM_STR);
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
                                        <td><?php echo htmlentities($result->totalItems);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$result->TotalAmount,2));?></td>
                                        <td><?php echo htmlentities($result->PaymentProvider . ' / ' . formatPaymentStatusLabel($result->PaymentStatus));?></td>
                                        <td>
                                            <span class="table-order-status"><?php echo htmlentities(formatOrderStatusLabel($result->OrderStatus));?></span>
<?php if(trim((string)$result->StatusNote)!==''){ ?>
                                            <br /><small><?php echo htmlentities($result->StatusNote);?></small>
<?php } ?>
                                        </td>
                                        <td><?php echo htmlentities($result->CreatedDate);?></td>
                                        <td>
                                            <a href="order-details.php?orderid=<?php echo htmlentities($result->id);?>" class="btn btn-xs uiverse-btn uiverse-btn--primary">View Details</a>
<?php if(canUserCancelOrder($result->OrderStatus)){ ?>
                                                <form method="post" style="display:inline-block; margin-left:6px;">
                                                <input type="hidden" name="orderid" value="<?php echo htmlentities($result->id);?>">
                                                <button type="submit" name="cancel_order" class="btn btn-xs uiverse-btn uiverse-btn--danger" onclick="return confirm('Cancel this order? Your money will be refunded shortly.');">Cancel Order</button>
                                            </form>
<?php } elseif(canUserConfirmDeliveredOrder($result->OrderStatus)) { ?>
                                            <form method="post" style="display:inline-block; margin-left:6px;">
                                                <input type="hidden" name="orderid" value="<?php echo htmlentities($result->id);?>">
                                                <button type="submit" name="confirm_received" class="btn btn-xs uiverse-btn uiverse-btn--success" onclick="return confirm('Confirm that you received this order? This will mark it completed for admin too.');">Confirm Received</button>
                                            </form>
<?php } ?>
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
