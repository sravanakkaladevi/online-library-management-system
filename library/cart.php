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

function redirectToCart($path)
{
header('location:' . $path);
exit;
}

if(isset($_POST['add_to_cart']) || isset($_POST['buy_now']))
{
$bookid=intval($_POST['bookid']);
$quantity=max(1, intval($_POST['quantity']));
if($bookid<=0)
{
$_SESSION['error']="Invalid book selected.";
redirectToCart('cart.php');
}

$book=fetchBookWithInventory($dbh, $bookid);
if(!$book)
{
$_SESSION['error']="Book not found.";
redirectToCart('cart.php');
}

$existingSql="SELECT id,Quantity FROM tblcart WHERE StudentId=:sid AND BookId=:bookid LIMIT 1";
$existingQuery=$dbh->prepare($existingSql);
$existingQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$existingQuery->bindParam(':bookid',$bookid,PDO::PARAM_INT);
$existingQuery->execute();
$existingItem=$existingQuery->fetch(PDO::FETCH_ASSOC);
$existingQty=$existingItem ? (int)$existingItem['Quantity'] : 0;
$newQty=$existingQty+$quantity;

if($newQty>(int)$book['availableQty'])
{
$_SESSION['error']="Only " . $book['availableQty'] . " copies are available right now for " . $book['BookName'] . ".";
redirectToCart('cart.php');
}

if($existingItem)
{
$updateSql="UPDATE tblcart SET Quantity=:quantity WHERE id=:cartid AND StudentId=:sid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':quantity',$newQty,PDO::PARAM_INT);
$updateQuery->bindParam(':cartid',$existingItem['id'],PDO::PARAM_INT);
$updateQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$updateQuery->execute();
}
else {
$insertSql="INSERT INTO tblcart(StudentId,BookId,Quantity) VALUES(:sid,:bookid,:quantity)";
$insertQuery=$dbh->prepare($insertSql);
$insertQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$insertQuery->bindParam(':bookid',$bookid,PDO::PARAM_INT);
$insertQuery->bindParam(':quantity',$quantity,PDO::PARAM_INT);
$insertQuery->execute();
}

$_SESSION['msg']=$book['BookName'] . " added to cart successfully.";
if(isset($_POST['buy_now']))
{
redirectToCart('checkout.php');
}
redirectToCart('cart.php');
}

if(isset($_POST['update_cart']))
{
$cartid=intval($_POST['cartid']);
$quantity=intval($_POST['quantity']);
if($cartid<=0)
{
$_SESSION['error']="Invalid cart item selected.";
redirectToCart('cart.php');
}

$itemSql="SELECT tblcart.id,tblcart.BookId,tblbooks.BookName FROM tblcart INNER JOIN tblbooks ON tblbooks.id=tblcart.BookId WHERE tblcart.id=:cartid AND tblcart.StudentId=:sid LIMIT 1";
$itemQuery=$dbh->prepare($itemSql);
$itemQuery->bindParam(':cartid',$cartid,PDO::PARAM_INT);
$itemQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$itemQuery->execute();
$item=$itemQuery->fetch(PDO::FETCH_ASSOC);
if(!$item)
{
$_SESSION['error']="Cart item not found.";
redirectToCart('cart.php');
}

if($quantity<=0)
{
$deleteSql="DELETE FROM tblcart WHERE id=:cartid AND StudentId=:sid";
$deleteQuery=$dbh->prepare($deleteSql);
$deleteQuery->bindParam(':cartid',$cartid,PDO::PARAM_INT);
$deleteQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$deleteQuery->execute();
$_SESSION['msg']=$item['BookName'] . " removed from your cart.";
redirectToCart('cart.php');
}

$book=fetchBookWithInventory($dbh, (int)$item['BookId']);
if(!$book)
{
$_SESSION['error']="Book not found.";
redirectToCart('cart.php');
}

if($quantity>(int)$book['availableQty'])
{
$_SESSION['error']="Only " . $book['availableQty'] . " copies are available right now for " . $book['BookName'] . ".";
redirectToCart('cart.php');
}

$updateSql="UPDATE tblcart SET Quantity=:quantity WHERE id=:cartid AND StudentId=:sid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':quantity',$quantity,PDO::PARAM_INT);
$updateQuery->bindParam(':cartid',$cartid,PDO::PARAM_INT);
$updateQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$updateQuery->execute();
$_SESSION['msg']="Cart updated successfully.";
redirectToCart('cart.php');
}

if(isset($_POST['remove_item']))
{
$cartid=intval($_POST['cartid']);
if($cartid>0)
{
$deleteSql="DELETE FROM tblcart WHERE id=:cartid AND StudentId=:sid";
$deleteQuery=$dbh->prepare($deleteSql);
$deleteQuery->bindParam(':cartid',$cartid,PDO::PARAM_INT);
$deleteQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$deleteQuery->execute();
$_SESSION['msg']="Item removed from cart.";
}
redirectToCart('cart.php');
}

if(isset($_POST['clear_cart']))
{
$clearSql="DELETE FROM tblcart WHERE StudentId=:sid";
$clearQuery=$dbh->prepare($clearSql);
$clearQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$clearQuery->execute();
$_SESSION['msg']="Your cart has been cleared.";
redirectToCart('cart.php');
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
    <title>Online Library Management System | My Cart</title>
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
                <h4 class="header-line">My Cart</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="listed-books.php" class="btn btn-default">Continue Shopping</a>
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
                        <p>Add books from the listed books page and come back here to checkout.</p>
                        <a href="listed-books.php" class="btn btn-primary">Browse Books</a>
                    </div>
                </div>
            </div>
        </div>
<?php } else { ?>
        <div class="row">
            <div class="col-md-9">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Cart Items
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book</th>
                                        <th>Price</th>
                                        <th>Available</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$cnt=1;
foreach($cartItems as $cartItem)
{
$availabilityNote='';
if((int)$cartItem['Quantity']>(int)$cartItem['availableQty'])
{
$availabilityNote='Only ' . $cartItem['availableQty'] . ' available now';
}
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td>
                                            <strong><?php echo htmlentities($cartItem['BookName']);?></strong><br />
                                            <?php echo htmlentities($cartItem['AuthorName']);?><br />
                                            ISBN: <?php echo htmlentities($cartItem['ISBNNumber']);?><br />
                                            <a href="book-details.php?bookid=<?php echo htmlentities($cartItem['BookId']);?>">Open details</a>
                                        </td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$cartItem['BookPrice'],2));?></td>
                                        <td>
                                            <?php echo htmlentities($cartItem['availableQty']);?>
<?php if($availabilityNote!=""){ ?>
                                            <br /><span style="color:red;"><?php echo htmlentities($availabilityNote);?></span>
<?php } ?>
                                        </td>
                                        <td>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="cartid" value="<?php echo htmlentities($cartItem['cartId']);?>">
                                                <input type="number" name="quantity" min="0" value="<?php echo htmlentities($cartItem['Quantity']);?>" class="form-control" style="width:90px; display:inline-block;" />
                                                <button type="submit" name="update_cart" class="btn btn-info btn-sm" style="margin-top:8px;">Update</button>
                                            </form>
                                        </td>
                                        <td>Rs. <?php echo htmlentities(number_format((float)$cartItem['lineTotal'],2));?></td>
                                        <td>
                                            <form method="post" style="margin:0;">
                                                <input type="hidden" name="cartid" value="<?php echo htmlentities($cartItem['cartId']);?>">
                                                <button type="submit" name="remove_item" class="btn btn-danger btn-sm">Remove</button>
                                            </form>
                                        </td>
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
            <div class="col-md-3">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        Cart Summary
                    </div>
                    <div class="panel-body">
                        <p><strong>Total Items:</strong> <?php echo htmlentities($totalItems);?></p>
                        <p><strong>Grand Total:</strong> Rs. <?php echo htmlentities(number_format((float)$grandTotal,2));?></p>
<?php if($hasAvailabilityIssue){ ?>
                        <div class="alert alert-warning">
                            Some cart quantities are higher than current stock. Update the cart before checkout.
                        </div>
                        <button type="button" class="btn btn-default btn-block" disabled>Proceed to Checkout</button>
<?php } else { ?>
                        <a href="checkout.php" class="btn btn-success btn-block">Proceed to Checkout</a>
<?php } ?>
                        <form method="post" style="margin-top:10px;">
                            <button type="submit" name="clear_cart" class="btn btn-default btn-block">Clear Cart</button>
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
