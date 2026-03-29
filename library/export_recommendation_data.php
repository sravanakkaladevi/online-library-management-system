<?php
include('includes/config.php');
include_once('includes/store-helpers.php');

ensureBookReviewTable($dbh);

$outputDir=__DIR__ . DIRECTORY_SEPARATOR . 'data';
if(!is_dir($outputDir))
{
mkdir($outputDir, 0777, true);
}

$bookSql="SELECT tblbooks.id,tblbooks.BookName,tblbooks.CatId,tblbooks.AuthorId,tblbooks.ISBNNumber,
tblcategory.CategoryName,tblauthors.AuthorName
FROM tblbooks
LEFT JOIN tblcategory ON tblcategory.id=tblbooks.CatId
LEFT JOIN tblauthors ON tblauthors.id=tblbooks.AuthorId
ORDER BY tblbooks.id ASC";
$bookQuery=$dbh->prepare($bookSql);
$bookQuery->execute();
$books=$bookQuery->fetchAll(PDO::FETCH_ASSOC);

$reviewSql="SELECT StudentId,BookId,Rating,ReviewText,CreatedDate,UpdatedDate
FROM tblbookreviews
ORDER BY StudentId ASC, BookId ASC";
$reviewQuery=$dbh->prepare($reviewSql);
$reviewQuery->execute();
$reviews=$reviewQuery->fetchAll(PDO::FETCH_ASSOC);

$payload=array(
'generated_at' => date('c'),
'books' => $books,
'reviews' => $reviews,
);

$outputPath=$outputDir . DIRECTORY_SEPARATOR . 'recommendation_training.json';
file_put_contents($outputPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Exported recommendation training data to: " . $outputPath . PHP_EOL;
