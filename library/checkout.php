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
'card_payment'=>array('label'=>'Card Payment','provider'=>'Card Gateway'),
'counter_payment'=>array('label'=>'Pay at Library Counter','provider'=>'Library Counter'),
);

function redirectToCheckout($path)
{
header('location:' . $path);
exit;
}

if(isset($_POST['place_order']))
{
$paymentMethod=isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'card_payment';
if(!isset($paymentMethods[$paymentMethod]))
{
$paymentMethod='card_payment';
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
$paymentStatus=$paymentMethod==='counter_payment' ? 'pending_confirmation' : 'paid';
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
if($paymentMethod==='counter_payment')
{
$_SESSION['msg']="Order placed successfully. Pay at the library counter and wait for admin confirmation. Order number: " . $orderNumber;
}
else {
$_SESSION['msg']="Order placed successfully. Card payment was confirmed instantly. Order number: " . $orderNumber;
}
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

        .counter-transaction-card {
            background-color: #ffffff;
            display: flex;
            width: 100%;
            min-height: 120px;
            position: relative;
            border-radius: 16px;
            transition: 0.3s ease-in-out;
            border: 1px solid #d8f3e5;
            overflow: hidden;
        }

        .counter-transaction-card:hover {
            transform: scale(1.02);
        }

        .counter-transaction-left {
            background-color: #5de2a3;
            width: 130px;
            min-height: 120px;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
            overflow: hidden;
        }

        .counter-transaction-right {
            width: calc(100% - 130px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px 18px;
        }

        .counter-arrow {
            width: 22px;
            height: 22px;
            flex: 0 0 auto;
        }

        .counter-card-mini {
            width: 70px;
            height: 46px;
            background-color: #c7ffbc;
            border-radius: 6px;
            position: absolute;
            display: flex;
            z-index: 10;
            flex-direction: column;
            align-items: center;
            box-shadow: 9px 9px 9px -2px rgba(77, 200, 143, 0.72);
        }

        .counter-card-line {
            width: 65px;
            height: 13px;
            background-color: #80ea69;
            border-radius: 2px;
            margin-top: 7px;
        }

        .counter-buttons {
            width: 8px;
            height: 8px;
            background-color: #379e1f;
            box-shadow: 0 -10px 0 0 #26850e, 0 10px 0 0 #56be3e;
            border-radius: 50%;
            transform: rotate(90deg);
            margin: 10px 0 0 -30px;
        }

        .counter-post {
            width: 63px;
            height: 75px;
            background-color: #dddde0;
            position: absolute;
            z-index: 11;
            bottom: 10px;
            top: 120px;
            border-radius: 6px;
            overflow: hidden;
        }

        .counter-post-line {
            width: 47px;
            height: 9px;
            background-color: #545354;
            position: absolute;
            border-radius: 0 0 3px 3px;
            right: 8px;
            top: 8px;
        }

        .counter-post-line:before {
            content: "";
            position: absolute;
            width: 47px;
            height: 9px;
            background-color: #757375;
            top: -8px;
        }

        .counter-screen {
            width: 47px;
            height: 23px;
            background-color: #ffffff;
            position: absolute;
            top: 22px;
            right: 8px;
            border-radius: 3px;
        }

        .counter-rupee {
            position: absolute;
            font-size: 16px;
            font-family: "Lexend Deca", sans-serif;
            width: 100%;
            left: 0;
            top: 0;
            color: #4b953b;
            text-align: center;
        }

        .counter-numbers {
            width: 12px;
            height: 12px;
            background-color: #838183;
            box-shadow: 0 -18px 0 0 #838183, 0 18px 0 0 #838183;
            border-radius: 2px;
            position: absolute;
            transform: rotate(90deg);
            left: 25px;
            top: 52px;
        }

        .counter-numbers-line2 {
            width: 12px;
            height: 12px;
            background-color: #aaa9ab;
            box-shadow: 0 -18px 0 0 #aaa9ab, 0 18px 0 0 #aaa9ab;
            border-radius: 2px;
            position: absolute;
            transform: rotate(90deg);
            left: 25px;
            top: 68px;
        }

        .counter-instructions {
            margin: 0;
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #effff5 0%, #f8fffc 100%);
            border: 1px solid #d8f3e5;
            color: #285943;
            font-size: 13px;
            line-height: 1.7;
        }

        .counter-waiting-note {
            margin: 0;
            text-align: center;
            color: #285943;
            font-size: 13px;
            line-height: 1.7;
            font-weight: 600;
        }

        .card-instructions {
            margin: 0;
            padding: 14px 16px;
            border-radius: 14px;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
            border: 1px solid #dbe8f5;
            color: #1e3a5f;
            font-size: 13px;
            line-height: 1.7;
        }

        .payment-section {
            display: none;
        }

        .payment-section.is-active {
            display: block;
        }

        .counter-transaction-card:hover .counter-card-mini {
            animation: slide-top 1.2s cubic-bezier(0.645, 0.045, 0.355, 1) both;
        }

        .counter-transaction-card:hover .counter-post {
            animation: slide-post 1s cubic-bezier(0.165, 0.84, 0.44, 1) both;
        }

        .counter-transaction-card:hover .counter-rupee {
            animation: fade-in-fwd 0.3s 1s backwards;
        }

        @keyframes slide-top {
            0% { transform: translateY(0); }
            50% { transform: translateY(-70px) rotate(90deg); }
            60% { transform: translateY(-70px) rotate(90deg); }
            100% { transform: translateY(-8px) rotate(90deg); }
        }

        @keyframes slide-post {
            50% { transform: translateY(0); }
            100% { transform: translateY(-70px); }
        }

        @keyframes fade-in-fwd {
            0% {
                opacity: 0;
                transform: translateY(-5px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
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

            .counter-transaction-card {
                flex-direction: column;
            }

            .counter-transaction-left,
            .counter-transaction-right {
                width: 100%;
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
                    <form method="post" class="form">
                        <div class="checkout-payment-top">
                            <div>
                                <h4>Choose Payment</h4>
                                <p>Select card for instant payment or pay at counter for admin confirmation.</p>
                            </div>
                            <span class="checkout-payment-pill">2 Options</span>
                        </div>
                        <div class="checkout-summary-box">
                            <p><strong>Total Items:</strong> <?php echo htmlentities($totalItems);?></p>
                            <p><strong>Grand Total:</strong> Rs. <?php echo htmlentities(number_format((float)$grandTotal,2));?></p>
                        </div>
                        <div class="payment--options">
                            <button type="button" class="payment-method-btn is-selected" data-method="card_payment" title="Card Payment">
                                <i class="fa fa-credit-card" style="font-size:20px;"></i>
                            </button>
                            <button type="button" class="payment-method-btn" data-method="counter_payment" title="Pay at Library Counter">
                                <i class="fa fa-money" style="font-size:20px;"></i>
                            </button>
                        </div>
                        <input type="hidden" name="payment_method" id="selectedPaymentMethod" value="card_payment" />

                        <div class="payment-section payment-section-card is-active" id="paymentSectionCard">
                            <div class="separator">
                                <hr class="line" />
                                <p>card payment</p>
                                <hr class="line" />
                            </div>
                            <div class="credit-card-info--form">
                                <div class="input_container">
                                    <label for="card_holder_name" class="input_label">Card holder full name</label>
                                    <input id="card_holder_name" class="input_field" type="text" placeholder="Enter your full name" value="<?php echo htmlentities(isset($_SESSION['login']) ? $_SESSION['login'] : '');?>" />
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
                            <p class="card-instructions">Card payment marks the order as paid immediately and order processing can continue without admin payment confirmation.</p>
                        </div>

                        <div class="payment-section payment-section-counter" id="paymentSectionCounter">
                        <div class="counter-transaction-card">
                            <div class="counter-transaction-left">
                                <div class="counter-card-mini">
                                    <div class="counter-card-line"></div>
                                    <div class="counter-buttons"></div>
                                </div>
                                <div class="counter-post">
                                    <div class="counter-post-line"></div>
                                    <div class="counter-screen">
                                        <div class="counter-rupee">Rs</div>
                                    </div>
                                    <div class="counter-numbers"></div>
                                    <div class="counter-numbers-line2"></div>
                                </div>
                            </div>
                            <div class="counter-transaction-right">
                                <svg viewBox="0 0 451.846 451.847" xmlns="http://www.w3.org/2000/svg" class="counter-arrow"><path fill="#cfcfcf" d="M345.441 248.292L151.154 442.573c-12.359 12.365-32.397 12.365-44.75 0-12.354-12.354-12.354-32.391 0-44.744L278.318 225.92 106.409 54.017c-12.354-12.359-12.354-32.394 0-44.748 12.354-12.359 32.391-12.359 44.75 0l194.287 194.284c6.177 6.18 9.262 14.271 9.262 22.366 0 8.099-3.091 16.196-9.267 22.373z"></path></svg>
                            </div>
                        </div>
                        <p class="counter-waiting-note">Waiting for admin approval after offline payment at counter.</p>
                        <p class="counter-instructions">Payment status will remain pending until the admin confirms that the amount was collected at the counter.</p>
                        </div>
                        <p class="demo-payment-note">Card Payment -> order direct Paid. Pay at Counter -> admin confirmation needed.</p>
<?php if($hasAvailabilityIssue){ ?>
                        <div class="alert alert-warning" style="margin-bottom:0;">
                            Update the cart first because one or more quantities exceed the current available stock.
                        </div>
                        <a href="cart.php" class="btn btn-warning btn-block">Review Cart</a>
<?php } else { ?>
                        <button type="submit" name="place_order" class="purchase--btn">Place Order</button>
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
        var cardSection=document.getElementById('paymentSectionCard');
        var counterSection=document.getElementById('paymentSectionCounter');
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

            if(cardSection){
                cardSection.className='payment-section payment-section-card' + (method==='card_payment' ? ' is-active' : '');
            }

            if(counterSection){
                counterSection.className='payment-section payment-section-counter' + (method==='counter_payment' ? ' is-active' : '');
            }
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
