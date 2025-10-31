<?php

declare(strict_types=1);

namespace Tourze\AnthropicTokenUsageBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\AnthropicTokenUsageBundle\Entity\AccessKeyUsage;

/**
 * AccessKey维度Token使用记录管理
 *
 * @extends AbstractCrudController<AccessKeyUsage>
 */
#[AdminCrud(
    routePath: '/anthropic-token-usage/access-key-usage',
    routeName: 'anthropic_token_usage_access_key_usage'
)]
final class AccessKeyUsageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AccessKeyUsage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('AccessKey使用记录')
            ->setEntityLabelInPlural('AccessKey使用记录')
            ->setPageTitle('index', 'AccessKey Token使用记录')
            ->setPageTitle('detail', '使用记录详情')
            ->setDefaultSort(['occurTime' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setPaginatorRangeSize(4)
            ->showEntityActionsInlined()
            ->setSearchFields(['requestId', 'model', 'endpoint', 'feature'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // 禁用创建、编辑、删除操作，只保留查看
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            // 添加导出操作
            ->addBatchAction(Action::new('exportBatch', '导出选中')
                ->linkToCrudAction('exportBatch')
                ->addCssClass('btn btn-primary')
                ->setIcon('fa fa-download'))
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('accessKey', 'AccessKey'))
            ->add(EntityFilter::new('user', '用户'))
            ->add(TextFilter::new('model', '模型'))
            ->add(TextFilter::new('endpoint', 'API端点'))
            ->add(TextFilter::new('feature', '功能标识'))
            ->add(DateTimeFilter::new('occurTime', '发生时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setSortable(true)
        ;

        yield AssociationField::new('accessKey', 'AccessKey')
            ->setRequired(true)
            ->setSortable(true)
            ->formatValue(fn ($value) => $this->formatAccessKeyDisplay($value))
        ;

        yield AssociationField::new('user', '用户')
            ->setSortable(true)
            ->formatValue(fn ($value) => $this->formatUserDisplay($value))
        ;

        yield IntegerField::new('inputTokens', '输入Token')
            ->setSortable(true)
            ->setHelp('用户输入消耗的Token数量')
            ->setTextAlign('right')
            ->formatValue(fn ($value) => $this->formatNumber($value))
        ;

        yield IntegerField::new('cacheCreationInputTokens', '缓存创建Token')
            ->setSortable(true)
            ->setHelp('缓存创建消耗的输入Token数量')
            ->setTextAlign('right')
            ->formatValue(fn ($value) => $this->formatNumber($value))
            ->hideOnIndex()
        ;

        yield IntegerField::new('cacheReadInputTokens', '缓存读取Token')
            ->setSortable(true)
            ->setHelp('缓存读取消耗的输入Token数量')
            ->setTextAlign('right')
            ->formatValue(fn ($value) => $this->formatNumber($value))
            ->hideOnIndex()
        ;

        yield IntegerField::new('outputTokens', '输出Token')
            ->setSortable(true)
            ->setHelp('API响应生成的Token数量')
            ->setTextAlign('right')
            ->formatValue(fn ($value) => $this->formatNumber($value))
        ;

        // 计算字段：总Token数
        yield IntegerField::new('totalTokens', '总Token数')
            ->setSortable(false)
            ->setHelp('所有Token类型的总和')
            ->setTextAlign('right')
            ->formatValue(static function ($value, AccessKeyUsage $entity) {
                $total = $entity->getInputTokens()
                    + $entity->getCacheCreationInputTokens()
                    + $entity->getCacheReadInputTokens()
                    + $entity->getOutputTokens();

                return number_format($total);
            })
            ->onlyOnIndex()
        ;

        yield TextField::new('model', '模型')
            ->setSortable(true)
            ->setHelp('使用的AI模型名称')
        ;

        yield TextField::new('requestId', '请求ID')
            ->setSortable(true)
            ->setHelp('API请求的追踪ID')
            ->hideOnIndex()
        ;

        yield TextField::new('stopReason', '停止原因')
            ->setSortable(true)
            ->setHelp('对话停止的原因')
            ->hideOnIndex()
        ;

        yield TextField::new('endpoint', 'API端点')
            ->setSortable(true)
            ->setHelp('调用的API端点路径')
            ->hideOnIndex()
        ;

        yield TextField::new('feature', '功能标识')
            ->setSortable(true)
            ->setHelp('使用的功能标识')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('occurTime', '发生时间')
            ->setSortable(true)
            ->setHelp('实际的API调用发生时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('createTime', '记录时间')
            ->setSortable(true)
            ->setHelp('数据库记录创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setSortable(true)
            ->setHelp('数据库记录最后更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
        ;
    }

    /**
     * 格式化AccessKey显示
     */
    private function formatAccessKeyDisplay(mixed $value): string
    {
        if (null === $value) {
            return '-';
        }

        if (!is_object($value)) {
            return 'Invalid AccessKey';
        }

        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        $id = method_exists($value, 'getId') ? $value->getId() : 'unknown';

        return sprintf('AccessKey#%s', is_scalar($id) ? (string) $id : 'unknown');
    }

    /**
     * 格式化用户显示
     */
    private function formatUserDisplay(mixed $value): string
    {
        if (null === $value) {
            return '-';
        }

        if (!is_object($value)) {
            return 'Invalid User';
        }

        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        $identifier = method_exists($value, 'getUserIdentifier') ? $value->getUserIdentifier() : 'unknown';

        return sprintf('User#%s', is_scalar($identifier) ? (string) $identifier : 'unknown');
    }

    /**
     * 格式化数字显示
     */
    private function formatNumber(mixed $value): string
    {
        return number_format(is_numeric($value) ? (int) $value : 0);
    }

    /**
     * 批量导出操作
     */
    #[AdminAction(routeName: 'access_key_usage_export_batch', routePath: '/access-key-usage/export-batch')]
    public function exportBatch(): void
    {
        // TODO: 实现批量导出功能
        // 可以集成现有的导出服务
        $this->addFlash('info', '导出功能开发中，请使用详细查询页面的导出功能。');
    }
}
