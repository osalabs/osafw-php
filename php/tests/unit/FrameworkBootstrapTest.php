<?php
declare(strict_types=1);

require_once __DIR__ . '/../FrameworkTestCase.php';

final class LegacyIlistOverrideModelForTest extends FwModel {
    public function __construct() {
    }

    public function ilist(?array $statuses = null): array {
        return [['statuses' => $statuses]];
    }
}

final class LegacyUsersOverrideModelForTest extends Users {
    public function __construct() {
    }

    public function doLogin(int $id): void {
    }
}

final class AttachmentStorageModelForTest extends Att {
    public array $updatedFields = [];
    public bool $localFilesDeleted = false;

    public function __construct(private readonly string $originalPath) {
        $this->table_name = 'att';
    }

    public function one(string|int|null $id): array {
        return [
            'storage' => self::STORAGE_FILE,
            'ext'     => 'txt',
        ];
    }

    public function getUploadPath($id, $ext, $size = ''): string {
        return $size === self::SIZE_ORIGINAL ? $this->originalPath : $this->originalPath . '.' . $size;
    }

    public function getUploadImgPath(int $id, string $size, string $ext = ""): string {
        return $this->getUploadPath($id, $ext, $size);
    }

    public function update(int $id, array $item): bool {
        $this->updatedFields = $item;
        return true;
    }

    public function deleteLocalFiles(int $id): void {
        $this->localFilesDeleted = true;
    }
}

final class FrameworkBootstrapTest extends FrameworkTestCase {
    public function testFrameworkBootstrapInitializesConfig(): void {
        $this->assertSame($_SERVER['HTTP_HOST'], $this->fw->config->ROOT_DOMAIN0);
        $this->assertIsArray($this->fw->GLOBAL);
    }

    public function testRouteParserMapsStandardRestListRoute(): void {
        $dispatcher = new Dispatcher([], $this->fw->config->ROOT_URL, $this->fw->config->ROUTE_PREFIXES);
        $route = $dispatcher->uriToRoute('GET', '/Admin/Demos', []);

        $this->assertSame('AdminDemos', $route->controller);
        $this->assertSame(fw::ACTION_INDEX, $route->action);
    }

    public function testSafeUrlAllowsOnlyClickableSchemes(): void {
        $this->assertSame('https://example.com/path', Utils::safeUrl('example.com/path'));
        $this->assertSame('mailto:test@example.com', Utils::safeUrl('mailto:test@example.com'));
        $this->assertSame('', Utils::safeUrl('javascript:alert(1)'));
        $this->assertSame('', Utils::safeUrl('//example.com/path'));
    }

    public function testLegacyIlistOverrideRemainsCompatibleWithDynamicDefinitions(): void {
        $model = new LegacyIlistOverrideModelForTest();

        $this->assertSame([['statuses' => [FwModel::STATUS_ACTIVE]]], $model->ilistByDef([FwModel::STATUS_ACTIVE], ['lookup_params' => 'test']));
    }

    public function testLegacyUsersLoginOverrideRemainsCompatible(): void {
        $this->assertTrue(is_subclass_of(LegacyUsersOverrideModelForTest::class, Users::class));
    }

    public function testDifferenceMinutesUsesElapsedTimeAcrossCalendarBoundaries(): void {
        $this->assertSame(41760, DateUtils::differenceMinutes('2026-01-31 00:00:00 UTC', '2026-03-01 00:00:00 UTC'));
        $this->assertSame(60, DateUtils::differenceMinutes(
            new DateTimeImmutable('2026-03-08 01:30:00', new DateTimeZone('America/Chicago')),
            new DateTimeImmutable('2026-03-08 03:30:00', new DateTimeZone('America/Chicago')),
        ));
    }

    public function testAutocompleteParserOnlyTreatsNumericSuffixAsAnId(): void {
        $this->assertSame(['Acme', '42'], FormUtils::parseAutocomplete(FormUtils::formatAutocomplete('Acme', '42')));
        $this->assertSame(['Acme ::: Research', ''], FormUtils::parseAutocomplete('Acme ::: Research'));
    }

    public function testPermanentCookieUsesConfiguredSanitizedSuffix(): void {
        $users    = $this->newInstanceWithoutConstructor(Users::class);
        $original = $this->fw->config->PERM_COOKIE_ENV_SUFFIX;
        $this->setProtectedProperty($users, 'fw', $this->fw);

        try {
            $this->fw->config->PERM_COOKIE_ENV_SUFFIX = 'staging west';
            $this->assertSame('perm_stagingwest', $this->invokeProtected($users, 'permCookieName'));
        } finally {
            $this->fw->config->PERM_COOKIE_ENV_SUFFIX = $original;
        }
    }

    public function testAutoloadModelDirectoriesAreSharedAndRejectTraversal(): void {
        $original = $this->fw->config->AUTOLOAD_MODELS;

        try {
            $this->fw->config->AUTOLOAD_MODELS = ['/Extra', 'MissingSlash', '/../Secrets', '/Nested/Reports', '/Extra'];
            $root = rtrim($this->fw->config->PHP_ROOT, '/\\') . '/models';

            $this->assertSame([
                $root,
                $root . '/Extra',
                $root . '/Nested/Reports',
            ], $this->fw->getAutoloadModelDirs());
        } finally {
            $this->fw->config->AUTOLOAD_MODELS = $original;
        }
    }

    public function testMoveAttachmentToDatabaseRequiresReadableOriginal(): void {
        $model = new AttachmentStorageModelForTest(__DIR__ . '/missing-attachment.txt');

        $this->assertFalse($model->moveToDB(123));
        $this->assertSame([], $model->updatedFields);
        $this->assertFalse($model->localFilesDeleted);
    }

    public function testMoveAttachmentToDatabaseStoresContentBeforeDeletingFiles(): void {
        $path = tempnam(sys_get_temp_dir(), 'osafw-att-');
        $this->assertNotFalse($path);
        file_put_contents($path, 'attachment-content');

        try {
            $model = new AttachmentStorageModelForTest($path);

            $this->assertTrue($model->moveToDB(123));
            $this->assertSame(Att::STORAGE_TABLE, $model->updatedFields['storage']);
            $this->assertSame('attachment-content', $model->updatedFields['raw']);
            $this->assertTrue($model->localFilesDeleted);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testJwtDependencySupportsFrameworkHs256Contract(): void {
        $secret         = str_repeat('x', 32);
        $now            = time();
        $originalLeeway = Firebase\JWT\JWT::$leeway;

        try {
            Firebase\JWT\JWT::$leeway = 30;
            $token = Firebase\JWT\JWT::encode([
                'sub' => 'phpunit',
                'iat' => $now,
                'nbf' => $now - 1,
                'exp' => $now + 60,
            ], $secret, 'HS256');
            $headers = new stdClass();
            $payload = Firebase\JWT\JWT::decode($token, new Firebase\JWT\Key($secret, 'HS256'), $headers);

            $this->assertSame('phpunit', $payload->sub);
            $this->assertSame('HS256', $headers->alg);
        } finally {
            Firebase\JWT\JWT::$leeway = $originalLeeway;
        }
    }
}
