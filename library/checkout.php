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
'google_pay'=>array('label'=>'Google Pay','provider'=>'Google Pay'),
'apple_pay'=>array('label'=>'Apple Pay','provider'=>'Apple Pay'),
'paypal'=>array('label'=>'PayPal','provider'=>'PayPal'),
'upi'=>array('label'=>'UPI','provider'=>'UPI'),
'counter_payment'=>array('label'=>'Pay at Library Counter','provider'=>'Library Counter'),
);

function redirectToCheckout($path)
{
header('location:' . $path);
exit;
}

if(isset($_POST['read_online']))
{
$bookid=intval($_POST['bookid']);
$orderType='read_online';
if($bookid<=0)
{
$_SESSION['error']="Invalid book selected.";
redirectToCheckout('listed-books.php');
}

$book=fetchBookWithInventory($dbh, $bookid);
if(!$book)
{
$_SESSION['error']="Book not found.";
redirectToCheckout('listed-books.php');
}

// For read_online, assume a fixed price, say 50% of book price or fixed amount. Let's use a fixed rental price, say Rs. 100 for 1 year.
$rentalPrice=100.00; // Fixed price for read online

// Check if already has access
if(hasStudentPaidOnlineAccessToBook($dbh, $sid, $bookid))
{
$_SESSION['error']="You already have online access to this book.";
redirectToCheckout('listed-books.php');
}

// Create order directly
try {
$dbh->beginTransaction();

$studentSql="SELECT Status FROM tblstudents WHERE StudentID=:sid FOR UPDATE";
$studentQuery=$dbh->prepare($studentSql);
$studentQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$studentQuery->execute();
$student=$studentQuery->fetch(PDO::FETCH_ASSOC);
if(!$student || (int)$student['Status']!==1)
{
throw new Exception("Your account is not active.");
}

$orderNumber='ORD-' . time() . '-' . $sid;
$paymentMethod='card_payment'; // Default for read_online
$paymentProvider=$paymentMethods[$paymentMethod]['provider'];

$orderSql="INSERT INTO tblorders (OrderNumber, OrderType, StudentId, TotalAmount, PaymentMethod, PaymentProvider, PaymentStatus, OrderStatus)
VALUES (:orderNumber, :orderType, :sid, :totalAmount, :paymentMethod, :paymentProvider, 'pending_confirmation', 'placed')";
$orderQuery=$dbh->prepare($orderSql);
$orderQuery->bindParam(':orderNumber',$orderNumber,PDO::PARAM_STR);
$orderQuery->bindParam(':orderType',$orderType,PDO::PARAM_STR);
$orderQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$orderQuery->bindParam(':totalAmount',$rentalPrice,PDO::PARAM_STR);
$orderQuery->bindParam(':paymentMethod',$paymentMethod,PDO::PARAM_STR);
$orderQuery->bindParam(':paymentProvider',$paymentProvider,PDO::PARAM_STR);
$orderQuery->execute();
$orderId=$dbh->lastInsertId();

$itemSql="INSERT INTO tblorderitems (OrderId, BookId, Quantity, UnitPrice, LineTotal)
VALUES (:orderId, :bookId, 1, :unitPrice, :lineTotal)";
$itemQuery=$dbh->prepare($itemSql);
$itemQuery->bindParam(':orderId',$orderId,PDO::PARAM_INT);
$itemQuery->bindParam(':bookId',$bookid,PDO::PARAM_INT);
$itemQuery->bindParam(':unitPrice',$rentalPrice,PDO::PARAM_STR);
$itemQuery->bindParam(':lineTotal',$rentalPrice,PDO::PARAM_STR);
$itemQuery->execute();

$dbh->commit();
$_SESSION['msg']="1-year online rent order placed successfully. Proceed to demo payment.";
header('location:checkout.php?orderid=' . $orderId);
exit;
} catch(Exception $e) {
$dbh->rollBack();
$_SESSION['error']="Failed to place order: " . $e->getMessage();
redirectToCheckout('listed-books.php');
}
}

$orderId=isset($_GET['orderid']) ? intval($_GET['orderid']) : 0;
$checkoutItems=array();
$totalAmount=0;
$orderType='buy';
if($orderId>0)
{
$orderSql="SELECT OrderType, PaymentStatus, OrderStatus FROM tblorders WHERE id=:orderId AND StudentId=:sid LIMIT 1";
$orderQuery=$dbh->prepare($orderSql);
$orderQuery->bindParam(':orderId',$orderId,PDO::PARAM_INT);
$orderQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$orderQuery->execute();
$order=$orderQuery->fetch(PDO::FETCH_ASSOC);
if(!$order)
{
$_SESSION['error']="Order not found.";
redirectToCheckout('listed-books.php');
}
$orderType=$order['OrderType'];
if($order['PaymentStatus']==='paid' && $order['OrderStatus']==='completed')
{
$_SESSION['msg']="Order already completed.";
redirectToCheckout('my-orders.php');
}

$itemSql="SELECT tblorderitems.Quantity,tblorderitems.UnitPrice,tblorderitems.LineTotal,tblbooks.BookName,tblbooks.ISBNNumber
FROM tblorderitems
JOIN tblbooks ON tblbooks.id=tblorderitems.BookId
WHERE tblorderitems.OrderId=:orderId
ORDER BY tblorderitems.id ASC";
$itemQuery=$dbh->prepare($itemSql);
$itemQuery->bindParam(':orderId',$orderId,PDO::PARAM_INT);
$itemQuery->execute();
$checkoutItems=$itemQuery->fetchAll(PDO::FETCH_OBJ);
foreach($checkoutItems as $item)
{
$totalAmount+=(float)$item->LineTotal;
}
}
else
{
// Existing cart logic
$cartSql="SELECT BookId,Quantity FROM tblcart WHERE StudentId=:sid FOR UPDATE";
$cartQuery=$dbh->prepare($cartSql);
$cartQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$cartQuery->execute();
$cartRows=$cartQuery->fetchAll(PDO::FETCH_ASSOC);
if(empty($cartRows))
{
$_SESSION['error']="Your cart is empty.";
redirectToCheckout('cart.php');
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
$_SESSION['error']="One or more books in your cart are no longer available.";
redirectToCheckout('cart.php');
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

$validatedItems[]=array(
'BookId'=>$bookId,
'BookName'=>$bookRow['BookName'],
'ISBNNumber'=>$bookRow['ISBNNumber'],
'Quantity'=>$quantity,
'UnitPrice'=>$unitPrice,
'LineTotal'=>$lineTotal
);
}
$checkoutItems=$validatedItems;
}

if(isset($_POST['place_order']))
{
$paymentMethod=isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'card_payment';
if(!isset($paymentMethods[$paymentMethod]))
{
$paymentMethod='card_payment';
}

$orderIdFromPost=isset($_POST['orderid']) ? intval($_POST['orderid']) : 0;
$orderTypeFromPost=isset($_POST['order_type']) ? trim($_POST['order_type']) : 'buy';

try {
$dbh->beginTransaction();

if($orderIdFromPost>0)
{
$orderVerifySql="SELECT id,StudentId,PaymentStatus,OrderStatus,OrderType FROM tblorders WHERE id=:orderId FOR UPDATE";
$orderVerifyQuery=$dbh->prepare($orderVerifySql);
$orderVerifyQuery->bindParam(':orderId',$orderIdFromPost,PDO::PARAM_INT);
$orderVerifyQuery->execute();
$orderVerify=$orderVerifyQuery->fetch(PDO::FETCH_ASSOC);
if(!$orderVerify || $orderVerify['StudentId']!==$sid)
{
throw new Exception("Invalid order for checkout.");
}
if($orderVerify['OrderStatus']==='cancelled')
{
throw new Exception("Cancelled orders cannot be processed.");
}

// keep pending confirmation until admin approves (update-order handles paid->online access)
$updateOrderSql="UPDATE tblorders SET PaymentMethod=:paymentMethod, PaymentProvider=:paymentProvider, PaymentStatus='pending_confirmation' WHERE id=:orderId";
$updateOrderQuery=$dbh->prepare($updateOrderSql);
$updateOrderQuery->bindParam(':paymentMethod',$paymentMethod,PDO::PARAM_STR);
$updateOrderQuery->bindParam(':paymentProvider',$paymentMethods[$paymentMethod]['provider'],PDO::PARAM_STR);
$updateOrderQuery->bindParam(':orderId',$orderIdFromPost,PDO::PARAM_INT);
$updateOrderQuery->execute();

$dbh->commit();
$_SESSION['msg']="Payment submitted, pending admin confirmation. Online preview will unlock after admin confirms payment.";
header('location:order-details.php?orderid=' . $orderIdFromPost);
exit;
}

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
$paymentStatus='pending_confirmation';
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
$_SESSION['msg']="Order placed successfully. Pay at the library counter and wait for admin payment approval before online reading opens. Order number: " . $orderNumber;
}
else {
$_SESSION['msg']="Order placed successfully. Payment was recorded and is waiting for admin approval. Online reading will open after admin marks the payment as paid. Order number: " . $orderNumber;
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

// Use existing checkoutItems if read-online or existing order
if($orderId>0 && !empty($checkoutItems))
{
foreach($checkoutItems as $item)
{
$grandTotal+=(float)$item->LineTotal;
$totalItems+=(int)$item->Quantity;
}
$cartItems=$checkoutItems;
}
else
{
// Cart mode: use fetchCartItems for buy orders
foreach($cartItems as $cartItem)
{
$grandTotal+=$cartItem['lineTotal'];
$totalItems+=(int)$cartItem['Quantity'];
if((int)$cartItem['Quantity']>(int)$cartItem['availableQty'])
{
$hasAvailabilityIssue=true;
}
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

        .payment--options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            align-items: stretch;
        }

        .payment-method-radio {
            cursor: pointer;
            flex: 1;
            min-width: 60px;
            max-width: 80px;
        }

        .payment-method-radio input[type="radio"] {
            display: none;
        }

        .payment-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 8px 4px;
            background: #f2f2f2;
            border-radius: 11px;
            border: 2px solid transparent;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
        }

        .payment-method-radio input[type="radio"]:checked + .payment-card {
            background: #111827;
            border-color: #374151;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .payment-method-radio input[type="radio"]:checked + .payment-card .payment-icon-wrapper,
        .payment-method-radio input[type="radio"]:checked + .payment-card .payment-label {
            color: #ffffff;
        }

        .payment-icon-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
            margin-bottom: 2px;
        }

        .payment-label {
            font-size: 9px;
            font-weight: 700;
            color: #333;
            text-align: center;
            white-space: nowrap;
        }

        .payment-method-radio:hover .payment-card {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .payment-method-radio input[type="radio"]:checked + .payment-card .payment-icon-wrapper svg path,
        .payment-method-radio input[type="radio"]:checked + .payment-card .payment-icon-wrapper i {
            filter: brightness(0) invert(1);
        }

        .payment-section-upi {
            display: none;
        }

        .payment-section-upi.is-active {
            display: block;
        }

        @media (max-width: 767px) {
            .payment--options {
                grid-template-columns: 1fr;
                width: 100%;
                padding: 0;
            }

            .payment-method-radio {
                min-width: auto;
                max-width: none;
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
// Handle both arrays (from fetchCartItems) and objects (from tblorderitems)
$bookName=is_object($cartItem) ? $cartItem->BookName : $cartItem['BookName'];
$authorName=is_object($cartItem) ? (isset($cartItem->AuthorName) ? $cartItem->AuthorName : 'N/A') : $cartItem['AuthorName'];
$isbn=is_object($cartItem) ? $cartItem->ISBNNumber : $cartItem['ISBNNumber'];
$quantity=is_object($cartItem) ? $cartItem->Quantity : $cartItem['Quantity'];
$unitPrice=is_object($cartItem) ? $cartItem->UnitPrice : $cartItem['BookPrice'];
$lineTotal=is_object($cartItem) ? $cartItem->LineTotal : $cartItem['lineTotal'];
$availableQty=is_object($cartItem) ? 999 : (isset($cartItem['availableQty']) ? $cartItem['availableQty'] : 999);
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td>
                                            <?php echo htmlentities($bookName);?><br />
                                            <small><?php echo htmlentities($authorName);?><?php if($isbn){ echo ' | ISBN: ' . htmlentities($isbn); } ?></small>
<?php if((int)$quantity>(int)$availableQty){ ?>
                                            <br /><span style="color:red;">Only <?php echo htmlentities($availableQty);?> available now</span>
<?php } ?>
                                        </td>
                                        <td><?php echo htmlentities($quantity);?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$unitPrice,2));?></td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$lineTotal,2));?></td>
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
                                <p>Pay now, then wait for admin to approve the payment before online reading is unlocked.</p>
                            </div>
                            <span class="checkout-payment-pill">2 Options</span>
                        </div>
                        <div class="checkout-summary-box">
                            <p><strong>Total Items:</strong> <?php echo htmlentities($totalItems);?></p>
                            <p><strong>Grand Total:</strong> Rs. <?php echo htmlentities(number_format((float)$grandTotal,2));?></p>
                        </div>
                        <div class="payment--options">
                            <label class="payment-method-radio">
                                <input type="radio" name="payment_method_radio" value="card_payment" checked>
                                <div class="payment-card">
                                    <div class="payment-icon-wrapper">
                                        <i class="fa fa-credit-card" style="font-size:18px;"></i>
                                    </div>
                                    <span class="payment-label">Card</span>
                                </div>
                            </label>
                            <label class="payment-method-radio">
                                <input type="radio" name="payment_method_radio" value="google_pay">
                                <div class="payment-card">
                                    <div class="payment-icon-wrapper">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18" height="18">
                                            <path fill="#4285F4" d="M24 9.5c3.3 0 6.3 1.3 8.5 3.5l6.2-6.2C31.8 3.5 28.1 2 24 2 13.7 2 5.6 9.2 2.6 19.2l7.4 5.8c1.8-5.1 6.2-8.8 11.6-8.8z"/>
                                            <path fill="#34A853" d="M45.5 24.5c0-1.6-.2-3.2-.5-4.7H24v9h12.7c-.5 2.8-2.1 5.2-4.5 6.8l7.4 5.8c4.3-4 6.8-9.9 6.8-16.6z"/>
                                            <path fill="#FBBC05" d="M10.2 28.3c-1-2.8-1-6.1 0-8.9l-7.4-5.8c-3.4 6.8-3.4 14.9 0 21.7l7.4-5.8z"/>
                                            <path fill="#EA4335" d="M24 46c5.3 0 9.8-1.7 13.1-4.6l-7.4-5.8c-1.8 1.2-4.1 1.9-6.6 1.9-5.2 0-9.6-3.5-11.2-8.3l-7.4 11.3C5.6 38.8 13.7 46 24 46z"/>
                                        </svg>
                                    </div>
                                    <span class="payment-label">GPay</span>
                                </div>
                            </label>
                            <label class="payment-method-radio">
                                <input type="radio" name="payment_method_radio" value="apple_pay">
                                <div class="payment-card">
                                    <div class="payment-icon-wrapper">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18" height="18">
                                            <path fill="#000" d="M34.6 13.4h-2.5v-2c0-1.1-.5-1.9-1.7-1.9h-4.7c-1.2 0-1.7.8-1.7 1.9v2h-2.5c-1.1 0-1.9.9-1.9 1.9v2c0 1 .8 1.9 1.9 1.9h2.5v2c0 1.1.5 1.9 1.7 1.9h4.7c1.2 0 1.7-.8 1.7-1.9v-2h2.5c1.1 0 1.9-.9 1.9-1.9v-2c0-1-.8-1.9-1.9-1.9zm-7.7 5.1c-.4-.3-1-.5-1.9-.5-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3c-.9 0-1.5.2-1.9.5zm5.6 0c-.4-.3-1-.5-1.9-.5-1.7 0-3 1.3-3 3s1.3 3 3 3 3-1.3 3-3-1.3-3-3-3c-.9 0-1.5.2-1.9.5z"/>
                                        </svg>
                                    </div>
                                    <span class="payment-label">Apple</span>
                                </div>
                            </label>
                            <label class="payment-method-radio">
                                <input type="radio" name="payment_method_radio" value="paypal">
                                <div class="payment-card">
                                    <div class="payment-icon-wrapper">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18" height="18">
                                            <circle fill="#003087" cx="24" cy="24" r="22"/>
                                            <path fill="#fff" d="M24 15l-2.3 7.5h-2.4l2.7-7.5zm-2.6 9.6c-1.4 0-2.4-1-2.6-2.3l1.8-5.8c.2.6.8 1.1 1.9 1.1 1.2 0 2-.8 2-2 0-1.2-.8-2-2-2-.9 0-1.4.3-2.1.9l-1.6-2.2c.5-.5 1.2-.7 2-.7 1.7 0 2.9 1.4 2.9 3.1 0 2.1-1.7 3.5-3.9 3.5H17l2 6.4h2.4z"/>
                                        </svg>
                                    </div>
                                    <span class="payment-label">PayPal</span>
                                </div>
                            </label>
                            <label class="payment-method-radio">
                                <input type="radio" name="payment_method_radio" value="upi">
                                <div class="payment-card">
                                    <div class="payment-icon-wrapper">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="18" height="18">
                                            <rect fill="#4CAF50" width="48" height="48" rx="8"/>
                                            <path fill="#fff" d="M26 22l6-12h-4l-4 8-4-8h-4l6 12z"/>
                                        </svg>
                                    </div>
                                    <span class="payment-label">UPI</span>
                                </div>
                            </label>
                            <label class="payment-method-radio">
                                <input type="radio" name="payment_method_radio" value="counter_payment">
                                <div class="payment-card">
                                    <div class="payment-icon-wrapper">
                                        <i class="fa fa-money" style="font-size:18px;"></i>
                                    </div>
                                    <span class="payment-label">Counter</span>
                                </div>
                            </label>
                        </div>
<?php if($orderId>0){ ?>
                        <input type="hidden" name="orderid" value="<?php echo htmlentities($orderId); ?>" />
                        <input type="hidden" name="order_type" value="<?php echo htmlentities($orderType); ?>" />
<?php } ?>
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
                            <p class="card-instructions">Card payment submits the payment details now, but online reading stays locked until admin verifies and marks the order as paid.</p>
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
                        <p class="counter-instructions">Payment status will remain pending until the admin confirms that the amount was collected at the counter. After approval, the online book will open from your order details and book pages.</p>
                        </div>

                        <div class="payment-section payment-section-upi" id="paymentSectionUPI">
                            <div class="separator">
                                <hr class="line" />
                                <p>UPI / QR PAYMENT</p>
                                <hr class="line" />
                            </div>
                            <div class="checkout-payment-upi">
                                <div class="upi-qr-container" style="text-align:center;padding:20px;">
                                    <div style="width:200px;height:200px;background:#f0f0f0;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;border-radius:10px;">
                                        <img src="assets/img/upi-qr.png" alt="UPI QR Code" style="max-width:100%;max-height:100%;border-radius:10px;" onerror="this.style.display='none';this.parentElement.innerHTML='<span style='color:#888'>QR Code</span>';" />
                                    </div>
                                    <p style="font-size:13px;color:#555;margin:10px 0;">Scan QR with any UPI app (GPay, PhonePe, Paytm, etc.)</p>
                                    <div class="upi-id-box" style="background:#f4f4f4;padding:10px 15px;border-radius:8px;margin:10px 0;cursor:pointer;user-select:all;" onclick="navigator.clipboard.writeText('library@upi')">
                                        <strong>UPI ID:</strong> <code>library@upi</code>
                                        <br><small style="color:#888;">Click to copy</small>
                                    </div>
                                    <p class="upi-instructions" style="font-size:12px;color:#666;line-height:1.6;margin-top:15px;padding:10px;background:#f8fafc;border-radius:8px;">
                                        After payment, your payment will be marked pending. Admin will verify your transaction and approve your order. Online access will unlock after admin approval.
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p class="demo-payment-note">Card Payment -> payment submitted, admin approval still needed. Pay at Counter -> admin approval needed after cash collection. UPI -> payment via QR, admin verifies before approval. Online reading opens only after admin marks the payment as Paid.</p>
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
        var radios=document.querySelectorAll('input[name="payment_method_radio"]');
        var paymentInput=document.getElementById('selectedPaymentMethod');
        var cardSection=document.getElementById('paymentSectionCard');
        var counterSection=document.getElementById('paymentSectionCounter');
        var upiSection=document.getElementById('paymentSectionUPI');

        if(!paymentInput){
            return;
        }

        function setSelected(method) {
            radios.forEach(function(radio) {
                if(radio.value === method) {
                    radio.checked = true;
                }
            });
            paymentInput.value = method;

            if(cardSection){
                cardSection.style.display = method === 'card_payment' ? 'block' : 'none';
            }
            if(counterSection){
                counterSection.style.display = method === 'counter_payment' ? 'block' : 'none';
            }
            if(upiSection){
                upiSection.style.display = method === 'upi' ? 'block' : 'none';
            }
        }

        radios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                setSelected(this.value);
            });
        });

        // Initialize based on current paymentInput value
        setSelected(paymentInput.value);
    })();
    </script>
</body>
</html>
