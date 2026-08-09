<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/FrameworkTestCase.php';

#[Group('db')]
abstract class DatabaseTestCase extends FrameworkTestCase {
    protected DB $db;
    private static bool $dbAvailabilityResolved = false;
    private static ?string $dbUnavailableReason = null;

    protected function setUp(): void {
        parent::setUp();

        $skipReason = $this->databaseUnavailableReason();
        if (!is_null($skipReason)) {
            $this->markTestSkipped($skipReason);
        }

        $this->db = $this->fw->db;
        $this->db->transaction();
    }

    protected function tearDown(): void {
        if (isset($this->db)) {
            try {
                $this->db->rollback();
            } catch (Throwable) {
                // Ignore rollback errors when the connection was already closed by the test.
            }
        }

        parent::tearDown();
    }

    private function databaseUnavailableReason(): ?string {
        if (self::$dbAvailabilityResolved) {
            return self::$dbUnavailableReason;
        }

        self::$dbAvailabilityResolved = true;

        $dbConfig = $this->fw->config->DB ?? null;
        $dbName   = is_array($dbConfig) ? ($dbConfig['DBNAME'] ?? '') : ($dbConfig->DBNAME ?? '');
        if (trim((string)$dbName) === '') {
            self::$dbUnavailableReason = 'Database not configured for host [' . ($_SERVER['HTTP_HOST'] ?? '') . ']';
            return self::$dbUnavailableReason;
        }

        try {
            $this->fw->db->arrp('SELECT 1 AS ok');
            self::$dbUnavailableReason = null;
        } catch (Throwable $throwable) {
            self::$dbUnavailableReason = 'Database not available: ' . $throwable->getMessage();
        }

        return self::$dbUnavailableReason;
    }

    protected function withoutActivityLogging(callable $callback): mixed {
        $previous = $this->fw->is_log_events;
        $this->fw->is_log_events = false;

        try {
            return $callback();
        } finally {
            $this->fw->is_log_events = $previous;
        }
    }

    /**
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    protected function createUser(array $fields = []): array {
        return $this->withoutActivityLogging(function () use ($fields): array {
            $defaults = [
                'email'        => uniqid('user', true) . '@example.test',
                'fname'        => 'Test',
                'lname'        => 'User',
                'access_level' => Users::ACL_USER,
            ];

            $id = Users::i()->add(array_merge($defaults, $fields));
            return Users::i()->one($id);
        });
    }
}
