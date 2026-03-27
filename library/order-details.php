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

if(isset($_POST['confirm_received']))
{
$confirmResult=confirmDeliveredOrderForStudent($dbh, $orderid, $sid);
if($confirmResult['success'])
{
$_SESSION['msg']=$confirmResult['message'];
}
else {
$_SESSION['error']=$confirmResult['message'];
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
        .order-summary-shell {
            border: 1px solid #dfe7f0;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
        }

        .order-summary-shell .panel-heading {
            background: linear-gradient(135deg, #eff8ff 0%, #f8fbff 100%);
            border-bottom: 1px solid #d8e5f2;
            font-size: 18px;
            font-weight: 700;
            color: #1f3b57;
        }

        .status-chip {
            display: inline-block;
            padding: 7px 14px;
            border-radius: 999px;
            background: #eef4ff;
            color: #27548a;
            font-weight: 700;
        }

        .delivery-confirm-card {
            border: 1px solid #c9f0df;
            border-radius: 20px;
            background: linear-gradient(135deg, #f1fff8 0%, #fcfffd 100%);
            padding: 20px;
            margin-top: 18px;
            box-shadow: 0 18px 40px rgba(16, 185, 129, 0.12);
            animation: floatCard 2.2s ease-in-out infinite;
        }

        .delivery-confirm-card h4 {
            margin-top: 0;
            font-weight: 700;
        }

        .review-order-card {
            border: 1px solid #eee;
            background: #fafafa;
            padding: 15px;
            margin-bottom: 15px;
            min-height: 210px;
            border-radius: 16px;
        }

        .recommendation-item {
            border: 1px solid #eee;
            background: #fafafa;
            padding: 15px;
            margin-bottom: 15px;
            min-height: 180px;
            border-radius: 16px;
        }

        .recommendation-item h5,
        .review-order-card h5 {
            margin-top: 0;
        }

        .uiverse-btn {
            position: relative;
            overflow: hidden;
            border: 0;
            border-radius: 999px;
            padding: 11px 18px;
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

        @keyframes floatCard {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }

        .delivery-tracker-card {
            margin-top: 18px;
            padding: 22px 20px 18px;
            border: 1px solid #dbe8f5;
            border-radius: 22px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            box-shadow: 0 18px 40px rgba(37, 99, 235, 0.10);
        }

        .delivery-tracker-card h4 {
            margin-top: 0;
            margin-bottom: 8px;
            font-weight: 700;
            color: #16324f;
        }

        .delivery-tracker-card p {
            margin-bottom: 18px;
            color: #52606d;
        }

        .delivery-loader {
            width: fit-content;
            height: fit-content;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }

        .delivery-loader .truckWrapper {
            width: 220px;
            height: 110px;
            display: flex;
            flex-direction: column;
            position: relative;
            align-items: center;
            justify-content: flex-end;
            overflow-x: hidden;
        }

        .delivery-loader .truckBody {
            width: 136px;
            margin-bottom: 8px;
            animation: truckMotion 1s linear infinite;
        }

        .delivery-loader .trucksvg {
            width: 100%;
            height: auto;
        }

        .delivery-loader .truckTires {
            width: 136px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 11px 0 17px;
            position: absolute;
            bottom: 0;
        }

        .delivery-loader .tiresvg {
            width: 24px;
            height: 24px;
            animation: tyreSpin 0.9s linear infinite;
        }

        .delivery-loader .road {
            width: 100%;
            height: 2px;
            background-color: #282828;
            position: relative;
            bottom: 0;
            align-self: flex-end;
            border-radius: 3px;
        }

        .delivery-loader .road:before,
        .delivery-loader .road:after {
            content: "";
            position: absolute;
            height: 100%;
            background-color: #282828;
            border-radius: 3px;
            animation: roadAnimation 1.4s linear infinite;
        }

        .delivery-loader .road:before {
            width: 22px;
            right: -50%;
            border-left: 10px solid #fff;
        }

        .delivery-loader .road:after {
            width: 12px;
            right: -68%;
            border-left: 4px solid #fff;
        }

        .delivery-loader .lampPost {
            position: absolute;
            bottom: 0;
            right: -90%;
            height: 90px;
            animation: roadAnimation 1.4s linear infinite;
        }

        @keyframes truckMotion {
            0% { transform: translateY(0); }
            50% { transform: translateY(3px); }
            100% { transform: translateY(0); }
        }

        @keyframes roadAnimation {
            0% { transform: translateX(0); }
            100% { transform: translateX(-350px); }
        }

        @keyframes tyreSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <div class="panel panel-info order-summary-shell">
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
                                <p><strong>Payment Status:</strong> <span class="status-chip"><?php echo htmlentities(formatPaymentStatusLabel($order['PaymentStatus']));?></span></p>
                                <p><strong>Order Status:</strong> <span class="status-chip"><?php echo htmlentities(formatOrderStatusLabel($order['OrderStatus']));?></span></p>
                            </div>
                        </div>
                        <p><strong>Transaction ID:</strong> <?php echo htmlentities($order['TransactionId']);?></p>
                        <p><strong>Total Amount:</strong> Rs. <?php echo htmlentities(number_format((float)$order['TotalAmount'],2));?></p>
<?php if(trim((string)$order['StatusNote'])!==''){ ?>
                        <p><strong>Order Note:</strong> <?php echo htmlentities($order['StatusNote']);?></p>
<?php } ?>
<?php if(in_array($order['OrderStatus'], array('in_transit','out_for_delivery'), true)){ ?>
                        <div class="delivery-tracker-card">
                            <h4><?php echo $order['OrderStatus']==='in_transit' ? 'Your order is in transit' : 'Your order is out for delivery'; ?></h4>
                            <p><?php echo $order['OrderStatus']==='in_transit' ? 'The package is moving through delivery. The animation will continue while the order is in transit and out for delivery.' : 'The package is on the way for final delivery. This animation will stay visible until the admin marks the order as delivered.'; ?></p>
                            <div class="delivery-loader" aria-hidden="true">
                                <div class="truckWrapper">
                                    <div class="truckBody">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 198 93" class="trucksvg">
                                            <path stroke-width="3" stroke="#282828" fill="#F83D3D" d="M135 22.5H177.264C178.295 22.5 179.22 23.133 179.594 24.0939L192.33 56.8443C192.442 57.1332 192.5 57.4404 192.5 57.7504V89C192.5 90.3807 191.381 91.5 190 91.5H135C133.619 91.5 132.5 90.3807 132.5 89V25C132.5 23.6193 133.619 22.5 135 22.5Z"></path>
                                            <path stroke-width="3" stroke="#282828" fill="#7D7C7C" d="M146 33.5H181.741C182.779 33.5 183.709 34.1415 184.078 35.112L190.538 52.112C191.16 53.748 189.951 55.5 188.201 55.5H146C144.619 55.5 143.5 54.3807 143.5 53V36C143.5 34.6193 144.619 33.5 146 33.5Z"></path>
                                            <path stroke-width="2" stroke="#282828" fill="#282828" d="M150 65C150 65.39 149.763 65.8656 149.127 66.2893C148.499 66.7083 147.573 67 146.5 67C145.427 67 144.501 66.7083 143.873 66.2893C143.237 65.8656 143 65.39 143 65C143 64.61 143.237 64.1344 143.873 63.7107C144.501 63.2917 145.427 63 146.5 63C147.573 63 148.499 63.2917 149.127 63.7107C149.763 64.1344 150 64.61 150 65Z"></path>
                                            <rect stroke-width="2" stroke="#282828" fill="#FFFCAB" rx="1" height="7" width="5" y="63" x="187"></rect>
                                            <rect stroke-width="2" stroke="#282828" fill="#282828" rx="1" height="11" width="4" y="81" x="193"></rect>
                                            <rect stroke-width="3" stroke="#282828" fill="#DFDFDF" rx="2.5" height="90" width="121" y="1.5" x="6.5"></rect>
                                            <rect stroke-width="2" stroke="#282828" fill="#DFDFDF" rx="2" height="4" width="6" y="84" x="1"></rect>
                                        </svg>
                                    </div>
                                    <div class="truckTires">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 30 30" class="tiresvg">
                                            <circle stroke-width="3" stroke="#282828" fill="#282828" r="13.5" cy="15" cx="15"></circle>
                                            <circle fill="#DFDFDF" r="7" cy="15" cx="15"></circle>
                                        </svg>
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 30 30" class="tiresvg">
                                            <circle stroke-width="3" stroke="#282828" fill="#282828" r="13.5" cy="15" cx="15"></circle>
                                            <circle fill="#DFDFDF" r="7" cy="15" cx="15"></circle>
                                        </svg>
                                    </div>
                                    <div class="road"></div>
                                    <svg xml:space="preserve" viewBox="0 0 453.459 453.459" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns="http://www.w3.org/2000/svg" class="lampPost">
                                        <path d="M252.882,0c-37.781,0-68.686,29.953-70.245,67.358h-6.917v8.954c-26.109,2.163-45.463,10.011-45.463,19.366h9.993c-1.65,5.146-2.507,10.54-2.507,16.017c0,28.956,23.558,52.514,52.514,52.514c28.956,0,52.514-23.558,52.514-52.514c0-5.478-0.856-10.872-2.506-16.017h9.992c0-9.354-19.352-17.204-45.463-19.366v-8.954h-6.149C200.189,38.779,223.924,16,252.882,16c29.952,0,54.32,24.368,54.32,54.32c0,28.774-11.078,37.009-25.105,47.437c-17.444,12.968-37.216,27.667-37.216,78.884v113.914h-0.797c-5.068,0-9.174,4.108-9.174,9.177c0,2.844,1.293,5.383,3.321,7.066c-3.432,27.933-26.851,95.744-8.226,115.459v11.202h45.75v-11.202c18.625-19.715-4.794-87.527-8.227-115.459c2.029-1.683,3.322-4.223,3.322-7.066c0-5.068-4.107-9.177-9.176-9.177h-0.795V196.641c0-43.174,14.942-54.283,30.762-66.043c14.793-10.997,31.559-23.461,31.559-60.277C323.202,31.545,291.656,0,252.882,0zM232.77,111.694c0,23.442-19.071,42.514-42.514,42.514c-23.442,0-42.514-19.072-42.514-42.514c0-5.531,1.078-10.957,3.141-16.017h78.747C231.693,100.736,232.77,106.162,232.77,111.694z"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>
<?php } ?>
<?php if(canUserCancelOrder($order['OrderStatus'])){ ?>
                        <form method="post" style="margin-top:15px;">
                            <button type="submit" name="cancel_order" class="btn uiverse-btn uiverse-btn--danger" onclick="return confirm('Cancel this order? Your money will be refunded shortly.');">Cancel Order</button>
                        </form>
<?php } elseif(canUserConfirmDeliveredOrder($order['OrderStatus'])) { ?>
                        <div class="delivery-confirm-card">
                            <h4>Order Delivered?</h4>
                            <p>If you already received the package, confirm it here. The order will be marked completed automatically for admin.</p>
                            <form method="post" style="margin-top:10px;">
                                <button type="submit" name="confirm_received" class="btn uiverse-btn uiverse-btn--success" onclick="return confirm('Confirm that you received this order? This will mark it completed for admin too.');">Yes, I Received It</button>
                            </form>
                        </div>
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

<?php if(in_array($order['OrderStatus'], array('delivered','completed'), true)){ ?>
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
                                    <a href="book-details.php?bookid=<?php echo htmlentities($item->BookId);?>" class="btn uiverse-btn uiverse-btn--primary btn-sm">Update Review</a>
<?php } elseif($item->canReview) { ?>
                                    <p>Your review is now available for this delivered book.</p>
                                    <a href="book-details.php?bookid=<?php echo htmlentities($item->BookId);?>" class="btn uiverse-btn uiverse-btn--success btn-sm">Write Review</a>
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
                                    <a href="book-details.php?bookid=<?php echo htmlentities($recommendedBook['bookid']);?>" class="btn uiverse-btn uiverse-btn--primary btn-sm">Open Details</a>
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
