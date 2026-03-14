<?php
require_once('includes/config.php');
include_once('../includes/store-helpers.php');
if(!empty($_POST["bookid"])) {
$bookid=trim($_POST["bookid"]);
$bookname='%' . $bookid . '%';

$sql ="SELECT tblbooks.BookName,tblcategory.CategoryName,tblauthors.AuthorName,tblbooks.ISBNNumber,
tblbooks.BookPrice,tblbooks.id as bookid,tblbooks.bookImage,tblbooks.bookQty," . getInventorySelectSql() . "
FROM tblbooks
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
LEFT JOIN tblcategory ON tblcategory.id=tblbooks.CatId
" . getInventoryIssueJoinSql('tblbooks') . "
" . getInventoryOrderJoinSql('tblbooks') . "
WHERE tblbooks.ISBNNumber=:bookid OR tblbooks.BookName LIKE :bookname
ORDER BY tblbooks.id";
$query= $dbh->prepare($sql);
$query->bindParam(':bookid', $bookid, PDO::PARAM_STR);
$query->bindParam(':bookname', $bookname, PDO::PARAM_STR);
$query->execute();
$results = $query->fetchAll(PDO::FETCH_OBJ);
if($query->rowCount() > 0){
?>
<table border="1">
  <tr>
<?php foreach ($results as $result) {
$availableQty=calculateAvailableBookQty($result->bookQty, $result->activeIssues, $result->soldQty);
?>
    <th style="padding-left:5%; width: 10%;">
<img src="bookimg/<?php echo htmlentities($result->bookImage); ?>" width="120"><br />
      <?php echo htmlentities($result->BookName); ?><br />
    <?php echo htmlentities($result->AuthorName); ?><br />
  Book Quantity: <?php echo htmlentities($result->bookQty); ?><br />
  Active Issues: <?php echo htmlentities($result->activeIssues); ?><br />
  Sold Copies: <?php echo htmlentities($result->soldQty); ?><br />
  Available Book Quantity: <?php echo htmlentities($availableQty); ?><br />
    <?php if($availableQty<=0): ?>
<p style="color:red;">Book not available for issue.</p>
<?php else:?>
<input type="radio" name="bookid" value="<?php echo htmlentities($result->bookid); ?>" required>
<input type="hidden" name="aqty" value="<?php echo htmlentities($availableQty); ?>" required>
<?php endif;?>
  </th>
    <?php echo "<script>$('#submit').prop('disabled',false);</script>";
}
?>
  </tr>
</table>
<?php
} else {?>
<p>Record not found. Please try again.</p>
<?php
 echo "<script>$('#submit').prop('disabled',true);</script>";
}
}
?>
