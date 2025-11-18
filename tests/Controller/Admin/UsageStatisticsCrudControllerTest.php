<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\AnthropicTokenUsageBundle\Controller\Admin\UsageStatisticsCrudController;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * UsageStatisticsCrudController 测试
 * 验证预聚合统计数据的CRUD控制器配置和行为
 * @internal
 */
#[CoversClass(UsageStatisticsCrudController::class)]
#[RunTestsInSeparateProcesses]
final class UsageStatisticsCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testControllerIsInstantiable(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);

        $this->assertTrue($reflection->isInstantiable());
        $this->assertTrue($reflection->isFinal());
    }

    public function testControllerHasRequiredConfigurationMethods(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);

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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if (str_contains($attribute->getName(), 'AdminCrud')) {
                $hasAdminCrudAttribute = true;

                // 验证AdminCrud注解的参数
                $arguments = $attribute->getArguments();
                $this->assertArrayHasKey('routePath', $arguments);
                $this->assertEquals('/anthropic-token-usage/usage-statistics', $arguments['routePath']);
                $this->assertArrayHasKey('routeName', $arguments);
                $this->assertEquals('anthropic_token_usage_usage_statistics', $arguments['routeName']);
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller应该有AdminCrud注解');
    }

    public function testControllerHasExpectedFieldsConfiguration(): void
    {
        // 通过反射检查Controller中是否引用了正确的Field类型
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Field类
        $expectedFields = [
            'IdField',
            'ChoiceField',
            'TextField',
            'IntegerField',
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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证引用了正确的Filter类
        $expectedFilters = [
            'ChoiceFilter',
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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringContainsString('UsageStatistics::class', $source);
        $this->assertStringContainsString('use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;', $source);
    }

    public function testControllerHasRebuildBatchMethod(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);

        $this->assertTrue($reflection->hasMethod('rebuildBatch'), 'Controller应该有rebuildBatch方法');

        $method = $reflection->getMethod('rebuildBatch');
        $this->assertTrue($method->isPublic(), 'rebuildBatch方法应该是public');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testControllerEntityRelationship(): void
    {
        // 验证Controller返回的Entity类存在且正确
        $entityClass = UsageStatisticsCrudController::getEntityFqcn();
        $this->assertEquals(UsageStatistics::class, $entityClass);

        // 验证Entity类是可实例化的
        $entityReflection = new \ReflectionClass($entityClass);
        $this->assertTrue($entityReflection->isInstantiable(), 'Entity类必须可实例化');
    }

    public function testControllerMethodsReturnCorrectTypes(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);

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
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment, 'Controller应该有PHPDoc注释');
        $this->assertStringContainsString('预聚合统计数据管理', $docComment);
    }

    public function testControllerHasCorrectNamespace(): void
    {
        $this->assertEquals(
            'Tourze\AnthropicTokenUsageBundle\Controller\Admin',
            (new \ReflectionClass(UsageStatisticsCrudController::class))->getNamespaceName()
        );
    }

    public function testControllerStrictTypesDeclaration(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');
        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);", $source, 'Controller应该声明严格类型');
    }

    public function testControllerIsReadOnlyConfiguration(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
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

    public function testControllerHasBatchRebuildAction(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含重建统计功能
        $this->assertStringContainsString('rebuildBatch', $source, '应该有重建统计批处理功能');
        $this->assertStringContainsString('addBatchAction', $source, '应该添加批处理操作');
        $this->assertStringContainsString('重建统计', $source, '应该有重建统计按钮文本');
    }

    public function testControllerHasCorrectDimensionConfiguration(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含维度配置
        $this->assertStringContainsString('DIMENSION_ACCESS_KEY', $source, '应该引用ACCESS_KEY维度常量');
        $this->assertStringContainsString('DIMENSION_USER', $source, '应该引用USER维度常量');
        $this->assertStringContainsString('dimensionType', $source, '应该包含维度类型字段');
        $this->assertStringContainsString('dimensionId', $source, '应该包含维度ID字段');
    }

    public function testControllerHasCorrectPeriodConfiguration(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含时间粒度配置
        $this->assertStringContainsString('PERIOD_HOUR', $source, '应该引用HOUR粒度常量');
        $this->assertStringContainsString('PERIOD_DAY', $source, '应该引用DAY粒度常量');
        $this->assertStringContainsString('PERIOD_MONTH', $source, '应该引用MONTH粒度常量');
        $this->assertStringContainsString('periodType', $source, '应该包含时间粒度字段');
    }

    public function testControllerHasCorrectStatisticsFieldsConfiguration(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含统计数据相关字段
        $expectedStatisticsFields = [
            'totalRequests',
            'totalInputTokens',
            'totalCacheCreationInputTokens',
            'totalCacheReadInputTokens',
            'totalOutputTokens',
            'totalTokens',
            'avgTokensPerRequest',
        ];

        foreach ($expectedStatisticsFields as $field) {
            $this->assertStringContainsString(
                $field,
                $source,
                "应该包含统计字段: {$field}"
            );
        }
    }

    public function testControllerHasCorrectTimeFieldsConfiguration(): void
    {
        $reflection = new \ReflectionClass(UsageStatisticsCrudController::class);
        $filename = $reflection->getFileName();
        $this->assertNotFalse($filename, 'Controller文件路径应该可以获取');
        $source = file_get_contents($filename);
        $this->assertNotFalse($source, 'Controller源码应该可以读取');

        // 验证包含时间相关字段
        $expectedTimeFields = [
            'periodStart',
            'periodEnd',
            'lastUpdateTime',
        ];

        foreach ($expectedTimeFields as $field) {
            $this->assertStringContainsString(
                $field,
                $source,
                "应该包含时间字段: {$field}"
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'id_field' => ['ID'];
        yield 'dimension_type_field' => ['维度类型'];
        yield 'dimension_id_field' => ['维度ID'];
        yield 'period_type_field' => ['时间粒度'];
        yield 'period_start_field' => ['开始时间'];
        yield 'total_requests_field' => ['总请求数'];
        yield 'input_tokens_field' => ['总输入Token'];
        yield 'output_tokens_field' => ['总输出Token'];
        yield 'total_tokens_field' => ['总Token数'];
        yield 'avg_tokens_field' => ['平均Token/请求'];
        yield 'last_update_field' => ['最后更新时间'];
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
        yield 'dimension_type_field' => ['dimensionType'];
        yield 'dimension_id_field' => ['dimensionId'];
        yield 'period_type_field' => ['periodType'];
        yield 'period_start_field' => ['periodStart'];
        yield 'total_requests_field' => ['totalRequests'];
        yield 'total_input_tokens_field' => ['totalInputTokens'];
        yield 'total_output_tokens_field' => ['totalOutputTokens'];
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
        yield 'dimension_type_field' => ['dimensionType'];
        yield 'dimension_id_field' => ['dimensionId'];
        yield 'period_type_field' => ['periodType'];
        yield 'period_start_field' => ['periodStart'];
        yield 'total_requests_field' => ['totalRequests'];
        yield 'total_input_tokens_field' => ['totalInputTokens'];
        yield 'total_output_tokens_field' => ['totalOutputTokens'];
    }

    /**
     * {@inheritdoc}
     *
     * @return AbstractCrudController<UsageStatistics>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(UsageStatisticsCrudController::class);
    }
}
