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
    <style type="text/css">
        .checkout-payment-modal {
            width: 100%;
            height: fit-content;
            background: #ffffff;
            box-shadow: 0px 187px 75px rgba(0, 0, 0, 0.01), 0px 105px 63px rgba(0, 0, 0, 0.05), 0px 47px 47px rgba(0, 0, 0, 0.09), 0px 12px 26px rgba(0, 0, 0, 0.10), 0px 0px 0px rgba(0, 0, 0, 0.10);
            border-radius: 26px;
            overflow: hidden;
        }

        .checkout-payment-modal .form {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 20px;
        }

        .checkout-payment-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
        }

        .checkout-payment-top h4 {
            margin: 0 0 6px;
            font-size: 24px;
            font-weight: 800;
        }

        .checkout-payment-top p {
            margin: 0;
            color: #8b8e98;
        }

        .checkout-payment-pill {
            padding: 8px 14px;
            border-radius: 999px;
            background: #f2f2f2;
            font-size: 11px;
            font-weight: 700;
            color: #242424;
            white-space: nowrap;
        }

        .payment--options {
            width: calc(100% - 40px);
            display: grid;
            grid-template-columns: 33% 34% 33%;
            gap: 12px;
            padding: 10px;
            margin: 0 auto;
        }

        .payment--options button {
            height: 55px;
            background: #f2f2f2;
            border-radius: 11px;
            padding: 0;
            border: 0;
            outline: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .payment--options button.is-selected {
            background: #111827;
            box-shadow: 0 12px 24px rgba(17, 24, 39, 0.18);
            transform: translateY(-1px);
        }

        .payment--options button svg {
            height: 18px;
        }

        .payment--options button:last-child svg {
            height: 22px;
        }

        .payment--options button.is-selected svg {
            filter: brightness(0) invert(1);
        }

        .separator {
            width: calc(100% - 20px);
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 10px;
            color: #8b8e98;
            margin: 0 10px;
        }

        .separator > p {
            word-break: keep-all;
            display: block;
            text-align: center;
            font-weight: 600;
            font-size: 11px;
            margin: auto;
        }

        .separator .line {
            display: inline-block;
            width: 100%;
            height: 1px;
            border: 0;
            background-color: #e8e8e8;
            margin: auto;
        }

        .credit-card-info--form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .input_container {
            width: 100%;
            height: fit-content;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .split {
            display: grid;
            grid-template-columns: 4fr 2fr;
            gap: 15px;
        }

        .split input {
            width: 100%;
        }

        .input_label {
            font-size: 10px;
            color: #8b8e98;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .input_field {
            width: auto;
            height: 42px;
            padding: 0 0 0 16px;
            border-radius: 9px;
            outline: none;
            background-color: #f2f2f2;
            border: 1px solid #e5e5e500;
            transition: all 0.3s cubic-bezier(0.15, 0.83, 0.66, 1);
        }

        .input_field:focus {
            border: 1px solid transparent;
            box-shadow: 0px 0px 0px 2px #242424;
            background-color: transparent;
        }

        .purchase--btn {
            height: 55px;
            border-radius: 11px;
            border: 0;
            outline: none;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            background: linear-gradient(180deg, #363636 0%, #1b1b1b 50%, #000000 100%);
            box-shadow: 0px 0px 0px 0px #ffffff, 0px 0px 0px 0px #000000;
            transition: all 0.3s cubic-bezier(0.15, 0.83, 0.66, 1);
        }

        .purchase--btn:hover {
            box-shadow: 0px 0px 0px 2px #ffffff, 0px 0px 0px 4px #0000003a;
        }

        .checkout-summary-box {
            padding: 14px 16px;
            border-radius: 16px;
            background: linear-gradient(135deg, #fafafa 0%, #f4f4f4 100%);
        }

        .checkout-summary-box p:last-child {
            margin-bottom: 0;
        }

        .demo-payment-note {
            margin: 0;
            padding: 12px 14px;
            border-radius: 14px;
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            line-height: 1.6;
        }

        .input_field::-webkit-outer-spin-button,
        .input_field::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .input_field[type=number] {
            -moz-appearance: textfield;
        }

        @media (max-width: 767px) {
            .payment--options {
                grid-template-columns: 1fr;
                width: 100%;
                padding: 0;
            }

            .split {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                <div class="checkout-payment-modal">
                    <form method="post" class="form" id="demoCheckoutForm">
                        <div class="checkout-payment-top">
                            <div>
                                <h4>Payment Gateway</h4>
                                <p>Demo checkout with a realistic payment look and feel.</p>
                            </div>
                            <span class="checkout-payment-pill">Secure Demo</span>
                        </div>
                        <div class="checkout-summary-box">
                            <p><strong>Total Items:</strong> <?php echo htmlentities($totalItems);?></p>
                            <p><strong>Grand Total:</strong> Rs. <?php echo htmlentities(number_format((float)$grandTotal,2));?></p>
                        </div>
                        <div class="payment--options">
                            <button name="paypal" type="button" class="payment-method-btn is-selected" data-method="demo_gateway" title="Card / Net Banking (Demo)">
                                <svg xml:space="preserve" viewBox="0 0 124 33" width="124" height="33" xmlns="http://www.w3.org/2000/svg"><path d="M46.211,6.749h-6.839c-0.468,0-0.866,0.34-0.939,0.802l-2.766,17.537c-0.055,0.346,0.213,0.658,0.564,0.658h3.265c0.468,0,0.866-0.34,0.939-0.803l0.746-4.73c0.072-0.463,0.471-0.803,0.938-0.803h2.165c4.505,0,7.105-2.18,7.784-6.5c0.306-1.89,0.013-3.375-0.872-4.415C50.224,7.353,48.5,6.749,46.211,6.749z M47,13.154c-0.374,2.454-2.249,2.454-4.062,2.454h-1.032l0.724-4.583c0.043-0.277,0.283-0.481,0.563-0.481h0.473c1.235,0,2.4,0,3.002,0.704C47.027,11.668,47.137,12.292,47,13.154z" fill="#253B80"></path><path d="M66.654,13.075h-3.275c-0.279,0-0.52,0.204-0.563,0.481l-0.145,0.916l-0.229-0.332c-0.709-1.029-2.29-1.373-3.868-1.373c-3.619,0-6.71,2.741-7.312,6.586c-0.313,1.918,0.132,3.752,1.22,5.031c0.998,1.176,2.426,1.666,4.125,1.666c2.916,0,4.533-1.875,4.533-1.875l-0.146,0.91c-0.055,0.348,0.213,0.66,0.562,0.66h2.95c0.469,0,0.865-0.34,0.939-0.803l1.77-11.209C67.271,13.388,67.004,13.075,66.654,13.075z" fill="#253B80"></path><path d="M94.992,6.749h-6.84c-0.467,0-0.865,0.34-0.938,0.802l-2.766,17.537c-0.055,0.346,0.213,0.658,0.562,0.658h3.51c0.326,0,0.605-0.238,0.656-0.562l0.785-4.971c0.072-0.463,0.471-0.803,0.938-0.803h2.164c4.506,0,7.105-2.18,7.785-6.5c0.307-1.89,0.012-3.375-0.873-4.415C99.004,7.353,97.281,6.749,94.992,6.749z" fill="#179BD7"></path></svg>
                            </button>
                            <button name="apple-pay" type="button" class="payment-method-btn" data-method="upi_demo" title="UPI (Demo)">
                                <svg xml:space="preserve" viewBox="0 0 512 210.2" xmlns="http://www.w3.org/2000/svg"><path d="M93.6,27.1C87.6,34.2,78,39.8,68.4,39c-1.2-9.6,3.5-19.8,9-26.1c6-7.3,16.5-12.5,25-12.9C103.4,10,99.5,19.8,93.6,27.1 M102.3,40.9c-13.9-0.8-25.8,7.9-32.4,7.9c-6.7,0-16.8-7.5-27.8-7.3c-14.3,0.2-27.6,8.3-34.9,21.2c-15,25.8-3.9,64,10.6,85c7.1,10.4,15.6,21.8,26.8,21.4c10.6-0.4,14.8-6.9,27.6-6.9c12.9,0,16.6,6.9,27.8,6.7c11.6-0.2,18.9-10.4,26-20.8c8.1-11.8,11.4-23.3,11.6-23.9c-0.2-0.2-22.4-8.7-22.6-34.3c-0.2-21.4,17.5-31.6,18.3-32.2C123.3,42.9,107.7,41.3,102.3,40.9" id="XMLID_34_"></path></svg>
                            </button>
                            <button name="google-pay" type="button" class="payment-method-btn" data-method="counter_payment" title="Pay at Library Counter">
                                <svg fill="none" viewBox="0 0 80 39" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_134_34)"><path fill="#5F6368" d="M37.8 19.7V29H34.8V6H42.6C44.5 6 46.3001 6.7 47.7001 8C49.1001 9.2 49.8 11 49.8 12.9C49.8 14.8 49.1001 16.5 47.7001 17.8C46.3001 19.1 44.6 19.8 42.6 19.8L37.8 19.7Z"></path><path fill="#4285F4" d="M25.9 17.7C25.9 16.8 25.8 15.9 25.7 15H13.2V20.1H20.3C20 21.7 19.1 23.2 17.7 24.1V27.4H22C24.5 25.1 25.9 21.7 25.9 17.7Z"></path><path fill="#34A853" d="M13.1999 30.5999C16.7999 30.5999 19.7999 29.3999 21.9999 27.3999L17.6999 24.0999C16.4999 24.8999 14.9999 25.3999 13.1999 25.3999C9.7999 25.3999 6.7999 23.0999 5.7999 19.8999H1.3999V23.2999C3.6999 27.7999 8.1999 30.5999 13.1999 30.5999Z"></path></g><defs><clipPath id="clip0_134_34"><rect fill="white" height="38.1" width="80"></rect></clipPath></defs></svg>
                            </button>
                        </div>
                        <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="demo_gateway" />
                        <div class="separator">
                            <hr class="line" />
                            <p>or pay using credit card</p>
                            <hr class="line" />
                        </div>
                        <div class="credit-card-info--form">
                            <div class="input_container">
                                <label for="card_holder_name" class="input_label">Card holder full name</label>
                                <input id="card_holder_name" class="input_field" type="text" placeholder="Enter your full name" value="<?php echo htmlentities(isset($_SESSION['login']) ? $_SESSION['login'] : '');?>"/>
                            </div>
                            <div class="input_container">
                                <label for="card_number" class="input_label">Card Number</label>
                                <input id="card_number" class="input_field" type="text" inputmode="numeric" placeholder="0000 0000 0000 0000" value="4242 4242 4242 4242" />
                            </div>
                            <div class="input_container">
                                <label for="expiry_date" class="input_label">Expiry Date / CVV</label>
                                <div class="split">
                                    <input id="expiry_date" class="input_field" type="text" placeholder="12/28" value="12/28" />
                                    <input id="cvv" class="input_field" type="password" inputmode="numeric" placeholder="123" value="123" />
                                </div>
                            </div>
                        </div>
                        <p class="demo-payment-note">This is a demo payment UI for a realistic checkout experience. Any option will still place a demo paid order in the current project.</p>
<?php if($hasAvailabilityIssue){ ?>
                        <div class="alert alert-warning" style="margin-bottom:0;">
                            Update the cart first because one or more quantities exceed the current available stock.
                        </div>
                        <a href="cart.php" class="btn btn-warning btn-block">Review Cart</a>
<?php } else { ?>
                        <button type="submit" name="place_order" class="purchase--btn">Checkout</button>
<?php } ?>
                    </form>
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
    <script type="text/javascript">
    (function () {
        var buttons=document.getElementsByClassName('payment-method-btn');
        var paymentInput=document.getElementById('selectedPaymentMethod');
        var i=0;

        if(!paymentInput){
            return;
        }

        function setSelected(method) {
            for(i=0;i<buttons.length;i++){
                if(buttons[i].getAttribute('data-method')===method){
                    buttons[i].className='payment-method-btn is-selected';
                } else {
                    buttons[i].className='payment-method-btn';
                }
            }
            paymentInput.value=method;
        }

        for(i=0;i<buttons.length;i++){
            buttons[i].onclick=function () {
                setSelected(this.getAttribute('data-method'));
            };
        }

        setSelected(paymentInput.value);
    })();
    </script>
</body>
</html>
