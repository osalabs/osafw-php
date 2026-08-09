<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/../DatabaseTestCase.php';

#[Group('db')]
abstract class ControllerTestCase extends DatabaseTestCase {
    protected function setUp(): void {
        parent::setUp();

        $_SESSION = [];
        unset($_SERVER['HTTP_AUTHORIZATION']);

        if (!isset($this->fw->dispatcher)) {
            $this->fw->dispatcher = new Dispatcher([], $this->fw->config->ROOT_URL, $this->fw->config->ROUTE_PREFIXES);
        }
    }

    /**
     * @param array<int,mixed> $params
     * @return array<string,mixed>|null
     */
    protected function runController(string $controller, string $action, array $params = []): ?array {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($action, [fw::ACTION_SAVE, fw::ACTION_DELETE, fw::ACTION_SAVE_MULTI], true)) {
            $method = 'POST';
        }

        $this->fw->route = (object)[
            'method'          => $method,
            'prefix'          => str_starts_with($controller, 'v1') ? '/v1' : '',
            'controller'      => $controller,
            'controller_path' => strtolower(preg_replace('/^Admin/', '', $controller)),
            'action'          => $action,
            'action_more'     => '',
            'id'              => (string)($params[0] ?? ''),
            'params'          => $params,
            'format'          => '',
        ];

        http_response_code(200);
        ob_start();
        try {
            $result = $this->fw->dispatcher->runController($controller, $action, $params);
            $output = trim((string)ob_get_contents());

            if ($result === null && $output !== '') {
                $decoded = json_decode($output, true);
                if (is_array($decoded)) {
                    return ['_json' => $decoded];
                }
            }

            return $result;
        } finally {
            ob_end_clean();
        }
    }

    /**
     * @param array<string,mixed> $user
     */
    protected function loginUser(array $user): void {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['access_level'] = intval($user['access_level'] ?? Users::ACL_USER);
        $_SESSION['XSS'] ??= Utils::getRandStr(16);
    }
}
