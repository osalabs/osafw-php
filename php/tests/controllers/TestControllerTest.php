<?php
declare(strict_types=1);

require_once __DIR__ . '/ControllerTestCase.php';

final class TestControllerTest extends ControllerTestCase {
    public function testControllerActionCanReturnParseData(): void {
        $user = $this->createUser([
            'access_level' => Users::ACL_SITE_ADMIN,
        ]);
        $this->loginUser($user);

        $result = $this->runController('Test', 'Bench2');

        $this->assertIsArray($result);
    }
}
