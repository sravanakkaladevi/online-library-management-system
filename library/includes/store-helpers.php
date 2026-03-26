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

function ensureBookReviewTable($dbh)
{
static $isReady=false;
if($isReady)
{
return;
}

$sql="CREATE TABLE IF NOT EXISTS tblbookreviews (
id int NOT NULL AUTO_INCREMENT,
BookId int NOT NULL,
StudentId varchar(100) NOT NULL,
Rating tinyint NOT NULL,
ReviewText mediumtext,
CreatedDate timestamp NULL DEFAULT CURRENT_TIMESTAMP,
UpdatedDate timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
PRIMARY KEY (id),
UNIQUE KEY uniq_book_review_student (BookId,StudentId),
KEY idx_book_reviews_book (BookId),
KEY idx_book_reviews_student (StudentId)
) ENGINE=InnoDB DEFAULT CHARSET=latin1";
$dbh->exec($sql);
$isReady=true;
}

function normalizeReviewText($text)
{
$text=strtolower(trim((string)$text));
$text=preg_replace('/[^a-z0-9\s]+/', ' ', $text);
$text=preg_replace('/\s+/', ' ', $text);
return trim((string)$text);
}

function extractReviewKeywords($text)
{
$text=normalizeReviewText($text);
if($text==='')
{
return array();
}

$stopWords=array(
'a','an','and','are','as','at','be','been','book','books','but','by','for','from','good','great',
'had','has','have','i','in','into','is','it','its','me','my','of','on','or','so','that','the','their',
'them','this','to','very','was','we','were','with','you','your'
);
$stopWordMap=array_fill_keys($stopWords, true);
$keywords=array();
$parts=explode(' ', $text);
foreach($parts as $part)
{
$part=trim($part);
if($part==='' || strlen($part)<3 || isset($stopWordMap[$part]))
{
continue;
}
if(!isset($keywords[$part]))
{
$keywords[$part]=0;
}
$keywords[$part]++;
}
return $keywords;
}

function buildKeywordProfileFromTexts($texts)
{
$profile=array();
foreach($texts as $text)
{
$keywords=extractReviewKeywords($text);
foreach($keywords as $keyword => $count)
{
if(!isset($profile[$keyword]))
{
$profile[$keyword]=0;
}
$profile[$keyword]+=$count;
}
}
return $profile;
}

function calculateKeywordSimilarityScore($sourceProfile, $targetProfile)
{
if(empty($sourceProfile) || empty($targetProfile))
{
return 0;
}

$dotProduct=0;
$sourceMagnitude=0;
$targetMagnitude=0;

foreach($sourceProfile as $keyword => $weight)
{
$sourceMagnitude+=($weight*$weight);
if(isset($targetProfile[$keyword]))
{
$dotProduct+=($weight*$targetProfile[$keyword]);
}
}

foreach($targetProfile as $weight)
{
$targetMagnitude+=($weight*$weight);
}

if($dotProduct<=0 || $sourceMagnitude<=0 || $targetMagnitude<=0)
{
return 0;
}

return $dotProduct / (sqrt($sourceMagnitude) * sqrt($targetMagnitude));
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
$bookRows=array($book);
attachReviewDataToBookRows($dbh, $bookRows);
$book=$bookRows[0];
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

function fetchCatalogFilterOptions($dbh)
{
$filters=array(
'categories' => array(),
'authors' => array(),
);

$categorySql="SELECT id,CategoryName FROM tblcategory WHERE Status=1 ORDER BY CategoryName ASC";
$categoryQuery=$dbh->prepare($categorySql);
$categoryQuery->execute();
$filters['categories']=$categoryQuery->fetchAll(PDO::FETCH_ASSOC);

$authorSql="SELECT id,AuthorName FROM tblauthors ORDER BY AuthorName ASC";
$authorQuery=$dbh->prepare($authorSql);
$authorQuery->execute();
$filters['authors']=$authorQuery->fetchAll(PDO::FETCH_ASSOC);

return $filters;
}

function getBookCatalogFiltersFromRequest()
{
$keyword=isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$categoryId=isset($_GET['category']) ? intval($_GET['category']) : 0;
$authorId=isset($_GET['author']) ? intval($_GET['author']) : 0;
$availability=isset($_GET['availability']) ? trim((string)$_GET['availability']) : 'all';
$preview=isset($_GET['preview']) ? trim((string)$_GET['preview']) : 'all';
$sort=isset($_GET['sort']) ? trim((string)$_GET['sort']) : 'latest';

$allowedAvailability=array('all','available','unavailable');
if(!in_array($availability,$allowedAvailability,true))
{
$availability='all';
}

$allowedPreview=array('all','yes','no');
if(!in_array($preview,$allowedPreview,true))
{
$preview='all';
}

$allowedSort=array('recommended','popular','latest','name_asc','price_low','price_high');
if(!in_array($sort,$allowedSort,true))
{
$sort='latest';
}

return array(
'keyword' => $keyword,
'category' => $categoryId,
'author' => $authorId,
'availability' => $availability,
'preview' => $preview,
'sort' => $sort,
);
}

function fetchStudentPreferenceProfile($dbh, $studentId)
{
$profile=array(
'categories' => array(),
'authors' => array(),
);

if(trim((string)$studentId)==='')
{
return $profile;
}

$sql="SELECT tblbooks.CatId,tblbooks.AuthorId
FROM tblbooks
INNER JOIN (
SELECT BookId FROM tblissuedbookdetails WHERE StudentID=:issuedSid
UNION ALL
SELECT BookId FROM tblbookrequests WHERE StudentId=:requestSid
UNION ALL
SELECT BookId FROM tblcart WHERE StudentId=:cartSid
UNION ALL
SELECT tblorderitems.BookId
FROM tblorderitems
INNER JOIN tblorders ON tblorders.id=tblorderitems.OrderId
WHERE tblorders.StudentId=:orderSid
) student_books ON student_books.BookId=tblbooks.id";
$query=$dbh->prepare($sql);
$query->bindValue(':issuedSid',$studentId,PDO::PARAM_STR);
$query->bindValue(':requestSid',$studentId,PDO::PARAM_STR);
$query->bindValue(':cartSid',$studentId,PDO::PARAM_STR);
$query->bindValue(':orderSid',$studentId,PDO::PARAM_STR);
$query->execute();
$rows=$query->fetchAll(PDO::FETCH_ASSOC);

foreach($rows as $row)
{
$categoryId=(int)$row['CatId'];
$authorId=(int)$row['AuthorId'];

if($categoryId>0)
{
if(!isset($profile['categories'][$categoryId]))
{
$profile['categories'][$categoryId]=0;
}
$profile['categories'][$categoryId]++;
}

if($authorId>0)
{
if(!isset($profile['authors'][$authorId]))
{
$profile['authors'][$authorId]=0;
}
$profile['authors'][$authorId]++;
}
}

return $profile;
}

function getRecommendedScoreExpression($bookAlias, $preferenceProfile=array())
{
$scoreParts=array();
$scoreParts[]="(COALESCE(issue_counts.activeIssues,0) * 3)";
$scoreParts[]="(COALESCE(order_counts.soldQty,0) * 2)";

$categoryScores=array();
if(!empty($preferenceProfile['categories']))
{
foreach($preferenceProfile['categories'] as $categoryId => $weight)
{
$categoryScores[]="WHEN " . $bookAlias . ".CatId=" . intval($categoryId) . " THEN " . intval($weight * 12);
}
}

if(!empty($categoryScores))
{
$scoreParts[]="(CASE " . implode(' ', $categoryScores) . " ELSE 0 END)";
}

$authorScores=array();
if(!empty($preferenceProfile['authors']))
{
foreach($preferenceProfile['authors'] as $authorId => $weight)
{
$authorScores[]="WHEN " . $bookAlias . ".AuthorId=" . intval($authorId) . " THEN " . intval($weight * 16);
}
}

if(!empty($authorScores))
{
$scoreParts[]="(CASE " . implode(' ', $authorScores) . " ELSE 0 END)";
}

$scoreParts[]="(CASE WHEN " . $bookAlias . ".PreviewLink IS NOT NULL AND TRIM(" . $bookAlias . ".PreviewLink)<>'' THEN 4 ELSE 0 END)";
$scoreParts[]="(CASE WHEN ((" . $bookAlias . ".bookQty - COALESCE(issue_counts.activeIssues,0) - COALESCE(order_counts.soldQty,0)) > 0) THEN 6 ELSE 0 END)";

return '(' . implode(' + ', $scoreParts) . ')';
}

function getBookCatalogOrderBySql($sort, $bookAlias, $recommendedScoreExpression)
{
if($sort==='popular')
{
return "COALESCE(issue_counts.activeIssues,0) DESC, COALESCE(order_counts.soldQty,0) DESC, " . $bookAlias . ".BookName ASC";
}

if($sort==='latest')
{
return $bookAlias . ".id DESC";
}

if($sort==='name_asc')
{
return $bookAlias . ".BookName ASC";
}

if($sort==='price_low')
{
return $bookAlias . ".BookPrice ASC, " . $bookAlias . ".BookName ASC";
}

if($sort==='price_high')
{
return $bookAlias . ".BookPrice DESC, " . $bookAlias . ".BookName ASC";
}

return $recommendedScoreExpression . " DESC, " . $bookAlias . ".BookName ASC";
}

function fetchBookReviewStatsMap($dbh, $bookIds=array())
{
ensureBookReviewTable($dbh);
$statsMap=array();
if(empty($bookIds))
{
return $statsMap;
}

$bookIds=array_values(array_unique(array_map('intval', $bookIds)));
$bookIds=array_filter($bookIds, function($bookId) {
return $bookId>0;
});
if(empty($bookIds))
{
return $statsMap;
}

$placeholders=array();
$params=array();
foreach($bookIds as $index => $bookId)
{
$key=':bookid' . $index;
$placeholders[]=$key;
$params[$key]=$bookId;
}

$sql="SELECT BookId,COUNT(*) AS reviewCount,ROUND(AVG(Rating),1) AS averageRating
FROM tblbookreviews
WHERE BookId IN (" . implode(',', $placeholders) . ")
GROUP BY BookId";
$query=$dbh->prepare($sql);
foreach($params as $key => $bookId)
{
$query->bindValue($key,$bookId,PDO::PARAM_INT);
}
$query->execute();
$rows=$query->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row)
{
$statsMap[(int)$row['BookId']]=array(
'reviewCount' => (int)$row['reviewCount'],
'averageRating' => $row['averageRating']!==null ? (float)$row['averageRating'] : 0.0,
);
}
return $statsMap;
}

function fetchBookReviewKeywordProfiles($dbh, $bookIds=array())
{
ensureBookReviewTable($dbh);
$profiles=array();
if(empty($bookIds))
{
return $profiles;
}

$bookIds=array_values(array_unique(array_map('intval', $bookIds)));
$bookIds=array_filter($bookIds, function($bookId) {
return $bookId>0;
});
if(empty($bookIds))
{
return $profiles;
}

$placeholders=array();
$params=array();
foreach($bookIds as $index => $bookId)
{
$key=':reviewBook' . $index;
$placeholders[]=$key;
$params[$key]=$bookId;
}

$sql="SELECT BookId,ReviewText FROM tblbookreviews WHERE BookId IN (" . implode(',', $placeholders) . ")";
$query=$dbh->prepare($sql);
foreach($params as $key => $bookId)
{
$query->bindValue($key,$bookId,PDO::PARAM_INT);
}
$query->execute();
$rows=$query->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row)
{
$bookId=(int)$row['BookId'];
if(!isset($profiles[$bookId]))
{
$profiles[$bookId]=array();
}
$profiles[$bookId][]=(string)$row['ReviewText'];
}

foreach($profiles as $bookId => $texts)
{
$profiles[$bookId]=buildKeywordProfileFromTexts($texts);
}

return $profiles;
}

function fetchStudentReviewKeywordProfile($dbh, $studentId, $excludeBookId=0)
{
ensureBookReviewTable($dbh);
$studentId=trim((string)$studentId);
if($studentId==='')
{
return array();
}

$sql="SELECT ReviewText FROM tblbookreviews WHERE StudentId=:sid";
if((int)$excludeBookId>0)
{
$sql.=" AND BookId<>:excludeBookId";
}
$query=$dbh->prepare($sql);
$query->bindValue(':sid',$studentId,PDO::PARAM_STR);
if((int)$excludeBookId>0)
{
$query->bindValue(':excludeBookId',(int)$excludeBookId,PDO::PARAM_INT);
}
$query->execute();
$rows=$query->fetchAll(PDO::FETCH_ASSOC);
$texts=array();
foreach($rows as $row)
{
$texts[]=(string)$row['ReviewText'];
}
return buildKeywordProfileFromTexts($texts);
}

function attachReviewDataToBookRows($dbh, &$books, $studentId='', $excludeBookId=0)
{
if(empty($books))
{
return;
}

ensureBookReviewTable($dbh);
$bookIds=array();
foreach($books as $book)
{
$bookIds[]=(int)(isset($book['bookid']) ? $book['bookid'] : $book['id']);
}

$statsMap=fetchBookReviewStatsMap($dbh, $bookIds);
$studentKeywordProfile=fetchStudentReviewKeywordProfile($dbh, $studentId, $excludeBookId);
$bookKeywordProfiles=fetchBookReviewKeywordProfiles($dbh, $bookIds);

foreach($books as $index => $book)
{
$bookId=(int)(isset($book['bookid']) ? $book['bookid'] : $book['id']);
$stats=isset($statsMap[$bookId]) ? $statsMap[$bookId] : array('reviewCount' => 0, 'averageRating' => 0.0);
$books[$index]['reviewCount']=$stats['reviewCount'];
$books[$index]['averageRating']=$stats['averageRating'];
$books[$index]['reviewTextScore']=0.0;
$books[$index]['finalRecommendationScore']=isset($book['recommendedScore']) ? (float)$book['recommendedScore'] : 0.0;

if(isset($bookKeywordProfiles[$bookId]))
{
$textScore=calculateKeywordSimilarityScore($studentKeywordProfile, $bookKeywordProfiles[$bookId]);
$books[$index]['reviewTextScore']=$textScore;
$books[$index]['finalRecommendationScore']+=(float)($textScore * 35);
}

$books[$index]['finalRecommendationScore']+=($stats['averageRating'] * 6);
$books[$index]['finalRecommendationScore']+=min($stats['reviewCount'], 5);
}
}

function sortBooksByRecommendationScore(&$books)
{
usort($books, function($left, $right) {
$leftScore=isset($left['finalRecommendationScore']) ? (float)$left['finalRecommendationScore'] : 0.0;
$rightScore=isset($right['finalRecommendationScore']) ? (float)$right['finalRecommendationScore'] : 0.0;
if($leftScore===$rightScore)
{
return strcmp((string)$left['BookName'], (string)$right['BookName']);
}
return ($leftScore < $rightScore) ? 1 : -1;
});
}

function fetchCatalogBooks($dbh, $filters=array(), $studentId='', $excludeBookId=0)
{
$defaults=array(
'keyword' => '',
'category' => 0,
'author' => 0,
'availability' => 'all',
'preview' => 'all',
'sort' => 'latest',
);
$filters=array_merge($defaults, $filters);
$preferenceProfile=fetchStudentPreferenceProfile($dbh, $studentId);
$recommendedScoreExpression=getRecommendedScoreExpression('tblbooks', $preferenceProfile);

$sql="SELECT tblbooks.BookName,tblcategory.CategoryName,tblauthors.AuthorName,tblbooks.ISBNNumber,
tblbooks.BookPrice,tblbooks.id as bookid,tblbooks.bookImage,tblbooks.bookQty,tblbooks.PreviewLink,
" . getInventorySelectSql() . ",
" . $recommendedScoreExpression . " AS recommendedScore
FROM tblbooks
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
LEFT JOIN tblcategory ON tblcategory.id=tblbooks.CatId
" . getInventoryIssueJoinSql('tblbooks') . "
" . getInventoryOrderJoinSql('tblbooks') . "
WHERE 1=1";

$params=array();

if($excludeBookId>0)
{
$sql.=" AND tblbooks.id<>:excludeBookId";
$params[':excludeBookId']=array('value' => $excludeBookId, 'type' => PDO::PARAM_INT);
}

if($filters['keyword']!=='')
{
$sql.=" AND (tblbooks.BookName LIKE :keyword OR tblbooks.ISBNNumber LIKE :keyword OR tblauthors.AuthorName LIKE :keyword OR tblcategory.CategoryName LIKE :keyword)";
$params[':keyword']=array('value' => '%' . $filters['keyword'] . '%', 'type' => PDO::PARAM_STR);
}

if((int)$filters['category']>0)
{
$sql.=" AND tblbooks.CatId=:categoryId";
$params[':categoryId']=array('value' => (int)$filters['category'], 'type' => PDO::PARAM_INT);
}

if((int)$filters['author']>0)
{
$sql.=" AND tblbooks.AuthorId=:authorId";
$params[':authorId']=array('value' => (int)$filters['author'], 'type' => PDO::PARAM_INT);
}

if($filters['availability']==='available')
{
$sql.=" AND (tblbooks.bookQty - COALESCE(issue_counts.activeIssues,0) - COALESCE(order_counts.soldQty,0)) > 0";
}
elseif($filters['availability']==='unavailable')
{
$sql.=" AND (tblbooks.bookQty - COALESCE(issue_counts.activeIssues,0) - COALESCE(order_counts.soldQty,0)) <= 0";
}

if($filters['preview']==='yes')
{
$sql.=" AND tblbooks.PreviewLink IS NOT NULL AND TRIM(tblbooks.PreviewLink)<>''";
}
elseif($filters['preview']==='no')
{
$sql.=" AND (tblbooks.PreviewLink IS NULL OR TRIM(tblbooks.PreviewLink)='')";
}

$sql.=" ORDER BY " . getBookCatalogOrderBySql($filters['sort'], 'tblbooks', $recommendedScoreExpression);

$query=$dbh->prepare($sql);
foreach($params as $key => $param)
{
$query->bindValue($key, $param['value'], $param['type']);
}
$query->execute();
$books=$query->fetchAll(PDO::FETCH_ASSOC);

foreach($books as $index => $book)
{
$books[$index]['availableQty']=calculateAvailableBookQty($book['bookQty'], $book['activeIssues'], $book['soldQty']);
}

attachReviewDataToBookRows($dbh, $books, $studentId, $excludeBookId);
if($filters['sort']==='recommended')
{
sortBooksByRecommendationScore($books);
}

return $books;
}

function fetchRecommendedBooks($dbh, $studentId, $limit=4, $excludeBookId=0)
{
$books=fetchCatalogBooks(
$dbh,
array(
'sort' => 'recommended',
'availability' => 'available',
),
$studentId,
$excludeBookId
);

if($limit>0)
{
$books=array_slice($books, 0, $limit);
}

return $books;
}

function hasStudentDeliveredOrderForBook($dbh, $studentId, $bookId)
{
$studentId=trim((string)$studentId);
$bookId=(int)$bookId;
if($studentId==='' || $bookId<=0)
{
return false;
}

$sql="SELECT 1
FROM tblorderitems
INNER JOIN tblorders ON tblorders.id=tblorderitems.OrderId
WHERE tblorders.StudentId=:sid
AND tblorders.OrderStatus='delivered'
AND tblorderitems.BookId=:bookid
LIMIT 1";
$query=$dbh->prepare($sql);
$query->bindValue(':sid',$studentId,PDO::PARAM_STR);
$query->bindValue(':bookid',$bookId,PDO::PARAM_INT);
$query->execute();
return (bool)$query->fetch(PDO::FETCH_ASSOC);
}

function canStudentReviewBook($dbh, $studentId, $bookId)
{
$studentId=trim((string)$studentId);
$bookId=(int)$bookId;
if($studentId==='' || $bookId<=0)
{
return false;
}

$sql="SELECT 1
FROM (
SELECT BookId FROM tblissuedbookdetails WHERE StudentID=:issuedSid
UNION ALL
SELECT BookId FROM tblbookrequests WHERE StudentId=:requestSid AND Status IN (0,1,2)
) interactions
WHERE BookId=:bookid
LIMIT 1";
$query=$dbh->prepare($sql);
$query->bindValue(':issuedSid',$studentId,PDO::PARAM_STR);
$query->bindValue(':requestSid',$studentId,PDO::PARAM_STR);
$query->bindValue(':bookid',$bookId,PDO::PARAM_INT);
$query->execute();
$hasIssueOrRequest=(bool)$query->fetch(PDO::FETCH_ASSOC);
if($hasIssueOrRequest)
{
return true;
}

return hasStudentDeliveredOrderForBook($dbh, $studentId, $bookId);
}

function fetchStudentBookReview($dbh, $studentId, $bookId)
{
ensureBookReviewTable($dbh);
$sql="SELECT id,BookId,StudentId,Rating,ReviewText,CreatedDate,UpdatedDate
FROM tblbookreviews
WHERE StudentId=:sid AND BookId=:bookid
LIMIT 1";
$query=$dbh->prepare($sql);
$query->bindValue(':sid',$studentId,PDO::PARAM_STR);
$query->bindValue(':bookid',(int)$bookId,PDO::PARAM_INT);
$query->execute();
$review=$query->fetch(PDO::FETCH_ASSOC);
return $review ? $review : null;
}

function fetchBookReviews($dbh, $bookId, $limit=10)
{
ensureBookReviewTable($dbh);
$limit=max(1, (int)$limit);
$sql="SELECT tblbookreviews.id,tblbookreviews.Rating,tblbookreviews.ReviewText,tblbookreviews.CreatedDate,
tblstudents.FullName,tblstudents.StudentId
FROM tblbookreviews
LEFT JOIN tblstudents ON tblstudents.StudentId=tblbookreviews.StudentId
WHERE tblbookreviews.BookId=:bookid
ORDER BY tblbookreviews.UpdatedDate DESC, tblbookreviews.CreatedDate DESC
LIMIT " . $limit;
$query=$dbh->prepare($sql);
$query->bindValue(':bookid',(int)$bookId,PDO::PARAM_INT);
$query->execute();
return $query->fetchAll(PDO::FETCH_ASSOC);
}

function saveBookReview($dbh, $studentId, $bookId, $rating, $reviewText)
{
ensureBookReviewTable($dbh);
$studentId=trim((string)$studentId);
$bookId=(int)$bookId;
$rating=(int)$rating;
$reviewText=trim((string)$reviewText);

if($studentId==='' || $bookId<=0)
{
return array('success' => false, 'message' => 'Invalid review request.');
}

if($rating<1 || $rating>5)
{
return array('success' => false, 'message' => 'Please choose a rating between 1 and 5.');
}

if($reviewText==='')
{
return array('success' => false, 'message' => 'Please write a short review.');
}

if(strlen($reviewText)>1500)
{
return array('success' => false, 'message' => 'Review text is too long. Keep it under 1500 characters.');
}

if(!canStudentReviewBook($dbh, $studentId, $bookId))
{
return array('success' => false, 'message' => 'You can review this book after you request or issue it, or once your order is delivered.');
}

$existingReview=fetchStudentBookReview($dbh, $studentId, $bookId);
if($existingReview)
{
$sql="UPDATE tblbookreviews
SET Rating=:rating,ReviewText=:reviewtext
WHERE BookId=:bookid AND StudentId=:sid";
$message='Your review was updated successfully.';
}
else {
$sql="INSERT INTO tblbookreviews(BookId,StudentId,Rating,ReviewText)
VALUES(:bookid,:sid,:rating,:reviewtext)";
$message='Your review was submitted successfully.';
}

$query=$dbh->prepare($sql);
$query->bindValue(':bookid',$bookId,PDO::PARAM_INT);
$query->bindValue(':sid',$studentId,PDO::PARAM_STR);
$query->bindValue(':rating',$rating,PDO::PARAM_INT);
$query->bindValue(':reviewtext',$reviewText,PDO::PARAM_STR);
$query->execute();

return array('success' => true, 'message' => $message);
}

function renderStarRating($rating)
{
$rating=max(0, min(5, (float)$rating));
$fullStars=(int)floor($rating);
$emptyStars=5-$fullStars;
return str_repeat('*', $fullStars) . str_repeat('.', $emptyStars);
}

function getDisplayValue($value, $fallback='Not Available')
{
$value=trim((string)$value);
if($value==='')
{
return $fallback;
}
return $value;
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
