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
$orderid=intval($_GET['orderid']);
if($orderid<=0)
{
$_SESSION['error']="Invalid order selected.";
header('location:my-orders.php');
exit;
}

if(isset($_POST['cancel_order']))
{
$cancelResult=cancelOrderForStudent($dbh, $orderid, $sid);
if($cancelResult['success'])
{
$_SESSION['msg']=$cancelResult['message'];
}
else {
$_SESSION['error']=$cancelResult['message'];
}
header('location:order-details.php?orderid=' . $orderid);
exit;
}

$orderSql="SELECT id,OrderNumber,TotalAmount,PaymentMethod,PaymentProvider,PaymentStatus,OrderStatus,StatusNote,TransactionId,CreatedDate
FROM tblorders
WHERE id=:orderid AND StudentId=:sid
LIMIT 1";
$orderQuery=$dbh->prepare($orderSql);
$orderQuery->bindParam(':orderid',$orderid,PDO::PARAM_INT);
$orderQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$orderQuery->execute();
$order=$orderQuery->fetch(PDO::FETCH_ASSOC);
if(!$order)
{
$_SESSION['error']="Order not found.";
header('location:my-orders.php');
exit;
}

$itemSql="SELECT tblorderitems.Quantity,tblorderitems.UnitPrice,tblorderitems.LineTotal,
tblbooks.id AS BookId,tblbooks.BookName,tblbooks.ISBNNumber,tblauthors.AuthorName
FROM tblorderitems
INNER JOIN tblbooks ON tblbooks.id=tblorderitems.BookId
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
WHERE tblorderitems.OrderId=:orderid
ORDER BY tblorderitems.id ASC";
$itemQuery=$dbh->prepare($itemSql);
$itemQuery->bindParam(':orderid',$orderid,PDO::PARAM_INT);
$itemQuery->execute();
$items=$itemQuery->fetchAll(PDO::FETCH_OBJ);

$recommendedBooks=array();
if($order['OrderStatus']==='delivered')
{
$recommendedBooks=fetchRecommendedBooks($dbh, $sid, 4);
foreach($items as $index => $item)
{
$items[$index]->canReview=canStudentReviewBook($dbh, $sid, $item->BookId);
$items[$index]->studentReview=fetchStudentBookReview($dbh, $sid, $item->BookId);
}
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Order Details</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .review-order-card {
            border: 1px solid #eee;
            background: #fafafa;
            padding: 15px;
            margin-bottom: 15px;
            min-height: 210px;
        }

        .recommendation-item {
            border: 1px solid #eee;
            background: #fafafa;
            padding: 15px;
            margin-bottom: 15px;
            min-height: 180px;
        }

        .recommendation-item h5,
        .review-order-card h5 {
            margin-top: 0;
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-8">
                <h4 class="header-line">Order Details</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="my-orders.php" class="btn btn-default">Back to Orders</a>
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
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Order Summary
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Order Number:</strong> <?php echo htmlentities($order['OrderNumber']);?></p>
                                <p><strong>Order Date:</strong> <?php echo htmlentities($order['CreatedDate']);?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Payment Method:</strong> <?php echo htmlentities($order['PaymentMethod']);?></p>
                                <p><strong>Payment Provider:</strong> <?php echo htmlentities($order['PaymentProvider']);?></p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Payment Status:</strong> <?php echo htmlentities(formatPaymentStatusLabel($order['PaymentStatus']));?></p>
                                <p><strong>Order Status:</strong> <?php echo htmlentities(formatOrderStatusLabel($order['OrderStatus']));?></p>
                            </div>
                        </div>
                        <p><strong>Transaction ID:</strong> <?php echo htmlentities($order['TransactionId']);?></p>
                        <p><strong>Total Amount:</strong> Rs. <?php echo htmlentities(number_format((float)$order['TotalAmount'],2));?></p>
<?php if(trim((string)$order['StatusNote'])!==''){ ?>
                        <p><strong>Order Note:</strong> <?php echo htmlentities($order['StatusNote']);?></p>
<?php } ?>
<?php if(canUserCancelOrder($order['OrderStatus'])){ ?>
                        <form method="post" style="margin-top:15px;">
                            <button type="submit" name="cancel_order" class="btn btn-danger" onclick="return confirm('Cancel this order? Your money will be refunded shortly.');">Cancel Order</button>
                        </form>
<?php } elseif($order['OrderStatus']==='cancelled' && $order['PaymentStatus']==='refund_pending') { ?>
                        <div class="alert alert-warning" style="margin-top:15px;">
                            Your order is cancelled. Your money will be refunded shortly.
                        </div>
<?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Ordered Books
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
                                        <td><a href="book-details.php?bookid=<?php echo htmlentities($item->BookId);?>"><?php echo htmlentities($item->BookName);?></a></td>
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

<?php if($order['OrderStatus']==='delivered'){ ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-success">
                    <div class="panel-heading">
                        Review Your Delivered Books
                    </div>
                    <div class="panel-body">
                        <p>These books are now delivered, so the user can submit a review and the system can improve recommendations from that feedback.</p>
                        <div class="row">
<?php foreach($items as $item){ ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="review-order-card">
                                    <h5><?php echo htmlentities($item->BookName);?></h5>
                                    <p><strong>Author:</strong> <?php echo htmlentities($item->AuthorName);?></p>
                                    <p><strong>Quantity:</strong> <?php echo htmlentities($item->Quantity);?></p>
<?php if(!empty($item->studentReview)){ ?>
                                    <p><strong>Your Rating:</strong> <?php echo htmlentities($item->studentReview['Rating']);?> / 5</p>
                                    <p><?php echo nl2br(htmlentities(getDisplayValue($item->studentReview['ReviewText'], 'No review text added.')));?></p>
                                    <a href="book-details.php?bookid=<?php echo htmlentities($item->BookId);?>" class="btn btn-primary btn-sm">Update Review</a>
<?php } elseif($item->canReview) { ?>
                                    <p>Your review is now available for this delivered book.</p>
                                    <a href="book-details.php?bookid=<?php echo htmlentities($item->BookId);?>" class="btn btn-success btn-sm">Write Review</a>
<?php } else { ?>
                                    <div class="alert alert-info" style="margin-bottom:0;">Review will appear after delivery is fully confirmed.</div>
<?php } ?>
                                </div>
                            </div>
<?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Recommended Next Reads
                    </div>
                    <div class="panel-body">
                        <p>Recommendations are based on your activity, ratings, and review text similarity.</p>
                        <div class="row">
<?php if(!empty($recommendedBooks)){ ?>
<?php foreach($recommendedBooks as $recommendedBook){ ?>
                            <div class="col-md-3 col-sm-6">
                                <div class="recommendation-item">
                                    <h5><?php echo htmlentities(getDisplayValue($recommendedBook['BookName'], 'Untitled Book'));?></h5>
                                    <p><strong>Author:</strong> <?php echo htmlentities(getDisplayValue($recommendedBook['AuthorName'], 'Author not assigned'));?></p>
                                    <p><strong>Category:</strong> <?php echo htmlentities(getDisplayValue($recommendedBook['CategoryName'], 'Category not assigned'));?></p>
                                    <p><strong>Available:</strong> <?php echo htmlentities($recommendedBook['availableQty']);?></p>
                                    <p><strong>Rating:</strong> <?php echo htmlentities(number_format((float)$recommendedBook['averageRating'],1));?> / 5</p>
                                    <a href="book-details.php?bookid=<?php echo htmlentities($recommendedBook['bookid']);?>" class="btn btn-info btn-sm">Open Details</a>
                                </div>
                            </div>
<?php } ?>
<?php } else { ?>
                            <div class="col-md-12">
                                <div class="alert alert-info" style="margin-bottom:0;">More personalized recommendations will appear after more reading activity and reviews.</div>
                            </div>
<?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php } ?>
    </div>
    </div>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
