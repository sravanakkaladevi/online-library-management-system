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
$catalogFilters=getBookCatalogFiltersFromRequest();
$catalogFilterOptions=fetchCatalogFilterOptions($dbh);
$isRecommendedView=($catalogFilters['sort']==='recommended');
$recommendedBooks=fetchRecommendedBooks($dbh, $sid, 3);
$books=fetchCatalogBooks($dbh, $catalogFilters, $sid);
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

        .book-actions .cart-action-form {
            margin-bottom: 10px;
        }

        .book-actions .cart-link-btn {
            width: 100%;
            margin-bottom: 10px;
        }

        .catalog-toolbar,
        .recommendation-panel {
            border: 1px solid #ddd;
            background: #fff;
            margin-bottom: 25px;
            padding: 20px;
        }

        .catalog-toolbar .form-group,
        .recommendation-panel .form-group {
            margin-bottom: 15px;
        }

        .recommended-badge {
            display: inline-block;
            margin-bottom: 8px;
            padding: 4px 10px;
            background: #5bc0de;
            color: #fff;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .recommended-card {
            min-height: 100%;
            border: 1px solid #e7e7e7;
            padding: 15px;
            background: #fafafa;
            margin-bottom: 15px;
        }

        .recommended-card h5 {
            margin-top: 0;
            min-height: 40px;
        }

        .results-summary {
            margin-bottom: 20px;
        }

        .recommended-slide-chatbot {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1040;
            max-width: calc(100vw - 24px);
        }

        .recommended-slide-chatbot__toggle {
            border: 0;
            border-radius: 999px;
            padding: 12px 18px;
            background: #337ab7;
            color: #fff;
            font-weight: 600;
            box-shadow: 0 12px 32px rgba(51, 122, 183, 0.28);
        }

        .recommended-slide-chatbot__panel {
            position: absolute;
            right: 0;
            bottom: 72px;
            width: 420px;
            max-width: calc(100vw - 30px);
            max-height: calc(100vh - 150px);
            background: #fff;
            border: 1px solid #d9edf7;
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
            overflow: hidden;
            transform: translateY(18px);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .recommended-slide-chatbot--open .recommended-slide-chatbot__panel {
            transform: translateY(0);
            opacity: 1;
            pointer-events: auto;
        }

        .recommended-slide-chatbot__header {
            padding: 16px 18px;
            background: linear-gradient(135deg, #eaf6ff 0%, #f8fcff 100%);
            border-bottom: 1px solid #d9edf7;
        }

        .recommended-slide-chatbot__header h4 {
            margin: 0 0 6px;
        }

        .recommended-slide-chatbot__header p {
            margin: 0;
            color: #5f6f7f;
        }

        .recommended-slide-chatbot__close {
            position: absolute;
            top: 12px;
            right: 14px;
            border: 0;
            background: transparent;
            color: #5f6f7f;
            font-size: 24px;
            line-height: 1;
            padding: 0;
        }

        .recommended-slide-chatbot__body {
            padding: 18px;
            max-height: calc(100vh - 290px);
            overflow-y: auto;
            overflow-x: hidden;
        }

        .recommended-slide-chatbot__body textarea {
            resize: vertical;
            min-height: 120px;
        }

        .recommended-slide-chatbot__quick {
            margin-bottom: 14px;
        }

        .recommended-slide-chatbot__quick .btn {
            margin: 0 8px 8px 0;
        }

        .inline-chatbot-status {
            margin-top: 15px;
            padding: 12px 14px;
            border-radius: 4px;
            background: #ffffff;
            border: 1px solid #d9edf7;
            color: #31708f;
        }

        .inline-chatbot-status.is-error {
            border-color: #ebccd1;
            background: #fff7f7;
            color: #a94442;
        }

        .inline-chatbot-results {
            margin-top: 20px;
            max-height: 300px;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
        }

        .inline-chatbot-results .row {
            margin-left: -8px;
            margin-right: -8px;
        }

        .inline-chatbot-results .col-md-4,
        .inline-chatbot-results .col-sm-6 {
            padding-left: 8px;
            padding-right: 8px;
        }

        .inline-chatbot-results .recommended-card {
            background: #fff;
        }

        .inline-chatbot-match {
            display: inline-block;
            margin-bottom: 10px;
            padding: 4px 10px;
            border-radius: 20px;
            background: #337ab7;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
        }

        .inline-chatbot-reason {
            color: #555;
            font-size: 13px;
        }

        @media (max-width: 991px) {
            .book-grid-row {
                display: block;
            }

            .book-card-col {
                display: block;
            }

            .recommended-slide-chatbot {
                right: 12px;
                left: 12px;
                bottom: 12px;
            }

            .recommended-slide-chatbot__panel {
                width: auto;
                max-width: none;
                left: 0;
                bottom: 64px;
                max-height: calc(100vh - 110px);
            }

            .recommended-slide-chatbot__body {
                max-height: calc(100vh - 230px);
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
                <h4 class="header-line"><?php echo $isRecommendedView ? 'Recommended Books' : 'Listed Books'; ?></h4>
            </div>
            <div class="col-md-4 text-right">
<?php if($isRecommendedView){ ?>
                <a href="listed-books.php" class="btn btn-default">Browse All Books</a>
<?php } else { ?>
                <a href="book-requests.php" class="btn btn-info">View My Requests</a>
<?php } ?>
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

<?php if($isRecommendedView){ ?>
        <div class="recommendation-panel">
            <div class="row">
                <div class="col-md-8">
                    <h4 style="margin-top:0;">Recommended For You</h4>
                    <p style="margin-bottom:0;">Suggestions are ranked using your recent activity, matching author or category interest, popularity, and current availability.</p>
                </div>
                <div class="col-md-4 text-right">
                    <span class="recommended-badge">Smart Picks</span>
                </div>
            </div>
            <div class="row" style="margin-top:15px;">
<?php if(!empty($recommendedBooks)){ ?>
<?php foreach($recommendedBooks as $recommendedBook){ ?>
                <div class="col-md-4 col-sm-6">
                    <div class="recommended-card">
                        <h5><?php echo htmlentities(getDisplayValue($recommendedBook['BookName'], 'Untitled Book'));?></h5>
                        <p><strong>Author:</strong> <?php echo htmlentities(getDisplayValue($recommendedBook['AuthorName'], 'Author not assigned'));?></p>
                        <p><strong>Category:</strong> <?php echo htmlentities(getDisplayValue($recommendedBook['CategoryName'], 'Category not assigned'));?></p>
                        <p><strong>Available:</strong> <?php echo htmlentities($recommendedBook['availableQty']);?></p>
                        <a href="book-details.php?bookid=<?php echo htmlentities($recommendedBook['bookid']);?>" class="btn btn-info btn-sm">View Recommendation</a>
                    </div>
                </div>
<?php } ?>
<?php } else { ?>
                <div class="col-md-12">
                    <div class="alert alert-info" style="margin-bottom:0;">Recommendations will appear here after you browse, request, or purchase a few books.</div>
                </div>
<?php } ?>
            </div>
        </div>

<?php } ?>

        <div class="catalog-toolbar">
            <form method="get">
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="form-group">
                            <label>Search</label>
                            <input type="text" name="keyword" class="form-control" value="<?php echo htmlentities($catalogFilters['keyword']);?>" placeholder="Title, author, ISBN">
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category" class="form-control">
                                <option value="0">All Categories</option>
<?php foreach($catalogFilterOptions['categories'] as $categoryOption){ ?>
                                <option value="<?php echo htmlentities($categoryOption['id']);?>" <?php if((int)$catalogFilters['category']===(int)$categoryOption['id']){ echo 'selected'; } ?>><?php echo htmlentities($categoryOption['CategoryName']);?></option>
<?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Author</label>
                            <select name="author" class="form-control">
                                <option value="0">All Authors</option>
<?php foreach($catalogFilterOptions['authors'] as $authorOption){ ?>
                                <option value="<?php echo htmlentities($authorOption['id']);?>" <?php if((int)$catalogFilters['author']===(int)$authorOption['id']){ echo 'selected'; } ?>><?php echo htmlentities($authorOption['AuthorName']);?></option>
<?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Availability</label>
                            <select name="availability" class="form-control">
                                <option value="all" <?php if($catalogFilters['availability']==='all'){ echo 'selected'; } ?>>All</option>
                                <option value="available" <?php if($catalogFilters['availability']==='available'){ echo 'selected'; } ?>>Available</option>
                                <option value="unavailable" <?php if($catalogFilters['availability']==='unavailable'){ echo 'selected'; } ?>>Unavailable</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-1 col-sm-6">
                        <div class="form-group">
                            <label>Preview</label>
                            <select name="preview" class="form-control">
                                <option value="all" <?php if($catalogFilters['preview']==='all'){ echo 'selected'; } ?>>All</option>
                                <option value="yes" <?php if($catalogFilters['preview']==='yes'){ echo 'selected'; } ?>>Yes</option>
                                <option value="no" <?php if($catalogFilters['preview']==='no'){ echo 'selected'; } ?>>No</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <div class="form-group">
                            <label>Sort By</label>
                            <select name="sort" class="form-control">
                                <option value="recommended" <?php if($catalogFilters['sort']==='recommended'){ echo 'selected'; } ?>>Recommended</option>
                                <option value="popular" <?php if($catalogFilters['sort']==='popular'){ echo 'selected'; } ?>>Popular</option>
                                <option value="latest" <?php if($catalogFilters['sort']==='latest'){ echo 'selected'; } ?>>Latest</option>
                                <option value="name_asc" <?php if($catalogFilters['sort']==='name_asc'){ echo 'selected'; } ?>>Name A-Z</option>
                                <option value="price_low" <?php if($catalogFilters['sort']==='price_low'){ echo 'selected'; } ?>>Price Low-High</option>
                                <option value="price_high" <?php if($catalogFilters['sort']==='price_high'){ echo 'selected'; } ?>>Price High-Low</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 text-right">
                        <a href="listed-books.php" class="btn btn-default">Reset</a>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="results-summary">
            <strong><?php echo htmlentities(count($books));?></strong> <?php echo $isRecommendedView ? 'recommended books found for you.' : 'books found with the selected filters.'; ?>
        </div>

        <div class="row">
<?php
        $totalBooks=count($books);
if($totalBooks > 0)
{
foreach($books as $index => $result)
{
if($index % 3 === 0)
{
?>
        <div class="row book-grid-row">
<?php
}
$availableQty=(int)$result['availableQty'];
$bookId=(int)$result['bookid'];
$alreadyIssued=isset($activeIssuedBookIds[$bookId]);
$requestPending=isset($pendingRequestBookIds[$bookId]);
$cartQty=isset($cartBookQuantities[$bookId]) ? (int)$cartBookQuantities[$bookId] : 0;
$canAddToCart=$availableQty>$cartQty;
$hasPreview=hasBookPreview($result['PreviewLink']);
?>
    <div class="col-md-4 col-sm-6 book-card-col">
        <div class="panel panel-default book-card">
            <div class="panel-heading"><?php echo htmlentities(getDisplayValue($result['BookName'], 'Untitled Book'));?></div>
            <div class="book-cover-wrap">
                <img src="admin/bookimg/<?php echo htmlentities($result['bookImage']);?>" class="book-cover" alt="<?php echo htmlentities(getDisplayValue($result['BookName'], 'Untitled Book'));?>">
            </div>
            <div class="panel-body" style="padding:0;">
                <table class="table table-bordered">
                    <tr>
                        <th>Author</th>
                        <td><?php echo htmlentities(getDisplayValue($result['AuthorName'], 'Author not assigned'));?></td>
                    </tr>
                    <tr>
                        <th>Category</th>
                        <td><?php echo htmlentities(getDisplayValue($result['CategoryName'], 'Category not assigned'));?></td>
                    </tr>
                    <tr>
                        <th>ISBN Number</th>
                        <td><?php echo htmlentities(getDisplayValue($result['ISBNNumber'], 'ISBN not added'));?></td>
                    </tr>
                    <tr>
                        <th>Book Price</th>
                        <td>Rs. <?php echo htmlentities(number_format((float)$result['BookPrice'],2));?></td>
                    </tr>
                    <tr>
                        <th>Book Quantity</th>
                        <td><?php echo htmlentities($result['bookQty']);?></td>
                    </tr>
                    <tr>
                        <th>Available Quantity</th>
                        <td><?php echo htmlentities($availableQty);?></td>
                    </tr>
                    <tr>
                        <th>Currently Reading</th>
                        <td><?php echo htmlentities($result['activeIssues']);?> people</td>
                    </tr>
                    <tr>
                        <th>Sold Copies</th>
                        <td><?php echo htmlentities($result['soldQty']);?></td>
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
                <form method="post" action="cart.php" class="cart-action-form cart-action-form-block">
                    <input type="hidden" name="bookid" value="<?php echo htmlentities($bookId);?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" name="add_to_cart" class="cart-action-btn cart-action-btn--success" data-tooltip="Price Rs. <?php echo htmlentities(number_format((float)$result['BookPrice'],2));?>">
                        <span class="cart-action-btn__tooltip">Price Rs. <?php echo htmlentities(number_format((float)$result['BookPrice'],2));?></span>
                        <span class="cart-action-btn__content">
                            <span class="cart-action-btn__label">Add to Cart</span>
                            <span class="cart-action-btn__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M0 2.5A.5.5 0 0 1 .5 2H2a.5.5 0 0 1 .485.379L2.89 4H14.5a.5.5 0 0 1 .485.621l-1.5 6A.5.5 0 0 1 13 11H4a.5.5 0 0 1-.485-.379L1.61 3H.5a.5.5 0 0 1-.5-.5zM3.14 5l1.25 5h8.22l1.25-5H3.14zM5 13a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0zm9-1a1 1 0 1 0 0 2 1 1 0 0 0 0-2zm-2 1a2 2 0 1 1 4 0 2 2 0 0 1-4 0z"></path>
                                </svg>
                            </span>
                        </span>
                    </button>
                </form>
                <a href="cart.php" class="cart-link-btn">Check Cart</a>
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
}
} else { ?>
            <div class="col-md-12">
                <div class="alert alert-warning">No books matched your current filters. Try clearing one or two filter options.</div>
            </div>
<?php } ?>
        </div>
    </div>
    </div>
<?php if(!$isRecommendedView){ include('includes/book-chatbot.php'); } ?>
<?php include('includes/footer.php');?>
    <script src="assets/js/jquery-1.10.2.js"></script>
    <script src="assets/js/bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
<?php if($isRecommendedView){ ?>
    <div class="recommended-slide-chatbot" id="recommendedSlideChatbot">
        <button type="button" class="recommended-slide-chatbot__toggle" id="recommendedSlideChatbotToggle">Recommendation Chatbot</button>
        <div class="recommended-slide-chatbot__panel" id="recommendedSlideChatbotPanel" aria-hidden="true">
            <div class="recommended-slide-chatbot__header">
                <button type="button" class="recommended-slide-chatbot__close" id="recommendedSlideChatbotClose" aria-label="Close chatbot">&times;</button>
                <h4>Slide Recommendation Bot</h4>
                <p>Open the chatbot and it will automatically show related books for a topic like algorithm.</p>
            </div>
            <div class="recommended-slide-chatbot__body">
                <div class="recommended-slide-chatbot__quick">
                    <button type="button" class="btn btn-default btn-sm recommended-topic-btn" data-topic="algorithm">Algorithm</button>
                    <button type="button" class="btn btn-default btn-sm recommended-topic-btn" data-topic="database">Database</button>
                    <button type="button" class="btn btn-default btn-sm recommended-topic-btn" data-topic="history">History</button>
                    <button type="button" class="btn btn-default btn-sm recommended-topic-btn" data-topic="finance">Finance</button>
                </div>
                <form id="recommendedChatbotForm">
                    <div class="form-group">
                        <label for="recommendedChatbotContent">Paste book content</label>
                        <textarea id="recommendedChatbotContent" name="content" class="form-control" placeholder="Paste a few lines from the book you are reading, or use a quick topic like algorithm."></textarea>
                    </div>
                    <div class="text-right">
                        <button type="button" class="btn btn-default" id="recommendedChatbotPaste">Paste</button>
                        <button type="submit" class="btn btn-primary">Find Similar Books</button>
                    </div>
                </form>
                <div class="inline-chatbot-status" id="recommendedChatbotStatus">Open the chatbot to auto-load related books for algorithm, or paste your own content.</div>
                <div class="inline-chatbot-results" id="recommendedChatbotResults"></div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
    (function () {
        var widget=document.getElementById('recommendedSlideChatbot');
        var toggle=document.getElementById('recommendedSlideChatbotToggle');
        var panel=document.getElementById('recommendedSlideChatbotPanel');
        var closeBtn=document.getElementById('recommendedSlideChatbotClose');
        var form=document.getElementById('recommendedChatbotForm');
        var textarea=document.getElementById('recommendedChatbotContent');
        var pasteBtn=document.getElementById('recommendedChatbotPaste');
        var statusBox=document.getElementById('recommendedChatbotStatus');
        var resultsBox=document.getElementById('recommendedChatbotResults');
        var topicButtons=document.getElementsByClassName('recommended-topic-btn');
        var hasAutoLoaded=false;
        var defaultTopicText='Algorithms and data structures help solve problems efficiently through step by step logic, sorting, searching, graphs, trees, recursion, and computational thinking used in programming and computer science.';

        if(!widget || !toggle || !panel || !closeBtn || !form || !textarea || !pasteBtn || !statusBox || !resultsBox){
            return;
        }

        function setOpenState(isOpen) {
            if(isOpen){
                widget.className='recommended-slide-chatbot recommended-slide-chatbot--open';
                panel.setAttribute('aria-hidden', 'false');
            } else {
                widget.className='recommended-slide-chatbot';
                panel.setAttribute('aria-hidden', 'true');
            }
        }

        function setStatus(message, isError) {
            statusBox.className=isError ? 'inline-chatbot-status is-error' : 'inline-chatbot-status';
            if(typeof statusBox.textContent!=='undefined'){
                statusBox.textContent=message;
            } else {
                statusBox.innerText=message;
            }
        }

        function escapeHtml(value) {
            return String(value).replace(/[&<>"']/g, function (char) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[char];
            });
        }

        function renderResults(books) {
            var html='';
            var i=0;
            var book=null;

            if(!books || !books.length){
                resultsBox.innerHTML='';
                return;
            }

            html='<div class="row">';
            for(i=0;i<books.length;i++){
                book=books[i];
                html+='<div class="col-md-4 col-sm-6">';
                html+='<div class="recommended-card">';
                html+='<span class="inline-chatbot-match">'+escapeHtml(book.smartMatchPercent)+'% smart match</span>';
                html+='<h5>'+escapeHtml(book.title)+'</h5>';
                html+='<p><strong>Author:</strong> '+escapeHtml(book.author)+'</p>';
                html+='<p><strong>Category:</strong> '+escapeHtml(book.category)+'</p>';
                html+='<p><strong>Available:</strong> '+escapeHtml(book.availableQty)+'</p>';
                html+='<p><strong>Rating:</strong> '+escapeHtml(Number(book.averageRating).toFixed(1))+' / 5 ('+escapeHtml(book.reviewCount)+' reviews)</p>';
                html+='<p class="inline-chatbot-reason"><strong>Why matched:</strong> '+escapeHtml(book.matchReason || 'Recommended for you')+'</p>';
                html+='<a class="btn btn-info btn-sm" href="'+escapeHtml(book.detailsUrl)+'">Open Details</a>';
                html+='</div>';
                html+='</div>';
            }
            html+='</div>';
            resultsBox.innerHTML=html;
        }

        function loadRecommendations(content, autoOpen) {
            var xhr=null;

            if(autoOpen){
                setOpenState(true);
            }

            textarea.value=content;
            resultsBox.innerHTML='';

            if(content.replace(/^\s+|\s+$/g, '').length<25){
                setStatus('Paste at least a few sentences so the chatbot can detect the topic and category.', true);
                return false;
            }

            setStatus('Checking your text and finding similar books...', false);

            xhr=new XMLHttpRequest();
            xhr.open('POST', 'chatbot-recommend.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onreadystatechange=function () {
                var response=null;

                if(xhr.readyState!==4){
                    return;
                }

                try {
                    response=JSON.parse(xhr.responseText);
                } catch (error) {
                    response=null;
                }

                if(xhr.status!==200 || !response){
                    setStatus('Recommendations could not be loaded right now. Please try again.', true);
                    return;
                }

                setStatus(response.message || 'Recommendations loaded.', !response.success);
                renderResults(response.books || []);
            };
            xhr.send('content='+encodeURIComponent(content));
            return true;
        }

        toggle.onclick=function () {
            var isClosed=panel.getAttribute('aria-hidden')!=='false';

            setOpenState(isClosed);
            if(isClosed && !hasAutoLoaded){
                hasAutoLoaded=true;
                loadRecommendations(defaultTopicText, false);
            }
        };

        closeBtn.onclick=function () {
            setOpenState(false);
        };

        pasteBtn.onclick=function () {
            if(navigator.clipboard && navigator.clipboard.readText){
                navigator.clipboard.readText().then(function (text) {
                    if(text){
                        textarea.value=text;
                        setStatus('Clipboard text pasted. Click "Find Similar Books" to get recommendations.', false);
                    } else {
                        setStatus('Clipboard is empty. Copy a line from a book first.', true);
                    }
                }).catch(function () {
                    setStatus('Clipboard access was blocked. Paste the text manually into the box.', true);
                });
            } else {
                setStatus('Clipboard paste is not supported here. Paste the text manually into the box.', true);
            }
        };

        for(var index=0; index<topicButtons.length; index++){
            topicButtons[index].onclick=function () {
                var topic=this.getAttribute('data-topic') || 'algorithm';
                var content='This reader is interested in ' + topic + ' books, concepts, examples, theory, problem solving, and practical learning from the same topic area.';
                loadRecommendations(content, true);
            };
        }

        form.onsubmit=function (event) {
            var content=textarea.value.replace(/^\s+|\s+$/g, '');

            event.preventDefault();
            loadRecommendations(content, true);
            return false;
        };
    })();
    </script>
<?php } ?>
</body>
</html>
