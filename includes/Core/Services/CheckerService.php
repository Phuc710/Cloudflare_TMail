<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

use PDO;
use PDOException;
use KaiMail\Core\Http\ApiException;

/**
 * Fast Email Checker engine with sub-millisecond FULLTEXT scanning and robust LIKE fallback.
 */
final class CheckerService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function search(string $keyword, int $days = 30, int $limit = 100, string $domain = ''): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw ApiException::badRequest('Vui lòng nhập từ khóa quét');
        }

        $days = max(1, min($days, 365));
        $limit = max(1, min($limit, 1000));

        // Format Boolean FTS term: require all words
        $words = array_filter(explode(' ', $keyword));
        $ftsTerm = !empty($words) ? '+' . implode(' +', $words) : '+' . $keyword;

        $where = ["m.received_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];
        $params = [$days];

        $domainRow = null;
        if ($domain !== '') {
            $stmtDomain = $this->db->prepare("SELECT id FROM domains WHERE domain = ? LIMIT 1");
            $stmtDomain->execute([$domain]);
            $domainRow = $stmtDomain->fetch();
            if (!$domainRow) {
                return [
                    'count' => 0,
                    'keyword' => $keyword,
                    'results' => [],
                    'execution_time' => '0s',
                    'note' => 'Domain not found',
                ];
            }
            $where[] = "e.domain_id = ?";
            $params[] = (int) $domainRow['id'];
        }

        $where[] = "MATCH(m.subject, m.body_text) AGAINST (? IN BOOLEAN MODE)";
        $params[] = $ftsTerm;

        $sql = "
            SELECT 
                e.id as email_id,
                e.email,
                m.subject,
                m.received_at,
                m.id as message_id,
                m.body_text,
                m.body_html
            FROM emails e
            JOIN messages m ON m.email_id = e.id
            WHERE " . implode(" AND ", $where) . "
            AND m.id = (
                SELECT MAX(m2.id) 
                FROM messages m2 
                WHERE m2.email_id = e.id 
                AND m2.received_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND MATCH(m2.subject, m2.body_text) AGAINST (? IN BOOLEAN MODE)
            )
            ORDER BY m.received_at DESC
            LIMIT ?
        ";

        $params[] = $days;
        $params[] = $ftsTerm;
        $params[] = $limit;

        $startTime = microtime(true);
        $results = [];

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll() ?: [];
        } catch (PDOException $pdoEx) {
            // Fallback to LIKE if FULLTEXT index is absent
            if (str_contains($pdoEx->getMessage(), 'FULLTEXT') || str_contains($pdoEx->getMessage(), 'MATCH')) {
                error_log("CheckerService FTS fallback to LIKE: " . $pdoEx->getMessage());
                $results = $this->searchWithLike($keyword, $days, $limit, $domainRow ? (int) $domainRow['id'] : null);
            } else {
                throw $pdoEx;
            }
        }

        $endTime = microtime(true);

        return [
            'count' => count($results),
            'keyword' => $keyword,
            'results' => $results,
            'execution_time' => round($endTime - $startTime, 4) . 's',
            'server_time' => date('Y-m-d H:i:s'),
        ];
    }

    private function searchWithLike(string $keyword, int $days, int $limit, ?int $domainId): array
    {
        $paramsLike = [$days];
        $whereLike = ["m.received_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"];

        if ($domainId !== null) {
            $whereLike[] = "e.domain_id = ?";
            $paramsLike[] = $domainId;
        }

        $searchTerm = "%{$keyword}%";
        $whereLike[] = "(m.subject LIKE ? OR m.body_text LIKE ?)";
        $paramsLike[] = $searchTerm;
        $paramsLike[] = $searchTerm;

        $sqlFallback = "
            SELECT 
                e.id as email_id,
                e.email,
                m.subject,
                m.received_at,
                m.id as message_id,
                m.body_text,
                m.body_html
            FROM emails e
            JOIN messages m ON m.email_id = e.id
            WHERE " . implode(" AND ", $whereLike) . "
            AND m.id = (
                SELECT MAX(m2.id) 
                FROM messages m2 
                WHERE m2.email_id = e.id 
                AND m2.received_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND (m2.subject LIKE ? OR m2.body_text LIKE ?)
            )
            ORDER BY m.received_at DESC
            LIMIT ?
        ";

        $paramsLike[] = $days;
        $paramsLike[] = $searchTerm;
        $paramsLike[] = $searchTerm;
        $paramsLike[] = $limit;

        $stmt = $this->db->prepare($sqlFallback);
        $stmt->execute($paramsLike);
        return $stmt->fetchAll() ?: [];
    }
}
