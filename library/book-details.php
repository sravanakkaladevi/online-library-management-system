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

if(isset($_POST['save_review']))
{
$reviewRating=isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$reviewText=isset($_POST['review_text']) ? trim((string)$_POST['review_text']) : '';
$reviewResult=saveBookReview($dbh, $sid, $bookid, $reviewRating, $reviewText);
if($reviewResult['success'])
{
$_SESSION['msg']=$reviewResult['message'];
}
else {
$_SESSION['error']=$reviewResult['message'];
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
$canReviewBook=canStudentReviewBook($dbh, $sid, $bookid);
$studentReview=fetchStudentBookReview($dbh, $sid, $bookid);
$bookReviews=fetchBookReviews($dbh, $bookid, 8);
$recommendedBooks=fetchRecommendedBooks($dbh, $sid, 4, $bookid);
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
    <style type="text/css">
        .recommendation-box {
            border: 1px solid #ddd;
            background: #fff;
            padding: 20px;
            margin-top: 25px;
        }

        .recommendation-item {
            border: 1px solid #eee;
            background: #fafafa;
            padding: 15px;
            margin-bottom: 15px;
            min-height: 180px;
        }

        .recommendation-item h5 {
            margin-top: 0;
            min-height: 40px;
        }

        .review-box {
            border: 1px solid #ddd;
            background: #fff;
            padding: 20px;
            margin-top: 25px;
        }

        .review-item {
            border-bottom: 1px solid #eee;
            padding: 12px 0;
        }

        .review-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .rating-summary {
            font-size: 16px;
            font-weight: 600;
        }

        .review-rating-radio {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 8px;
        }

        .review-rating-radio > input {
            position: absolute;
            appearance: none;
        }

        .review-rating-radio > label {
            cursor: pointer;
            font-size: 30px;
            position: relative;
            display: inline-block;
            transition: transform 0.3s ease;
            margin-bottom: 0;
        }

        .review-rating-radio > label > svg {
            width: 1em;
            height: 1em;
            fill: #666;
            transition: fill 0.3s ease;
        }

        .review-rating-radio > label:before,
        .review-rating-radio > label:after {
            content: "";
            position: absolute;
            width: 6px;
            height: 6px;
            background-color: #ff9e0b;
            border-radius: 50%;
            opacity: 0;
            transform: scale(0);
            transition: transform 0.4s ease, opacity 0.4s ease;
            animation: particle-explosion 1s ease-out;
        }

        .review-rating-radio > label:before {
            top: -15px;
            left: 50%;
            transform: translateX(-50%) scale(0);
        }

        .review-rating-radio > label:after {
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%) scale(0);
        }

        .review-rating-radio > label:hover:before,
        .review-rating-radio > label:hover:after {
            opacity: 1;
            transform: translateX(-50%) scale(1.5);
        }

        .review-rating-radio > label:hover {
            transform: scale(1.2);
            animation: pulse 0.6s infinite alternate;
        }

        .review-rating-radio > label:hover > svg,
        .review-rating-radio > label:hover ~ label > svg,
        .review-rating-radio > input:checked ~ label > svg {
            fill: #ff9e0b;
            filter: drop-shadow(0 0 15px rgba(255, 158, 11, 0.9));
        }

        .review-rating-radio > label:hover > svg {
            animation: shimmer 1s ease infinite alternate;
        }

        .review-rating-radio > input:checked + label {
            animation: pulse 0.8s infinite alternate;
        }

        .review-rating-note {
            margin-top: 10px;
            color: #64748b;
            font-size: 12px;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            100% {
                transform: scale(1.1);
            }
        }

        @keyframes particle-explosion {
            0% {
                opacity: 0;
                transform: scale(0.5);
            }
            50% {
                opacity: 1;
                transform: scale(1.2);
            }
            100% {
                opacity: 0;
                transform: scale(0.5);
            }
        }

        @keyframes shimmer {
            0% {
                filter: drop-shadow(0 0 10px rgba(255, 158, 11, 0.5));
            }
            100% {
                filter: drop-shadow(0 0 20px rgba(255, 158, 11, 1));
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
                        <?php echo htmlentities(getDisplayValue($book['BookName'], 'Untitled Book'));?>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Author:</strong> <?php echo htmlentities(getDisplayValue($book['AuthorName'], 'Author not assigned'));?></p>
                                <p><strong>Category:</strong> <?php echo htmlentities(getDisplayValue($book['CategoryName'], 'Category not assigned'));?></p>
                                <p><strong>ISBN:</strong> <?php echo htmlentities(getDisplayValue($book['ISBNNumber'], 'ISBN not added'));?></p>
                                <p><strong>Price:</strong> Rs. <?php echo htmlentities(number_format((float)$book['BookPrice'],2));?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Quantity:</strong> <?php echo htmlentities($book['bookQty']);?></p>
                                <p><strong>Available Quantity:</strong> <?php echo htmlentities($book['availableQty']);?></p>
                                <p><strong>Currently Reading:</strong> <?php echo htmlentities($book['activeIssues']);?> people</p>
                                <p><strong>Sold Copies:</strong> <?php echo htmlentities($book['soldQty']);?></p>
                                <p><strong>In Your Cart:</strong> <?php echo htmlentities($cartQty);?></p>
                                <p><strong>Preview:</strong> <?php echo $hasPreview ? 'Available' : 'Not Added'; ?></p>
                                <p><strong>Rating:</strong> <?php echo htmlentities(number_format((float)$book['averageRating'],1));?> / 5 (<?php echo htmlentities($book['reviewCount']);?> reviews)</p>
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
                                    <div class="cart-action-group">
                                        <button type="submit" name="add_to_cart" class="cart-action-btn cart-action-btn--success" data-tooltip="Price Rs. <?php echo htmlentities(number_format((float)$book['BookPrice'],2));?>">
                                            <span class="cart-action-btn__tooltip">Price Rs. <?php echo htmlentities(number_format((float)$book['BookPrice'],2));?></span>
                                            <span class="cart-action-btn__content">
                                                <span class="cart-action-btn__label">Add to Cart</span>
                                                <span class="cart-action-btn__icon" aria-hidden="true">
                                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                                        <path d="M0 2.5A.5.5 0 0 1 .5 2H2a.5.5 0 0 1 .485.379L2.89 4H14.5a.5.5 0 0 1 .485.621l-1.5 6A.5.5 0 0 1 13 11H4a.5.5 0 0 1-.485-.379L1.61 3H.5a.5.5 0 0 1-.5-.5zM3.14 5l1.25 5h8.22l1.25-5H3.14zM5 13a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0zm9-1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"></path>
                                                    </svg>
                                                </span>
                                            </span>
                                        </button>
                                        <a href="cart.php" class="cart-link-btn">Check Cart</a>
                                    </div>
                                    <div style="margin-top:10px;">
                                    <button type="submit" name="buy_now" class="btn btn-warning">Buy Now</button>
                                    </div>
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

        <div class="review-box">
            <div class="row">
                <div class="col-md-6">
                    <h4 style="margin-top:0;">Ratings and Reviews</h4>
                    <p class="rating-summary"><?php echo htmlentities(number_format((float)$book['averageRating'],1));?> / 5 using <?php echo htmlentities($book['reviewCount']);?> reader reviews</p>
                    <p style="margin-bottom:0;">Recommendations use a content-based text model: your past review keywords are compared with review text from other books to surface similar reads.</p>
                </div>
                <div class="col-md-6">
<?php if($canReviewBook){ ?>
                    <form method="post">
                        <div class="form-group">
                            <label>Your Rating</label>
                            <div class="review-rating-radio">
<?php for($ratingOption=5;$ratingOption>=1;$ratingOption--){ ?>
                                <input value="<?php echo $ratingOption; ?>" name="rating" type="radio" id="rating-<?php echo $ratingOption; ?>" <?php if($studentReview && (int)$studentReview['Rating']===$ratingOption){ echo 'checked'; } ?> required />
                                <label title="<?php echo $ratingOption; ?> star<?php if($ratingOption>1){ echo 's'; } ?>" for="rating-<?php echo $ratingOption; ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" aria-hidden="true">
                                        <path d="M316.9 18C311.6 7 300.4 0 288.1 0s-23.4 7-28.8 18L195 150.3 51.4 171.5c-12 1.8-22 10.2-25.7 21.7s-.7 24.2 7.9 32.7L137.8 329 113.2 474.7c-2 12 3 24.2 12.9 31.3s23 8 33.8 2.3l128.3-68.5 128.3 68.5c10.8 5.7 23.9 4.9 33.8-2.3s14.9-19.3 12.9-31.3L438.5 329 542.7 225.9c8.6-8.5 11.7-21.2 7.9-32.7s-13.7-19.9-25.7-21.7L381.2 150.3 316.9 18z"></path>
                                    </svg>
                                </label>
<?php } ?>
                            </div>
                            <p class="review-rating-note">Animated rating is available only after you rent this book or after your purchase is delivered.</p>
                        </div>
                        <div class="form-group">
                            <label>Your Review</label>
                            <textarea name="review_text" class="form-control" rows="4" maxlength="1500" placeholder="Share what you liked, the topics covered, writing style, or who this book is best for." required><?php echo $studentReview ? htmlentities($studentReview['ReviewText']) : ''; ?></textarea>
                        </div>
                        <button type="submit" name="save_review" class="btn btn-primary"><?php echo $studentReview ? 'Update Review' : 'Submit Review'; ?></button>
                    </form>
<?php } else { ?>
                    <div class="alert alert-info" style="margin-bottom:0;">Rate and review becomes available only after you rent this book or once your order is delivered and confirmed.</div>
<?php } ?>
                </div>
            </div>
            <hr />
<?php if(!empty($bookReviews)){ ?>
<?php foreach($bookReviews as $review){ ?>
            <div class="review-item">
                <p style="margin-bottom:6px;"><strong><?php echo htmlentities(getDisplayValue($review['FullName'], $review['StudentId']));?></strong> <span style="color:#777;">rated <?php echo htmlentities(renderStarRating($review['Rating']));?> (<?php echo htmlentities($review['Rating']);?>/5)</span></p>
                <p style="margin-bottom:6px;"><?php echo nl2br(htmlentities(getDisplayValue($review['ReviewText'], 'No review text added.')));?></p>
                <small style="color:#777;"><?php echo htmlentities($review['CreatedDate']);?></small>
            </div>
<?php } ?>
<?php } else { ?>
            <div class="alert alert-info" style="margin-bottom:0;">No reviews yet. The first reader review will help power text-based recommendations.</div>
<?php } ?>
        </div>

        <div class="recommendation-box">
            <div class="row">
                <div class="col-md-12">
                    <h4 style="margin-top:0;">Recommended Next Reads</h4>
                    <p>These suggestions are picked from your activity history, reader ratings, popularity, and text similarity from review content.</p>
                </div>
            </div>
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
                    <div class="alert alert-info" style="margin-bottom:0;">More personalized recommendations will appear here after you interact with more books.</div>
                </div>
<?php } ?>
            </div>
        </div>
    </div>
    </div>
<?php include('includes/book-chatbot.php');?>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
