<?php
session_start();
error_reporting(0);
header('Content-Type: application/json');

include('includes/config.php');
include_once('includes/store-helpers.php');

if (empty($_SESSION['login']) || empty($_SESSION['stdid']))
{
http_response_code(401);
echo json_encode(array(
'success' => false,
'message' => 'Please log in to use the recommendation chatbot.',
));
exit;
}

if($_SERVER['REQUEST_METHOD']!=='POST')
{
http_response_code(405);
echo json_encode(array(
'success' => false,
'message' => 'Invalid request method.',
));
exit;
}

$contentText=isset($_POST['content']) ? trim((string)$_POST['content']) : '';
$excludeBookId=isset($_POST['exclude_bookid']) ? intval($_POST['exclude_bookid']) : 0;
$result=fetchContentRecommendations($dbh, $_SESSION['stdid'], $contentText, 5, $excludeBookId);
$books=array();

if(!empty($result['books']))
{
foreach($result['books'] as $book)
{
$books[]=array(
'bookid' => (int)$book['bookid'],
'title' => getDisplayValue(isset($book['BookName']) ? $book['BookName'] : '', 'Untitled Book'),
'author' => getDisplayValue(isset($book['AuthorName']) ? $book['AuthorName'] : '', 'Author not assigned'),
'category' => getDisplayValue(isset($book['CategoryName']) ? $book['CategoryName'] : '', 'Category not assigned'),
'availableQty' => isset($book['availableQty']) ? (int)$book['availableQty'] : 0,
'averageRating' => isset($book['averageRating']) ? (float)$book['averageRating'] : 0.0,
'reviewCount' => isset($book['reviewCount']) ? (int)$book['reviewCount'] : 0,
'matchPercent' => max(0, min(100, (int)round(((float)$book['contentMatchScore']) * 100))),
'smartMatchPercent' => max(0, min(100, (int)round((((float)$book['contentMatchScore']) * 0.7 + ((float)(isset($book['entityMatchScore']) ? $book['entityMatchScore'] : 0.0)) * 0.3) * 100))),
'matchReason' => isset($book['matchReason']) ? $book['matchReason'] : 'Recommended for you',
'detailsUrl' => 'book-details.php?bookid=' . (int)$book['bookid'],
);
}
}

echo json_encode(array(
'success' => (bool)$result['success'],
'message' => $result['message'],
'books' => $books,
));
exit;
?>
