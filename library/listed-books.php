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
if(isset($_POST['request_book']))
{
$bookid=intval($_POST['bookid']);
if($bookid<=0)
{
$_SESSION['error']="Invalid book selected.";
}
else {
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
}
header('location:listed-books.php');
exit;
}

$pendingRequestBookIds=fetchStudentPendingRequestBookIds($dbh, $sid);
$activeIssuedBookIds=fetchStudentIssuedBookIds($dbh, $sid);
$cartBookQuantities=fetchStudentCartQuantities($dbh, $sid);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Listed Books</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .book-grid-row {
            display: flex;
            flex-wrap: wrap;
        }

        .book-grid-row:before,
        .book-grid-row:after {
            display: none;
        }

        .book-card-col {
            display: flex;
            margin-bottom: 30px;
        }

        .book-card {
            width: 100%;
            border: 1px solid #ddd;
            background: #fff;
        }

        .book-card .panel-heading {
            min-height: 72px;
            font-size: 18px;
            font-weight: 600;
            line-height: 1.4;
        }

        .book-cover-wrap {
            text-align: center;
            padding: 15px 15px 0 15px;
        }

        .book-cover {
            max-width: 120px;
            max-height: 170px;
            margin: 0 auto;
        }

        .book-card .table {
            margin-bottom: 0;
        }

        .book-card .table > tbody > tr > th {
            width: 38%;
        }

        .book-actions {
            padding: 15px;
            border-top: 1px solid #eee;
        }

        .book-actions .btn {
            margin-bottom: 10px;
        }

        .book-actions .btn:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 991px) {
            .book-grid-row {
                display: block;
            }

            .book-card-col {
                display: block;
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
                <h4 class="header-line">Listed Books</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="book-requests.php" class="btn btn-info">View My Requests</a>
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
<?php
$sql = "SELECT tblbooks.BookName,tblcategory.CategoryName,tblauthors.AuthorName,tblbooks.ISBNNumber,
tblbooks.BookPrice,tblbooks.id as bookid,tblbooks.bookImage,tblbooks.bookQty,tblbooks.PreviewLink," . getInventorySelectSql() . "
FROM tblbooks
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
LEFT JOIN tblcategory ON tblcategory.id=tblbooks.CatId
" . getInventoryIssueJoinSql('tblbooks') . "
" . getInventoryOrderJoinSql('tblbooks') . "
ORDER BY tblbooks.id";
$query = $dbh->prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$totalBooks=count($results);
if($query->rowCount() > 0)
{
foreach($results as $index => $result)
{
if($index % 3 === 0)
{
?>
        <div class="row book-grid-row">
<?php
}
$availableQty=calculateAvailableBookQty($result->bookQty, $result->activeIssues, $result->soldQty);
$bookId=(int)$result->bookid;
$alreadyIssued=isset($activeIssuedBookIds[$bookId]);
$requestPending=isset($pendingRequestBookIds[$bookId]);
$cartQty=isset($cartBookQuantities[$bookId]) ? (int)$cartBookQuantities[$bookId] : 0;
$canAddToCart=$availableQty>$cartQty;
$hasPreview=hasBookPreview($result->PreviewLink);
?>
    <div class="col-md-4 col-sm-6 book-card-col">
        <div class="panel panel-default book-card">
            <div class="panel-heading"><?php echo htmlentities($result->BookName);?></div>
            <div class="book-cover-wrap">
                <img src="admin/bookimg/<?php echo htmlentities($result->bookImage);?>" class="book-cover" alt="<?php echo htmlentities($result->BookName);?>">
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="table table-bordered">
                    <tr>
                        <th>Author</th>
                        <td><?php echo htmlentities($result->AuthorName);?></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?php echo htmlentities($result->CategoryName);?></td>
                    </tr>
                    <tr>
                        <th>ISBN Number</th>
                        <td><?php echo htmlentities($result->ISBNNumber);?></td>
                    </tr>
                    <tr>
                        <th>Book Price</th>
                        <td>Rs. <?php echo htmlentities(number_format((float)$result->BookPrice,2));?></td>
                    </tr>
                    <tr>
                        <th>Book Quantity</th>
                        <td><?php echo htmlentities($result->bookQty);?></td>
                    </tr>
                    <tr>
                        <th>Available Quantity</th>
                        <td><?php echo htmlentities($availableQty);?></td>
                    </tr>
                    <tr>
                        <th>Currently Reading</th>
                        <td><?php echo htmlentities($result->activeIssues);?> people</td>
                    </tr>
                    <tr>
                        <th>Sold Copies</th>
                        <td><?php echo htmlentities($result->soldQty);?></td>
                    </tr>
                    <tr>
                        <th>In Your Cart</th>
                        <td><?php echo htmlentities($cartQty);?></td>
                    </tr>
                    <tr>
                        <th>Preview</th>
                        <td><?php echo $hasPreview ? 'Available' : 'Not Added'; ?></td>
                    </tr>
                    <tr>
                        <th>Issue Status</th>
                        <td>
        <?php if($alreadyIssued){ ?>
        <span style="color:green;">Already issued to you</span>
        <?php } elseif($requestPending){ ?>
        <span style="color:#b8860b;">Request pending admin review</span>
        <?php } elseif($availableQty<=0){ ?>
        <span style="color:red;">Not available right now</span>
        <?php } else { ?>
        <span style="color:green;">Available to request</span>
        <?php } ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="book-actions">
                <a href="book-details.php?bookid=<?php echo htmlentities($bookId);?>" class="btn btn-info btn-block">Open Details</a>
<?php if($hasPreview){ ?>
                <a href="preview-book.php?bookid=<?php echo htmlentities($bookId);?>" class="btn btn-default btn-block">Preview Book</a>
<?php } else { ?>
                <button type="button" class="btn btn-default btn-block" disabled>Preview Not Added</button>
<?php } ?>
<?php if($canAddToCart){ ?>
                <form method="post" action="cart.php" style="margin:0;">
                    <input type="hidden" name="bookid" value="<?php echo htmlentities($bookId);?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" name="add_to_cart" class="btn btn-success btn-block">Add to Cart</button>
                </form>
<?php } else { ?>
                <button type="button" class="btn btn-default btn-block" disabled><?php echo $availableQty>0 ? 'Cart Limit Reached' : 'Unavailable'; ?></button>
<?php } ?>
<?php if($canAddToCart){ ?>
                <form method="post" action="cart.php" style="margin:0;">
                    <input type="hidden" name="bookid" value="<?php echo htmlentities($bookId);?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" name="buy_now" class="btn btn-warning btn-block">Buy Now</button>
                </form>
<?php } else { ?>
                <button type="button" class="btn btn-default btn-block" disabled>Buy Now</button>
<?php } ?>
<?php if(!$alreadyIssued && !$requestPending && $availableQty>0){ ?>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="bookid" value="<?php echo htmlentities($bookId);?>">
                    <button type="submit" name="request_book" class="btn btn-primary btn-block">Request Book Issue</button>
                </form>
<?php } elseif($alreadyIssued) { ?>
                <button type="button" class="btn btn-success btn-block" disabled>Already Issued</button>
<?php } elseif($requestPending) { ?>
                <button type="button" class="btn btn-warning btn-block" disabled>Request Pending</button>
<?php } else { ?>
                <button type="button" class="btn btn-default btn-block" disabled>Unavailable for Issue</button>
<?php } ?>
            </div>
        </div>
    </div>
<?php
if((($index + 1) % 3 === 0) || ($index + 1 === $totalBooks))
{
?>
        </div>
<?php
}
}} ?>
        </div>
    </div>
    </div>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
