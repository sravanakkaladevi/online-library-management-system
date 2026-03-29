<?php
session_start();
error_reporting(0);
include('includes/config.php');
include_once('../includes/store-helpers.php');
if(strlen($_SESSION['alogin'])==0)
{
header('location:index.php');
exit;
}

ensureBookReviewTable($dbh);

$summarySql="SELECT COUNT(*) AS totalReviews, ROUND(AVG(Rating),1) AS averageRating, COUNT(DISTINCT BookId) AS reviewedBooks
FROM tblbookreviews";
$summaryQuery=$dbh->prepare($summarySql);
$summaryQuery->execute();
$summary=$summaryQuery->fetch(PDO::FETCH_ASSOC);
$totalReviews=$summary ? (int)$summary['totalReviews'] : 0;
$averageRating=$summary && $summary['averageRating']!==null ? (float)$summary['averageRating'] : 0.0;
$reviewedBooks=$summary ? (int)$summary['reviewedBooks'] : 0;
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Manage Reviews</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/js/dataTables/dataTables.bootstrap.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .review-summary-card {
            border-radius: 16px;
            padding: 18px 16px;
            background: #fff;
            border: 1px solid #dbe4ef;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.08);
            margin-bottom: 20px;
            text-align: center;
        }

        .review-summary-card h5 {
            margin-top: 0;
            color: #486581;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.08em;
        }

        .review-summary-card .review-summary-card__value {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-12">
                <h4 class="header-line">Manage Reviews</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 col-sm-6">
                <div class="review-summary-card">
                    <h5>Total Reviews</h5>
                    <div class="review-summary-card__value"><?php echo htmlentities($totalReviews);?></div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="review-summary-card">
                    <h5>Average Rating</h5>
                    <div class="review-summary-card__value"><?php echo htmlentities(number_format($averageRating,1));?> / 5</div>
                </div>
            </div>
            <div class="col-md-4 col-sm-6">
                <div class="review-summary-card">
                    <h5>Books Reviewed</h5>
                    <div class="review-summary-card__value"><?php echo htmlentities($reviewedBooks);?></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        Reader Ratings and Reviews
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover" id="dataTables-example">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Book Name</th>
                                        <th>Student</th>
                                        <th>Rating</th>
                                        <th>Review</th>
                                        <th>Created</th>
                                        <th>Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
<?php
$sql="SELECT tblbookreviews.id,tblbookreviews.Rating,tblbookreviews.ReviewText,tblbookreviews.CreatedDate,tblbookreviews.UpdatedDate,
tblstudents.StudentId,tblstudents.FullName,tblbooks.BookName
FROM tblbookreviews
LEFT JOIN tblstudents ON tblstudents.StudentId=tblbookreviews.StudentId
LEFT JOIN tblbooks ON tblbooks.id=tblbookreviews.BookId
ORDER BY COALESCE(tblbookreviews.UpdatedDate, tblbookreviews.CreatedDate) DESC, tblbookreviews.id DESC";
$query=$dbh->prepare($sql);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_OBJ);
$cnt=1;
if($query->rowCount() > 0)
{
foreach($results as $result)
{
?>
                                    <tr>
                                        <td><?php echo htmlentities($cnt);?></td>
                                        <td><?php echo htmlentities(getDisplayValue($result->BookName, 'Unknown Book'));?></td>
                                        <td><?php echo htmlentities(getDisplayValue($result->FullName, $result->StudentId));?></td>
                                        <td><?php echo htmlentities($result->Rating);?> / 5</td>
                                        <td><?php echo nl2br(htmlentities(getDisplayValue($result->ReviewText, 'No review text added.')));?></td>
                                        <td><?php echo htmlentities($result->CreatedDate);?></td>
                                        <td><?php echo htmlentities($result->UpdatedDate!="" ? $result->UpdatedDate : '--'); ?></td>
                                    </tr>
<?php
$cnt=$cnt+1;
}}
?>
                                </tbody>
                            </table>
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
    <script src="assets/js/dataTables/jquery.dataTables.js"></script>
    <script src="assets/js/dataTables/dataTables.bootstrap.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
