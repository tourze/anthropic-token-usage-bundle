<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Controller\Admin\AccessKeyUsageCrudController;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * AccessKeyUsageCrudController 测试
 * 验证AccessKey维度Token使用记录的CRUD控制器配置和行为
 * @internal
 * @phpstan-ignore-next-line
 */
#[CoversClass(AccessKeyUsageCrudController::class)]
#[RunTestsInSeparateProcesses]
final class AccessKeyUsageCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    public function testGetEntityFqcnShouldReturnAccessKeyUsageClass(): void
    {
        $this->assertEquals(AccessKeyUsage::class, AccessKeyUsageCrudController::getEntityFqcn());
    }

    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);

        $requiredMethods = [
            'getEntityFqcn',
            'configureCrud',
            'configureActions',
            'configureFields',
            'configureFilters',
        ];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "方法 {$methodName} 必须存在");

            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isPublic(), "方法 {$methodName} 必须是public");
        }
    }

    public function testConfigureCrudMethodConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $method = $reflection->getMethod('configureCrud');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('crud', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(Crud::class, $paramType->getName());
    }

    public function testConfigureActionsMethodConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $method = $reflection->getMethod('configureActions');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('actions', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(Actions::class, $paramType->getName());
    }

    public function testConfigureFiltersMethodConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $method = $reflection->getMethod('configureFilters');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('filters', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals(Filters::class, $paramType->getName());
    }

    public function testConfigureFieldsReturnsIterable(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $method = $reflection->getMethod('configureFields');

        $this->assertTrue($method->isPublic());

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('pageName', $parameters[0]->getName());

        $paramType = $parameters[0]->getType();
        $this->assertInstanceOf(\ReflectionNamedType::class, $paramType);
        $this->assertEquals('string', $paramType->getName());
    }

    public function testControllerHasCorrectAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;

                // 验证AdminCrud注解的参数
                $arguments = $attribute->getArguments();
                $this->assertArrayHasKey('routePath', $arguments);
                $this->assertEquals('/anthropic-token-usage/access-key-usage', $arguments['routePath']);
                $this->assertArrayHasKey('routeName', $arguments);
                $this->assertEquals('anthropic_token_usage_access_key_usage', $arguments['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    public function testControllerHasExpectedFieldsConfiguration(): void
    {
        // 通过反射检查Controller中是否引用了正确的Field类型
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Field类
        $expectedFields = [
            'IdField',
            'AssociationField',
            'IntegerField',
            'TextField',
            'DateTimeField',
        ];

        foreach ($expectedFields as $fieldType) {
            $this->assertStringContainsString(
                $fieldType,
                $source,
                "Controller应该使用{$fieldType}字段类型"
            );
        }
    }

    public function testControllerHasExpectedFiltersConfiguration(): void
    {
        // 通过反射检查Controller中是否引用了正确的Filter类型
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Filter类
        $expectedFilters = [
            'EntityFilter',
            'TextFilter',
            'DateTimeFilter',
        ];

        foreach ($expectedFilters as $filterType) {
            $this->assertStringContainsString(
                $filterType,
                $source,
                "Controller应该使用{$filterType}过滤器类型"
            );
        }
    }

    public function testControllerUsesCorrectEntityClass(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString('AccessKeyUsage::class', $source);
        $this->assertStringContainsString('use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;', $source);
    }

    public function testControllerHasExportBatchMethod(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);

        $this->assertTrue($reflection->hasMethod('exportBatch'), 'Controller应该有exportBatch方法');

        $method = $reflection->getMethod('exportBatch');
        $this->assertTrue($method->isPublic(), 'exportBatch方法应该是public');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerEntityRelationship(): void
    {
        // 验证Controller返回的Entity类存在且正确
        $entityClass = AccessKeyUsageCrudController::getEntityFqcn();
        $this->assertEquals(AccessKeyUsage::class, $entityClass);

        // 验证Entity类是可实例化的
        $entityReflection = new \ReflectionClass($entityClass);
        $this->assertTrue($entityReflection->isInstantiable(), 'Entity类必须可实例化');
    }

    public function testControllerMethodsReturnCorrectTypes(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);

        // getEntityFqcn 应该返回string
        $getEntityMethod = $reflection->getMethod('getEntityFqcn');
        $returnType = $getEntityMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureCrud 应该返回Crud
        $configureCrudMethod = $reflection->getMethod('configureCrud');
        $returnType = $configureCrudMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Crud::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureActions 应该返回Actions
        $configureActionsMethod = $reflection->getMethod('configureActions');
        $returnType = $configureActionsMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Actions::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureFilters 应该返回Filters
        $configureFiltersMethod = $reflection->getMethod('configureFilters');
        $returnType = $configureFiltersMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals(Filters::class, $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);

        // configureFields 应该返回iterable
        $configureFieldsMethod = $reflection->getMethod('configureFields');
        $returnType = $configureFieldsMethod->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('iterable', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerHasCorrectPhpDocComment(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment, 'Controller应该有PHPDoc注释');
        $this->assertStringContainsString('AccessKey维度Token使用记录管理', $docComment);
    }

    public function testControllerHasCorrectNamespace(): void
    {
        $this->assertEquals(
            'Tourze\AnthropicTokenUsageBundle\Controller\Admin',
            (new \ReflectionClass(AccessKeyUsageCrudController::class))->getNamespaceName()
        );
    }

    public function testControllerStrictTypesDeclaration(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source, 'Controller应该声明严格类型');
    }

    public function testControllerIsReadOnlyConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证禁用了创建、编辑、删除操作（只读配置）
        $this->assertStringContainsString('Action::NEW', $source, '应该禁用NEW操作');
        $this->assertStringContainsString('Action::EDIT', $source, '应该禁用EDIT操作');
        $this->assertStringContainsString('Action::DELETE', $source, '应该禁用DELETE操作');
        $this->assertStringContainsString('disable(', $source, '应该使用disable方法禁用操作');
    }

    public function testControllerHasBatchExportAction(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含导出功能
        $this->assertStringContainsString('exportBatch', $source, '应该有导出批处理功能');
        $this->assertStringContainsString('addBatchAction', $source, '应该添加批处理操作');
        $this->assertStringContainsString('导出选中', $source, '应该有导出按钮文本');
    }

    public function testControllerHasCorrectTokenFieldsConfiguration(): void
    {
        $reflection = new \ReflectionClass(AccessKeyUsageCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含Token相关字段
        $expectedTokenFields = [
            'inputTokens',
            'cacheCreationInputTokens',
            'cacheReadInputTokens',
            'outputTokens',
            'totalTokens',
        ];

        foreach ($expectedTokenFields as $field) {
            $this->assertStringContainsString(
                $field,
                $source,
                "应该包含Token字段: {$field}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'id_field' => ['ID'];
        yield 'access_key_field' => ['AccessKey'];
        yield 'user_field' => ['用户'];
        yield 'input_tokens_field' => ['输入Token'];
        yield 'output_tokens_field' => ['输出Token'];
        yield 'total_tokens_field' => ['总Token数'];
        yield 'model_field' => ['模型'];
        yield 'occur_time_field' => ['发生时间'];
    }

    /**
     * 提供新增页字段数据 - 此控制器禁用了NEW操作
     *
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // 注意：由于是只读控制器，NEW操作被禁用，以下字段不会真正显示
        // 但需要提供以避免测试框架的空DataProvider错误
        yield 'id_field' => ['id'];
        yield 'access_key_field' => ['accessKey'];
        yield 'user_field' => ['user'];
        yield 'input_tokens_field' => ['inputTokens'];
        yield 'output_tokens_field' => ['outputTokens'];
        yield 'model_field' => ['model'];
        yield 'occur_time_field' => ['occurTime'];
    }

    /**
     * 提供编辑页字段数据 - 此控制器禁用了EDIT操作
     *
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // 注意：由于是只读控制器，EDIT操作被禁用，以下字段不会真正显示
        // 但需要提供以避免测试框架的空DataProvider错误
        yield 'id_field' => ['id'];
        yield 'access_key_field' => ['accessKey'];
        yield 'user_field' => ['user'];
        yield 'input_tokens_field' => ['inputTokens'];
        yield 'output_tokens_field' => ['outputTokens'];
        yield 'model_field' => ['model'];
        yield 'occur_time_field' => ['occurTime'];
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractCrudController<AccessKeyUsage>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(AccessKeyUsageCrudController::class);
    }

    /**
     * 测试表单验证错误
     * 由于这是只读控制器，此测试标记为跳过
     */
    public function testValidationErrors(): void
    {
        self::markTestSkipped('AccessKeyUsageCrudController is read-only, no form validation needed');
    }
}
