<?php
declare(strict_types=1);

require_once __DIR__ . '/../DatabaseTestCase.php';

final class UsersModelTest extends DatabaseTestCase {
    public function testCreateUserFixtureRollsBackAfterTest(): void {
        $user = $this->createUser([
            'fname' => 'Framework',
            'lname' => 'Tester',
        ]);

        $this->assertSame('Framework', $user['fname']);
        $this->assertSame('Tester', $user['lname']);
        $this->assertNotEmpty($user['id']);
    }
}
