<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class FrameworkTestCase extends TestCase {
    protected fw $fw;

    protected function setUp(): void {
        parent::setUp();

        $this->fw = fw::i();
        $this->fw->resetRuntimeCaches();
        $this->resetRequestState();
    }

    protected function tearDown(): void {
        $this->resetRequestState();

        parent::tearDown();
    }

    protected function resetRequestState(): void {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $this->fw->postedJson = [];
        $this->fw->FormErrors = [];
    }

    protected function newInstanceWithoutConstructor(string $className): object {
        $reflection = new ReflectionClass($className);
        return $reflection->newInstanceWithoutConstructor();
    }

    protected function invokeProtected(object $object, string $method, array $args = []): mixed {
        $reflection = new ReflectionMethod($object, $method);
        return $reflection->invokeArgs($object, $args);
    }

    protected function setProtectedProperty(object $object, string $propertyName, mixed $value): void {
        $reflection = new ReflectionProperty($object, $propertyName);
        $reflection->setValue($object, $value);
    }

    protected function getProtectedProperty(object $object, string $propertyName): mixed {
        $reflection = new ReflectionProperty($object, $propertyName);
        return $reflection->getValue($object);
    }

    protected function setModelDb(FwModel $model, DB $db): void {
        $this->setProtectedProperty($model, 'db', $db);
    }

    /**
     * @param class-string $class
     */
    protected function registerModel(string $class, FwModel $instance): void {
        $this->fw->setModelInstanceForTest($class, $instance);
    }
}
