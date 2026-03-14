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
$paymentMethods=array(
'demo_gateway'=>array('label'=>'Card / Net Banking (Demo Gateway)','provider'=>'Demo Gateway'),
'upi_demo'=>array('label'=>'UPI (Demo)','provider'=>'Demo UPI'),
'counter_payment'=>array('label'=>'Pay at Library Counter','provider'=>'Library Counter'),
);

function redirectToCheckout($path)
{
header('location:' . $path);
exit;
}

if(isset($_POST['place_order']))
{
$paymentMethod=isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'demo_gateway';
if(!isset($paymentMethods[$paymentMethod]))
{
$paymentMethod='demo_gateway';
}

try {
$dbh->beginTransaction();

$studentSql="SELECT Status FROM tblstudents WHERE StudentId=:sid FOR UPDATE";
$studentQuery=$dbh->prepare($studentSql);
$studentQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$studentQuery->execute();
$student=$studentQuery->fetch(PDO::FETCH_ASSOC);
if(!$student || (int)$student['Status']!==1)
{
throw new Exception("Your account is not active for checkout.");
}

$cartSql="SELECT BookId,Quantity FROM tblcart WHERE StudentId=:sid FOR UPDATE";
$cartQuery=$dbh->prepare($cartSql);
$cartQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$cartQuery->execute();
$cartRows=$cartQuery->fetchAll(PDO::FETCH_ASSOC);
if(empty($cartRows))
{
throw new Exception("Your cart is empty.");
}

$bookIds=array();
$cartMap=array();
foreach($cartRows as $cartRow)
{
$bookIds[]=(int)$cartRow['BookId'];
$cartMap[(int)$cartRow['BookId']]=(int)$cartRow['Quantity'];
}

$placeholders=implode(',', array_fill(0, count($bookIds), '?'));

$bookSql="SELECT id,BookName,BookPrice,bookQty FROM tblbooks WHERE id IN (" . $placeholders . ") FOR UPDATE";
$bookQuery=$dbh->prepare($bookSql);
$bookQuery->execute($bookIds);
$bookRows=$bookQuery->fetchAll(PDO::FETCH_ASSOC);
if(count($bookRows)!==count($bookIds))
{
throw new Exception("One or more books in your cart are no longer available.");
}

$issueSql="SELECT BookId,SUM(CASE WHEN RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0 THEN 1 ELSE 0 END) AS activeIssues
FROM tblissuedbookdetails
WHERE BookId IN (" . $placeholders . ")
GROUP BY BookId";
$issueQuery=$dbh->prepare($issueSql);
$issueQuery->execute($bookIds);
$issueRows=$issueQuery->fetchAll(PDO::FETCH_ASSOC);
$issueMap=array();
foreach($issueRows as $issueRow)
{
$issueMap[(int)$issueRow['BookId']]=(int)$issueRow['activeIssues'];
}

$soldSql="SELECT tblorderitems.BookId,SUM(tblorderitems.Quantity) AS soldQty
FROM tblorderitems
INNER JOIN tblorders ON tblorders.id=tblorderitems.OrderId
WHERE tblorders.PaymentStatus='paid' AND tblorders.OrderStatus<>'cancelled'
AND tblorderitems.BookId IN (" . $placeholders . ")
GROUP BY tblorderitems.BookId";
$soldQuery=$dbh->prepare($soldSql);
$soldQuery->execute($bookIds);
$soldRows=$soldQuery->fetchAll(PDO::FETCH_ASSOC);
$soldMap=array();
foreach($soldRows as $soldRow)
{
$soldMap[(int)$soldRow['BookId']]=(int)$soldRow['soldQty'];
}

$totalAmount=0;
$validatedItems=array();
foreach($bookRows as $bookRow)
{
$bookId=(int)$bookRow['id'];
$quantity=isset($cartMap[$bookId]) ? (int)$cartMap[$bookId] : 0;
$activeIssues=isset($issueMap[$bookId]) ? (int)$issueMap[$bookId] : 0;
$soldQty=isset($soldMap[$bookId]) ? (int)$soldMap[$bookId] : 0;
$availableQty=calculateAvailableBookQty($bookRow['bookQty'], $activeIssues, $soldQty);
if($quantity<=0)
{
throw new Exception("Invalid quantity found in your cart.");
}
if($quantity>$availableQty)
{
throw new Exception("Only " . $availableQty . " copies are available right now for " . $bookRow['BookName'] . ".");
}

$unitPrice=(float)$bookRow['BookPrice'];
$lineTotal=$unitPrice*$quantity;
$totalAmount+=$lineTotal;
$validatedItems[]=
array(
'BookId'=>$bookId,
'BookName'=>$bookRow['BookName'],
'Quantity'=>$quantity,
'UnitPrice'=>$unitPrice,
'LineTotal'=>$lineTotal,
);
}

$orderNumber=generateOrderNumber();
$transactionId=generateTransactionId();
$provider=$paymentMethods[$paymentMethod]['provider'];
$paymentStatus='paid';
$orderStatus='placed';
$insertOrderSql="INSERT INTO tblorders(OrderNumber,StudentId,TotalAmount,PaymentMethod,PaymentProvider,PaymentStatus,OrderStatus,TransactionId)
VALUES(:ordernumber,:sid,:totalamount,:paymentmethod,:provider,:paymentstatus,:orderstatus,:transactionid)";
$insertOrderQuery=$dbh->prepare($insertOrderSql);
$insertOrderQuery->bindParam(':ordernumber',$orderNumber,PDO::PARAM_STR);
$insertOrderQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$insertOrderQuery->bindParam(':totalamount',$totalAmount);
$insertOrderQuery->bindParam(':paymentmethod',$paymentMethod,PDO::PARAM_STR);
$insertOrderQuery->bindParam(':provider',$provider,PDO::PARAM_STR);
$insertOrderQuery->bindParam(':paymentstatus',$paymentStatus,PDO::PARAM_STR);
$insertOrderQuery->bindParam(':orderstatus',$orderStatus,PDO::PARAM_STR);
$insertOrderQuery->bindParam(':transactionid',$transactionId,PDO::PARAM_STR);
$insertOrderQuery->execute();
$orderId=$dbh->lastInsertId();

$itemSql="INSERT INTO tblorderitems(OrderId,BookId,Quantity,UnitPrice,LineTotal) VALUES(:orderid,:bookid,:quantity,:unitprice,:linetotal)";
$itemQuery=$dbh->prepare($itemSql);
foreach($validatedItems as $validatedItem)
{
$itemQuery->bindValue(':orderid',$orderId,PDO::PARAM_INT);
$itemQuery->bindValue(':bookid',$validatedItem['BookId'],PDO::PARAM_INT);
$itemQuery->bindValue(':quantity',$validatedItem['Quantity'],PDO::PARAM_INT);
$itemQuery->bindValue(':unitprice',$validatedItem['UnitPrice']);
$itemQuery->bindValue(':linetotal',$validatedItem['LineTotal']);
$itemQuery->execute();
}

$clearSql="DELETE FROM tblcart WHERE StudentId=:sid";
$clearQuery=$dbh->prepare($clearSql);
$clearQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$clearQuery->execute();

$dbh->commit();
$_SESSION['msg']="Order placed successfully. Order number: " . $orderNumber;
redirectToCheckout('order-details.php?orderid=' . $orderId);
}
catch (Exception $e)
{
if($dbh->inTransaction())
{
$dbh->rollBack();
}
$_SESSION['error']=$e->getMessage();
redirectToCheckout('checkout.php');
}
}

$cartItems=fetchCartItems($dbh, $sid);
$grandTotal=0;
$totalItems=0;
$hasAvailabilityIssue=false;
foreach($cartItems as $cartItem)
{
$grandTotal+=$cartItem['lineTotal'];
$totalItems+=(int)$cartItem['Quantity'];
if((int)$cartItem['Quantity']>(int)$cartItem['availableQty'])
{
$hasAvailabilityIssue=true;
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
    <title>Online Library Management System | Checkout</title>
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
                <h4 class="header-line">Checkout</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="cart.php" class="btn btn-default">Back to Cart</a>
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

<?php if(empty($cartItems)){ ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <h4>Your cart is empty.</h4>
                        <p>Add books to your cart before visiting checkout.</p>
                        <a href="listed-books.php" class="btn btn-primary">Browse Books</a>
                    </div>
                </div>
            </div>
        </div>
<?php } else { ?>
        <div class="row">
            <div class="col-md-8">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Order Review
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$cnt=1;
foreach($cartItems as $cartItem)
{
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td>
                                            <?php echo htmlentities($cartItem['BookName']);?><br />
                                            <small><?php echo htmlentities($cartItem['AuthorName']);?> | ISBN: <?php echo htmlentities($cartItem['ISBNNumber']);?></small>
<?php if((int)$cartItem['Quantity']>(int)$cartItem['availableQty']){ ?>
                                            <br /><span style="color:red;">Only <?php echo htmlentities($cartItem['availableQty']);?> available now</span>
<?php } ?>
                                        </td>
                                        <td><?php echo htmlentities($cartItem['Quantity']);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$cartItem['BookPrice'],2));?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$cartItem['lineTotal'],2));?></td>
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
            <div class="col-md-4">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Payment Gateway
                    </div>
                    <div class="panel-body">
                        <form method="post">
                            <p><strong>Total Items:</strong> <?php echo htmlentities($totalItems);?></p>
                            <p><strong>Grand Total:</strong> Rs. <?php echo htmlentities(number_format((float)$grandTotal,2));?></p>
                            <p><strong>Payment Mode:</strong></p>
<?php foreach($paymentMethods as $paymentKey => $paymentValue){ ?>
                            <div class="radio">
                                <label>
                                    <input type="radio" name="payment_method" value="<?php echo htmlentities($paymentKey);?>" <?php if($paymentKey==='demo_gateway'){ echo 'checked'; } ?> />
                                    <?php echo htmlentities($paymentValue['label']);?>
                                </label>
                            </div>
<?php } ?>
                            <div class="alert alert-info" style="margin-top:15px;">
                                This project now uses a demo payment gateway step. It marks orders as paid immediately and can be replaced later with Razorpay or Stripe.
                            </div>
<?php if($hasAvailabilityIssue){ ?>
                            <div class="alert alert-warning">
                                Update the cart first because one or more quantities exceed the current available stock.
                            </div>
                            <a href="cart.php" class="btn btn-warning btn-block">Review Cart</a>
<?php } else { ?>
                            <button type="submit" name="place_order" class="btn btn-success btn-block">Pay and Place Order</button>
<?php } ?>
                        </form>
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
