<?php
if (!function_exists('getInventoryIssueJoinSql')) {
function getInventoryIssueJoinSql($bookAlias)
{
return "LEFT JOIN (
SELECT BookId,
COUNT(*) AS totalIssued,
SUM(CASE WHEN RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0 THEN 1 ELSE 0 END) AS activeIssues
FROM tblissuedbookdetails
GROUP BY BookId
) issue_counts ON issue_counts.BookId=" . $bookAlias . ".id";
}

function getInventoryOrderJoinSql($bookAlias)
{
return "LEFT JOIN (
SELECT tblorderitems.BookId,
SUM(tblorderitems.Quantity) AS soldQty
FROM tblorderitems
INNER JOIN tblorders ON tblorders.id=tblorderitems.OrderId
WHERE tblorders.PaymentStatus='paid' AND tblorders.OrderStatus<>'cancelled'
GROUP BY tblorderitems.BookId
) order_counts ON order_counts.BookId=" . $bookAlias . ".id";
}

function getInventorySelectSql()
{
return "COALESCE(issue_counts.totalIssued,0) AS issuedBooks,
COALESCE(issue_counts.activeIssues,0) AS activeIssues,
COALESCE(order_counts.soldQty,0) AS soldQty";
}

function normalizeBookPreviewLink($previewLink)
{
return trim((string)$previewLink);
}

function extractGoogleDriveFileId($previewLink)
{
$previewLink=normalizeBookPreviewLink($previewLink);
if($previewLink==='')
{
return '';
}

$patterns=array(
'~drive\.google\.com/file/d/([^/]+)~i',
'~drive\.google\.com/open\?id=([^&]+)~i',
'~drive\.google\.com/uc\?id=([^&]+)~i',
'~docs\.google\.com/uc\?id=([^&]+)~i',
);

foreach($patterns as $pattern)
{
if(preg_match($pattern,$previewLink,$matches))
{
return $matches[1];
}
}

return '';
}

function hasBookPreview($previewLink)
{
return normalizeBookPreviewLink($previewLink)!=='';
}

function getBookPreviewEmbedUrl($previewLink)
{
$previewLink=normalizeBookPreviewLink($previewLink);
if($previewLink==='')
{
return '';
}

$fileId=extractGoogleDriveFileId($previewLink);
if($fileId!=='')
{
return 'https://drive.google.com/file/d/' . $fileId . '/preview';
}

if(preg_match('~docs\.google\.com/document/d/([^/]+)~i',$previewLink,$matches))
{
return 'https://docs.google.com/document/d/' . $matches[1] . '/preview';
}

if(preg_match('~docs\.google\.com/presentation/d/([^/]+)~i',$previewLink,$matches))
{
return 'https://docs.google.com/presentation/d/' . $matches[1] . '/preview';
}

return $previewLink;
}

function getBookPreviewOpenUrl($previewLink)
{
$previewLink=normalizeBookPreviewLink($previewLink);
if($previewLink==='')
{
return '';
}

$fileId=extractGoogleDriveFileId($previewLink);
if($fileId!=='')
{
return 'https://drive.google.com/file/d/' . $fileId . '/view';
}

return $previewLink;
}

function calculateAvailableBookQty($bookQty, $activeIssues, $soldQty)
{
$available=(int)$bookQty-(int)$activeIssues-(int)$soldQty;
if($available<0)
{
$available=0;
}
return $available;
}

function fetchStudentPendingRequestBookIds($dbh, $studentId)
{
$pendingRequestBookIds=array();
$sql="SELECT BookId FROM tblbookrequests WHERE StudentId=:sid AND Status=0";
$query=$dbh->prepare($sql);
$query->bindParam(':sid',$studentId,PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_ASSOC);
foreach($results as $result)
{
$pendingRequestBookIds[(int)$result['BookId']]=true;
}
return $pendingRequestBookIds;
}

function fetchStudentIssuedBookIds($dbh, $studentId)
{
$activeIssuedBookIds=array();
$sql="SELECT BookId FROM tblissuedbookdetails WHERE StudentID=:sid AND (RetrunStatus IS NULL OR RetrunStatus='' OR RetrunStatus=0)";
$query=$dbh->prepare($sql);
$query->bindParam(':sid',$studentId,PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_ASSOC);
foreach($results as $result)
{
$activeIssuedBookIds[(int)$result['BookId']]=true;
}
return $activeIssuedBookIds;
}

function fetchStudentCartQuantities($dbh, $studentId)
{
$cartQuantities=array();
$sql="SELECT BookId,Quantity FROM tblcart WHERE StudentId=:sid";
$query=$dbh->prepare($sql);
$query->bindParam(':sid',$studentId,PDO::PARAM_STR);
$query->execute();
$results=$query->fetchAll(PDO::FETCH_ASSOC);
foreach($results as $result)
{
$cartQuantities[(int)$result['BookId']]=(int)$result['Quantity'];
}
return $cartQuantities;
}

function fetchBookWithInventory($dbh, $bookId)
{
$sql="SELECT tblbooks.id,tblbooks.BookName,tblbooks.ISBNNumber,tblbooks.BookPrice,tblbooks.bookImage,tblbooks.bookQty,
tblbooks.PreviewLink,
tblcategory.CategoryName,tblauthors.AuthorName," . getInventorySelectSql() . "
FROM tblbooks
LEFT JOIN tblcategory ON tblcategory.id=tblbooks.CatId
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
" . getInventoryIssueJoinSql('tblbooks') . "
" . getInventoryOrderJoinSql('tblbooks') . "
WHERE tblbooks.id=:bookid
LIMIT 1";
$query=$dbh->prepare($sql);
$query->bindParam(':bookid',$bookId,PDO::PARAM_INT);
$query->execute();
$book=$query->fetch(PDO::FETCH_ASSOC);
if($book)
{
$book['availableQty']=calculateAvailableBookQty($book['bookQty'], $book['activeIssues'], $book['soldQty']);
}
return $book;
}

function fetchCartItems($dbh, $studentId)
{
$sql="SELECT tblcart.id AS cartId,tblcart.BookId,tblcart.Quantity,
tblbooks.BookName,tblbooks.ISBNNumber,tblbooks.BookPrice,tblbooks.bookImage,tblbooks.bookQty,
tblcategory.CategoryName,tblauthors.AuthorName," . getInventorySelectSql() . "
FROM tblcart
INNER JOIN tblbooks ON tblbooks.id=tblcart.BookId
LEFT JOIN tblcategory ON tblcategory.id=tblbooks.CatId
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
" . getInventoryIssueJoinSql('tblbooks') . "
" . getInventoryOrderJoinSql('tblbooks') . "
WHERE tblcart.StudentId=:sid
ORDER BY tblcart.id DESC";
$query=$dbh->prepare($sql);
$query->bindParam(':sid',$studentId,PDO::PARAM_STR);
$query->execute();
$rows=$query->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $index => $row)
{
$rows[$index]['availableQty']=calculateAvailableBookQty($row['bookQty'], $row['activeIssues'], $row['soldQty']);
$rows[$index]['lineTotal']=(float)$row['BookPrice']*(int)$row['Quantity'];
}
return $rows;
}

function generateOrderNumber()
{
return 'ORD' . date('YmdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

function generateTransactionId()
{
return 'TXN' . date('YmdHis') . strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

function getOrderStatusOptions()
{
return array(
'placed' => 'Placed',
'packed' => 'Packed',
'in_transit' => 'In Transit',
'out_for_delivery' => 'Out For Delivery',
'delivered' => 'Delivered',
'cancelled' => 'Cancelled',
);
}

function getPaymentStatusOptions()
{
return array(
'paid' => 'Paid',
'refund_pending' => 'Refund Pending',
'refunded' => 'Refunded',
);
}

function formatOrderStatusLabel($orderStatus)
{
$statuses=getOrderStatusOptions();
if(isset($statuses[$orderStatus]))
{
return $statuses[$orderStatus];
}
return ucwords(str_replace('_', ' ', (string)$orderStatus));
}

function formatPaymentStatusLabel($paymentStatus)
{
$statuses=getPaymentStatusOptions();
if(isset($statuses[$paymentStatus]))
{
return $statuses[$paymentStatus];
}
return ucwords(str_replace('_', ' ', (string)$paymentStatus));
}

function canUserCancelOrder($orderStatus)
{
return in_array((string)$orderStatus, array('placed', 'packed'), true);
}

function cancelOrderForStudent($dbh, $orderId, $studentId)
{
$orderSql="SELECT id,OrderNumber,OrderStatus,PaymentStatus
FROM tblorders
WHERE id=:orderid AND StudentId=:sid
LIMIT 1";
$orderQuery=$dbh->prepare($orderSql);
$orderQuery->bindParam(':orderid',$orderId,PDO::PARAM_INT);
$orderQuery->bindParam(':sid',$studentId,PDO::PARAM_STR);
$orderQuery->execute();
$order=$orderQuery->fetch(PDO::FETCH_ASSOC);

if(!$order)
{
return array('success' => false, 'message' => 'Order not found.');
}

if(!canUserCancelOrder($order['OrderStatus']))
{
return array('success' => false, 'message' => 'This order can no longer be cancelled from your account.');
}

$cancelledStatus='cancelled';
$paymentStatus='refund_pending';
$statusNote='Order cancelled by user. Your money will be refunded shortly.';
$updateSql="UPDATE tblorders
SET OrderStatus=:orderstatus,PaymentStatus=:paymentstatus,StatusNote=:statusnote
WHERE id=:orderid AND StudentId=:sid";
$updateQuery=$dbh->prepare($updateSql);
$updateQuery->bindParam(':orderstatus',$cancelledStatus,PDO::PARAM_STR);
$updateQuery->bindParam(':paymentstatus',$paymentStatus,PDO::PARAM_STR);
$updateQuery->bindParam(':statusnote',$statusNote,PDO::PARAM_STR);
$updateQuery->bindParam(':orderid',$orderId,PDO::PARAM_INT);
$updateQuery->bindParam(':sid',$studentId,PDO::PARAM_STR);
$updateQuery->execute();

return array(
'success' => true,
'message' => 'Order cancelled successfully. Your money will be refunded shortly.',
);
}
}
?>
