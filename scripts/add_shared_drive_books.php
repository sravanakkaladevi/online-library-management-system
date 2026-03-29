<?php
declare(strict_types=1);

require __DIR__ . '/../library/includes/config.php';

$defaultImage = '1efecc0ca822e40b7b673c0d79ae943f.jpg';
$categoryName = 'E-Books';
$authorName = 'Shared Drive Library';

$books = [
    [
        'title' => 'Algorithms to Live By',
        'isbn' => 'DRV2026001',
        'preview' => 'https://drive.google.com/file/d/1Mp-Qxx6m_k3DZlnO9icctn-wXmlAbIYG/view?usp=sharing',
    ],
    [
        'title' => 'Code',
        'isbn' => 'DRV2026002',
        'preview' => 'https://drive.google.com/file/d/1k4f6dFWY_B94rXGd5Bsw5WCejAWGLTN_/view?usp=sharing',
    ],
    [
        'title' => 'Digital Minimalism',
        'isbn' => 'DRV2026003',
        'preview' => 'https://drive.google.com/file/d/1x4gFmpzjVtFvb0MwJ3EO-tZzgIjcuhzK/view?usp=sharing',
    ],
    [
        'title' => 'Human Compatible',
        'isbn' => 'DRV2026004',
        'preview' => 'https://drive.google.com/file/d/1-vQz3Wyc8A4Lnrvx7oOCHdfHtk0OuE1l/view?usp=sharing',
    ],
    [
        'title' => 'Life 3.0',
        'isbn' => 'DRV2026005',
        'preview' => 'https://drive.google.com/file/d/1O_DZhKXUt-JzE3lBx4dlqUFuQ7jiB4sw/view?usp=sharing',
    ],
];

function findOrCreateCategory(PDO $dbh, string $categoryName): int
{
    $select = $dbh->prepare('SELECT id FROM tblcategory WHERE CategoryName = :name LIMIT 1');
    $select->execute([':name' => $categoryName]);
    $existingId = $select->fetchColumn();
    if ($existingId !== false) {
        return (int) $existingId;
    }

    $insert = $dbh->prepare('INSERT INTO tblcategory (CategoryName, Status) VALUES (:name, 1)');
    $insert->execute([':name' => $categoryName]);

    return (int) $dbh->lastInsertId();
}

function findOrCreateAuthor(PDO $dbh, string $authorName): int
{
    $select = $dbh->prepare('SELECT id FROM tblauthors WHERE AuthorName = :name LIMIT 1');
    $select->execute([':name' => $authorName]);
    $existingId = $select->fetchColumn();
    if ($existingId !== false) {
        return (int) $existingId;
    }

    $insert = $dbh->prepare('INSERT INTO tblauthors (AuthorName) VALUES (:name)');
    $insert->execute([':name' => $authorName]);

    return (int) $dbh->lastInsertId();
}

function upsertBook(PDO $dbh, array $book, int $categoryId, int $authorId, string $defaultImage): void
{
    $select = $dbh->prepare('SELECT id FROM tblbooks WHERE ISBNNumber = :isbn LIMIT 1');
    $select->execute([':isbn' => $book['isbn']]);
    $existingId = $select->fetchColumn();

    if ($existingId !== false) {
        $update = $dbh->prepare(
            'UPDATE tblbooks
             SET BookName = :title,
                 CatId = :categoryId,
                 AuthorId = :authorId,
                 BookPrice = :price,
                 bookImage = :bookImage,
                 bookQty = :qty,
                 PreviewLink = :preview
             WHERE id = :id'
        );
        $update->execute([
            ':title' => $book['title'],
            ':categoryId' => $categoryId,
            ':authorId' => $authorId,
            ':price' => '0.00',
            ':bookImage' => $defaultImage,
            ':qty' => 5,
            ':preview' => $book['preview'],
            ':id' => (int) $existingId,
        ]);
        return;
    }

    $insert = $dbh->prepare(
        'INSERT INTO tblbooks
        (BookName, CatId, AuthorId, ISBNNumber, BookPrice, bookImage, bookQty, PreviewLink)
        VALUES
        (:title, :categoryId, :authorId, :isbn, :price, :bookImage, :qty, :preview)'
    );
    $insert->execute([
        ':title' => $book['title'],
        ':categoryId' => $categoryId,
        ':authorId' => $authorId,
        ':isbn' => $book['isbn'],
        ':price' => '0.00',
        ':bookImage' => $defaultImage,
        ':qty' => 5,
        ':preview' => $book['preview'],
    ]);
}

try {
    $dbh->beginTransaction();

    $categoryId = findOrCreateCategory($dbh, $categoryName);
    $authorId = findOrCreateAuthor($dbh, $authorName);

    foreach ($books as $book) {
        upsertBook($dbh, $book, $categoryId, $authorId, $defaultImage);
    }

    $dbh->commit();
    echo "Shared Drive books synced successfully.\n";
} catch (Throwable $e) {
    if ($dbh->inTransaction()) {
        $dbh->rollBack();
    }
    fwrite(STDERR, "Failed to sync Shared Drive books: " . $e->getMessage() . "\n");
    exit(1);
}
