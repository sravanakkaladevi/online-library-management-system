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
$bookid=0;
if(isset($_GET['bookid']))
{
$bookid=intval($_GET['bookid']);
}
elseif(isset($_POST['bookid']))
{
$bookid=intval($_POST['bookid']);
}

if($bookid<=0)
{
$_SESSION['error']="Invalid book selected.";
header('location:listed-books.php');
exit;
}

if(isset($_POST['request_book']))
{
$book=fetchBookWithInventory($dbh, $bookid);
if(!$book)
{
$_SESSION['error']="Book not found.";
}
else {
$activeIssueSql="SELECT id FROM tblissuedbookdetails WHERE StudentID=:sid AND BookId=:bookid AND (RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0) LIMIT 1";
$activeIssueQuery=$dbh->prepare($activeIssueSql);
$activeIssueQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$activeIssueQuery->bindParam(':bookid',$bookid,PDO::PARAM_INT);
$activeIssueQuery->execute();

if($activeIssueQuery->fetch(PDO::FETCH_ASSOC))
{
$_SESSION['error']="This book is already issued to you.";
}
else {
$pendingSql="SELECT id FROM tblbookrequests WHERE StudentId=:sid AND BookId=:bookid AND Status=0 LIMIT 1";
$pendingQuery=$dbh->prepare($pendingSql);
$pendingQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$pendingQuery->bindParam(':bookid',$bookid,PDO::PARAM_INT);
$pendingQuery->execute();

if($pendingQuery->fetch(PDO::FETCH_ASSOC))
{
$_SESSION['error']="You already have a pending request for this book.";
}
elseif((int)$book['availableQty']<=0)
{
$_SESSION['error']="This book is not available right now.";
}
else {
$requestSql="INSERT INTO tblbookrequests(BookId,StudentId) VALUES(:bookid,:sid)";
$requestQuery=$dbh->prepare($requestSql);
$requestQuery->bindParam(':bookid',$bookid,PDO::PARAM_INT);
$requestQuery->bindParam(':sid',$sid,PDO::PARAM_STR);
$requestQuery->execute();
$_SESSION['msg']="Book request submitted successfully. Admin can review it now.";
}
}
}
header('location:book-details.php?bookid=' . $bookid);
exit;
}

$book=fetchBookWithInventory($dbh, $bookid);
if(!$book)
{
$_SESSION['error']="Book not found.";
header('location:listed-books.php');
exit;
}

$pendingRequestBookIds=fetchStudentPendingRequestBookIds($dbh, $sid);
$activeIssuedBookIds=fetchStudentIssuedBookIds($dbh, $sid);
$cartBookQuantities=fetchStudentCartQuantities($dbh, $sid);
$alreadyIssued=isset($activeIssuedBookIds[$bookid]);
$requestPending=isset($pendingRequestBookIds[$bookid]);
$cartQty=isset($cartBookQuantities[$bookid]) ? (int)$cartBookQuantities[$bookid] : 0;
$canAddToCart=(int)$book['availableQty']>$cartQty;
$hasPreview=hasBookPreview($book['PreviewLink']);
$previewOpenUrl=$hasPreview ? getBookPreviewOpenUrl($book['PreviewLink']) : '';
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Book Details</title>
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
                <h4 class="header-line">Book Details</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="listed-books.php" class="btn btn-default">Back to Books</a>
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
            <div class="col-md-4">
                <div class="panel panel-default">
                    <div class="panel-body text-center">
                        <img src="admin/bookimg/<?php echo htmlentities($book['bookImage']);?>" class="img-responsive" style="margin:0 auto; max-height:320px;" alt="<?php echo htmlentities($book['BookName']);?>" />
                        <hr />
<?php if($hasPreview){ ?>
                        <a href="preview-book.php?bookid=<?php echo htmlentities($bookid);?>" class="btn btn-info btn-block">Preview Book</a>
                        <a href="<?php echo htmlentities($previewOpenUrl);?>" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-block" style="margin-top:10px;">Open Preview Link</a>
<?php } else { ?>
                        <p><strong>Digital Preview:</strong> Preview link is not added yet.</p>
                        <button type="button" class="btn btn-default btn-block" disabled>Preview Not Added</button>
<?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <?php echo htmlentities($book['BookName']);?>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Author:</strong> <?php echo htmlentities($book['AuthorName']);?></p>
                                <p><strong>Category:</strong> <?php echo htmlentities($book['CategoryName']);?></p>
                                <p><strong>ISBN:</strong> <?php echo htmlentities($book['ISBNNumber']);?></p>
                                <p><strong>Price:</strong> Rs. <?php echo htmlentities(number_format((float)$book['BookPrice'],2));?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Quantity:</strong> <?php echo htmlentities($book['bookQty']);?></p>
                                <p><strong>Available Quantity:</strong> <?php echo htmlentities($book['availableQty']);?></p>
                                <p><strong>Currently Reading:</strong> <?php echo htmlentities($book['activeIssues']);?> people</p>
                                <p><strong>Sold Copies:</strong> <?php echo htmlentities($book['soldQty']);?></p>
                                <p><strong>In Your Cart:</strong> <?php echo htmlentities($cartQty);?></p>
                                <p><strong>Preview:</strong> <?php echo $hasPreview ? 'Available' : 'Not Added'; ?></p>
                            </div>
                        </div>
                        <hr />
                        <div class="row">
                            <div class="col-md-6">
<?php if($alreadyIssued){ ?>
                                <div class="alert alert-success">This book is already issued to you.</div>
<?php } elseif($requestPending){ ?>
                                <div class="alert alert-warning">Your issue request is waiting for admin approval.</div>
<?php } elseif((int)$book['availableQty']<=0){ ?>
                                <div class="alert alert-danger">This book is not available for issue or purchase right now.</div>
<?php } else { ?>
                                <div class="alert alert-info">This book is available to request or buy.</div>
<?php } ?>
                            </div>
                            <div class="col-md-6">
<?php if(!$canAddToCart && (int)$book['availableQty']>0){ ?>
                                <div class="alert alert-warning">You already have the maximum available quantity of this book in your cart.</div>
<?php } ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <form method="post" action="cart.php">
                                    <input type="hidden" name="bookid" value="<?php echo htmlentities($bookid);?>">
                                    <div class="form-group">
                                        <label>Purchase Quantity</label>
                                        <input type="number" name="quantity" min="1" value="1" class="form-control" <?php if(!$canAddToCart){ echo 'disabled'; } ?> />
                                    </div>
<?php if($canAddToCart){ ?>
                                    <button type="submit" name="add_to_cart" class="btn btn-success">Add to Cart</button>
                                    <button type="submit" name="buy_now" class="btn btn-warning">Buy Now</button>
<?php } else { ?>
                                    <button type="button" class="btn btn-default" disabled>Add to Cart</button>
                                    <button type="button" class="btn btn-default" disabled>Buy Now</button>
<?php } ?>
                                </form>
                            </div>
                            <div class="col-md-6">
<?php if(!$alreadyIssued && !$requestPending && (int)$book['availableQty']>0){ ?>
                                <form method="post">
                                    <input type="hidden" name="bookid" value="<?php echo htmlentities($bookid);?>">
                                    <div class="form-group">
                                        <label>Issue Request</label>
                                        <p class="form-control-static">Send this book to admin for approval and issue.</p>
                                    </div>
                                    <button type="submit" name="request_book" class="btn btn-primary">Request Book Issue</button>
                                </form>
<?php } elseif($alreadyIssued) { ?>
                                <button type="button" class="btn btn-success" disabled>Already Issued</button>
<?php } elseif($requestPending) { ?>
                                <button type="button" class="btn btn-warning" disabled>Request Pending</button>
<?php } else { ?>
                                <button type="button" class="btn btn-default" disabled>Issue Unavailable</button>
<?php } ?>
                            </div>
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
