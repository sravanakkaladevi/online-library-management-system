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

$bookid=intval($_GET['bookid']);
if($bookid<=0)
{
$_SESSION['error']="Invalid book selected.";
header('location:listed-books.php');
exit;
}

$book=fetchBookWithInventory($dbh, $bookid);
if(!$book)
{
$_SESSION['error']="Book not found.";
header('location:listed-books.php');
exit;
}

if(!hasBookPreview($book['PreviewLink']))
{
$_SESSION['error']="Preview link is not available for this book yet.";
header('location:book-details.php?bookid=' . $bookid);
exit;
}

$previewEmbedUrl=getBookPreviewEmbedUrl($book['PreviewLink']);
$previewOpenUrl=getBookPreviewOpenUrl($book['PreviewLink']);
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="description" content="" />
    <meta name="author" content="" />
    <title>Online Library Management System | Preview Book</title>
    <link href="assets/css/bootstrap.css" rel="stylesheet" />
    <link href="assets/css/font-awesome.css" rel="stylesheet" />
    <link href="assets/css/style.css" rel="stylesheet" />
    <link href='http://fonts.googleapis.com/css?family=Open+Sans' rel='stylesheet' type='text/css' />
    <style type="text/css">
        .preview-frame {
            width: 100%;
            min-height: 720px;
            border: 1px solid #d6d6d6;
            background: #fafafa;
        }
    </style>
</head>
<body>
<?php include('includes/header.php');?>
    <div class="content-wrapper">
         <div class="container">
        <div class="row pad-botm">
            <div class="col-md-8">
                <h4 class="header-line">Preview Book</h4>
            </div>
            <div class="col-md-4 text-right">
                <a href="book-details.php?bookid=<?php echo htmlentities($bookid);?>" class="btn btn-default">Back to Details</a>
                <a href="<?php echo htmlentities($previewOpenUrl);?>" target="_blank" rel="noopener noreferrer" class="btn btn-info">Open in New Tab</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-info">
                    <div class="panel-heading">
                        <?php echo htmlentities($book['BookName']);?> Preview
                    </div>
                    <div class="panel-body">
                        <p><strong>Author:</strong> <?php echo htmlentities($book['AuthorName']);?></p>
                        <iframe class="preview-frame" src="<?php echo htmlentities($previewEmbedUrl);?>" allow="autoplay"></iframe>
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
