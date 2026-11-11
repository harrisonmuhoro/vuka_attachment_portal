<?php
/** Close approved vacancies whose deadline has passed. */
function closeExpiredVacancies(PDO $pdo): int
{
    $stmt = $pdo->prepare("UPDATE vacancies SET status = 'closed'
        WHERE status = 'approved' AND deadline_at IS NOT NULL AND deadline_at < NOW()");
    $stmt->execute();
    return $stmt->rowCount();
}
