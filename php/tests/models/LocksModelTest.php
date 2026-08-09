<?php
declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

final class LocksModelTest extends DatabaseTestCase {
    public function testExpiredLockCanBeReacquiredInOneCall(): void {
        $icode       = uniqid('phpunit-lock-', true);
        $environment = 'phpunit';
        $itemId      = random_int(1, 1000000);

        $this->db->exec("INSERT INTO locks (icode, environment, item_id, expires, add_time)
                         VALUES (@icode, @environment, @item_id, 1, DATE_SUB(NOW(), INTERVAL 10 SECOND))", [
            'icode'       => $icode,
            'environment' => $environment,
            'item_id'     => $itemId,
        ]);

        $this->assertTrue(Locks::i()->lock($icode, $itemId, 60, $environment));
        $this->assertTrue(Locks::i()->exists($icode, $itemId, $environment));
    }
}
