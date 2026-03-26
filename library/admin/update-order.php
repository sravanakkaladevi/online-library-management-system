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

$orderid=intval($_GET['orderid']);
if($orderid<=0)
{
$_SESSION['error']="Invalid order selected.";
header('location:manage-orders.php');
exit;
}

$orderStatusOptions=getOrderStatusOptions();
$paymentStatusOptions=getPaymentStatusOptions();

if(isset($_POST['update_order']))
{
$orderStatus=isset($_POST['orderstatus']) ? trim($_POST['orderstatus']) : 'placed';
$paymentStatus=isset($_POST['paymentstatus']) ? trim($_POST['paymentstatus']) : 'paid';
$statusNote=trim($_POST['statusnote']);

if(!isset($orderStatusOptions[$orderStatus]))
{
$orderStatus='placed';
}

if(!isset($paymentStatusOptions[$paymentStatus]))
{
$paymentStatus='paid';
}

if($orderStatus==='cancelled' && $paymentStatus==='paid')
{
$paymentStatus='refund_pending';
}

if($orderStatus==='cancelled' && $statusNote==='')
{
$statusNote='Order cancelled. Refund will be processed shortly.';
}

$updateSql="UPDATE tblorders
SET OrderStatus=:orderstatus,PaymentStatus=:paymentstatus,StatusNote=:statusnote
WHERE id=:orderid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':orderstatus',$orderStatus,PDO::PARAM_STR);
$updateQuery->bindParam(':paymentstatus',$paymentStatus,PDO::PARAM_STR);
$updateQuery->bindParam(':statusnote',$statusNote,PDO::PARAM_STR);
$updateQuery->bindParam(':orderid',$orderid,PDO::PARAM_INT);
$updateQuery->execute();

$_SESSION['msg']="Order status updated successfully.";
header('location:update-order.php?orderid=' . $orderid);
exit;
}

$orderSql="SELECT tblorders.id,tblorders.OrderNumber,tblorders.StudentId,tblorders.TotalAmount,tblorders.PaymentMethod,
tblorders.PaymentProvider,tblorders.PaymentStatus,tblorders.OrderStatus,tblorders.StatusNote,tblorders.TransactionId,
tblorders.CreatedDate,tblorders.UpdatedDate,tblstudents.FullName,tblstudents.EmailId,tblstudents.MobileNumber
FROM tblorders
JOIN tblstudents ON tblstudents.StudentId=tblorders.StudentId
WHERE tblorders.id=:orderid
LIMIT 1";
$orderQuery=$dbh->prepare($orderSql);
$orderQuery->bindParam(':orderid',$orderid,PDO::PARAM_INT);
$orderQuery->execute();
$order=$orderQuery->fetch(PDO::FETCH_ASSOC);
if(!$order)
{
$_SESSION['error']="Order not found.";
header('location:manage-orders.php');
exit;
}

$itemSql="SELECT tblorderitems.Quantity,tblorderitems.UnitPrice,tblorderitems.LineTotal,
tblbooks.BookName,tblbooks.ISBNNumber,tblauthors.AuthorName
FROM tblorderitems
JOIN tblbooks ON tblbooks.id=tblorderitems.BookId
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
WHERE tblorderitems.OrderId=:orderid
ORDER BY tblorderitems.id ASC";
$itemQuery=$dbh->prepare($itemSql);
$itemQuery->bindParam(':orderid',$orderid,PDO::PARAM_INT);
$itemQuery->execute();
$items=$itemQuery->fetchAll(PDO::FETCH_OBJ);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Update Order</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-8">
                <h4 class="header-line">Update Order</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="manage-orders.php" class="btn btn-default">Back to Orders</a>
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
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Order Summary
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Order Number:</strong> <?php echo htmlentities($order['OrderNumber']);?></p>
                                <p><strong>Order Date:</strong> <?php echo htmlentities($order['CreatedDate']);?></p>
                                <p><strong>Updated Date:</strong> <?php echo htmlentities($order['UpdatedDate']!="" ? $order['UpdatedDate'] : '--');?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Student ID:</strong> <?php echo htmlentities($order['StudentId']);?></p>
                                <p><strong>Student Name:</strong> <?php echo htmlentities($order['FullName']);?></p>
                                <p><strong>Email:</strong> <?php echo htmlentities($order['EmailId']);?></p>
                                <p><strong>Mobile:</strong> <?php echo htmlentities($order['MobileNumber']);?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Payment Method:</strong> <?php echo htmlentities($order['PaymentMethod']);?></p>
                                <p><strong>Payment Provider:</strong> <?php echo htmlentities($order['PaymentProvider']);?></p>
                                <p><strong>Transaction ID:</strong> <?php echo htmlentities($order['TransactionId']);?></p>
                                <p><strong>Total Amount:</strong> Rs. <?php echo htmlentities(number_format((float)$order['TotalAmount'],2));?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Update Status
                    </div>
                    <div class="panel-body">
                        <form method="post">
                            <div class="form-group">
                                <label>Order Status</label>
                                <select name="orderstatus" class="form-control" required>
<?php foreach($orderStatusOptions as $statusValue => $statusLabel){ ?>
                                    <option value="<?php echo htmlentities($statusValue);?>" <?php if($order['OrderStatus']===$statusValue){ echo 'selected'; } ?>><?php echo htmlentities($statusLabel);?></option>
<?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Payment Status</label>
                                <select name="paymentstatus" class="form-control" required>
<?php foreach($paymentStatusOptions as $paymentValue => $paymentLabel){ ?>
                                    <option value="<?php echo htmlentities($paymentValue);?>" <?php if($order['PaymentStatus']===$paymentValue){ echo 'selected'; } ?>><?php echo htmlentities($paymentLabel);?></option>
<?php } ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Status Note</label>
                                <textarea name="statusnote" rows="5" class="form-control" placeholder="Example: Packed and ready for dispatch"><?php echo htmlentities($order['StatusNote']);?></textarea>
                            </div>
                            <button type="submit" name="update_order" class="btn btn-primary">Update Order</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Current Status
                    </div>
                    <div class="panel-body">
                        <p><strong>Order Status:</strong> <?php echo htmlentities(formatOrderStatusLabel($order['OrderStatus']));?></p>
                        <p><strong>Payment Status:</strong> <?php echo htmlentities(formatPaymentStatusLabel($order['PaymentStatus']));?></p>
<?php if(trim((string)$order['StatusNote'])!==''){ ?>
                        <p><strong>Note:</strong> <?php echo htmlentities($order['StatusNote']);?></p>
<?php } ?>
                        <div class="alert alert-info" style="margin-top:15px;">
                            Suggested flow: Placed -> Packed -> In Transit -> Out For Delivery -> Delivered -> Completed.
                            If cancelled, set payment to Refund Pending or Refunded.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Order Items
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book Name</th>
                                        <th>Author</th>
                                        <th>ISBN</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$cnt=1;
foreach($items as $item)
{
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td><?php echo htmlentities($item->BookName);?></td>
                                        <td><?php echo htmlentities($item->AuthorName);?></td>
                                        <td><?php echo htmlentities($item->ISBNNumber);?></td>
                                        <td><?php echo htmlentities($item->Quantity);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$item->UnitPrice,2));?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$item->LineTotal,2));?></td>
                                    </tr>
<?php
$cnt=$cnt+1;
}
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
    <script src="assets/js/custom.js"></script>
</body>
</html>
