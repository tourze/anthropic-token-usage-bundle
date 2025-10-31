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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\AnthropicTokenUsageBundle\Entity\UsageStatistics;

/**
 * 预聚合统计数据管理
 *
 * @extends AbstractCrudController<UsageStatistics>
 */
#[AdminCrud(
    routePath: '/anthropic-token-usage/usage-statistics',
    routeName: 'anthropic_token_usage_usage_statistics'
)]
final class UsageStatisticsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return UsageStatistics::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('统计数据')
            ->setEntityLabelInPlural('统计数据')
            ->setPageTitle('index', 'Token使用统计数据')
            ->setPageTitle('detail', '统计数据详情')
            ->setDefaultSort(['periodStart' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->setPaginatorRangeSize(4)
            ->showEntityActionsInlined()
            ->setSearchFields(['dimensionId'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // 禁用创建、编辑、删除操作，只保留查看
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            // 添加重建统计操作
            ->addBatchAction(Action::new('rebuildBatch', '重建统计')
                ->linkToCrudAction('rebuildBatch')
                ->addCssClass('btn btn-warning')
                ->setIcon('fa fa-refresh'))
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('dimensionType', '维度类型')
                ->setChoices([
                    'AccessKey' => UsageStatistics::DIMENSION_ACCESS_KEY,
                    '用户' => UsageStatistics::DIMENSION_USER,
                ]))
            ->add(TextFilter::new('dimensionId', '维度ID'))
            ->add(ChoiceFilter::new('periodType', '时间粒度')
                ->setChoices([
                    '小时' => UsageStatistics::PERIOD_HOUR,
                    '天' => UsageStatistics::PERIOD_DAY,
                    '月' => UsageStatistics::PERIOD_MONTH,
                ]))
            ->add(DateTimeFilter::new('periodStart', '统计开始时间'))
            ->add(DateTimeFilter::new('lastUpdateTime', '最后更新时间'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->hideOnForm()
            ->setSortable(true)
        ;

        yield ChoiceField::new('dimensionType', '维度类型')
            ->setChoices([
                'AccessKey' => UsageStatistics::DIMENSION_ACCESS_KEY,
                '用户' => UsageStatistics::DIMENSION_USER,
            ])
            ->setSortable(true)
            ->setHelp('统计维度：按AccessKey或用户聚合')
            ->renderAsBadges([
                UsageStatistics::DIMENSION_ACCESS_KEY => 'primary',
                UsageStatistics::DIMENSION_USER => 'success',
            ])
        ;

        yield TextField::new('dimensionId', '维度ID')
            ->setSortable(true)
            ->setHelp('对应维度的具体ID值')
        ;

        yield ChoiceField::new('periodType', '时间粒度')
            ->setChoices([
                '小时' => UsageStatistics::PERIOD_HOUR,
                '天' => UsageStatistics::PERIOD_DAY,
                '月' => UsageStatistics::PERIOD_MONTH,
            ])
            ->setSortable(true)
            ->setHelp('统计的时间粒度')
            ->renderAsBadges([
                UsageStatistics::PERIOD_HOUR => 'info',
                UsageStatistics::PERIOD_DAY => 'primary',
                UsageStatistics::PERIOD_MONTH => 'warning',
            ])
        ;

        yield DateTimeField::new('periodStart', '开始时间')
            ->setSortable(true)
            ->setHelp('统计周期的开始时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;

        yield DateTimeField::new('periodEnd', '结束时间')
            ->setSortable(true)
            ->setHelp('统计周期的结束时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
        ;

        yield IntegerField::new('totalRequests', '总请求数')
            ->setSortable(true)
            ->setHelp('该时间段内的API请求总数')
            ->setTextAlign('right')
            ->formatValue(static function ($value) {
                return number_format(is_numeric($value) ? (int) $value : 0);
            })
        ;

        yield IntegerField::new('totalInputTokens', '总输入Token')
            ->setSortable(true)
            ->setHelp('该时间段内消耗的输入Token总数')
            ->setTextAlign('right')
            ->formatValue(static function ($value) {
                return number_format(is_numeric($value) ? (int) $value : 0);
            })
        ;

        yield IntegerField::new('totalCacheCreationInputTokens', '缓存创建Token')
            ->setSortable(true)
            ->setHelp('该时间段内缓存创建消耗的Token总数')
            ->setTextAlign('right')
            ->formatValue(static function ($value) {
                return number_format(is_numeric($value) ? (int) $value : 0);
            })
            ->hideOnIndex()
        ;

        yield IntegerField::new('totalCacheReadInputTokens', '缓存读取Token')
            ->setSortable(true)
            ->setHelp('该时间段内缓存读取消耗的Token总数')
            ->setTextAlign('right')
            ->formatValue(static function ($value) {
                return number_format(is_numeric($value) ? (int) $value : 0);
            })
            ->hideOnIndex()
        ;

        yield IntegerField::new('totalOutputTokens', '总输出Token')
            ->setSortable(true)
            ->setHelp('该时间段内生成的输出Token总数')
            ->setTextAlign('right')
            ->formatValue(static function ($value) {
                return number_format(is_numeric($value) ? (int) $value : 0);
            })
        ;

        // 计算字段：总Token数
        yield IntegerField::new('totalTokens', '总Token数')
            ->setSortable(false)
            ->setHelp('所有类型Token的总和')
            ->setTextAlign('right')
            ->formatValue(static function ($value) {
                return number_format(is_numeric($value) ? (int) $value : 0);
            })
            ->onlyOnIndex()
        ;

        // 计算字段：平均每请求Token数
        yield IntegerField::new('avgTokensPerRequest', '平均Token/请求')
            ->setSortable(false)
            ->setHelp('每个请求的平均Token消耗')
            ->setTextAlign('right')
            ->formatValue(static function ($value): string {
                if (!is_numeric($value) || 0.0 === (float) $value) {
                    return '-';
                }

                return number_format((float) $value, 1);
            })
            ->onlyOnIndex()
        ;

        yield DateTimeField::new('lastUpdateTime', '最后更新时间')
            ->setSortable(true)
            ->setHelp('统计数据的最后更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    /**
     * 批量重建统计
     */
    #[AdminAction(
        routeName: 'rebuild_batch',
        routePath: '/rebuild-batch'
    )]
    public function rebuildBatch(): void
    {
        // TODO: 实现批量重建统计功能
        // 可以调用聚合服务重新计算选中的统计数据
        $this->addFlash('info', '重建统计功能开发中，请联系系统管理员手动重建。');
    }
}
